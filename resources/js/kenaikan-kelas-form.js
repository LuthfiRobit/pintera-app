export function initRowSelect(el, searchable = false, placeholder = '') {
    if (!window.TomSelect || el.tomselect) return;

    const config = {
        maxItems: 1,
        create: false,
        allowEmptyOption: true,
        dropdownParent: 'body',
        dropdownClass: 'ts-dropdown ts-compact-dropdown',
        wrapperClass: 'ts-wrapper ts-compact',
        onChange: () => {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };

    if (!searchable) {
        config.controlInput = null;
    }

    if (placeholder) {
        config.placeholder = placeholder;
    }

    new window.TomSelect(el, config);
}

function extractClassSuffix(className) {
    if (!className) return '';
    let clean = className.replace(/^(kelas|tingkat)\s*/i, '').trim();
    clean = clean.replace(/^([0-9]+|[ivxlcdm]+)[\s\-\.]*/i, '').trim();
    return clean.toLowerCase();
}

export function kenaikanKelasForm() {
    let formEl = null;

    const self = {
        submitting: false,
        searchQuery: '',
        countNaik: 0,
        countLulus: 0,
        countLewati: 0,
        countPeringatan: 0,
        semuaSalinJadwal: false,
        initRowSelect,

        init() {
            formEl = this.$el;
            this.$nextTick(() => {
                self.hitungRingkasan();
            });
        },

        hitungRingkasan() {
            const form = formEl || document.querySelector('form[action*="kenaikan-kelas.store"]') || this.$el?.closest('form');
            if (!form) return;

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

            this.countNaik = naik;
            this.countLulus = lulus;
            this.countLewati = lewati;
            this.countPeringatan = peringatan;
            self.countNaik = naik;
            self.countLulus = lulus;
            self.countLewati = lewati;
            self.countPeringatan = peringatan;
        },

        terapkanRekomendasiOtomatis() {
            const form = formEl || document.querySelector('form[action*="kenaikan-kelas.store"]') || this.$el?.closest('form');
            if (!form) return;
            const rows = form.querySelectorAll('tbody tr[data-kelas-lama]');

            let mappedCount = 0;
            let lulusCount = 0;

            rows.forEach((row) => {
                const selectTindakan = row.querySelector('select[name$="[tindakan]"]');
                const selectKelasBaru = row.querySelector('select[name$="[kelas_baru_id]"]');
                const selectSemester = row.querySelector('select[name$="[semester_tujuan_id]"]');
                if (!selectTindakan) return;

                const isTingkatAkhir = row.dataset.isTingkatAkhir === '1';
                const tindakanVal = isTingkatAkhir ? 'lulus' : 'naik';

                // 1. Terapkan tindakan
                if (selectTindakan.tomselect) {
                    selectTindakan.tomselect.setValue(tindakanVal);
                } else {
                    selectTindakan.value = tindakanVal;
                    selectTindakan.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 2. Terapkan kelas tujuan
                if (selectKelasBaru) {
                    if (tindakanVal === 'lulus') {
                        if (selectKelasBaru.tomselect) {
                            selectKelasBaru.tomselect.setValue('');
                        } else {
                            selectKelasBaru.value = '';
                            selectKelasBaru.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        lulusCount++;
                    } else {
                        const namaLama = row.querySelector('td[data-nama]')?.dataset.nama || row.dataset.nama || '';
                        const rowData = row._x_dataStack ? row._x_dataStack[0] : null;
                        const tingkatAsal = rowData?.tingkatAsal != null ? String(rowData.tingkatAsal) : (row.dataset.tingkat || '');
                        const daftarTingkat = Array.isArray(rowData?.daftarTingkat) ? rowData.daftarTingkat.map(String) : [];

                        let targetTingkat = null;
                        if (tingkatAsal && daftarTingkat.length > 0) {
                            const idx = daftarTingkat.indexOf(tingkatAsal);
                            if (idx !== -1 && idx + 1 < daftarTingkat.length) {
                                targetTingkat = daftarTingkat[idx + 1];
                            }
                        }

                        const suffix = extractClassSuffix(namaLama);
                        const options = Array.from(selectKelasBaru.options || []).filter((opt) => opt.value !== '');
                        const candidateOptions = targetTingkat
                            ? options.filter((opt) => String(opt.dataset.tingkat || '') === String(targetTingkat))
                            : options;

                        let bestOption = null;
                        if (suffix && candidateOptions.length > 0) {
                            bestOption = candidateOptions.find((opt) => extractClassSuffix(opt.text) === suffix);
                        }
                        if (!bestOption && candidateOptions.length > 0) {
                            bestOption = candidateOptions[0];
                        }

                        if (bestOption) {
                            if (selectKelasBaru.tomselect) {
                                selectKelasBaru.tomselect.setValue(bestOption.value);
                            } else {
                                selectKelasBaru.value = bestOption.value;
                                selectKelasBaru.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            mappedCount++;
                        }
                    }
                }

                // 3. Terapkan semester tujuan default jika ada dan belum terisi
                if (selectSemester && !selectSemester.value) {
                    const firstValidSem = Array.from(selectSemester.options || []).find((opt) => opt.value !== '');
                    if (firstValidSem) {
                        if (selectSemester.tomselect) {
                            selectSemester.tomselect.setValue(firstValidSem.value);
                        } else {
                            selectSemester.value = firstValidSem.value;
                            selectSemester.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                }
            });

            self.hitungRingkasan();
            if (window.Alpine?.store('toast')) {
                const pesan = `Rekomendasi otomatis diterapkan: ${mappedCount} kelas dipetakan ke tingkat berikutnya, ${lulusCount} kelas tingkat akhir diset Lulus.`;
                window.Alpine.store('toast').push('success', pesan);
            }
        },

        toggleSemuaSalinJadwal() {
            const form = formEl || document.querySelector('form[action*="kenaikan-kelas.store"]') || this.$el?.closest('form');
            if (!form) return;
            const rows = form.querySelectorAll('tbody tr[data-kelas-lama]');

            rows.forEach((row) => {
                const checkbox = row.querySelector('input[type="checkbox"][name$="[salin_jadwal]"]');
                if (checkbox) {
                    checkbox.checked = self.semuaSalinJadwal;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        },

        barisCocokSearch(namaKelas) {
            if (!self.searchQuery) return true;
            return (namaKelas || '').toLowerCase().includes(self.searchQuery.toLowerCase().trim());
        },

        async konfirmasiDanKirim(event) {
            const form = event.target;
            self.hitungRingkasan();

            let message = `${self.countNaik} kelas akan dinaikkan, ${self.countLulus} kelas akan diluluskan, ${self.countLewati} kelas dilewati.`;
            if (self.countLulus > 0) {
                message += ' Siswa yang diluluskan akan dinonaktifkan akunnya secara otomatis.';
            }
            if (self.countPeringatan > 0) {
                message += ` Perhatian: ${self.countPeringatan} baris punya peringatan kurikulum/tingkat tidak wajar — periksa kembali kolom "Kelas Tujuan" sebelum lanjut.`;
            }
            message += ' Tindakan ini memindahkan/meluluskan siswa secara langsung dan tidak bisa dibatalkan otomatis.';

            const confirmed = await window.confirmDialog('Proses Kenaikan Kelas?', message, { confirmLabel: 'Ya, Proses Sekarang' });
            if (confirmed) {
                self.submitting = true;
                form.submit();
            }
        },
    };

    return self;
}
