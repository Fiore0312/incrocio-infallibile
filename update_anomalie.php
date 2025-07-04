<?php
require_once 'config/Database.php';
try {
    $database = new Database();
    $conn = $database->getConnection();
    $sql = file_get_contents('update_anomalie_table.sql');
    $conn->exec($sql);
    echo 'Tabella anomalie aggiornata con successo' . PHP_EOL;
} catch (Exception $e) {
    echo 'Errore aggiornamento tabella anomalie: ' . $e->getMessage() . PHP_EOL;
}
?>