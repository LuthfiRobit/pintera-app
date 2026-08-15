export function virtualAccountGenerateModal(config) {
    return {
        open: false,
        mode: 'semua',
        search: '',
        kelasId: '',
        calonList: [],
        selectedIds: [],
        calonUrlBase: config.calonUrlBase,

        buka() {
            this.open = true;
            this.mode = 'semua';
            this.search = '';
            this.kelasId = '';
            this.calonList = [];
            this.selectedIds = [];
        },

        async muatCalon() {
            try {
                const url = new URL(this.calonUrlBase, window.location.origin);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);

                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
                    return;
                }

                const json = await response.json();
                this.calonList = json.data;
                this.selectedIds = this.selectedIds.filter((id) => this.calonList.some((s) => s.id === id));
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
            }
        },

        toggleSiswa(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id);
            } else {
                this.selectedIds.push(id);
            }
        },
    };
}
