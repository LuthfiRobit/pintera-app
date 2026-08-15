export function virtualAccountFilter(config) {
    return {
        search: config.search ?? '',
        kelasId: config.kelasId ?? '',
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar virtual account.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl(url);
                this.$refs.daftarVirtualAccount.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar virtual account.');
            }
        },

        perbaruiUrl(url) {
            window.history.pushState({}, '', url);
        },
    };
}
