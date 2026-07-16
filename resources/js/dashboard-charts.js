// resources/js/dashboard-charts.js

import Chart from 'chart.js/auto';

export function trenPendaftaranChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Pendaftaran', data, borderColor: '#123363', backgroundColor: 'rgba(18,51,99,0.1)', fill: true, tension: 0.3 }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

export function donutTagihanChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: ['#f59e0b', '#3b82f6', '#22c55e'] }],
                },
                options: { responsive: true },
            });
        },
    };
}
