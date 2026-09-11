import TomSelect from 'tom-select';

export function dataTableFilter(config) {
    return {
        filters: config.filters || {},
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        roleGroups: config.roleGroups || {},
        tomSelects: {},

        init() {
            this.$nextTick(() => {
                this.bindPagination();
            });
        },

        bindPagination() {
            if (!this.$refs.tableContainer) return;
            const paginationLinks = this.$refs.tableContainer.querySelectorAll('nav[role="navigation"] a');
            paginationLinks.forEach((link) => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.muatUlangDaftar(link.href);
                });
            });
        },

        setScopeGroup(group) {
            this.filters.scope_group = group;
            this.filters.role = '';
            this.refreshRoleOptions(group);
            this.muatUlangDaftar();
        },

        refreshRoleOptions(group) {
            const ts = this.tomSelects.role;
            if (!ts) return;

            const roles = (group && this.roleGroups[group]) || this.roleGroups.semua || [];

            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: 'Semua Role' });
            roles.forEach((roleName) => {
                const titleCased = roleName
                    .replace(/_/g, ' ')
                    .split(' ')
                    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
                ts.addOption({ value: roleName, text: titleCased });
            });
            ts.refreshOptions(false);
        },

        initFilterSelect(el, fieldName, isSearchable = false) {
            let tomConfig = {
                maxItems: 1,
                create: false,
                allowEmptyOption: true,
                onChange: (value) => {
                    this.filters[fieldName] = value;
                    this.muatUlangDaftar();
                },
            };
            if (!isSearchable) {
                tomConfig.controlInput = null;
            }
            this.tomSelects[fieldName] = new TomSelect(el, tomConfig);
        },

        async muatUlangDaftar(pageUrl = null) {
            try {
                window.dispatchEvent(new CustomEvent('ajax-start'));
                let url;
                if (pageUrl) {
                    url = new URL(pageUrl, window.location.origin);
                } else {
                    url = new URL(this.indexUrlBase, window.location.origin);
                    for (const [key, value] of Object.entries(this.filters)) {
                        if (value) url.searchParams.set(key, value);
                    }
                    if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);
                }

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.ok) {
                    const html = await response.text();
                    window.history.pushState({}, '', url);
                    this.$refs.tableContainer.innerHTML = html;
                    if (window.Alpine) {
                        window.Alpine.initTree(this.$refs.tableContainer);
                    }
                    this.$nextTick(() => {
                        this.bindPagination();
                    });
                } else {
                    window.Alpine.store('toast').push('error', 'Gagal memuat data.');
                }
            } catch (error) {
                window.Alpine.store('toast').push('error', 'Gagal memuat data.');
            } finally {
                window.dispatchEvent(new CustomEvent('ajax-end'));
            }
        },
    };
}
