export function nilaiMassal(config) {
    return {
        seleksiPpdbId: config.seleksiPpdbId,
        nilai: config.initialNilai,
        saving: false,

        async simpan() {
            this.saving = true;
            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ seleksi_ppdb_id: this.seleksiPpdbId, nilai: this.nilai }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan nilai massal.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Nilai berhasil disimpan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan nilai massal.');
            } finally {
                this.saving = false;
            }
        },
    };
}
