export function approvalIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',
        viewMode: 'menunggu',

        get itemsInView() {
            return this.items.filter((item) => this.viewMode === 'menunggu' ? !item.isDecided : item.isDecided);
        },

        get filteredItems() {
            return this.itemsInView.filter((item) => {
                const query = this.searchQuery.toLowerCase();
                const matchSearch = item.nama.toLowerCase().includes(query) || item.alasan.toLowerCase().includes(query);
                const matchFilter = this.activeFilter === 'semua' || item.kategori === this.activeFilter;
                return matchSearch && matchFilter;
            });
        },

        get totalPending() {
            return this.items.filter((i) => !i.isDecided).length;
        },

        get countCuti() {
            return this.itemsInView.filter((i) => i.kategori === 'cuti').length;
        },

        get countSakit() {
            return this.itemsInView.filter((i) => i.kategori === 'sakit').length;
        },

        get countIzin() {
            return this.itemsInView.filter((i) => i.kategori === 'izin').length;
        },

        get countDispensasi() {
            return this.itemsInView.filter((i) => ['izin', 'sakit'].includes(i.kategori)).length;
        },
    };
}
