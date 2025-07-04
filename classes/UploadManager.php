<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ImportLogger.php';

/**
 * Upload Manager for improved file handling and session management
 * 
 * Risolve i problemi di gestione file upload e path persistenti
 * Supporta progress tracking, preview, e re-upload selettivo
 */
class UploadManager {
    private $conn;
    private $logger;
    private $session_id;
    private $upload_base_dir;
    private $max_file_age_days = 7; // Cleanup automatico dopo 7 giorni
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->logger = new ImportLogger('upload_manager');
        
        // Genera session ID unico o usa quello esistente
        $this->session_id = $this->getOrCreateSessionId();
        $this->upload_base_dir = __DIR__ . '/../uploads';
        
        $this->initializeUploadEnvironment();
    }
    
    /**
     * Ottiene o crea session ID per tracking upload
     */
    private function getOrCreateSessionId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['upload_session_id'])) {
            $_SESSION['upload_session_id'] = 'session_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        }
        
        return $_SESSION['upload_session_id'];
    }
    
    /**
     * Inizializza ambiente upload
     */
    private function initializeUploadEnvironment() {
        // Crea directory base se non esiste
        if (!is_dir($this->upload_base_dir)) {
            mkdir($this->upload_base_dir, 0755, true);
        }
        
        // Crea directory sessione
        $session_dir = $this->getSessionDirectory();
        if (!is_dir($session_dir)) {
            mkdir($session_dir, 0755, true);
        }
        
        // Cleanup file vecchi
        $this->cleanupOldUploads();
        
        $this->logger->info("Upload environment inizializzato per sessione: " . $this->session_id);
    }
    
    /**
     * Ottiene directory sessione corrente
     */
    public function getSessionDirectory() {
        return $this->upload_base_dir . '/' . $this->session_id;
    }
    
    /**
     * Processa upload files con tracking avanzato
     */
    public function processFileUploads($files) {
        $results = [
            'success' => false,
            'uploaded_files' => [],
            'errors' => [],
            'session_directory' => $this->getSessionDirectory(),
            'session_id' => $this->session_id
        ];
        
        try {
            $session_dir = $this->getSessionDirectory();
            
            foreach ($files as $field_name => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $upload_result = $this->processIndividualFile($file, $field_name, $session_dir);
                    
                    if ($upload_result['success']) {
                        $results['uploaded_files'][] = $upload_result;
                    } else {
                        $results['errors'][] = $upload_result['error'];
                    }
                } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $results['errors'][] = $this->getUploadErrorMessage($file['error'], $field_name);
                }
            }
            
            // Salva informazioni sessione
            $this->saveSessionInfo($results);
            
            $results['success'] = !empty($results['uploaded_files']) && empty($results['errors']);
            
            if ($results['success']) {
                $this->logger->info("Upload completato con successo", [
                    'files_count' => count($results['uploaded_files']),
                    'session_id' => $this->session_id
                ]);
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Errore generale upload: " . $e->getMessage();
            $this->logger->error("Errore processamento upload: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Processa singolo file
     */
    private function processIndividualFile($file, $field_name, $session_dir) {
        try {
            $original_filename = basename($file['name']);
            $file_info = pathinfo($original_filename);
            
            // Validazione estensione
            if (strtolower($file_info['extension']) !== 'csv') {
                return [
                    'success' => false,
                    'error' => "File $original_filename: solo file CSV sono permessi"
                ];
            }
            
            // Validazione dimensione (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                return [
                    'success' => false,
                    'error' => "File $original_filename: dimensione troppo grande (max 10MB)"
                ];
            }
            
            // Genera nome file sicuro
            $safe_filename = $this->generateSafeFilename($original_filename, $field_name);
            $target_path = $session_dir . '/' . $safe_filename;
            
            // Sposta file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Analizza file per preview
                $file_analysis = $this->analyzeUploadedFile($target_path);
                
                return [
                    'success' => true,
                    'field_name' => $field_name,
                    'original_filename' => $original_filename,
                    'safe_filename' => $safe_filename,
                    'target_path' => $target_path,
                    'file_size' => $file['size'],
                    'file_analysis' => $file_analysis
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Impossibile salvare file $original_filename"
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "Errore processamento $original_filename: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera nome file sicuro
     */
    private function generateSafeFilename($original_filename, $field_name) {
        $file_info = pathinfo($original_filename);
        $timestamp = date('H-i-s');
        
        // Rimuovi caratteri non sicuri
        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_info['filename']);
        
        return "{$field_name}_{$timestamp}_{$safe_name}.{$file_info['extension']}";
    }
    
    /**
     * Analizza file caricato per preview
     */
    private function analyzeUploadedFile($file_path) {
        try {
            if (!file_exists($file_path)) {
                return ['error' => 'File non trovato'];
            }
            
            $handle = fopen($file_path, 'r');
            if (!$handle) {
                return ['error' => 'Impossibile aprire file'];
            }
            
            // Rileva separatore
            $separator = $this->detectCsvSeparator($handle);
            
            // Leggi header
            $header = fgetcsv($handle, 0, $separator);
            if (!$header) {
                fclose($handle);
                return ['error' => 'Header non valido'];
            }
            
            // Rimuovi BOM se presente
            $header = $this->removeBomFromHeader($header);
            
            // Leggi prime 5 righe per preview
            $preview_rows = [];
            $row_count = 0;
            while (($row = fgetcsv($handle, 0, $separator)) !== FALSE && $row_count < 5) {
                if (count($row) === count($header)) {
                    $preview_rows[] = array_combine($header, $row);
                }
                $row_count++;
            }
            
            // Conta totale righe
            $total_rows = 1; // Header
            while (($row = fgetcsv($handle, 0, $separator)) !== FALSE) {
                $total_rows++;
            }
            
            fclose($handle);
            
            return [
                'separator' => $separator,
                'header' => $header,
                'preview_rows' => $preview_rows,
                'total_rows' => $total_rows,
                'columns_count' => count($header),
                'file_size_kb' => round(filesize($file_path) / 1024, 2)
            ];
            
        } catch (Exception $e) {
            if (isset($handle)) fclose($handle);
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Rileva separatore CSV
     */
    private function detectCsvSeparator($handle) {
        $pos = ftell($handle);
        $first_line = fgets($handle);
        fseek($handle, $pos);
        
        if (!$first_line) {
            return ';';
        }
        
        $separators = [',', ';', "\t", '|'];
        $separator_counts = [];
        
        foreach ($separators as $sep) {
            $separator_counts[$sep] = substr_count($first_line, $sep);
        }
        
        return array_search(max($separator_counts), $separator_counts) ?: ';';
    }
    
    /**
     * Rimuovi BOM da header
     */
    private function removeBomFromHeader($header) {
        if (!empty($header[0])) {
            $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);
        }
        return $header;
    }
    
    /**
     * Salva informazioni sessione per recovery
     */
    private function saveSessionInfo($upload_results) {
        $session_info = [
            'session_id' => $this->session_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'uploaded_files' => $upload_results['uploaded_files'],
            'errors' => $upload_results['errors'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
        
        $session_file = $this->getSessionDirectory() . '/session_info.json';
        file_put_contents($session_file, json_encode($session_info, JSON_PRETTY_PRINT));
    }
    
    /**
     * Recupera informazioni sessione esistente
     */
    public function getSessionInfo() {
        $session_file = $this->getSessionDirectory() . '/session_info.json';
        
        if (file_exists($session_file)) {
            $content = file_get_contents($session_file);
            return json_decode($content, true);
        }
        
        return null;
    }
    
    /**
     * Lista file sessione corrente
     */
    public function listSessionFiles() {
        $session_dir = $this->getSessionDirectory();
        $files = [];
        
        if (is_dir($session_dir)) {
            $scan_files = array_diff(scandir($session_dir), ['.', '..', 'session_info.json']);
            
            foreach ($scan_files as $filename) {
                $file_path = $session_dir . '/' . $filename;
                if (is_file($file_path)) {
                    $files[] = [
                        'filename' => $filename,
                        'path' => $file_path,
                        'size' => filesize($file_path),
                        'modified' => filemtime($file_path),
                        'analysis' => $this->analyzeUploadedFile($file_path)
                    ];
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Rimuovi file specifico dalla sessione
     */
    public function removeSessionFile($filename) {
        $file_path = $this->getSessionDirectory() . '/' . basename($filename);
        
        if (file_exists($file_path) && is_file($file_path)) {
            if (unlink($file_path)) {
                $this->logger->info("File rimosso dalla sessione: $filename");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Cleanup automatico upload vecchi
     */
    private function cleanupOldUploads() {
        if (!is_dir($this->upload_base_dir)) return;
        
        $directories = array_diff(scandir($this->upload_base_dir), ['.', '..']);
        $cleaned_count = 0;
        
        foreach ($directories as $dir) {
            $dir_path = $this->upload_base_dir . '/' . $dir;
            
            if (is_dir($dir_path)) {
                $dir_age = time() - filemtime($dir_path);
                $max_age = $this->max_file_age_days * 24 * 60 * 60;
                
                if ($dir_age > $max_age) {
                    $this->removeDirectoryRecursive($dir_path);
                    $cleaned_count++;
                }
            }
        }
        
        if ($cleaned_count > 0) {
            $this->logger->info("Cleanup automatico: $cleaned_count directory rimosse");
        }
    }
    
    /**
     * Rimuovi directory ricorsivamente
     */
    private function removeDirectoryRecursive($dir) {
        if (!is_dir($dir)) return false;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                $this->removeDirectoryRecursive($file_path);
            } else {
                unlink($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Ottieni messaggio errore upload
     */
    private function getUploadErrorMessage($error_code, $field_name) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => "File $field_name troppo grande (limite PHP)",
            UPLOAD_ERR_FORM_SIZE => "File $field_name troppo grande (limite form)",
            UPLOAD_ERR_PARTIAL => "Upload $field_name incompleto",
            UPLOAD_ERR_NO_TMP_DIR => "Directory temporanea mancante",
            UPLOAD_ERR_CANT_WRITE => "Impossibile scrivere file $field_name",
            UPLOAD_ERR_EXTENSION => "Upload $field_name bloccato da estensione"
        ];
        
        return $messages[$error_code] ?? "Errore sconosciuto upload $field_name";
    }
    
    /**
     * Ottieni statistiche upload
     */
    public function getUploadStats() {
        $stats = [
            'session_id' => $this->session_id,
            'session_directory' => $this->getSessionDirectory(),
            'files_in_session' => count($this->listSessionFiles()),
            'session_size_mb' => 0
        ];
        
        // Calcola dimensione sessione
        $session_files = $this->listSessionFiles();
        $total_size = 0;
        foreach ($session_files as $file) {
            $total_size += $file['size'];
        }
        $stats['session_size_mb'] = round($total_size / 1024 / 1024, 2);
        
        return $stats;
    }
}
?>