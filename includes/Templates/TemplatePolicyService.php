<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplatePolicyService {

    public function canEdit(object $template): bool {
        $status = strtoupper($template->status ?? 'DRAFT');

        return in_array($status, [
            'DRAFT',
            'REJECTED',
            'PAUSED',
            'APPROVED',
        ], true);
    }

    public function shouldBlockEdit(object $template): bool {
        $status = strtoupper($template->status ?? 'DRAFT');
        return in_array($status, [
            'PENDING',
            'IN_REVIEW',
            'DISABLED',
            'DELETED',
        ], true);
    }

    public function shouldRecommendDuplicate(object $template): bool {
        $status = strtoupper($template->status ?? 'DRAFT');
        return in_array($status, [
            'APPROVED',
            'DISABLED',
        ], true);
    }
}
