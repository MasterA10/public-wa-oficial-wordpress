<?php

namespace WAS\Core;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Global para Erros de Sistema e Exceções
 */
class SystemLogger {
    
    /**
     * Registra um erro ou mensagem de debug no sistema.
     * 
     * @param string $message Mensagem principal do erro
     * @param array $context Dados adicionais para depuração
     */
    public static function logError(string $message, array $context = []) {
        self::writeLog('SYSTEM_ERROR', $message, $context);
    }

    /**
     * Registra uma mensagem informativa de operação no sistema.
     * 
     * @param string $message Mensagem principal
     * @param array $context Dados adicionais para depuração
     */
    public static function logInfo(string $message, array $context = []) {
        self::writeLog('SYSTEM_INFO', $message, $context);
    }

    /**
     * Registra uma mensagem de warning no sistema.
     * 
     * @param string $message Mensagem principal
     * @param array $context Dados adicionais
     */
    public static function logWarning(string $message, array $context = []) {
        self::writeLog('SYSTEM_WARNING', $message, $context);
    }

    /**
     * Método interno unificado de escrita de log.
     */
    private static function writeLog(string $action, string $message, array $context = []) {
        global $wpdb;

        $table_name = TableNameResolver::getAuditLogsTable();
        $tenant_id = TenantContext::getTenantId();
        $user_id = get_current_user_id();

        $metadata = json_encode([
            'error_message' => $message,
            'context'       => $context
        ]);

        $wpdb->insert($table_name, [
            'tenant_id'   => $tenant_id ?: 0,
            'user_id'     => $user_id ?: 0,
            'action'      => $action,
            'entity_type' => 'system',
            'entity_id'   => '0',
            'metadata'    => $metadata,
            'created_at'  => current_time('mysql', 1)
        ]);
    }

    /**
     * Captura e registra uma Exceção completa (com Stack Trace).
     * 
     * @param \Throwable $e A exceção capturada
     * @param array $context Contexto adicional onde a exceção ocorreu
     */
    public static function logException(\Throwable $e, array $context = []) {
        $errorContext = array_merge([
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'code'    => $e->getCode(),
            'trace'   => $e->getTraceAsString(),
        ], $context);

        self::logError($e->getMessage(), $errorContext);
    }
}
