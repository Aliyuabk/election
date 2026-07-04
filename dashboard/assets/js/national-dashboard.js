// ============================================================
// STATE COORDINATOR DASHBOARD CHARTS
// ============================================================
const DashboardCharts = {
    init: function() {
        const data = window.DASHBOARD_DATA || {};
        
        // Progress Chart
        this.progressChart = new Chart(
            document.getElementById('progressChart'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Verified', 'Pending', 'Flagged'],
                    datasets: [{
                        data: [
                            data.verified || 0,
                            data.pending || 0,
                            data.flagged || 0
                        ],
                        backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 12,
                                font: { size: 11 }
                            }
                        }
                    },
                    cutout: '65%'
                }
            }
        );

        // Top LGAs Chart
        this.topLgasChart = new Chart(
            document.getElementById('topLgasChart'),
            {
                type: 'bar',
                data: {
                    labels: data.topLgasLabels || ['No Data'],
                    datasets: [{
                        label: 'Verified Results',
                        data: data.topLgasData || [0],
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: '#10B981',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { size: 10 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { 
                                font: { size: 10 },
                                maxRotation: 45
                            }
                        }
                    }
                }
            }
        );
    }
};