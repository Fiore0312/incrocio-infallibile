// Employee Analytics Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeRealTimeUpdates();
    initializeTooltips();
});

function initializeCharts() {
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        loadPerformanceChart();
    }
}

function loadPerformanceChart() {
    fetch('api/performance_data.php?period=7')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [
                        {
                            label: 'Efficiency Rate %',
                            data: data.efficiency_rates,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Profit/Loss €',
                            data: data.profit_loss,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Ore Fatturabili',
                            data: data.billable_hours,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                afterLabel: function(context) {
                                    if (context.datasetIndex === 1) {
                                        return 'Target: €' + (data.daily_cost || 80);
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Efficiency Rate %'
                            },
                            min: 0,
                            max: 120
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Profit/Loss €'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                        y2: {
                            type: 'linear',
                            display: false,
                            position: 'right',
                        }
                    },
                }
            });
        })
        .catch(error => {
            console.error('Error loading performance data:', error);
            showErrorMessage('Errore caricamento dati performance');
        });
}

function initializeRealTimeUpdates() {
    // Update KPIs every 5 minutes
    setInterval(updateKpis, 300000);
    
    // Update alerts every 2 minutes
    setInterval(updateAlerts, 120000);
}

function updateKpis() {
    fetch('api/kpi_summary.php')
        .then(response => response.json())
        .then(data => {
            updateKpiCards(data);
        })
        .catch(error => {
            console.error('Error updating KPIs:', error);
        });
}

function updateKpiCards(data) {
    // Update Efficiency Rate
    const efficiencyCard = document.querySelector('.card.bg-success .card-title');
    if (efficiencyCard && data.avg_efficiency_rate !== undefined) {
        efficiencyCard.textContent = data.avg_efficiency_rate.toFixed(1) + '%';
    }
    
    // Update Profit/Loss
    const profitCard = document.querySelector('.card.text-white .card-title');
    if (profitCard && data.total_profit_loss !== undefined) {
        profitCard.textContent = '€' + data.total_profit_loss.toFixed(2);
        
        // Update card color based on profit/loss
        const cardElement = profitCard.closest('.card');
        cardElement.className = cardElement.className.replace(/(bg-success|bg-danger)/, '');
        cardElement.classList.add(data.total_profit_loss >= 0 ? 'bg-success' : 'bg-danger');
    }
    
    // Update Billable Hours
    const hoursCard = document.querySelector('.card.bg-info .card-title');
    if (hoursCard && data.totale_ore_fatturabili !== undefined) {
        hoursCard.textContent = data.totale_ore_fatturabili.toFixed(1) + 'h';
    }
}

function updateAlerts() {
    fetch('api/alerts_count.php')
        .then(response => response.json())
        .then(data => {
            updateAlertsDisplay(data);
        })
        .catch(error => {
            console.error('Error updating alerts:', error);
        });
}

function updateAlertsDisplay(data) {
    const alertsTotal = Object.values(data).reduce((sum, count) => sum + count, 0);
    
    // Update main alert card
    const alertCard = document.querySelector('.card.bg-warning .card-title');
    if (alertCard) {
        alertCard.textContent = alertsTotal;
    }
    
    // Update individual alert counters
    const alertElements = {
        'efficiency_warnings': document.querySelector('.alert-item:nth-child(1) .badge'),
        'efficiency_critical': document.querySelector('.alert-item:nth-child(2) .badge'),
        'profit_warnings': document.querySelector('.alert-item:nth-child(3) .badge'),
        'ore_insufficienti': document.querySelector('.alert-item:nth-child(4) .badge')
    };
    
    Object.keys(alertElements).forEach(key => {
        const element = alertElements[key];
        if (element && data[key] !== undefined) {
            element.textContent = data[key];
            
            // Update badge color based on count
            element.className = element.className.replace(/(bg-warning|bg-danger|bg-success)/, '');
            if (data[key] === 0) {
                element.classList.add('bg-success');
            } else if (data[key] < 3) {
                element.classList.add('bg-warning');
            } else {
                element.classList.add('bg-danger');
            }
        }
    });
}

function initializeTooltips() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function showErrorMessage(message) {
    const alertContainer = document.createElement('div');
    alertContainer.className = 'alert alert-danger alert-dismissible fade show';
    alertContainer.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertContainer, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertContainer.remove();
        }, 5000);
    }
}

function showSuccessMessage(message) {
    const alertContainer = document.createElement('div');
    alertContainer.className = 'alert alert-success alert-dismissible fade show';
    alertContainer.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertContainer, container.firstChild);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            alertContainer.remove();
        }, 3000);
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

function formatPercentage(value) {
    return new Intl.NumberFormat('it-IT', {
        style: 'percent',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(value / 100);
}

function formatHours(hours) {
    return hours.toFixed(1) + 'h';
}

// Export functions for external use
window.EmployeeAnalytics = {
    updateKpis,
    updateAlerts,
    showErrorMessage,
    showSuccessMessage,
    formatCurrency,
    formatPercentage,
    formatHours
};