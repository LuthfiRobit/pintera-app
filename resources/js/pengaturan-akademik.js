function formatTanggalTampil(tanggal) {
    // `tanggal` arrives as a full ISO datetime string (Eloquent's `date` cast
    // serializes to e.g. "2026-08-17T00:00:00.000000Z"), so it's already
    // parseable by `new Date()` directly.
    return new Date(tanggal).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

export function kalenderAkademikTable(config) {
    return {
        items: config.initialItems,
        storeUrl: config.storeUrl,
        updateUrlTemplate: config.updateUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,
        bolehNasional: config.bolehNasional,
        editingId: null,
        form: { tanggal: '', tanggal_selesai: '', nama: '', tipe: 'libur', keterangan: '', berlaku_nasional: false },
        errors: {},
        submitting: false,

        startEdit(item) {
            this.editingId = item.id;
            this.form = {
                tanggal: item.tanggal,
                tanggal_selesai: item.tanggal_selesai ?? item.tanggal,
                nama: item.nama,
                tipe: item.tipe,
                keterangan: item.keterangan ?? '',
                berlaku_nasional: item.lembaga_id === null,
            };
            this.errors = {};
            this.$refs.formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cancelEdit() {
            this.editingId = null;
            this.form = { tanggal: '', tanggal_selesai: '', nama: '', tipe: 'libur', keterangan: '', berlaku_nasional: false };
            this.errors = {};
        },

        tampilTanggal(item) {
            const selesai = item.tanggal_selesai ?? item.tanggal;
            return item.tanggal === selesai
                ? formatTanggalTampil(item.tanggal)
                : `${formatTanggalTampil(item.tanggal)} – ${formatTanggalTampil(selesai)}`;
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            const isEdit = this.editingId !== null;
            const url = isEdit ? this.updateUrlTemplate.replace('__ID__', this.editingId) : this.storeUrl;
            const body = isEdit
                ? { nama: this.form.nama, tipe: this.form.tipe, keterangan: this.form.keterangan }
                : this.form;

            try {
                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan entri kalender.');
                    return;
                }

                if (isEdit) {
                    const index = this.items.findIndex((existing) => existing.id === this.editingId);
                    if (index !== -1) this.items[index] = json.data;
                    this.cancelEdit();
                    Alpine.store('toast').push('success', 'Entri kalender berhasil diperbarui.');
                    return;
                }

                this.items.push(json.data);
                this.items.sort((a, b) => (a.tanggal < b.tanggal ? -1 : a.tanggal > b.tanggal ? 1 : 0));
                Alpine.store('toast').push('success', 'Entri kalender berhasil ditambahkan.');
                this.cancelEdit();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan entri kalender.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Entri Kalender?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus entri kalender.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                if (this.editingId === item.id) {
                    this.cancelEdit();
                }
                Alpine.store('toast').push('success', json.message ?? 'Entri kalender berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus entri kalender.');
            }
        },
    };
}
