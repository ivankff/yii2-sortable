<?php

namespace ivankff\yii2Sortable;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class SortableBehavior extends Behavior
{

    /**
     * @var ActiveRecord
     */
    public $owner;

    /** @var string */
    public $positionAttribute = 'position';

    /**
     * @var array По указанным атрибутам происходит группировка
     */
    public $groupAttributes = [];

    /**
     * @var int
     */
    public $positionStep = 10;

    /**
     * @var null|string
     */
    public $primaryKeyAttribute = null;

    private $_oldPosition = null;
    private $_groupAttributesChanged = false;

    public function events(){
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function beforeInsert(){
        if (empty($this->owner->{$this->positionAttribute})){
            $this->owner->{$this->positionAttribute} = $this->_getNextPositionInGroup();
        }
    }

    public function afterInsert(){
        $this->_resort();
    }

    public function beforeUpdate(){
        $this->_groupAttributesChanged = false;
        foreach ($this->groupAttributes as $attr){
            if ($this->owner->getOldAttribute($attr) != $this->owner->{$attr})
                $this->_groupAttributesChanged = true;
        }
        if ($this->_groupAttributesChanged){
            $this->owner->{$this->positionAttribute} = $this->_getNextPositionInGroup();
        }
        $this->_oldPosition = $this->owner->getOldAttribute($this->positionAttribute);
    }

    public function afterUpdate(){
        if (!$this->_groupAttributesChanged && $this->owner->{$this->positionAttribute} != $this->_oldPosition){
            $this->_resort();
        }
    }

    public function afterDelete(){
        $this->_resort();
    }

    private function _whereCondition(){
        $where = [];
        foreach ($this->groupAttributes as $attr){
            $where[$attr] = $this->owner->{$attr};
        }
        return $where;
    }

    private function _getNextPositionInGroup(){
        $last = $this->owner->find()
            ->andWhere($this->_whereCondition())
            ->orderBy([$this->positionAttribute => SORT_DESC])
            ->limit(1)
            ->one();
        return (null === $last) ?  $this->positionStep : $last->{$this->positionAttribute} + $this->positionStep;
    }

    private function _resort(){
        Yii::$app->db->createCommand('SET @p:=0')->execute();
        $orderBy = ['t.[['.$this->positionAttribute.']]'];
        $primaryKeyColumn = null;
        if ($this->primaryKeyAttribute){
            $primaryKeyColumn = $this->primaryKeyAttribute;
        } else {
            $primaryKeyArray = $this->owner->getTableSchema()->primaryKey;
            if (is_array($primaryKeyArray) && sizeof($primaryKeyArray) == 1) $primaryKeyColumn = array_shift($primaryKeyArray);
        }
        if ($primaryKeyColumn){
            $orderBy[] = 'IF(t.[['.$primaryKeyColumn.']] IN ('.$this->owner->{$primaryKeyColumn}.'), 1, 2)';
        }
        $where = $params = [];
        foreach ($this->_whereCondition() as $k=>$v){
            $where[] = 't.[['.$k.']] = :'.$k;
            $params[':'.$k] = $v;
        }
        $command = Yii::$app->db->createCommand(
            'UPDATE '.$this->owner->tableName().' as t'.
            ' SET t.[['.$this->positionAttribute.']] = @p:=@p+'.$this->positionStep.
            (!empty($where) ? ' WHERE ('.implode(') AND (', $where).')' : null).
            ' ORDER BY '.implode(',',$orderBy).' '
            , $params);
        return $command->execute();
    }

}
