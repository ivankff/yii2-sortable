<?php

namespace ivankff\yii2Sortable;

use yii\base\Event;
use yii\db\ActiveRecord;

class PositionActionEvent extends Event
{

    /**
     * @var ActiveRecord model which will be changed position
     */
    public $model;
    /**
     * @var bool execute changing position
     */
    public $execute = true;
    /**
     * @var int position which will be set
     */
    public $position;

}