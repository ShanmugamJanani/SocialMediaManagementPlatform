<?php
    use kartik\builder\Form;
    use kartik\form\ActiveForm;
    use yii\helpers\Html;
    use kartik\cmenu\ContextMenu;
?>

    <div class="login-container" style="width: 50vw; margin: 50px auto;" >    
        <div class="card shadow-sm"> <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Password Reset</h3> </div>
        
            <div class="card-body">   
    
                <?php $form = ActiveForm::begin(['type' => ActiveForm::TYPE_VERTICAL]); ?>

                <?= $form ->field($user, 'email') ?>
                     
            <?php // *************************************************************** //  ?>
            <?= ActiveForm::end();?>        
                

            </div>
        </div>
    </div>