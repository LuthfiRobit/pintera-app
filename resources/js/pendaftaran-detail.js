export function pendaftaranDetail(config) {
    return {
        canVerifikasiDokumen: config.canVerifikasiDokumen,
        canNilaiSeleksi: config.canNilaiSeleksi,
        canTetapkanKeputusan: config.canTetapkanKeputusan,
        verifikasiDokumenUrlTemplate: config.verifikasiDokumenUrlTemplate,
        nilaiUrl: config.nilaiUrl,
        keputusanUrl: config.keputusanUrl,
        catatanTolak: {},
        savingDokumen: {},
        savingNilai: {},
        savingKeputusan: false,
        keputusanStatus: config.initialStatus,
        catatanKeputusan: config.initialCatatanKeputusan ?? '',

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async verifikasiDokumen(dokumenId, status) {
            if (status === 'ditolak' && !this.catatanTolak[dokumenId]) {
                Alpine.store('toast').push('error', 'Catatan wajib diisi saat menolak dokumen.');
                return;
            }

            this.savingDokumen[dokumenId] = true;
            try {
                const response = await fetch(this.verifikasiDokumenUrlTemplate.replace('__ID__', dokumenId), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({
                        status_verifikasi: status,
                        catatan_verifikasi: this.catatanTolak[dokumenId] ?? null,
                    }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal memverifikasi dokumen.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Dokumen berhasil diverifikasi.');
                setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memverifikasi dokumen.');
            } finally {
                this.savingDokumen[dokumenId] = false;
            }
        },

        async simpanNilai(seleksiPpdbId, nilai, catatan) {
            this.savingNilai[seleksiPpdbId] = true;
            try {
                const response = await fetch(this.nilaiUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ seleksi_ppdb_id: seleksiPpdbId, nilai, catatan }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan nilai.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Nilai berhasil disimpan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan nilai.');
            } finally {
                this.savingNilai[seleksiPpdbId] = false;
            }
        },

        async tetapkanKeputusan() {
            this.savingKeputusan = true;
            try {
                const response = await fetch(this.keputusanUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ status: this.keputusanStatus, catatan_keputusan: this.catatanKeputusan }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menetapkan keputusan.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Keputusan berhasil ditetapkan.');
                setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menetapkan keputusan.');
            } finally {
                this.savingKeputusan = false;
            }
        },
    };
}
