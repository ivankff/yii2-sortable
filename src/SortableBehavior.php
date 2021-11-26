<?php

namespace ivankff\yii2Sortable;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
use yii\db\Expression;

/**
 * @property ActiveRecord $owner
 */
class SortableBehavior extends Behavior
{

    /**
     * @var string столбец в таблице с порядковым номером
     */
    public $positionAttribute = 'position';
    /**
     * @var array По указанным атрибутам происходит группировка
     * для таблицы `shop_product_category` (`product_id`, `category_id`, `position`) указываем
     * ```php
     * $groupAttributes = ['category_id']
     * ```
     */
    public $groupAttributes = [];
    /**
     * @var int шаг для порядкового номера
     */
    public $positionStep = 10;

    /**
     * {@inheritDoc}
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * @param ModelEvent $event
     */
    public function beforeInsert($event)
    {
        if (! $this->owner->getAttribute($this->positionAttribute))
            $this->owner->setAttribute($this->positionAttribute, $this->_getNextPositionInGroup());
    }

    /**
     * @param ModelEvent $event
     */
    public function beforeUpdate($event)
    {
        $groupAttributesChanged = false;

        foreach ($this->groupAttributes as $attribute) {
            $oldAttribute = $this->owner->getOldAttribute($attribute);
            $newAttribute = $this->owner->getAttribute($attribute);

            if ($oldAttribute != $newAttribute)
                $groupAttributesChanged = true;
        }

        if ($groupAttributesChanged)
            $this->owner->setAttribute($this->positionAttribute, $this->_getNextPositionInGroup());
    }

    /**
     * @param AfterSaveEvent $event
     */
    public function afterInsert($event)
    {
        $condition = $this->groupAttributes ? $this->owner->getAttributes($this->groupAttributes) : [];
        static::resort(get_class($this->owner), $this->positionAttribute, $this->positionStep, $condition);
    }

    /**
     * @param AfterSaveEvent $event
     */
    public function afterUpdate($event)
    {
        $groupAttributesChanged = false;
        $oldGroup = $this->owner->getAttributes($this->groupAttributes);

        foreach ($this->groupAttributes as $attribute) {
            if ($this->_wasAttributesChanged($attribute, $event)) {
                $groupAttributesChanged = true;
                $oldGroup[$attribute] = $event->changedAttributes[$attribute];
            }
        }

        if ($groupAttributesChanged) {
            // если сменили группу, к которой относится строка
            // пересортировываем новую группу
            $condition = $this->groupAttributes ? $this->owner->getAttributes($this->groupAttributes) : [];
            static::resort(get_class($this->owner), $this->positionAttribute, $this->positionStep, $condition);
            // пересортировываем старую группу
            static::resort(get_class($this->owner), $this->positionAttribute, $this->positionStep, $oldGroup);
        } elseif ($this->_wasAttributesChanged($this->positionAttribute, $event)) {
            // если сменили порядковый номер без перемещения в другую группу
            // пересортировываем в своей группе
            $condition = $this->groupAttributes ? $this->owner->getAttributes($this->groupAttributes) : [];
            static::resort(get_class($this->owner), $this->positionAttribute, $this->positionStep, $condition, $this->owner->getPrimaryKey(true));
        }
    }

    /**
     * @param Event $event
     */
    public function afterDelete($event)
    {
        $condition = $this->groupAttributes ? $this->owner->getAttributes($this->groupAttributes) : [];
        static::resort(get_class($this->owner), $this->positionAttribute, $this->positionStep, $condition);
    }

    /**
     * применяется в afterInsert afterUpdate
     * применять очень осторожно, т.к. AttributeTypecastBehavior срабатывает не всегда
     *
     * @param array|string $attributes
     * @param AfterSaveEvent $event
     * @return bool
     */
    private function _wasAttributesChanged($attributes, $event)
    {
        $attributes = (array)$attributes;

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $event->changedAttributes)) {
                $oldAttribute = $event->changedAttributes[$attribute];
                $newAttribute = $this->owner->getAttribute($attribute);

                if ($oldAttribute != $newAttribute)
                    return true;
            }
        }

        return false;
    }

    /**
     * @return int
     */
    private function _getNextPositionInGroup()
    {
        $query = $this->owner->find();

        if ($this->groupAttributes)
            $query->andWhere($this->owner->getAttributes($this->groupAttributes));

        $last = $query
            ->orderBy([$this->positionAttribute => SORT_DESC])
            ->limit(1)
            ->one();

        return $last ? $last->{$this->positionAttribute} + $this->positionStep : $this->positionStep;
    }

    /**
     * @param string $activeRecordClassName имя класса ActiveRecord для получения таблицы и primaryKey
     * @param string $positionAttribute столбец с порядковым номером
     * @param int $positionStep шаг для порядковых номеров
     * @param array $condition атрибуты для группировки сортировки внутри таблицы
     * ```php
     * ['category_id' => 25]
     * ```
     * @param array $itemPrimaryKey сортируемая строка для приоритета
     * в случае, если указываем порядковый номер, например, 180 для какой то строки, а 180 уже существует
     * если нужно просто произвести полную пересортировку, то передаем пустой массив
     * ```php
     * ['category_id' => 25, 'product_id' => 1124]
     * ```
     * @return int
     */
    public static function resort($activeRecordClassName, $positionAttribute, $positionStep, $condition = [], $itemPrimaryKey = [])
    {
        /** @var ActiveRecord $activeRecordClassName */
        $queryBuilder = Yii::$app->db->getQueryBuilder();

        $params = [];
        $orderBy = [$positionAttribute => SORT_ASC];

        if ($itemPrimaryKey) {
            $if = $queryBuilder->buildCondition($itemPrimaryKey, $params);
            array_unshift($orderBy, new Expression("IF ({$if}, 1, 2)", $params));
        }

        $sql = $queryBuilder->update(
                $activeRecordClassName::tableName(),
                [$positionAttribute => new Expression("@p:=@p+{$positionStep}")],
                $condition ?: '',
                $params
            ) . " " . $queryBuilder->buildOrderBy($orderBy);

        Yii::$app->db->createCommand('SET @p:=0')->execute();
        return Yii::$app->db->createCommand($sql, $params)->execute();
    }

}
