<?php

namespace Asyntai\Chatbot\controllers;

use Asyntai\Chatbot\Feed;
use Asyntai\Chatbot\Plugin;
use Asyntai\Chatbot\Store;
use Craft;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;

/**
 * Control Panel screen and its actions. Every action needs a signed-in
 * admin and Craft's CSRF token.
 */
class SettingsController extends Controller
{
    public function actionIndex()
    {
        $this->requireCpRequest();
        $this->requireAsyntaiAdmin();
        $connection = Store::connection();
        return $this->renderTemplate('asyntai/settings', [
            'siteId' => $connection['siteId'],
            'accountEmail' => $connection['accountEmail'],
            'shareOn' => Store::get('feed_enabled') === '1' && Store::get('feed_token') !== '',
            'origin' => Plugin::origin(),
        ]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireAsyntaiAdmin();
        $request = Craft::$app->getRequest();

        $siteId = trim((string)$request->getBodyParam('site_id', ''));
        if (!preg_match('/^[A-Za-z0-9_-]{6,64}$/', $siteId)) {
            return $this->asJson(['success' => false, 'error' => 'missing site_id']);
        }

        $values = [
            'site_id' => $siteId,
            'script_url' => Store::cleanScriptUrl((string)$request->getBodyParam('script_url', '')),
            'account_email' => substr(trim((string)$request->getBodyParam('account_email', '')), 0, 255),
        ];

        // The feed token parked by the last "prepare" becomes live now that
        // the sign-in it belongs to has finished.
        $pending = Store::get('handshake_token');
        if ($pending !== '') {
            $values['feed_token'] = $pending;
            $values['feed_enabled'] = Store::get('handshake_share') === '1' ? '1' : '0';
            $values['handshake_token'] = '';
            $values['handshake_share'] = '0';
        }

        try {
            Store::set($values);
        } catch (\Throwable $e) {
            Craft::error('Asyntai save: ' . $e->getMessage(), __METHOD__);
            return $this->asJson(['success' => false, 'error' => 'failed to save settings']);
        }

        $this->refreshPageCaches();

        return $this->asJson([
            'success' => true,
            'feed_enabled' => Store::get('feed_enabled') === '1' && Store::get('feed_token') !== '',
        ]);
    }

    public function actionReset()
    {
        $this->requirePostRequest();
        $this->requireAsyntaiAdmin();

        // The feed token goes too: a token kept after a reset could only
        // sign a call nobody should be able to make.
        Store::clear();
        Store::set(['site_id' => '', 'script_url' => '', 'account_email' => '']);

        // A connection saved by an earlier version lives in the plugin
        // settings. Clear it too where the project config allows changes.
        try {
            $plugin = Plugin::getInstance();
            $settings = $plugin->getSettings();
            if (trim((string)($settings->siteId ?? '')) !== '') {
                $settings->siteId = '';
                $settings->accountEmail = '';
                Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
            }
        } catch (\Throwable $e) {
            // Read-only project config. The empty site_id above already wins.
        }

        $this->refreshPageCaches();

        return $this->asJson(['success' => true]);
    }

    /**
     * Park a fresh feed token at Asyntai under a one-time handshake id.
     * Without "share" nothing is parked, so connecting works exactly as
     * before page sharing existed.
     */
    public function actionPrepare()
    {
        $this->requirePostRequest();
        $this->requireAsyntaiAdmin();

        $share = (string)Craft::$app->getRequest()->getBodyParam('share', '0') === '1';
        $state = 'craft_' . bin2hex(random_bytes(12));

        if (!$share) {
            Store::set(['handshake_token' => '', 'handshake_share' => '0']);
            return $this->asJson(['success' => true, 'state' => $state, 'staged' => false]);
        }

        $token = bin2hex(random_bytes(24));
        $ok = false;
        try {
            $client = Craft::createGuzzleClient(['timeout' => 10, 'connect_timeout' => 5]);
            $response = $client->post(Plugin::origin() . '/api/v1/wp-search/stage/', [
                'json' => [
                    'state' => $state,
                    'platform' => 'craft',
                    'feed_protocol' => 'store_feed_v1',
                    // A chatbot install must never unlock the paid AI Search bar.
                    'product' => 'chat',
                    'site_url' => Feed::siteRoot(),
                    'feed_url' => Feed::feedUrl('products'),
                    'feed_token' => $token,
                ],
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $ok = $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            Craft::warning('Asyntai stage: ' . $e->getMessage(), __METHOD__);
        }

        if (!$ok) {
            return $this->asJson(['success' => false, 'error' => 'Could not reach Asyntai. Please try again later.']);
        }

        // Kept aside until the sign-in finishes, so a handshake that is
        // never completed leaves no live token behind.
        Store::set(['handshake_token' => $token, 'handshake_share' => '1']);
        return $this->asJson(['success' => true, 'state' => $state, 'staged' => true]);
    }

    public function actionShare()
    {
        $this->requirePostRequest();
        $this->requireAsyntaiAdmin();

        $on = (string)Craft::$app->getRequest()->getBodyParam('enabled', '0') === '1';
        if ($on && Store::get('feed_token') === '') {
            // Connected before page sharing existed, or after a reset:
            // Asyntai has no token for this site yet, so the owner signs in
            // once more.
            return $this->asJson(['success' => false, 'needs_connect' => true]);
        }
        Store::set(['feed_enabled' => $on ? '1' : '0']);
        return $this->asJson(['success' => true, 'feed_enabled' => $on]);
    }

    /** What Asyntai says about the last read of the pages. */
    public function actionStatus()
    {
        $this->requireAsyntaiAdmin();
        $siteId = Store::connection()['siteId'];
        if ($siteId === '') {
            return $this->asJson(['success' => true, 'sync' => null]);
        }
        $sync = null;
        try {
            $client = Craft::createGuzzleClient(['timeout' => 10, 'connect_timeout' => 5]);
            $response = $client->get(Plugin::origin() . '/api/v1/search-widget/status/', [
                'query' => ['widget_id' => $siteId],
                'http_errors' => false,
            ]);
            if ($response->getStatusCode() === 200) {
                $decoded = json_decode((string)$response->getBody(), true);
                if (is_array($decoded) && isset($decoded['sync']) && is_array($decoded['sync'])) {
                    $sync = [
                        'status' => (string)($decoded['sync']['status'] ?? ''),
                        'count' => (int)($decoded['sync']['count'] ?? 0),
                        'error' => (string)($decoded['sync']['error'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $sync = null;
        }
        return $this->asJson(['success' => true, 'sync' => $sync]);
    }

    /**
     * The widget tag is part of every page, so cached pages must be built
     * again after a connect or a reset. Clears Craft's own caches and, on
     * Craft Cloud, the static page cache.
     */
    private function refreshPageCaches(): void
    {
        try {
            Craft::$app->getElements()->invalidateAllCaches();
        } catch (\Throwable $e) {
            Craft::warning('Asyntai cache clear: ' . $e->getMessage(), __METHOD__);
        }

        try {
            if (class_exists(\craft\cloud\Module::class)
                && class_exists(\craft\cloud\Helper::class)
                && \craft\cloud\Helper::isCraftCloud()) {
                $module = \craft\cloud\Module::getInstance();
                if ($module) {
                    $cache = $module->getStaticCache();
                    if (method_exists($cache, 'purgeOrigin')) {
                        $cache->purgeOrigin();
                    } elseif (method_exists($cache, 'purgeGateway')) {
                        $cache->purgeGateway();
                    }
                }
            }
        } catch (\Throwable $e) {
            Craft::warning('Asyntai static cache clear: ' . $e->getMessage(), __METHOD__);
        }
    }

    private function requireAsyntaiAdmin(): void
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user || !$user->admin) {
            throw new ForbiddenHttpException('Only an admin can change the Asyntai settings.');
        }
    }
}
