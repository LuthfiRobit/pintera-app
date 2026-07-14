export function pendaftaranTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        status: '',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,
        showUrlTemplate: config.showUrlTemplate,

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

        showUrl(row) {
            return this.showUrlTemplate.replace('__ID__', row.id);
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
                Alpine.store('toast').push('error', 'Gagal memuat data pendaftaran.');
            } finally {
                this.loading = false;
            }
        },
    };
}
