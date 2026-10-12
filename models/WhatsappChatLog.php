<?php
    namespace app\models;

    use Yii;
    use yii\db\ActiveRecord;
    use app\model\SocialContact;

class WhatsappChatLog extends ActiveRecord
{
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }

    public function rules()
{
    return [
        [['phone_number', 'type', 'direction', 'created_at', 'meta_message_id'], 'required'],
        [['message_body', 'direction'], 'string'],
        [['created_at'], 'safe'],
        [['phone_number'], 'string', 'max' => 20],
        [['meta_message_id', 'meta_id_of_parent_msg'], 'string', 'max' => 255],

    ];
}


/**
 * Establishes a relation back to the parent message row using the Meta ID mapping.
 */
public function getParentMessage()
{
    return $this->hasOne(self::class, ['meta_message_id' => 'meta_id_of_parent_msg']);
}

public static function logMessage($phone, $body, $direction, $type, $metaId, $parentId = null)
{
    $now = date('Y-m-d H:i:s');
    
    $log = new self();
    $log->phone_number = $phone;
    $log->message_body = $body;
    $log->direction = $direction;
    $log->type = $type;
    $log->meta_message_id = $metaId;
    $log->created_at = $now;
    
    if ($parentId) {
        $log->meta_id_of_parent_msg = $parentId;
    }

    if ($log->save()) {
        // Automatically verify and track contact logic inside outbound/inbound operations
        if ($direction === 'inbound') {
            self::ensureContactExists($phone, $now);
        }
        return true;
    }
    
    \Yii::error($log->errors, 'whatsapp_log_saving_failure');
    return false;
}

private static function ensureContactExists($phone, $timestamp)
{
    $contact = \app\models\SocialContact::findOne(['phone_number' => $phone]);
    if (!$contact) {
        $contact = new SocialContact();
        $contact->phone_number = $phone;
        $contact->platform = 'whatsapp';
        $contact->created_at = $timestamp;
        if (!$contact->save()) {
            Yii::error($contact->errors, 'whatsapp_contact_creation_failure');
        }
    }
}

}

?>