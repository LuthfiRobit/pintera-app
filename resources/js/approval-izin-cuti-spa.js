export function approvalIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',

        get filteredItems() {
            return this.items.filter((item) => {
                const matchSearch = item.nama.toLowerCase().includes(this.searchQuery.toLowerCase());
                if (this.activeFilter === 'semua') return matchSearch;
                return matchSearch && item.kategori === this.activeFilter;
            });
        },

        get countCuti() {
            return this.items.filter((i) => i.kategori === 'cuti').length;
        },

        get countSakit() {
            return this.items.filter((i) => i.kategori === 'sakit').length;
        },

        get countIzin() {
            return this.items.filter((i) => i.kategori === 'izin').length;
        },

        get countDispensasi() {
            return this.items.filter((i) => ['izin', 'sakit'].includes(i.kategori)).length;
        },
    };
}
