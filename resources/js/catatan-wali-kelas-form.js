export function catatanWaliKelasForm(initial) {
    return {
        ekstrakurikuler: initial.ekstrakurikuler.length ? initial.ekstrakurikuler : [],
        prestasi: initial.prestasi.length ? initial.prestasi : [],
        pklInfo: initial.pklInfo.length ? initial.pklInfo : [],
        isGeneratingNarasi: false,
        generateNarasiUrl: initial.generateNarasiUrl,
        semesterId: initial.semesterId,
        csrfToken: initial.csrfToken,

        async generateNarasi() {
            const existing = this.$refs.catatanPerkembangan.value.trim();
            if (existing && !(await confirmDialog('Timpa Catatan?', 'Draft otomatis akan menimpa isi catatan perkembangan yang sudah ada jika berhasil dibuat. Lanjutkan?'))) {
                return;
            }

            this.isGeneratingNarasi = true;
            try {
                const url = `${this.generateNarasiUrl}?semester_id=${this.semesterId}`;
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrfToken, Accept: 'application/json' },
                });

                if (!response.ok) {
                    window.Alpine?.store('toast')?.push('error', 'Gagal membuat draft narasi otomatis.');
                    return;
                }

                const data = await response.json();

                if (!data.narasi) {
                    window.Alpine?.store('toast')?.push('error', 'Belum ada data asesmen untuk membuat draft narasi -- catatan Anda tidak diubah.');
                    return;
                }

                this.$refs.catatanPerkembangan.value = data.narasi;
            } catch (error) {
                window.Alpine?.store('toast')?.push('error', 'Terjadi kesalahan jaringan saat membuat draft narasi.');
            } finally {
                this.isGeneratingNarasi = false;
            }
        },
    };
}
