<?php
class Security {
    
    public static function validateFileUpload($file) {
        // Load environment variables
        $envFile = __DIR__ . '/../.env';
        $maxFileSize = 10485760; // 10MB default
        $allowedTypes = ['csv', 'txt'];
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    if ($key === 'MAX_FILE_SIZE') {
                        $maxFileSize = (int)$value;
                    } elseif ($key === 'ALLOWED_FILE_TYPES') {
                        $allowedTypes = explode(',', $value);
                    }
                }
            }
        }
        
        // Check file upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Errore durante l'upload del file: " . self::getUploadErrorMessage($file['error']));
        }
        
        // Check file size
        if ($file['size'] > $maxFileSize) {
            throw new Exception("File troppo grande. Massimo consentito: " . self::formatBytes($maxFileSize));
        }
        
        // Check file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("Tipo di file non consentito. Tipi accettati: " . implode(', ', $allowedTypes));
        }
        
        // Check MIME type
        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception("Tipo MIME non consentito: " . $mimeType);
        }
        
        // Check file content (basic CSV validation)
        if ($fileExtension === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new Exception("Impossibile leggere il file CSV");
            }
            
            $firstLine = fgets($handle);
            fclose($handle);
            
            if (empty($firstLine) || strpos($firstLine, ',') === false) {
                throw new Exception("Il file non sembra essere un CSV valido");
            }
        }
        
        return true;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check token expiry (default 1 hour)
        $tokenExpiry = $_SESSION['csrf_token_time'] + 3600;
        if (time() > $tokenExpiry) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function validateDatabaseInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT) !== false;
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
            case 'string':
            default:
                return is_string($input) && strlen($input) <= 255;
        }
    }
    
    private static function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return "Il file supera la dimensione massima consentita dal server";
            case UPLOAD_ERR_FORM_SIZE:
                return "Il file supera la dimensione massima consentita dal form";
            case UPLOAD_ERR_PARTIAL:
                return "Il file è stato caricato parzialmente";
            case UPLOAD_ERR_NO_FILE:
                return "Nessun file è stato caricato";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Cartella temporanea mancante";
            case UPLOAD_ERR_CANT_WRITE:
                return "Impossibile scrivere il file su disco";
            case UPLOAD_ERR_EXTENSION:
                return "Upload fermato da un'estensione";
            default:
                return "Errore sconosciuto durante l'upload";
        }
    }
    
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>