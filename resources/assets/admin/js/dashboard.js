/**
 * Dashboard Chart - Revenue vs Expenses (12 Months)
 * =========================================================
 * این اسکریپت نمودار مقایسه درآمد و هزینه 12 ماهه را رسم می‌کند
 */

document.addEventListener('DOMContentLoaded', function() {
    const chartCanvas = document.getElementById('revenueExpenseChart');
    
    if (!chartCanvas) {
        console.warn('نمودار درآمد و هزینه پیدا نشد');
        return;
    }

    // دریافت داده از data attributes
    const labels = JSON.parse(chartCanvas.dataset.labels || '[]');
    const revenueData = JSON.parse(chartCanvas.dataset.revenue || '[]');
    const expenseData = JSON.parse(chartCanvas.dataset.expenses || '[]');

    // تنظیمات نمودار
    const ctx = chartCanvas.getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'درآمد',
                    data: revenueData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                },
                {
                    label: 'هزینه',
                    data: expenseData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    rtl: true,
                    textDirection: 'rtl',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            family: 'IRANSans',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    rtl: true,
                    textDirection: 'rtl',
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: {
                        family: 'IRANSans',
                        size: 13
                    },
                    bodyFont: {
                        family: 'IRANSans',
                        size: 12
                    },
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            // فرمت عدد به میلیارد تومان
                            const value = context.parsed.y;
                            label += (value / 1000000000).toFixed(2) + ' میلیارد تومان';
                            return label;
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
                            family: 'IRANSans',
                            size: 11
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
                            family: 'IRANSans',
                            size: 11
                        },
                        callback: function(value) {
                            // نمایش به صورت میلیارد
                            return (value / 1000000000).toFixed(1) + 'B';
                        }
                    }
                }
            }
        }
    });
});
