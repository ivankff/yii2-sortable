<?php

namespace ivankff\yii2Sortable;

use yii;
use kartik\grid\DataColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 */
class PositionColumn extends DataColumn
{

    public $width = '5%';

    public $label = null;

    public $primaryKeyColumn = null;

    public $cssPositionField = 'kv-position-field';
    public $cssPositionSetButton = 'kv-position-set';

    protected $_view;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (null === $this->label) {
            $this->label = Yii::t('backend', 'Порядковый номер');
        }
        $this->_view = $this->grid->getView();
        PositionColumnAsset::register($this->_view);
        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function renderDataCellContent($model, $key, $index)
    {
        if (null === $this->primaryKeyColumn){
            $primaryKey = $model->getPrimaryKey();
        } else {
            $primaryKey = $model->$this->primaryKeyColumn;
        }
        $pjax = $this->grid->pjax ? true : false;
        $jsOpts = Json::encode(
            [
                'css' => $this->cssPositionSetButton,
                'cssField' => $this->cssPositionField,
                'pjax' => $pjax,
                'pjaxContainer' => $pjax ? $this->grid->pjaxSettings['options']['id'] : '',
            ]
        );
        $js = "navikgPositionSet({$jsOpts});";
        $this->_view->registerJs($js);
        $this->initPjax($js);
        if ($this->content === null) {
            return Html::input('text', 'position['.$primaryKey.']', $this->grid->formatter->format($this->getDataCellValue($model, $key, $index), $this->format), ['class'=>['form-control', $this->cssPositionField]]);
        } else {
            return parent::renderDataCellContent($model, $key, $index);
        }
    }

}
