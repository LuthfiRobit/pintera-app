export function formulirFieldList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: { label: '', field_type: 'text', is_required: false, options: '' },
        errors: {},
        submitting: false,

        async addItem() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ jalur_ppdb_id: this.jalurId, ...this.form }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah field.');
                    return;
                }

                this.items.push(json.data);
                this.form = { label: '', field_type: 'text', is_required: false, options: '' };
                Alpine.store('kelengkapan').formulir++;
                Alpine.store('toast').push('success', 'Field formulir berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah field.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog(
                'Hapus Field Formulir?',
                `Apakah Anda yakin ingin menghapus field "${item.label}"?`
            );
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus field.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('kelengkapan').formulir--;
                Alpine.store('toast').push('success', json.message ?? 'Field formulir berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus field.');
            }
        },
    };
}
