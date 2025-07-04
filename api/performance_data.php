<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/KpiCalculator.php';

try {
    $period = (int) ($_GET['period'] ?? 7);
    $dipendente_id = $_GET['dipendente_id'] ?? null;
    
    $data_fine = date('Y-m-d');
    $data_inizio = date('Y-m-d', strtotime("-{$period} days"));
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $sql = "SELECT 
        data,
        AVG(efficiency_rate) as avg_efficiency_rate,
        SUM(profit_loss) as total_profit_loss,
        SUM(ore_fatturabili) as total_billable_hours,
        COUNT(DISTINCT dipendente_id) as active_employees
    FROM kpi_giornalieri 
    WHERE data BETWEEN :data_inizio AND :data_fine";
    
    $params = [
        ':data_inizio' => $data_inizio,
        ':data_fine' => $data_fine
    ];
    
    if ($dipendente_id) {
        $sql .= " AND dipendente_id = :dipendente_id";
        $params[':dipendente_id'] = $dipendente_id;
    }
    
    $sql .= " GROUP BY data ORDER BY data";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $response = [
        'dates' => [],
        'efficiency_rates' => [],
        'profit_loss' => [],
        'billable_hours' => [],
        'daily_cost' => 80
    ];
    
    // Fill in missing dates with zero values
    $current_date = $data_inizio;
    $data_map = [];
    
    foreach ($results as $row) {
        $data_map[$row['data']] = $row;
    }
    
    while ($current_date <= $data_fine) {
        $response['dates'][] = date('d/m', strtotime($current_date));
        
        if (isset($data_map[$current_date])) {
            $row = $data_map[$current_date];
            $response['efficiency_rates'][] = round($row['avg_efficiency_rate'], 2);
            $response['profit_loss'][] = round($row['total_profit_loss'], 2);
            $response['billable_hours'][] = round($row['total_billable_hours'], 2);
        } else {
            $response['efficiency_rates'][] = 0;
            $response['profit_loss'][] = 0;
            $response['billable_hours'][] = 0;
        }
        
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore nel caricamento dati performance',
        'message' => $e->getMessage()
    ]);
}
?>