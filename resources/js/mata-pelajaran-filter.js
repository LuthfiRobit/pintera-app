import TomSelect from 'tom-select';

export function mataPelajaranFilter(config) {
    return {
        search: config.search ?? '',
        tipe: config.tipe ?? '',
        kelompok: config.kelompok ?? '',
        status: config.status ?? '',
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        tomSelects: {},

        initFilterSelect(el, fieldName) {
            this.tomSelects[fieldName] = new TomSelect(el, {
                maxItems: 1,
                create: false,
                allowEmptyOption: true,
                onChange: (value) => {
                    this[fieldName] = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.tipe) url.searchParams.set('tipe', this.tipe);
                if (this.kelompok) url.searchParams.set('kelompok', this.kelompok);
                if (this.status) url.searchParams.set('status', this.status);
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar mata pelajaran.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl(url);
                this.$refs.daftarMataPelajaran.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar mata pelajaran.');
            }
        },

        perbaruiUrl(url) {
            window.history.pushState({}, '', url);
        },
    };
}
