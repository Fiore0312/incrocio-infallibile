<?php

class ImportLogger {
    private $log_file;
    private $session_id;
    private $start_time;
    
    public function __construct($type = 'import') {
        $this->session_id = uniqid();
        $this->start_time = microtime(true);
        
        // Crea directory logs se non esiste
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $this->log_file = $log_dir . '/' . $type . '_' . date('Y-m-d') . '.log';
        
        $this->log('INFO', "=== Avvio sessione import (ID: {$this->session_id}) ===");
    }
    
    public function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $memory = round(memory_get_usage() / 1024 / 1024, 2);
        $elapsed = round((microtime(true) - $this->start_time), 3);
        
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        $log_entry = "[{$timestamp}] [{$this->session_id}] [{$level}] [Memory: {$memory}MB] [Time: {$elapsed}s] {$message}{$context_str}" . PHP_EOL;
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Log anche su error_log di PHP per livelli critici
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            error_log("ImportLogger [{$level}] {$message}");
        }
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log('CRITICAL', $message, $context);
    }
    
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    public function logFileStart($filename, $type) {
        $this->info("Inizio elaborazione file", [
            'filename' => $filename,
            'type' => $type,
            'file_size' => file_exists($filename) ? filesize($filename) : 'N/A'
        ]);
    }
    
    public function logFileEnd($filename, $stats) {
        $this->info("Fine elaborazione file", [
            'filename' => basename($filename),
            'stats' => $stats
        ]);
    }
    
    public function logValidationError($field, $value, $reason) {
        $this->warning("Validazione fallita", [
            'field' => $field,
            'value' => $value,
            'reason' => $reason
        ]);
    }
    
    public function logDuplicateSkipped($table, $identifier) {
        $this->info("Duplicato saltato", [
            'table' => $table,
            'identifier' => $identifier
        ]);
    }
    
    public function logEmployeeCreated($name, $id) {
        $this->info("Dipendente creato automaticamente", [
            'name' => $name,
            'id' => $id
        ]);
    }
    
    public function logEmployeeRejected($name, $reason) {
        $this->warning("Creazione dipendente rifiutata", [
            'name' => $name,
            'reason' => $reason
        ]);
    }
    
    public function getSessionId() {
        return $this->session_id;
    }
    
    public function getLogFile() {
        return $this->log_file;
    }
    
    public function close() {
        $total_time = round((microtime(true) - $this->start_time), 3);
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
        
        $this->log('INFO', "=== Fine sessione import ===", [
            'total_time' => $total_time . 's',
            'peak_memory' => $peak_memory . 'MB'
        ]);
    }
    
    // Metodo per leggere gli ultimi log
    public static function getRecentLogs($type = 'import', $lines = 100) {
        $log_dir = __DIR__ . '/../logs';
        $log_file = $log_dir . '/' . $type . '_' . date('Y-m-d') . '.log';
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $file_lines = file($log_file);
        return array_slice($file_lines, -$lines);
    }
    
    // Metodo per ottenere statistiche log
    public static function getLogStats($type = 'import', $date = null) {
        $log_dir = __DIR__ . '/../logs';
        $date = $date ?: date('Y-m-d');
        $log_file = $log_dir . '/' . $type . '_' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return null;
        }
        
        $content = file_get_contents($log_file);
        
        return [
            'file_size' => filesize($log_file),
            'total_lines' => substr_count($content, "\n"),
            'error_count' => substr_count($content, '[ERROR]'),
            'warning_count' => substr_count($content, '[WARNING]'),
            'info_count' => substr_count($content, '[INFO]'),
            'debug_count' => substr_count($content, '[DEBUG]'),
            'sessions' => substr_count($content, '=== Avvio sessione import')
        ];
    }
}