<?php

namespace Asyntai\Chatbot\models;

use craft\base\Model;

class Settings extends Model
{
    public $siteId = '';
    public $scriptUrl = 'https://asyntai.com/static/js/chat-widget.js';
    public $accountEmail = '';

    public function rules(): array
    {
        return [
            [['siteId', 'scriptUrl', 'accountEmail'], 'string'],
            [['siteId', 'scriptUrl', 'accountEmail'], 'default', 'value' => ''],
        ];
    }
}


