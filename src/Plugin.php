<?php

namespace Asyntai\Chatbot;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

class Plugin extends BasePlugin
{
    use SettingsResponse;

    public const DEFAULT_ORIGIN = 'https://asyntai.com';

    public function init(): void
    {
        // Set here rather than declared above, because Craft 3 declares these
        // properties without types and Craft 4 and 5 declare them with types.
        $this->hasCpSettings = true;
        $this->hasCpSection = true;
        $this->schemaVersion = '1.1.0';

        parent::init();

        // Register CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['asyntai/save'] = 'asyntai/settings/save';
            $event->rules['asyntai/reset'] = 'asyntai/settings/reset';
            $event->rules['asyntai'] = 'asyntai/settings/index';
        });

        // The signed page feed: /asyntai-feed/products and /asyntai-feed/pages
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['asyntai-feed/<which:(products|pages)>'] = 'asyntai/feed/index';
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
            if (!$request->getIsSiteRequest() || $request->getIsConsoleRequest()) {
                return;
            }
            try {
                $connection = Store::connection();
            } catch (\Throwable $e) {
                return;
            }
            $siteId = $connection['siteId'];
            if ($siteId === '') {
                return;
            }
            // Inject the widget script only after the page's load event: an
            // async script tag still holds the load event open until its fetch
            // times out, so an unreachable Asyntai server would delay the page.
            $js = '(function(){var l=function(){var s=document.createElement("script");'
                . 's.src=' . json_encode(Store::cleanScriptUrl($connection['scriptUrl'])) . ';s.async=true;'
                . 's.setAttribute("data-asyntai-id",' . json_encode($siteId) . ');'
                . 'document.head.appendChild(s)};'
                . 'if(document.readyState==="complete"){l()}'
                . 'else{window.addEventListener("load",l)}})();';
            Craft::$app->getView()->registerJs($js, View::POS_END);
        });
    }

    /** Where the plugin talks to. The ASYNTAI_ORIGIN environment variable overrides it for testing. */
    public static function origin(): string
    {
        $custom = '';
        if (class_exists(\craft\helpers\App::class) && method_exists(\craft\helpers\App::class, 'env')) {
            $custom = (string)\craft\helpers\App::env('ASYNTAI_ORIGIN');
        } elseif (getenv('ASYNTAI_ORIGIN') !== false) {
            $custom = (string)getenv('ASYNTAI_ORIGIN');
        }
        return $custom !== '' ? rtrim($custom, '/') : self::DEFAULT_ORIGIN;
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new models\Settings();
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
