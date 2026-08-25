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

export function perLembagaBarChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ label: 'Pendaftar', data, backgroundColor: '#123363' }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

export function trenTenantChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Yayasan Baru', data, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.1)', fill: true, tension: 0.3 }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

export function presensiBulananChart(labels, hadir, izin, sakit, alpa) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Hadir', data: hadir, backgroundColor: '#22c55e' },
                        { label: 'Izin', data: izin, backgroundColor: '#3b82f6' },
                        { label: 'Sakit', data: sakit, backgroundColor: '#f59e0b' },
                        { label: 'Alpa', data: alpa, backgroundColor: '#ef4444' },
                    ],
                },
                options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

