// resources/js/pembayaran-table.js
// Antrian tanpa pencarian/filter — hanya daftar+paginasi, mengikuti bentuk
// dasar tagihan-table.js/pendaftaran-table.js (meta-driven pagination,
// toast on fetch failure), tanpa search/status karena semua barisnya
// memang selalu berstatus menunggu_verifikasi.

export function pembayaranTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        page: 1,
        loading: false,
        dataUrl: config.dataUrl,

        init() {
            this.fetchData();
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) {
                return;
            }
            this.page = page;
            this.fetchData();
        },

        async fetchData() {
            this.loading = true;
            const params = new URLSearchParams({ page: this.page });

            try {
                const response = await fetch(`${this.dataUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('request failed');
                }

                const json = await response.json();
                this.rows = json.data;
                this.meta = json.meta;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat antrian pembayaran.');
            } finally {
                this.loading = false;
            }
        },
    };
}
