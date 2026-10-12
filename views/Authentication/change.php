<?php
    use kartik\builder\Form;
    use kartik\form\ActiveForm;
    use yii\helpers\Html;
?>

    <div class="login-container" style="width: 50vw; margin: 50px auto;" >    
        <div class="card shadow-sm"> <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Change Password</h3> </div>
        
            <div class="card-body">   
    
                <?php $form = ActiveForm::begin(['type' => ActiveForm::TYPE_VERTICAL]);?>

                <?php $config = ['template'=>"{input}\n{error}\n{hint}"]; // config to deactivate label for ActiveField ?>

                    <?= $form->field($user,'email') ?>
                    <?= $form->field($user,'currentPassword') ?>
                    <?= $form->field($user,'newPassword') ?>

                    <?= Html::submitButton('Reset', ['class' => 'btn btn-success'])?>
                
                <?php ActiveForm::end(); ?>


            </div>
        </div>
        
    </div>