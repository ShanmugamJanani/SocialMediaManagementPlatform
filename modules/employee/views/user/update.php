<?php
    use kartik\widgets\ActiveForm;
    use kartik\widgets\ActiveField;
    use kartik\date\DatePicker;
    use yii\helpers\ArrayHelper;
    use yii\helpers\Html;
    use yii\bootstrap5\ButtonDropdown;
    use yii\helpers\Url;
    use kartik\widgets\DepDrop;
    use kartik\touchspin\TouchSpin;
    use kartik\widgets\Select2;
    use kartik\widgets\StarRating;
    use kartik\widgets\SwitchInput;
    use kartik\color\ColorInput;
    use kartik\range\RangeInput;
    use kartik\editors\Summernote;
    use kartik\editors\Codemirror;
    use kartik\widgets\SideNav;
    use kartik\daterange\DateRangePicker;
    use kartik\datecontrol\DateControl;
    use kartik\field\FieldRange;
    use kartik\money\MaskMoney;
    use yii\web\JsExpression;
    use kartik\slider\Slider;
    use kartik\password\PasswordInput;
    use kartik\sortable\Sortable;
    use kartik\sortinput\SortableInput;
    use kartik\grid\GridView;
    
    
    use kartik\icons\FontAwesomeAsset;
    FontAwesomeAsset::register($this);

    use app\models\Gender;
    use app\models\Country;
    use app\models\Education;
    use app\models\Institute;
    use app\models\Degree;
    use app\models\Technical;
    use app\models\Language;
    use app\models\Skill;
    use app\models\Department;
    use app\models\Field;



    $this->title = 'Employee Registration Form';
    $this->params['breadcrumbs'][] = $this->title;

?>  

<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Menu</div>
   
<?php echo SideNav::widget([
    'type' => SideNav::TYPE_INFO,
    'encodeLabels' => false,
    'heading' => '<i class="fas fa-cog"></i> Operations',
    'items' => [
        
        ['label' => 'Home', 'icon' => 'home', 'url' => [''], 'active' => false],
        ['label' => 'Books', 'icon' => 'book', 'items' => [
            ['label' => '<span class="pull-right float-right float-end badge">10</span> New Arrivals', 'url' => [''], 'active' =>  true],
            ['label' => '<span class="pull-right float-right float-end badge">5</span> Most Popular', 'url' => [''], 'active' =>  false],
            ['label' => 'Read Online', 'icon' => 'cloud', 'items' => [
                ['label' => 'Online 1', 'url' => [''], 'active' => false],
                ['label' => 'Online 2', 'url' => [''], 'active' => false]
            ]],
        ]],
        ['label' => '<span class="pull-right float-right float-end badge">3</span> Categories', 'icon' => 'tags', 'items' => [
            ['label' => 'Fiction', 'url' => [''], 'active' => false],
            ['label' => 'Historical', 'url' => [''], 'active' => false],
            ['label' => '<span class="pull-right float-right float-end badge">2</span> Announcements', 'icon' => 'bullhorn', 'items' => [
                ['label' => 'Event 1', 'url' => [''], 'active' => false],
                ['label' => 'Event 2', 'url' => [''], 'active' => false]
            ]],
        ]],
        ['label' => 'Profile', 'icon' => 'user', 'url' => [''], 'active' => false],
    ],
]); 
?>
</div>
</div>

<h1 class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;"><?= Html::encode($this->title) ?></h1>

<?php $form=ActiveForm::begin([
    'type'=>ActiveForm::TYPE_HORIZONTAL,
    'enableClientValidation' => true,
    'validateOnChange' => true,
    
    'formConfig'=>['labelSpan'=>4, 'deviceSize' => ActiveForm::SIZE_MEDIUM]
])?>


<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Personal Details</div>
        
    <div class="card-body">

        <?= $form->field($model, 'name')->textInput(['maxlength' => 100, 'placeholder' => 'Enter your name'])?>

        <?php // Adding a date picker widget for the date of birth field ?>
        <?= $form->field($model, 'dob')->widget(DatePicker::class,[
                'options' => ['placeholder' => 'Select date of birth'],
                'pluginOptions' => [
                'autoclose' => true,
                'format' => 'yyyy-mm-dd',
                'endDate' => '0d',
                'todayHighlight' => true,
                
            ]
        ])?>

        <?= $form->field($model, 'email')->textInput([
            'type' => 'email', 
            'placeholder' => 'Enter your email address...'
        ]) ?>

        <?php // Adding a prepend addons and hint ?>
        <?= $form->field($model, 'mobile',[
            'addon' =>[
                'prepend' => ['content' => '<i class="fas fa-phone"></i>']],
            
            'hintType' => ActiveField::HINT_SPECIAL,
            'hintSettings' => [
                'iconBesideInput' => true,
                'onLabelClick' => false,
                'onLabelHover' => false,
                'onIconClick' => true,
                'onIconHover' => true,
                'title' => '<i class="fas fa-info-sign"></i> Note'
            ]
        ])->textInput(['placeholder' => 'Enter your mobile number'])->hint('<div style="width:200px">Enter <b>Country codes </b> for more accuracy record.</div>')?>


        <?= $form->field($model, 'gender_id')->radioList(ArrayHelper::map(Gender::find()->all(), 'gender_id', 'gender'))?>

        <?php // Adding a prepend and append addons
        // Adding hint ?>
        <?php /*<?= $form->field($model, 'salary', [
            'addon' => ['prepend' => ['content' => '$'], 'append' => ['content' => 'USD']],
            'hintType' => ActiveField::HINT_SPECIAL,
            'hintSettings' => ['placement' => 'right','onLabelClick' => false,'onLabelHover' => false]
        ])->textInput()->hint('Please enter your gross annual salary')?>*/?>

        <?= $form->field($model, 'salary')->widget(MaskMoney::classname(), [
            'pluginOptions' => [
                'prefix' => '$ ',
                'allowNegative' => false ],
            'options' => ['id' => 'salary'] 
        ]); ?>

        <?= $form->field($model, 'advance')->widget(MaskMoney::classname(), [
            'pluginOptions' => [
                'prefix' => '$ ',
                'allowNegative' => false
            ]
        ]);?>

        <?= $form->field($model, 'country_id')->widget(select2::classname(),[
            'data' => ArrayHelper::map(Country::find()->all(), 'country_id', 'country'),
            'options' => [
                'id' => 'country_id', 
                'prompt' => 'Select Country'
            ]
        ]) ?>

        <?= $form->field($model, 'state_id')->widget(DepDrop::classname(),[
            'type' => DepDrop::TYPE_SELECT2, // This makes it searchable/editable
            'options'=>['id'=>'state_id','prompt'=> 'Select State'],
            'data' => $model->isNewRecord ? [] : [$model->state_id => ($model->state ? $model->state->states : $model->state_id)],
            'pluginOptions'=>[
                'depends'=>['country_id'],
                'url'=>Url::to(['/form/get-states']),
            ]
        ])?>


        <?= $form->field($model, 'city_id')->widget(DepDrop::classname(),[
            'type' => DepDrop::TYPE_SELECT2, // This makes it searchable/editable
            'options'=>['id'=>'city_id','prompt'=> 'Select City'],
            'data' => $model->isNewRecord ? [] : [$model->city_id => ($model->city ? $model->city->city : $model->city_id)],
            'pluginOptions'=>[
                'depends'=>['state_id'],
                'url'=>Url::to(['/form/get-cities'])
            ]
        ]) ?>

        <?= $form->field($model, 'food_preference_id')->widget(SwitchInput::class, [
            'pluginOptions' => [
                'size' => 'Medium',
                'onText' => 'Non-Vegetarian',
                'offText' => 'Vegetarian',
                'onColor' => 'primary',
                'offColor' => 'success' ]]);
        ?>

        <?= $form->field($model, 'fav_color')->widget(ColorInput::class, ['options' => ['placeholder' => 'Select color ...']]) ?>
        
        <?php   foreach($model->emp_language as $index => $langModel) {
                
                echo $form->field($langModel, "[$index]value")->widget(RangeInput::classname(), [
                    'options' => ['placeholder' => 'Rate (0 - 5)...'],
                    'html5Container' => ['style' => 'width:350px'],
                    'html5Options' => ['min' => 0, 'max' => 5],
                ])->label($langModel->language->language); 
                echo $form->field($langModel, "[$index]language_id")->hiddenInput()->label(false);
            }        
            ?>   


        <?php echo FieldRange::widget([
            'form' => $form,
            'model' => $model, // This binds the widget to the Employee model
            'label' => 'Enter salary expectation range (USD)',
            'attribute1' => 'minimum_salary',
            'attribute2' => 'maximum_salary',
            'type' => FieldRange::INPUT_SPIN,
        ]) ?>

        <?php /*<br><h5 class="mb-3">Set your preferred department order (Drag and Drop)</h5>
        <div class="mb-3">
           <?= $form->field($model, 'department_order')->widget(SortableInput::classname(), [
                
                'items' => array_map(function($label) {
                    return ['content' => $label];
                }, ArrayHelper::map(Department::find()->all(), 'department', 'department')),
                
                'sortableOptions' => [
                    'type' => Sortable::TYPE_LIST,
                ],
                'hideInput' => false,
                'options' => ['class' => 'form-control', 'readonly' => true]
            ]); ?>
        </div>*/ ?>


        <?=$form->field($model, 'password')->widget(PasswordInput::classname()) ?>

    </div>
</div>

<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Educational Details</div>

    <div class="card-body">
    <?= $form->field($model->emp_higher_edu, 'edu_id')->widget(select2::classname(),[
            'data' => ArrayHelper::map(Education::find()->all(), 'edu_id', 'edu_type'),
            'options' => [
                'id' => 'higher_edu_id', 
                'prompt' => 'Select Education'
            ]
        ]) ?>

        <?= $form->field($model->emp_higher_edu, 'degree_id')->widget(DepDrop::classname(),[
            'type' => DepDrop::TYPE_SELECT2, // This makes it searchable/editable
            'data'=>  $model->isNewRecord ? [] : [$model->emp_higher_edu->field_id => (Field::findOne($model->emp_higher_edu->field_id)->field ?? 'Select Degree')],
            'options'=>['id'=>'degree_id','prompt'=> 'Select Degree'],
            'pluginOptions'=>[
                'depends'=>['higher_edu_id'],
                'url'=>Url::to(['get-degrees']),
            ]
        ])?>

        <?= $form->field($model->emp_higher_edu, 'field_id')->widget(DepDrop::classname(),[
            'type' => DepDrop::TYPE_SELECT2, // This makes it searchable/editable
            'data'=>  $model->isNewRecord ? [] : [$model->emp_higher_edu->degree_id => (Degree::findOne($model->emp_higher_edu->degree_id)->degree ?? 'Select Degree')],
            'options'=>['id'=>'field_id','prompt'=> 'Select Field'],
            'pluginOptions'=>[
                'depends'=>['degree_id'],
                'url'=>Url::to(['get-fields']),
            ]
        ])?>

        <?= $form->field($model->emp_higher_edu, 'institute_id')->widget(select2::classname(),[
            'data' => ArrayHelper::map(Institute::find()->all(), 'institute_id', 'institute'),
            'options' => [
                'id' => 'institute_id', 
                'prompt' => 'Select Institute'
            ]
        ]) ?>

        <?php echo FieldRange::widget([
            'form' => $form,
            'model' => $model->emp_higher_edu, // Use the specific sub-model here
            'label' => 'Enter Period of Study',
            'attribute1' => 'start_date',
            'attribute2' => 'end_date',
            'type' => FieldRange::INPUT_WIDGET,
            'widgetClass' => DateControl::classname(),
            'widgetOptions1' => [
                'type' => DateControl::FORMAT_DATE, // Usually required for DateControl
                'saveFormat' => 'php:Y-m-d', // Save as YYYY-MM-DD string
                'displayFormat' => 'php:Y-m-d',
                'ajaxConversion' => true,
                'options' => [
                    'pluginOptions' => ['autoclose' => true]
                ]
            ],
            'widgetOptions2' => [
                'type' => DateControl::FORMAT_DATE,
                'saveFormat' => 'php:Y-m-d', // Save as YYYY-MM-DD string
                'displayFormat' => 'php:Y-m-d',
                'ajaxConversion' => true,
                'options' => [
                    'pluginOptions' => ['autoclose' => true]
                ]
            ],
        ]); ?>
 

    </div>

    <div class="card-body">

        <h3>C.C.E (O/L) Details</h3>
        <p style="color: blue;">Enter the number of passes obtained for each grade in the G.C.E. O/L Examination.</p>
        <div class="col-md-4">
            <?= $form->field($ol, '[0]grade_A')->widget(TouchSpin::classname(), ['pluginOptions' => [
                    'initval' => $ol->isNewRecord ? 0 : $ol->grade_A]]) ?>
        </div>
        
        <div class="col-md-4">
            <?= $form->field($model->ol, '[0]grade_B')->widget(TouchSpin::classname(), [
                'pluginOptions' => [
                    'initval' => $model->ol->isNewRecord ? 0 : $model->ol->grade_B,
                    'buttonup_class' => 'btn btn-primary', 
                    'buttondown_class' => 'btn btn-info', 
                    'buttonup_txt' => '<i class="fas fa-plus-circle"></i>', 
                    'buttondown_txt' => '<i class="fas fa-minus-circle"></i>']
                ]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model->ol, '[0]grade_C')->widget(TouchSpin::classname(), ['pluginOptions' => [
                'initval' => $model->ol->isNewRecord ? 0 : $model->ol->grade_C]]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model->ol, '[0]grade_S')->widget(TouchSpin::classname(), ['pluginOptions' => [
                'initval' => $model->ol->isNewRecord ? 0 : $model->ol->grade_S,
                'verticalbuttons' => true,
                'verticalup' => '<i class="fas fa-plus"></i>',
                 'verticaldown' => '<i class="fas fa-minus"></i>']]) ?>
        </div>
    </div>


    <div class="card-body">
        <h3>C.C.E (A/L) Details</h3>
        <p style="color: blue;">Enter the number of passes obtained for each grade in the G.C.E. A/L Examination.</p>

        <div class="col-md-4">
            <?= $form->field($model->al, '[1]grade_A')->input('number', ['value' => $model->al->isNewRecord ? 0 : $model->al->grade_A]) ?>
        </div>
        
        <div class="col-md-4">
            <?= $form->field($model->al, '[1]grade_B')->input('number', ['value' => $model->al->isNewRecord ? 0 : $model->al->grade_B]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model->al, '[1]grade_C')->input('number', ['value' => $model->al->isNewRecord ? 0 : $model->al->grade_C]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model->al, '[1]grade_S')->input('number', ['value' => $model->al->isNewRecord ? 0 : $model->al->grade_S]) ?>
        </div>

    </div>

</div>

<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Technical Knowledge</div>
        
    <div class="card-body">
        <?php 
            
            foreach($model->emp_tech_knowledge as $index => $techModel) {
                
                echo $form->field($techModel, "[$index]value")->widget(StarRating::classname(), [
                    'pluginOptions' => ['step' => 0.5, 'min'=> 0],
                ])->label($techModel->tech->technical_skill); 
                echo $form->field($techModel, "[$index]tech_id")->hiddenInput()->label(false);
            }        
            ?>        
        ?>
    </div>

    <div class="card-body">
        <h4>Soft Skills</h4>
        
    <?php 
        $SkillList = ArrayHelper::map(Skill::find()->all(), 'skill_id', 'skill');

        foreach($SkillList as $id => $skillName) {
            // 1. Get the specific model for this skill from the array
            // If it doesn't exist in the database yet, create a new one
            $empSkillModel = $model->emp_skill[$id] ?? new \app\models\EmpSkill(['skill_id' => $id]);

            // 2. Pass the OBJECT ($empSkillModel), NOT the array ($model->emp_skill)
            echo $form->field($empSkillModel, "[$id]skill_id")->hiddenInput(['value' => $id])->label(false);
            
            echo $form->field($empSkillModel, "[$id]value")->widget(Slider::classname(), [
                'sliderColor' => Slider::TYPE_INFO,
                'handleColor' => Slider::TYPE_INFO,
                'pluginOptions' => [
                    'min' => 0, 'max' => 5, 'step' => 0.1,
                    'tooltip' => 'always',
                ],
            ])->label($skillName);
        }
        ?>
    </div>
</div>

<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Show your self and skills</div>
        
    <div class="card-body">
        <?= $form->field($model->emp_brief_code, 'self_note')->widget(Summernote::class, [
            'useKrajeePresets' => true,]) ?>
        
        <?= $form->field($model->emp_brief_code, 'coding')->widget(Codemirror::class, [
    'useKrajeePresets' => true,])->label('Write simple Sign_In page UI code')?>
    </div>
</div>


<div class="card text-bg-light mb-3 mx-auto" style="max-width: 60rem;">
    <div class="card-header">Working Experience</div>
        
    <div class="card-body">
        <?= $form->field($model->emp_work, 'company_name')->textInput(['maxlength' => 100, 'placeholder' => 'Enter organization name'])?>
        <?= $form->field($model->emp_work, 'date_range')->widget(DateRangePicker::classname(), [
            'startAttribute' => 'start_date', // Submits to $model->start_date
            'endAttribute' => 'end_date',     // Submits to $model->end_date
            'options' => ['placeholder' => 'Select working period...',
            'required' => true],
            'pluginOptions' => [
                'locale' => ['format' => 'YYYY-MM-DD'],
                'maxDate' => new JsExpression('moment()'),
                'showDropdowns' => true, 
            ]
        ]) ?>
    </div>
</div>


<div class="card text-bg-light mb-3 mx-auto" style="max-width: 30rem;">
    <?= Html::submitButton('Register', ['class' => 'btn btn-success'])?>
    <?php ActiveForm::end() ?>
</div>