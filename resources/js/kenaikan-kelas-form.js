export function kenaikanKelasForm() {
    return {
        submitting: false,

        async konfirmasiDanKirim(event) {
            const form = event.target;
            const rows = form.querySelectorAll('tbody tr[data-kelas-lama]');
            let naik = 0;
            let lulus = 0;
            let lewati = 0;
            let peringatan = 0;

            rows.forEach((row) => {
                const tindakan = row.querySelector('select[name$="[tindakan]"]')?.value;
                if (tindakan === 'naik') naik++;
                else if (tindakan === 'lulus') lulus++;
                else lewati++;

                if (row.dataset.warning === '1') peringatan++;
            });

            let message = `${naik} kelas akan dinaikkan, ${lulus} kelas akan diluluskan, ${lewati} kelas dilewati.`;
            if (lulus > 0) {
                message += ' Siswa yang diluluskan akan dinonaktifkan akunnya secara otomatis.';
            }
            if (peringatan > 0) {
                message += ` Perhatian: ${peringatan} baris punya peringatan kurikulum/tingkat tidak wajar — periksa kembali kolom "Kelas Tujuan" sebelum lanjut.`;
            }
            message += ' Tindakan ini memindahkan/meluluskan siswa secara langsung dan tidak bisa dibatalkan otomatis.';

            const confirmed = await window.confirmDialog('Proses Kenaikan Kelas?', message, { confirmLabel: 'Ya, Proses Sekarang' });
            if (confirmed) {
                this.submitting = true;
                form.submit();
            }
        },
    };
}
