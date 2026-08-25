// resources/js/dashboard-charts.js

import Chart from 'chart.js/auto';

export function trenPendaftaranChart(labels, data) {
    return {
        init() {
            const ctx = this.$refs.canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.01)');

            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Pendaftaran',
                        data,
                        borderColor: '#3b82f6',
                        borderWidth: 3,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#3b82f6',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            bodyFont: { weight: 'bold' }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94a3b8' },
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] }
                        }
                    }
                },
            });
        },
    };
}

export function donutTagihanChart(labels, data) {
    return {
        init() {
            const total = data.reduce((a, b) => a + b, 0);

            const centerTextPlugin = {
                id: 'centerText',
                beforeDraw(chart) {
                    const { width, height, ctx } = chart;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    
                    ctx.font = '600 11px Inter, sans-serif';
                    ctx.fillStyle = '#64748b';
                    ctx.fillText('TOTAL', width / 2, height / 2 - 10);

                    ctx.font = '700 18px Inter, sans-serif';
                    ctx.fillStyle = '#0f172a';
                    ctx.fillText(total.toLocaleString(), width / 2, height / 2 + 10);
                    ctx.restore();
                }
            };

            new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 0,
                        borderRadius: 6,
                        spacing: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                    cutout: '76%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#0f172a',
                            bodyColor: '#334155',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 10
                        }
                    }
                },
                plugins: [centerTextPlugin]
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
                    datasets: [{
                        label: 'Pendaftar',
                        data,
                        backgroundColor: '#3b82f6',
                        hoverBackgroundColor: '#2563eb',
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#3b82f6',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            bodyFont: { weight: 'bold' }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: '600' } } },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#94a3b8' },
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] }
                        }
                    }
                },
            });
        },
    };
}

export function trenTenantChart(labels, data) {
    return {
        init() {
            const ctx = this.$refs.canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(124, 58, 237, 0.3)');
            gradient.addColorStop(1, 'rgba(124, 58, 237, 0.01)');

            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Yayasan Baru',
                        data,
                        borderColor: '#7c3aed',
                        borderWidth: 3,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#7c3aed',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' }, grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] } }
                    }
                },
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
                        { label: 'Hadir', data: hadir, backgroundColor: '#10b981', borderRadius: 6 },
                        { label: 'Izin', data: izin, backgroundColor: '#3b82f6', borderRadius: 6 },
                        { label: 'Sakit', data: sakit, backgroundColor: '#f59e0b', borderRadius: 6 },
                        { label: 'Alpa', data: alpa, backgroundColor: '#ef4444', borderRadius: 6 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                    plugins: {
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#334155',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 10,
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: '#94a3b8' }, grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] } }
                    }
                },
            });
        },
    };
}
