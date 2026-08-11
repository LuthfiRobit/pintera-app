export function jenisTagihanTable(config) {
    return {
        deleteUrlTemplate: config.deleteUrlTemplate,
        nominalUrlTemplate: config.nominalUrlTemplate,
        editUrlTemplate: config.editUrlTemplate,
        prosesUrlTemplate: config.prosesUrlTemplate,
        monitoringUrlTemplate: config.monitoringUrlTemplate,

        async prosesTagihan(id, nama) {
            const confirmed = await confirmDialog(
                'Proses Tagihan?',
                `Proses tagihan untuk "${nama}"? Ini akan membuat tagihan baru untuk siswa yang cocok kriteria dan belum tertagih periode ini.`
            );
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.prosesUrlTemplate.replace('__ID__', id), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal memproses tagihan.');
                    return;
                }

                Alpine.store('toast').push('success', json.message);
                
                // Jika ingin reload data setelah proses, bisa panggil ini:
                // if (typeof this.muatUlangDaftar === 'function') this.muatUlangDaftar();
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memproses tagihan.');
            }
        },

        async deleteItem(id, nama) {
            const confirmed = await confirmDialog('Hapus Jenis Tagihan?', `Apakah Anda yakin ingin menghapus "${nama}"?`);
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tagihan.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Jenis tagihan berhasil dihapus.');
                if (typeof this.muatUlangDaftar === 'function') {
                    this.muatUlangDaftar();
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tagihan.');
            }
        },
    };
}
