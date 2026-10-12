<?php
  use kartik\grid\GridView;
  use kartik\icons\FontAwesomeAsset;
  FontAwesomeAsset::register($this);

  use yii\helpers\ArrayHelper;
  use kartik\widgets\Growl;
  use yii\bootstrap5\Modal; 
  use kartik\helpers\Html;
  use kartik\cmenu\ContextMenu;
  use kartik\widgets\AlertBlock;


  use app\models\Country;
  use app\models\City;
  use app\models\State;
  use app\models\Gender;
  use app\models\FoodPreference;
  use app\models\EmpBriefCode;


ContextMenu::begin(['items'=>[['label' => 'Login', 'url' => ['/form/login']]], 'options'=>['tag'=>'div']]);
    echo Html::tag('h4', 'If you need to edit the list. Please login first.', ['class' => 'd-inline' ]);
    echo '<span class="text-decoration-underline fw-bold kv-context d-inline-block mb-3">Right click here to login</span>';
    ContextMenu::end(); ?>

<?php echo AlertBlock::widget([
    'useSessionFlash' => true,
    'type' => AlertBlock::TYPE_ALERT,
    'delay' => 0, // Display immediately
    'alertSettings' => [
        'warning' => ['type' => 'warning', 'icon' => 'fas fa-check-circle'],
    ],
]);
Yii::$app->session->setFlash('warning',"Don't click that aler button");?>


<div> 
    <?php 
        echo Html::a('Create New', ['form/create'], ['class' => 'btn btn-primary ms-3 mb-2']); 
        echo Html::a('Alert', [''], ['class' => 'btn btn-warning ms-3 mb-2']); 
    ?>
</div>


<?php

foreach (Yii::$app->session->getAllFlashes() as $type => $message): // Get the type (success/error)
    echo Growl::widget([
        'type' => ($type === 'error') ? Growl::TYPE_DANGER : Growl::TYPE_SUCCESS,
        'title' => ucfirst($type),
        'icon' => ($type === 'error') ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle',
        'body' => is_array($message) ? implode(', ', $message) : $message,
        'showSeparator' => true,
        'delay' => 0,
        'pluginOptions' => [
            'showProgressbar' => true,
            'width' => '850px',
            'height' => '400px',
            'placement' => [
                'from' => 'top',
                'align' => 'left',
            ],
            'offset' => 20, // Distance from the screen edge
            'spacing' => 10,
        ]
    ]);
endforeach;
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
          'filter' => ArrayHelper::map(Gender::find()->asArray()->all(), 'gender_id', 'gender'),
          'filterInputOptions' => ['prompt' => 'Select Gender', 'class' => 'form-control'],
      ],
      'salary',
      'advance',
      
      
    [
        'attribute' => 'country_id', 
        'label' => 'Country',
        'value' => 'country.country', 
        'filter' => ArrayHelper::map(Country::find()->asArray()->all(), 'country_id', 'country'),
        'filterInputOptions' => ['prompt' => 'Select Country', 'class' => 'form-control'],
    ],

    [
      'attribute' => 'state_id', 
      'label' => 'State',
      'value' => 'state.states',
      'filter' => ArrayHelper::map(State::find()->asArray()->all(), 'state_id', 'states'),
      'filterInputOptions' => ['prompt' => 'Select State', 'class' => 'form-control'],
    ],

    [
      'attribute' => 'city_id', 
      'label' => 'City',
      'value' => 'city.city',
      'filter' => ArrayHelper::map(City::find()->asArray()->all(), 'city_id', 'city'),
      'filterInputOptions' => ['prompt' => 'Select City', 'class' => 'form-control'],
    ],
      
    [
      'attribute' => 'food_preference_id', 
      'label' => 'Food Preference',
      'value' => 'foodPreference.preference',
      'filter' => ArrayHelper::map(FoodPreference::find()->asArray()->all(), 'food_preference_id', 'preference'),
      'filterInputOptions' => ['prompt' => 'Select Food Preference', 'class' => 'form-control'],
    ],

    
            
      ['class' => 'kartik\grid\ActionColumn',
      'dropdown' => false, 
      'hAlign' => GridView::ALIGN_CENTER,
      'vAlign' => GridView::ALIGN_MIDDLE,
      // Hide ActionColumn from the export file
      'hiddenFromExport' => true,
      'template' => '{view} {update} {delete}',
      'noWrap' => true,
      
      'urlCreator' => function($action, $model, $key, $index) { 
        // $action: Is 'view', 'update', or 'delete'
        // $model: The data for the current row (e.g., current Employee object)
        // $key: The primary key value for the current row
        return \yii\helpers\Url::to([$action, 'emp_id' => $key]); 
        
      },

      'buttons' => [
        'view' => function ($url, $model) {
            return \yii\helpers\Html::button('<i class="fas fa-eye"></i>', [
                'value' => $url,
                'title' => 'View Employee',
                'class' => 'showModalButton btn btn-xs btn-link',
                'data-toggle' => 'tooltip',
            ]);
        },
        'update' => function ($url, $model) {
            return \yii\helpers\Html::button('<i class="fas fa-pencil-alt"></i>', [
                'value' => $url,
                'title' => 'Update Employee',
                'class' => 'showModalButton btn btn-xs btn-link',
                'data-toggle' => 'tooltip',
            ]);
        },
    ],

   /*   'viewOptions' => ['title' => 'View', 'data-toggle' => 'tooltip'],
      'updateOptions' => ['title' => 'Update', 'data-toggle' => 'tooltip'],
      'deleteOptions' => ['title' => 'Delete', 'data-toggle' => 'tooltip'],*/
      ]
    ]; 

    echo GridView::widget([
        'dataProvider'=> $dataProvider,
        'filterModel' => $searchModel,
        'columns' => $gridColumns,
        'responsive'=>true,
        'hover'=>true,
        'toolbar'=>[
          '{export}',
          '{toggleData}'
        ],

        // This is the importantco piece that "activates" the toolbar layout for export
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => '<i class="fas fa-users"></i> Employee List',
        ],


        // Set 'export' to true to use defaults, or configure it:
      'export' => [
          'fontAwesome' => true,
          'showConfirmAlert' => false,
          'target' => GridView::TARGET_BLANK,
      ],

      'exportConfig' => [
        GridView::EXCEL => [
            'label' => 'Export to Excel',
            'iconOptions' => ['class' => 'text-success'],
            'showHeader' => true,
            'showFooter' => true,
        ],
        GridView::PDF => [
            'label' => 'Export to PDF',
            'iconOptions' => ['class' => 'text-danger'],
        ],
    ],

]);

  Modal::begin([
      'title' => '<h4 id="modalHeaderTitle">Employee Details</h4>',
      'id' => 'empModal',
      'size' => 'modal-xl',
  ]);
  echo "<div id='modalContent'><div style='text-align:center'><i class='fas fa-spinner fa-spin fa-2x'></i></div></div>";
  Modal::end();

  $js = <<<JS
  $(document).on('click', '.showModalButton', function() {
      var url = $(this).attr('value');
      var title = $(this).attr('title');
      
      $('#modalHeaderTitle').text(title);
      $('#empModal').modal('show');
      
      $.get(url, function(data) {
          $('#modalContent').html(data);
      });
  });
  JS;
  $this->registerJs($js);
      
?>
    
</div>