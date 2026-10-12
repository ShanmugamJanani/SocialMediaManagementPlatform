<?php
  use kartik\grid\GridView;
  use kartik\icons\FontAwesomeAsset;
  FontAwesomeAsset::register($this);
?>

<div class="cart-default-index"> 

  <?php
    $gridColumns = [
      ['class' => 'yii\grid\SerialColumn'],
      'name',
      'dob',
      'mobile',
      'email',
      [
          'attribute' => 'gender_id', // Links to public $gender in UserSearch
          'label' => 'Gender',
          'value' => 'gender.gender', // The actual data to show in the row
      ],
      'salary',
      'advance',
      
      
    [
        'attribute' => 'country_id', // Links to public $gender in UserSearch
        'label' => 'Country',
        'value' => 'country.country', // The actual data to show in the row
    ],

    [
      'attribute' => 'state_id', 
      'label' => 'State',
      'value' => 'state.states',
    ],

    [
      'attribute' => 'city_id', 
      'label' => 'City',
      'value' => 'city.city',
    ],
      
    [
      'attribute' => 'food_preference_id', 
      'label' => 'Food Preference',
      'value' => 'foodPreference.preference',
    ],

    
            
      ['class' => 'kartik\grid\ActionColumn',
      'dropdown' => false, 
      'vAlign' => 'middle',

      'urlCreator' => function($action, $model, $key, $index) { 
        // $action: Is 'view', 'update', or 'delete'
        // $model: The data for the current row (e.g., current Employee object)
        // $key: The primary key value for the current row
        return \yii\helpers\Url::to([$action, 'emp_id' => $key]); 
        
      },

      'viewOptions' => ['title' => 'View', 'data-toggle' => 'tooltip'],
      'updateOptions' => ['title' => 'Update', 'data-toggle' => 'tooltip'],
      'deleteOptions' => ['title' => 'Delete', 'data-toggle' => 'tooltip'],
      ]
    ];

    echo GridView::widget([
        'dataProvider'=> $dataProvider,
        'filterModel' => $searchModel,
        'columns' => $gridColumns,
        'responsive'=>true,
        'hover'=>true
    ]);
    ?>
    
</div>