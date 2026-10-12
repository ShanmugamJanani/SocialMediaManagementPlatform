<?php
    namespace app\models;

    use Yii;
    use yii\db\ActiveRecord;

    class InstagramChatLog extends ActiveRecord
    {
        public static function tableName()
        {
            return 'YOUR_DATABASE_NAME';
            }

        public function rules()
        {
            return [
                [['platform_uid', 'type', 'direction', 'created_at', 'meta_message_id'], 'required'],
                [['message_body', 'direction'], 'string'],
                [['created_at','deleted_at','enabled_comments'], 'safe'],
                [['platform_uid'], 'string', 'max' => 20],
                ['category', 'in', 'range' => ['DM', 'COMMENT', 'POST']],
                [['meta_message_id', 'meta_id_of_parent_msg'], 'string', 'max' => 255],

            ];
        }

        // Add these custom static methods inside your InstagramChatLog Model class body:

        public static function findSourceMessage($messageId)
        {
            $msg = self::findOne(['meta_message_id' => $messageId]);
            return $msg ?: self::findOne($messageId);
        }

        public static function logMessage($id, $body, $direction, $type, $metaId, $parentId = null)
        {
            $now = date('Y-m-d H:i:s');
            
            $log = new self();
            $log->platform_uid = $id;
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
                    self::ensureContactExists($id, $now);
                }
                return true;
            }
            
            Yii::error($log->errors, 'instagram_log_saving_failure');
            return false;
        }

        private static function ensureContactExists($id, $timestamp)
        {
            $contact = \app\models\SocialContact::findOne(['instagram_id' => $id]);
            if (!$contact) {
                $contact = new \app\models\InstagramContact();
                $contact->platform_uid = $id;
                $contact->platform = 'instagram';
                $contact->created_at = $timestamp;
                if (!$contact->save()) {
                    Yii::error($contact->errors, 'instagram_contact_creation_failure');
                }
            }
        }
    }

?>