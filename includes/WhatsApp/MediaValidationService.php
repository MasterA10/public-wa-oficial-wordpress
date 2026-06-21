<?php
namespace WAS\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

class MediaValidationService {
    
    public function validate($filePath, $mimeType, $mediaType) {
        switch ($mediaType) {
            case 'image':
                $this->validateImage($filePath, $mimeType);
                break;
            case 'audio':
                $this->validateAudio($filePath, $mimeType);
                break;
            case 'document':
                $this->validateDocument($filePath, $mimeType);
                break;
            case 'video':
                $this->validateVideo($filePath, $mimeType);
                break;
            default:
                throw new \Exception('Tipo de mídia não suportado.');
        }
        return true;
    }

    public function validateImage($filePath, $mimeType) {
        $allowed = ['image/jpeg', 'image/png'];
        $this->validateBase($filePath, $mimeType, $allowed, 5 * 1024 * 1024);
    }

    public function validateAudio($filePath, $mimeType) {
        $allowed = ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'];
        $this->validateBase($filePath, $mimeType, $allowed, 16 * 1024 * 1024);
    }

    public function validateDocument($filePath, $mimeType) {
        $allowed = [
            'application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        $this->validateBase($filePath, $mimeType, $allowed, 100 * 1024 * 1024);
    }

    public function validateVideo($filePath, $mimeType) {
        $allowed = ['video/mp4', 'video/3gp'];
        $this->validateBase($filePath, $mimeType, $allowed, 16 * 1024 * 1024);
    }

    private function validateBase($filePath, $mimeType, $allowed, $maxBytes) {
        if (!file_exists($filePath)) {
            throw new \Exception('Arquivo não encontrado.');
        }

        if (!in_array($mimeType, $allowed)) {
            throw new \Exception('Tipo de arquivo não permitido: ' . $mimeType);
        }

        if (filesize($filePath) > $maxBytes) {
            throw new \Exception('Arquivo excede o tamanho máximo permitido.');
        }
    }
}
