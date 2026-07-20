export function jenisTesTable(config) {
    return {
        items: config.initialItems,
        storeUrl: config.storeUrl,
        updateUrlTemplate: config.updateUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,
        editingId: null,
        form: { nama: '', deskripsi: '' },
        errors: {},
        submitting: false,

        startEdit(item) {
            this.editingId = item.id;
            this.form = { nama: item.nama, deskripsi: item.deskripsi ?? '' };
            this.errors = {};
            this.$refs.formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cancelEdit() {
            this.editingId = null;
            this.form = { nama: '', deskripsi: '' };
            this.errors = {};
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            const isEdit = this.editingId !== null;
            const url = isEdit ? this.updateUrlTemplate.replace('__ID__', this.editingId) : this.storeUrl;

            try {
                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan jenis tes.');
                    return;
                }

                if (isEdit) {
                    const index = this.items.findIndex((existing) => existing.id === this.editingId);
                    if (index !== -1) this.items[index] = json.data;
                } else {
                    this.items.push(json.data);
                }

                this.cancelEdit();
                Alpine.store('toast').push('success', isEdit ? 'Jenis tes berhasil diperbarui.' : 'Jenis tes berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan jenis tes.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tes?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tes.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                if (this.editingId === item.id) {
                    this.cancelEdit();
                }
                Alpine.store('toast').push('success', json.message ?? 'Jenis tes berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tes.');
            }
        },
    };
}
