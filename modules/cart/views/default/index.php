<?php

use kartik\grid\GridView;
?>

<div class="cart-default-index"> 

    <?php
    echo GridView::widget([
        'dataProvider'=> $dataProvider,
        'filterModel' => $searchModel,
        'columns' => $gridColumns,
        'responsive'=>true,
        'hover'=>true
    ]);
    ?>
    
</div>
