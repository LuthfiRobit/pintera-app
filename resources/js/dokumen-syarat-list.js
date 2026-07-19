export function dokumenSyaratList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: { nama_dokumen: '', wajib: true },
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah dokumen.');
                    return;
                }

                this.items.push(json.data);
                this.form = { nama_dokumen: '', wajib: true };
                Alpine.store('kelengkapan').dokumen++;
                Alpine.store('toast').push('success', 'Dokumen syarat berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah dokumen.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog(
                'Hapus Dokumen Syarat?',
                `Apakah Anda yakin ingin menghapus dokumen "${item.nama_dokumen}"?`
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus dokumen.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('kelengkapan').dokumen--;
                Alpine.store('toast').push('success', json.message ?? 'Dokumen syarat berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus dokumen.');
            }
        },
    };
}
