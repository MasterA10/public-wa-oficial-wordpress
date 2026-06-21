<?php

namespace WAS\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Checklist de App Review
 */
class ReviewChecklistService {
    
    public function getJustifications() {
        return [
            'whatsapp_business_messaging' => "We need whatsapp_business_messaging to allow businesses connected to our SaaS platform to send WhatsApp messages, receive incoming customer messages, and receive message delivery status webhooks through the official WhatsApp Business Platform.",
            'whatsapp_business_management' => "We need whatsapp_business_management to allow businesses to connect and manage their WhatsApp Business assets inside our SaaS platform, including WhatsApp Business Accounts, phone numbers, message templates, and webhook configuration."
        ];
    }

    public function getTechnicalWalkthrough() {
        return [
            'step_1' => 'Login to the SaaS dashboard.',
            'step_2' => 'Navigate to WhatsApp Setup to connect WABA.',
            'step_3' => 'Go to Templates to create and sync official message templates.',
            'step_4' => 'Use the Inbox to send a template message to a customer.',
            'step_5' => 'Verify message status and incoming webhooks in the Logs section.'
        ];
    }
}
