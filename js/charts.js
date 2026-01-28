// Store chart instances
let pieChartInstance = null;
let lineChartInstance = null;
let barChartInstance = null;

// Color palettes
const pieChartColors = [
    '#e2001a', '#ff6b6b', '#ff9e6d', '#ffd166', '#06d6a0',
    '#118ab2', '#073b4c', '#7209b7', '#f72585', '#4cc9f0',
    '#2a9d8f', '#e9c46a', '#f4a261', '#e76f51', '#264653'
];

const barChartColors = [
    'rgba(226, 0, 26, 0.8)',    // Red
    'rgba(17, 138, 178, 0.8)',  // Blue
    'rgba(111, 66, 193, 0.8)',  // Purple
    'rgba(6, 214, 160, 0.8)',   // Teal
    'rgba(255, 209, 102, 0.8)', // Yellow
    'rgba(255, 107, 107, 0.8)', // Light Red
    'rgba(233, 196, 106, 0.8)', // Light Yellow
    'rgba(42, 157, 143, 0.8)',  // Dark Teal
    'rgba(244, 162, 97, 0.8)',  // Orange
    'rgba(231, 111, 81, 0.8)'   // Dark Orange
];

document.addEventListener('DOMContentLoaded', function () {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded!');
        // Show error messages
        showNoDataMessage('pieChart', 'Chart library not loaded');
        showNoDataMessage('lineChart', 'Chart library not loaded');
        showNoDataMessage('barChart', 'Chart library not loaded');
        return;
    }

    console.log('Initializing charts with data:', chartData);

    // Wait a bit to ensure DOM is ready
    setTimeout(() => {
        try {
            createPieChart();
            createLineChart();
            createBarChart();
        } catch (error) {
            console.error('Chart initialization error:', error);
        }
    }, 100);
});

function createPieChart() {
    const ctx = document.getElementById('pieChart');
    if (!ctx) {
        console.error('Pie chart canvas not found');
        return;
    }

    // Destroy existing chart if it exists
    if (pieChartInstance) {
        pieChartInstance.destroy();
        pieChartInstance = null;
    }

    // Check if chartData exists
    if (typeof chartData === 'undefined') {
        console.error('chartData is undefined');
        showNoDataMessage('pieChart', 'Chart data not available');
        return;
    }

    const data = chartData.surveyResponseCounts || [];
    console.log('Pie chart data:', data);

    if (!Array.isArray(data) || data.length === 0) {
        showNoDataMessage('pieChart', 'No survey response data available');
        return;
    }

    // Prepare labels and data
    const labels = data.map(item => {
        const title = item.title || 'Untitled Survey';
        // Truncate long titles
        return title.length > 30 ? title.substring(0, 27) + '...' : title;
    });

    const values = data.map(item => parseInt(item.response_count) || 0);

    // Check if we have any positive values
    if (values.every(v => v === 0)) {
        showNoDataMessage('pieChart', 'No responses recorded yet');
        return;
    }

    // Create chart
    try {
        pieChartInstance = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: pieChartColors.slice(0, labels.length),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} responses (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
        console.log('Pie chart created successfully');
    } catch (error) {
        console.error('Error creating pie chart:', error);
        showNoDataMessage('pieChart', 'Error loading chart');
    }
}

function createLineChart() {
    const ctx = document.getElementById('lineChart');
    if (!ctx) {
        console.error('Line chart canvas not found');
        return;
    }

    // Destroy existing chart if it exists
    if (lineChartInstance) {
        lineChartInstance.destroy();
        lineChartInstance = null;
    }

    // Check if chartData exists
    if (typeof chartData === 'undefined') {
        console.error('chartData is undefined');
        showNoDataMessage('lineChart', 'Chart data not available');
        return;
    }

    const data = chartData.monthlyTrends || [];
    console.log('Line chart data (6-month intervals):', data);

    if (!Array.isArray(data) || data.length === 0) {
        showNoDataMessage('lineChart', 'No trend data available');
        return;
    }

    // Prepare data - handle different data structures
    const labels = [];
    const values = [];

    data.forEach(item => {
        // Handle different field names
        const label = item.period_label || item.label || item.month || 'Unknown';
        const value = parseInt(item.response_count || item.count || 0);

        // Format label for 6-month periods
        let formattedLabel = label;
        if (label.includes('H1')) {
            formattedLabel = label.replace('H1', 'Jan-Jun');
        } else if (label.includes('H2')) {
            formattedLabel = label.replace('H2', 'Jul-Dec');
        }

        labels.push(formattedLabel);
        values.push(value);
    });

    // Create chart
    try {
        lineChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Survey Responses',
                    data: values,
                    borderColor: '#e2001a',
                    backgroundColor: 'rgba(226, 0, 26, 0.1)',
                    borderWidth: 3,
                    tension: 0.2, // Slightly less curve for longer intervals
                    fill: true,
                    pointBackgroundColor: '#e2001a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6, // Slightly larger points
                    pointHoverRadius: 9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                const period = context.label || '';
                                const value = context.raw || 0;
                                return `${period}: ${value} responses`;
                            },
                            title: function (context) {
                                return '6-Month Period';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            maxRotation: 45 // Angle labels if they're long
                        },
                        title: {
                            display: true,
                            text: '6-Month Periods',
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Number of Responses',
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                }
            }
        });
        console.log('Line chart (6-month intervals) created successfully');
    } catch (error) {
        console.error('Error creating line chart:', error);
        showNoDataMessage('lineChart', 'Error loading chart');
    }
}

function createBarChart() {
    const ctx = document.getElementById('barChart');
    if (!ctx) {
        console.error('Bar chart canvas not found');
        return;
    }

    // Destroy existing chart if it exists
    if (barChartInstance) {
        barChartInstance.destroy();
        barChartInstance = null;
    }

    // Check if chartData exists
    if (typeof chartData === 'undefined') {
        console.error('chartData is undefined');
        showNoDataMessage('barChart', 'Chart data not available');
        return;
    }

    const data = chartData.programDistribution || [];
    console.log('Bar chart data:', data);

    if (!Array.isArray(data) || data.length === 0) {
        showNoDataMessage('barChart', 'No program distribution data available');
        return;
    }

    // Limit to top 8 programs for readability
    const limitedData = data.slice(0, 8);

    // Prepare labels and data
    const labels = limitedData.map(item => {
        const program = item.program || 'Unknown';
        return program.length > 20 ? program.substring(0, 17) + '...' : program;
    });

    const values = limitedData.map(item => parseInt(item.alumni_count) || 0);

    // Create chart with varied colors
    try {
        barChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Alumni',
                    data: values,
                    backgroundColor: barChartColors.slice(0, labels.length),
                    borderColor: barChartColors.map(color => color.replace('0.8', '1')),
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: barChartColors.map(color => color.replace('0.8', '1'))
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return `Alumni: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Number of Alumni',
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
        console.log('Bar chart created successfully');
    } catch (error) {
        console.error('Error creating bar chart:', error);
        showNoDataMessage('barChart', 'Error loading chart');
    }
}

function showNoDataMessage(chartId, message) {
    const container = document.getElementById(chartId);
    if (container && container.parentElement) {
        container.parentElement.innerHTML = `
            <div class="text-center p-5" style="height: 300px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <i class="bi bi-bar-chart text-muted fs-1 mb-3"></i>
                <p class="text-muted mb-0">${message}</p>
            </div>
        `;
    }
}