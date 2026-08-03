export function orangTuaCari(config) {
    return {
        searchUrl: config.searchUrl,
        nik: '',
        searching: false,
        searched: false,
        found: false,
        orangTua: null,

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async cari() {
            if (this.nik.length !== 16) {
                Alpine.store('toast').push('error', 'NIK harus tepat 16 digit.');
                return;
            }

            this.searching = true;
            this.searched = false;
            try {
                const response = await fetch(`${this.searchUrl}?nik=${encodeURIComponent(this.nik)}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await response.json();
                this.found = json.found;
                this.orangTua = json.orang_tua ?? null;
                this.searched = true;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal mencari data orang tua.');
            } finally {
                this.searching = false;
            }
        },

        reset() {
            this.nik = '';
            this.searched = false;
            this.found = false;
            this.orangTua = null;
        },
    };
}
