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
                    if (Alpine.store('toast')) {
                        Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
                    }
                    return;
                }

                const json = await response.json();
                this.calonList = json.data;
                this.selectedIds = this.selectedIds.filter((id) => this.calonList.some((s) => s.id === id));
            } catch (error) {
                if (Alpine.store('toast')) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar siswa.');
                }
            }
        },

        toggleSiswa(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id);
            } else {
                this.selectedIds.push(id);
            }
        },

        isAllSelected() {
            return this.calonList.length > 0 && this.calonList.every((s) => this.selectedIds.includes(s.id));
        },

        toggleSelectAll() {
            if (this.isAllSelected()) {
                const calonIds = this.calonList.map((s) => s.id);
                this.selectedIds = this.selectedIds.filter((id) => !calonIds.includes(id));
            } else {
                const newIds = new Set([...this.selectedIds, ...this.calonList.map((s) => s.id)]);
                this.selectedIds = Array.from(newIds);
            }
        },
    };
}
