<?php

namespace ivankff\yii2Sortable;

use yii\base\Action;
use Yii;
use yii\base\Exception;
use yii\base\UnknownClassException;
use yii\db\ActiveRecord;
use yii\web\Response;

/**
 */
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
        $requestPositions = Yii::$app->request->post($this->positionRequestParam);

        if (!empty($requestPositions) && is_array($requestPositions)){
            $toResort = [];
            $success = true;

            foreach ($requestPositions as $id => $position) {
                /** @var ActiveRecord $model */
                $model = call_user_func($this->model, $id);
                $position = (int)$position;

                /** @var SortableBehavior $positionBehavior */
                $positionBehavior = null;
                foreach ($model->getBehaviors() as $b)
                    if ($b instanceof SortableBehavior)
                        $positionBehavior = $b;

                if (! $positionBehavior)
                    throw new Exception("Model does not have an `SortableBehavior` behavior");

                $positionAttribute = $positionBehavior->positionAttribute;
                $positionStep = $positionBehavior->positionStep;
                $groupAttributes = $positionBehavior->groupAttributes;

                if ($model->getAttribute($positionAttribute) !== $position) {
                    $event = new PositionActionEvent(['model' => $model, 'position' => $position]);
                    $this->trigger(self::EVENT_BEFORE_SET_POSITION, $event);

                    if (! $event->execute)
                        continue;

                    $model::updateAll([$positionAttribute => $position], $model->getPrimaryKey(true));

                    $resort = [
                        'className' => get_class($model),
                        'positionAttribute' => $positionAttribute,
                        'positionStep' => $positionStep,
                        'condition' => $groupAttributes ? $model->getAttributes($groupAttributes) : [],
                    ];
                    $toResort[md5(serialize($resort))] = $resort;
                }
            }

            foreach ($toResort as $key => $resort)
                SortableBehavior::resort($resort['className'], $resort['positionAttribute'], $resort['positionStep'], $resort['condition']);
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['success' => $success];
    }

}