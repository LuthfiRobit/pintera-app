export function manualPaymentFilter(config) {
    return {
        search: config.search ?? '',
        dari: config.dari ?? '',
        sampai: config.sampai ?? '',
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.dari) url.searchParams.set('dari', this.dari);
                if (this.sampai) url.searchParams.set('sampai', this.sampai);
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar verifikasi.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl(url);
                this.$refs.daftarManualPayment.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar verifikasi.');
            }
        },

        perbaruiUrl(url) {
            window.history.pushState({}, '', url);
        },
    };
}
