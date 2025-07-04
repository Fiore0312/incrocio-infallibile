<?php
require_once 'config/Database.php';
try {
    $database = new Database();
    $conn = $database->getConnection();
    $sql = file_get_contents('update_kpi_table.sql');
    $statements = explode(';', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            $conn->exec($stmt);
        }
    }
    echo 'Database aggiornato con successo con campo validation_alerts' . PHP_EOL;
} catch (Exception $e) {
    echo 'Errore aggiornamento database: ' . $e->getMessage() . PHP_EOL;
}
?>