<?php
    use kartik\builder\Form;
    use kartik\form\ActiveForm;
    use yii\helpers\Html;
    use kartik\cmenu\ContextMenu;
    use yii\authclient\widgets\AuthChoice;
?>

    <div class="login-container" style="width: 50vw; margin: 50px auto;" >    
        <div class="card shadow-sm"> <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Login</h3> </div>
        
            <div class="card-body">   
    
                <?php $form = ActiveForm::begin(['type' => ActiveForm::TYPE_VERTICAL]);                 
                    
                    echo Form::widget([
                        'model' => $model,
                        'form' => $form,
                        'attributes' => [
                            'email' => [
                                'type' => Form::INPUT_TEXT,
                                'options' => [
                                    'type' => 'email',   
                                    'placeholder' => 'Enter your email...',
                                ],
                            ],
                            'password' => ['type' => Form::INPUT_PASSWORD],
                            
                            'actions' => [
                                'type' => Form::INPUT_RAW,
                                'value' => Html::submitButton('Login', [
                                    'class' => 'btn btn-primary btn-block w-100',
                                    'style' => 'margin-top: 10px'
                                ])
                            ],
                        ]
                        
                    ]);?>

                <br>
                
                <?php echo Html::a('Reset Password', ['form/reset'], [
                    'class' => 'btn btn-primary',
                    'style' => 'font-weight: bold; padding: 10px 20px;' 
                ]); 

                
                ActiveForm::end(); ?>


            </div>
        </div>
        
    </div>