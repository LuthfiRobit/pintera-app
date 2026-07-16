// resources/js/tagihan-table.js
// Copy of resources/js/pendaftaran-table.js's exact shape (config object with
// dataUrl, init() lifecycle hook, onSearchInput()/onStatusChange() debounced
// handlers, meta-driven pagination, toast on fetch failure) — this table has
// no per-row navigation, so showUrl()/showUrlTemplate are omitted.

export function tagihanTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        status: '',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,

        init() {
            this.fetchData();
        },

        onSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.fetchData();
            }, 350);
        },

        onStatusChange() {
            this.page = 1;
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
            const params = new URLSearchParams({
                search: this.search,
                status: this.status,
                page: this.page,
            });

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
                Alpine.store('toast').push('error', 'Gagal memuat data tagihan.');
            } finally {
                this.loading = false;
            }
        },
    };
}
