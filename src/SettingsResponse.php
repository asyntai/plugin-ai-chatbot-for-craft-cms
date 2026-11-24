<?php

namespace Asyntai\Chatbot;

use craft\helpers\UrlHelper;

// Craft 4 and 5 (PHP 8) declare getSettingsResponse(): mixed. Craft 3 can run
// on PHP 7, which has no "mixed" type, and declares the method without a type.
if (PHP_VERSION_ID >= 80000) {
    trait SettingsResponse
    {
        public function getSettingsResponse(): mixed
        {
            return \Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('asyntai'));
        }
    }
} else {
    trait SettingsResponse
    {
        public function getSettingsResponse()
        {
            return \Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('asyntai'));
        }
    }
}
