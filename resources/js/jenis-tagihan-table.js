export function jenisTagihanTable(config) {
    return {
        items: config.initialItems,
        deleteUrlTemplate: config.deleteUrlTemplate,
        nominalUrlTemplate: config.nominalUrlTemplate,
        editUrlTemplate: config.editUrlTemplate,

        nominalUrl(item) {
            return this.nominalUrlTemplate.replace('__ID__', item.id);
        },

        editUrl(item) {
            return this.editUrlTemplate.replace('__ID__', item.id);
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
