<?php

namespace Asyntai\Chatbot\controllers;

use Asyntai\Chatbot\Plugin;
use Craft;
use craft\web\Controller;
use yii\filters\VerbFilter;

class SettingsController extends Controller
{
    protected array|int|bool $allowAnonymous = false;
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'save' => ['post'],
                'reset' => ['post'],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        $this->requireCpRequest();
        $plugin = Plugin::getInstance();
        return $this->renderTemplate('asyntai/settings', [
            'settings' => $plugin->getSettings(),
        ]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        
        // Try to get data from body params first (form data)
        $body = $request->getBodyParams();
        
        // If empty, try JSON from raw body
        if (empty($body)) {
            $json = $request->getRawBody();
            if ($json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        $siteId = isset($body['site_id']) ? trim((string)$body['site_id']) : '';
        if ($siteId === '') {
            return $this->asJson(['success' => false, 'error' => 'missing site_id']);
        }

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $settings->siteId = $siteId;
        if (!empty($body['script_url'])) {
            $settings->scriptUrl = trim((string)$body['script_url']);
        }
        if (!empty($body['account_email'])) {
            $settings->accountEmail = trim((string)$body['account_email']);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            return $this->asJson(['success' => false, 'error' => 'failed to save settings']);
        }

        return $this->asJson([
            'success' => true,
            'saved' => [
                'site_id' => $settings->siteId,
                'script_url' => $settings->scriptUrl,
                'account_email' => $settings->accountEmail,
            ],
        ]);
    }

    public function actionReset()
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $settings->siteId = '';
        $settings->accountEmail = '';
        $settings->scriptUrl = 'https://asyntai.com/static/js/chat-widget.js';
        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

        return $this->asJson(['success' => true]);
    }
}


