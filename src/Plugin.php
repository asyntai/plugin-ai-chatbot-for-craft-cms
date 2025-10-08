<?php

namespace Asyntai\Chatbot;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();

        // Register CP routes for AJAX saves and reset
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['asyntai/save'] = 'asyntai/settings/save';
            $event->rules['asyntai/reset'] = 'asyntai/settings/reset';
            $event->rules['asyntai'] = 'asyntai/settings/index';
        });

        // Register plugin template roots (CP + Site)
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $event) {
            $event->roots['asyntai'] = __DIR__ . '/templates';
        });
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $event) {
            $event->roots['asyntai'] = __DIR__ . '/templates';
        });

        // Inject widget script on site requests when connected
        Event::on(View::class, View::EVENT_END_BODY, function ($event) {
            $request = Craft::$app->getRequest();
            if (!$request->getIsSiteRequest()) {
                return;
            }
            $settings = $this->getSettings();
            $siteId = trim((string)($settings->siteId ?? ''));
            if ($siteId === '') {
                return;
            }
            $scriptUrl = trim((string)($settings->scriptUrl ?? 'https://asyntai.com/static/js/chat-widget.js'));
            Craft::$app->getView()->registerJsFile($scriptUrl, [
                'async' => true,
                'defer' => true,
                'data' => ['asyntai-id' => $siteId],
                'position' => View::POS_END,
            ]);
        });
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new models\Settings();
    }

    public function getSettingsResponse(): mixed
    {
        return \Craft::$app->getResponse()->redirect(\craft\helpers\UrlHelper::cpUrl('asyntai'));
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Asyntai AI Chatbot';
        $item['url'] = 'asyntai';
        return $item;
    }

    public function getIconPath(): ?string
    {
        $base = dirname(__DIR__);
        $candidates = [
            $base . '/icon.svg',       // preferred: root icon.svg
            $base . '/src/icon.svg',   // fallback: src/icon.svg
            $base . '/logo.png',       // fallback: root logo.png
            $base . '/src/logo.png',   // fallback: src/logo.png
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return parent::getIconPath();
    }
}


