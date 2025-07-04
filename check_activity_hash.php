<?php
require_once 'config/Database.php';

$database = new Database();
$conn = $database->getConnection();

try {
    $stmt = $conn->prepare('DESCRIBE attivita');
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    $found = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'activity_hash') {
            $found = true;
            break;
        }
    }
    
    if ($found) {
        echo "FOUND";
    } else {
        echo "NOT_FOUND";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>