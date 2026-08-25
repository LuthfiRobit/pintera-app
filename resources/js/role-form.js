export function roleForm(config) {
    return {
        name: config.initialName,
        scopeLevel: config.initialScopeLevel,
        isProtected: config.initialIsProtected,
        moduleGroups: config.initialModuleGroups,
        checkedIds: [...config.initialCheckedIds],
        catalogUrl: config.catalogUrl,
        submitUrl: config.submitUrl,
        method: config.method,
        indexUrl: config.indexUrl,
        errors: {},
        submitting: false,
        syncing: false,
        auditMissingFromDatabase: [],
        auditUnusedInCode: [],
        showAuditBanner: false,
        permissionSearch: '',

        filteredModuleGroups() {
            const query = this.permissionSearch.trim().toLowerCase();
            if (!query) return this.moduleGroups;

            return this.moduleGroups
                .map((group) => ({
                    ...group,
                    permissions: group.permissions.filter((permission) =>
                        permission.label.toLowerCase().includes(query) || permission.name.toLowerCase().includes(query)
                    ),
                }))
                .filter((group) => group.permissions.length > 0);
        },

        isChecked(id) {
            return this.checkedIds.includes(id);
        },

        toggle(id) {
            if (this.isChecked(id)) {
                this.checkedIds = this.checkedIds.filter((checkedId) => checkedId !== id);
            } else {
                this.checkedIds.push(id);
            }
        },

        allCheckedInModule(group) {
            return group.permissions.length > 0 && group.permissions.every((permission) => this.isChecked(permission.id));
        },

        toggleModule(group) {
            const allChecked = this.allCheckedInModule(group);
            group.permissions.forEach((permission) => {
                const checked = this.isChecked(permission.id);
                if (allChecked && checked) {
                    this.checkedIds = this.checkedIds.filter((id) => id !== permission.id);
                } else if (!allChecked && !checked) {
                    this.checkedIds.push(permission.id);
                }
            });
        },

        selectAll() {
            this.checkedIds = this.moduleGroups.flatMap((group) => group.permissions.map((permission) => permission.id));
        },

        clearAll() {
            this.checkedIds = [];
        },

        dismissAuditBanner() {
            this.showAuditBanner = false;
        },

        async syncPermissions() {
            this.syncing = true;
            try {
                const response = await fetch(this.catalogUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    throw new Error('request failed');
                }
                const json = await response.json();
                const validIds = json.modules.flatMap((group) => group.permissions.map((permission) => permission.id));
                this.moduleGroups = json.modules;
                this.checkedIds = this.checkedIds.filter((id) => validIds.includes(id));

                this.auditMissingFromDatabase = json.audit?.missingFromDatabase ?? [];
                this.auditUnusedInCode = json.audit?.unusedInCode ?? [];
                this.showAuditBanner = this.auditMissingFromDatabase.length > 0 || this.auditUnusedInCode.length > 0;

                Alpine.store('toast').push('success', 'Katalog permission diperbarui.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyegarkan katalog permission.');
            } finally {
                this.syncing = false;
            }
        },

        async submit() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.submitUrl, {
                    method: this.method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        name: this.name,
                        scope_level: this.scopeLevel,
                        permissions: this.checkedIds,
                    }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan role.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Role berhasil disimpan.');
                window.location.href = this.indexUrl;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan role.');
            } finally {
                this.submitting = false;
            }
        },
    };
}
