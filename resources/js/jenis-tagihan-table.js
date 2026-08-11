export function jenisTagihanTable(config) {
    return {
        items: config.initialItems,
        deleteUrlTemplate: config.deleteUrlTemplate,
        nominalUrlTemplate: config.nominalUrlTemplate,
        editUrlTemplate: config.editUrlTemplate,
        prosesUrlTemplate: config.prosesUrlTemplate,

        nominalUrl(item) {
            return this.nominalUrlTemplate.replace('__ID__', item.id);
        },

        editUrl(item) {
            return this.editUrlTemplate.replace('__ID__', item.id);
        },

        async prosesTagihan(item) {
            const confirmed = await confirmDialog(
                'Proses Tagihan?',
                `Proses tagihan untuk "${item.nama}"? Ini akan membuat tagihan baru untuk siswa yang cocok kriteria dan belum tertagih periode ini.`
            );
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(this.prosesUrlTemplate.replace('__ID__', item.id), {
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
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memproses tagihan.');
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tagihan?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tagihan.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Jenis tagihan berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tagihan.');
            }
        },
    };
}
