# yii2-sortable

SortableBehavior
------------------------------
```php
public function behaviors()
{
	return [
		...
		'sort' => [
			'class' => 'ivankff\yii2Sortable\SortableBehavior',
			'groupAttributes' => ['parent_id'],
		],
		...
	];
}
```

Controller
------------------------------
```php
public function actions()
{
	return [
		...
		'position' => [
			'class' => 'ivankff\yii2Sortable\BulkPositionAction',
			'modelClass' => 'common\models\Product',
		],
		...
	];
}
```

Grid view
------------------------------
```php
GridView::widget([
	'columns' => [
		...
		[
			'class' => 'ivankff\yii2Sortable\PositionColumn',
			'attribute'=>'position',
		],
		...
	],
	'panel' => [
		...
		'after' => '<div class="clearfix">' . Html::a('<i class="fas fa-sort-numeric-down"></i> Пересортировать', \yii\helpers\Url::to(['position']), ['data-pjax' => '1', 'data-pjax-container' => 'crud-datatable-pjax', 'class' => 'btn btn-primary float-right kv-position-set']) . '</div>',
		...
	],
]);
```


