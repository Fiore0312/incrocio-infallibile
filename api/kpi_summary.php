<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../classes/KpiCalculator.php';

try {
    $kpiCalculator = new KpiCalculator();
    
    $data_fine = date('Y-m-d');
    $data_inizio = date('Y-m-d', strtotime('-7 days'));
    
    $summary = $kpiCalculator->getKpiSummary(null, $data_inizio, $data_fine);
    
    // Ensure all values are properly formatted
    $response = [
        'avg_efficiency_rate' => round($summary['avg_efficiency_rate'] ?? 0, 2),
        'total_profit_loss' => round($summary['total_profit_loss'] ?? 0, 2),
        'totale_ore_fatturabili' => round($summary['totale_ore_fatturabili'] ?? 0, 2),
        'giorni_lavorativi' => (int) ($summary['giorni_lavorativi'] ?? 0),
        'totale_sessioni_remote' => (int) ($summary['totale_sessioni_remote'] ?? 0),
        'giorni_uso_veicoli' => (int) ($summary['giorni_uso_veicoli'] ?? 0),
        'avg_onsite_hours' => round($summary['avg_onsite_hours'] ?? 0, 2),
        'avg_travel_hours' => round($summary['avg_travel_hours'] ?? 0, 2),
        'remote_vs_onsite_ratio' => round($summary['remote_vs_onsite_ratio'] ?? 0, 2),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore nel caricamento KPI summary',
        'message' => $e->getMessage()
    ]);
}
?>