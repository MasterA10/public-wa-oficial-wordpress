<?php
namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class MetaEndpointRegistry {
    public static function resolve(string $operation, array $params = []): string {
        $map = [
            'templates.create' => '/{waba_id}/message_templates',
            'templates.list'   => '/{waba_id}/message_templates',
            'templates.delete' => '/{waba_id}/message_templates',
            'templates.get'    => '/{template_id}',
            'templates.update' => '/{template_id}',

            'messages.send'    => '/{phone_number_id}/messages',
            'messages.mark_read' => '/{phone_number_id}/messages',

            'media.upload'     => '/{phone_number_id}/media',
            'media.get'        => '/{media_id}',
            'media.delete'     => '/{media_id}',

            'oauth.access_token' => '/oauth/access_token',
            'waba.subscribe_webhooks' => '/{waba_id}/subscribed_apps',
            'waba.get_subscribed_apps' => '/{waba_id}/subscribed_apps',
            'waba.get' => '/{waba_id}',
            'waba.phone_numbers' => '/{waba_id}/phone_numbers',
            'phone.get' => '/{phone_number_id}',
            'phone.business_profile' => '/{phone_number_id}/whatsapp_business_profile',
        ];

        if (!isset($map[$operation])) {
            return '';
        }

        $path = $map[$operation];
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string)$value, $path);
        }

        return $path;
    }
}
