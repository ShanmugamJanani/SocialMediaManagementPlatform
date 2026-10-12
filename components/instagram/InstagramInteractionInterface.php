<?php
    namespace app\components\instagram;

    interface InstagramInteractionInterface 
    {
        /**
         * Send a response (Reply to a DM, Reply to a Comment).
         * @param string $targetId (Can be a native Instagram user IGID or a Comment ID)
         * @param string $message
         * @return bool
         */
        public function sendResponse(string $targetId, string $message): bool;

        /**
         * Fetch incoming data formatted uniformly.
         * @param array $webhookPayload
         * @return array
         */
        public function parseWebhookData(array $webhookPayload): array;
    }
?>