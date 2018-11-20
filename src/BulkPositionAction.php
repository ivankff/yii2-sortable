<?php

namespace ivankff\yii2Sortable;

use yii\base\Action;
use Yii;
use yii\base\Exception;
use yii\base\UnknownClassException;
use yii\db\ActiveRecord;
use yii\web\Response;

class BulkPositionAction extends Action
{

    const EVENT_BEFORE_SET_POSITION = 'beforeSetPosition';

    /**
     * @var string|callable
     * `string` = className
     */
    public $model;
    /**
     * @var string
     */
    public $positionAttribute = 'position';
    /**
     * @var string
     */
    public $positionRequestParam = 'position';

    /**
     * {@inheritdoc}
     * @throws
     */
    public function init()
    {
        if (is_string($this->model))
            $this->model = [$this->model, 'findOne'];

        if (! is_callable($this->model))
            throw new UnknownClassException("`Model` must be callable.");

        parent::init();
    }

    /**
     * {@inheritdoc}
     * @throws
     */
    public function run()
    {
        $success = false;

        $position = Yii::$app->request->post($this->positionRequestParam);

        if (!empty($position) && is_array($position)){
            arsort($position);
            $success = true;

            foreach ($position as $id => $pos) {
                /** @var ActiveRecord $model */
                $model = call_user_func($this->model, $id);

                if (! $model->hasAttribute($this->positionAttribute))
                    throw new Exception("Object of `{$this->modelClass}` does not have an `{$this->positionAttribute}` attribute");

                $pos = (int)$pos;

                if ($model->getAttribute($this->positionAttribute) !== $pos) {
                    $event = new PositionActionEvent(['model' => $model, 'position' => $pos]);
                    $this->trigger(self::EVENT_BEFORE_SET_POSITION, $event);

                    if (! $event->execute)
                        continue;

                    $model->setAttribute($this->positionAttribute, $pos);
                    $success = $success && $model->save();
                }
            }
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['success' => $success];
    }

}