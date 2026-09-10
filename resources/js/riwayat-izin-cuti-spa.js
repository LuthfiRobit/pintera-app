export function riwayatIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',

        get filteredItems() {
            return this.items.filter((i) => {
                const q = this.searchQuery.toLowerCase();
                const matchSearch = i.kategori.toLowerCase().includes(q) || i.periode.toLowerCase().includes(q);
                if (this.activeFilter === 'semua') return matchSearch;
                if (this.activeFilter === 'pending') return matchSearch && ['pending', 'in_review'].includes(i.status);
                if (this.activeFilter === 'approved') return matchSearch && i.status === 'approved';
                return matchSearch;
            });
        },

        get countPending() {
            return this.items.filter((i) => ['pending', 'in_review'].includes(i.status)).length;
        },

        get countApproved() {
            return this.items.filter((i) => i.status === 'approved').length;
        },
    };
}
