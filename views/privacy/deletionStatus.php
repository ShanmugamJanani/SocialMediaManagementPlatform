<?php
/* @var $this yii\web\View */
/* @var $confirmationCode string */

$this->title = 'Data Deletion Status';
?>

<div class="privacy-deletion-status" style="padding: 40px 0; max-width: 600px; margin: 0 auto;">
    <div class="card" style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); font-family: sans-serif;">
        
        <!-- Success Icon / Header -->
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="font-size: 48px; color: #28a745;">✓</span>
            <h2 style="color: #333; margin-top: 10px;">Request Processed</h2>
        </div>

        <p style="color: #666; line-size: 1.6; text-align: center;">
            Your request to delete your third-party account data has been successfully received and fully executed. All personal records linked to this account have been scrubbed from our systems.
        </p>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

        <!-- Confirmation Code Display -->
        <div style="background-color: #f8f9fa; border-radius: 6px; padding: 15px; text-align: center;">
            <span style="display: block; font-size: 12px; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 5px;">
                Confirmation Code
            </span>
            <strong style="font-size: 16px; color: #111; font-family: monospace;">
                <?= $confirmationCode ?>
            </strong>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="<?= \yii\helpers\Url::home(true) ?>" class="btn btn-primary" style="background-color: #007bff; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none; display: inline-block;">
                Return to Homepage
            </a>
        </div>

    </div>
</div>