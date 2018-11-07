<?php

namespace ivankff\yii2Sortable;

use \yii\web\AssetBundle;

class PositionColumnAsset extends AssetBundle
{

    public $sourcePath = __DIR__ . '/assets';
    public $js = [
        'js/grid-position.js',
    ];
    public $depends = [
    ];

}
