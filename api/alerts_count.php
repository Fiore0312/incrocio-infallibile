<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../classes/KpiCalculator.php';

try {
    $kpiCalculator = new KpiCalculator();
    
    $data_fine = date('Y-m-d');
    $data_inizio = date('Y-m-d', strtotime('-7 days'));
    
    $alerts = $kpiCalculator->getAlertsCount($data_inizio, $data_fine);
    
    $response = [
        'efficiency_warnings' => (int) ($alerts['efficiency_warnings'] ?? 0),
        'efficiency_critical' => (int) ($alerts['efficiency_critical'] ?? 0),
        'profit_warnings' => (int) ($alerts['profit_warnings'] ?? 0),
        'ore_insufficienti' => (int) ($alerts['ore_insufficienti'] ?? 0),
        'total_alerts' => 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    $response['total_alerts'] = array_sum(array_slice($response, 0, 4));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore nel caricamento alert count',
        'message' => $e->getMessage()
    ]);
}
?>