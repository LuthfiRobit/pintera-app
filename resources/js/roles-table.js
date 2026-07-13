export function rolesTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        scope: '',
        sort: 'name',
        direction: 'asc',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,
        editUrlTemplate: config.editUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,

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

        onScopeChange() {
            this.page = 1;
            this.fetchData();
        },

        sortBy(column) {
            if (this.sort === column) {
                this.direction = this.direction === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort = column;
                this.direction = 'asc';
            }
            this.fetchData();
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) {
                return;
            }
            this.page = page;
            this.fetchData();
        },

        editUrl(row) {
            return this.editUrlTemplate.replace('__ID__', row.id);
        },

        async fetchData() {
            this.loading = true;
            const params = new URLSearchParams({
                search: this.search,
                scope: this.scope,
                sort: this.sort,
                direction: this.direction,
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
                Alpine.store('toast').push('error', 'Gagal memuat data role.');
            } finally {
                this.loading = false;
            }
        },

        async deleteRole(row) {
            if (!confirm(`Hapus role "${row.name}"? Tindakan ini tidak bisa dibatalkan.`)) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', row.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus role.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Role berhasil dihapus.');
                this.fetchData();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus role.');
            }
        },
    };
}
