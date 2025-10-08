<?php

namespace Asyntai\Chatbot\assetbundles;

use craft\web\AssetBundle;

class AsyntaiAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__;
        parent::init();
    }
}


