export function seleksiList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: {
            gelombang_ppdb_id: config.defaultGelombangId ?? '',
            jenis_tes_master_id: config.defaultJenisTesId ?? '',
            jadwal: '',
            bobot: '',
            kriteria_kelulusan: '',
        },
        errors: {},
        submitting: false,

        formatJadwal(iso) {
            const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const tanggalObj = new Date(iso);
            const tanggal = String(tanggalObj.getUTCDate()).padStart(2, '0');
            const jam = String(tanggalObj.getUTCHours()).padStart(2, '0');
            const menit = String(tanggalObj.getUTCMinutes()).padStart(2, '0');
            return `${tanggal} ${bulan[tanggalObj.getUTCMonth()]} ${tanggalObj.getUTCFullYear()} ${jam}:${menit}`;
        },

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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah jadwal seleksi.');
                    return;
                }

                this.items.push(json.data);
                this.form = {
                    gelombang_ppdb_id: config.defaultGelombangId ?? '',
                    jenis_tes_master_id: config.defaultJenisTesId ?? '',
                    jadwal: '',
                    bobot: '',
                    kriteria_kelulusan: '',
                };
                Alpine.store('toast').push('success', 'Jadwal seleksi berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah jadwal seleksi.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            if (!confirm('Hapus jadwal seleksi ini?')) {
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jadwal seleksi.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Jadwal seleksi berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jadwal seleksi.');
            }
        },
    };
}
