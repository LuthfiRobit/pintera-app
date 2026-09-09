import TomSelect from 'tom-select';

export function raporFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        tahunAjaranTomSelect: null,
        kelasTomSelect: null,
        semesterTomSelect: null,

        initTahunAjaranSelect(el) {
            this.tahunAjaranTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    this.tahunAjaranId = value;
                    this.gantiTahunAjaran(value);
                },
            });
        },

        initKelasSelect(el) {
            this.kelasTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari kelas...',
                onChange: (value) => {
                    this.kelasId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        initSemesterSelect(el) {
            this.semesterTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
                onChange: (value) => {
                    this.semesterId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();
            this.kelasId = '';
            this.semesterId = '';

            if (!tahunAjaranId) {
                await this.muatUlangDaftar();
                return;
            }

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
                } else {
                    json.kelasList.forEach((kelas) => {
                        this.kelasTomSelect.addOption({ value: String(kelas.id), text: kelas.nama });
                    });
                    this.kelasTomSelect.refreshOptions(false);
                    if (json.kelasList.length > 0) {
                        this.kelasId = String(json.kelasList[0].id);
                        this.kelasTomSelect.setValue(this.kelasId, true);
                    }

                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                    if (json.semesterList.length > 0) {
                        this.semesterId = String(json.semesterList[0].id);
                        this.semesterTomSelect.setValue(this.semesterId, true);
                    }
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId ?? '');
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat rekap nilai.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl();
                this.$refs.hasilRapor.innerHTML = html;
                if (window.Alpine) {
                    window.Alpine.initTree(this.$refs.hasilRapor);
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat rekap nilai.');
            }
        },

        bukaCetakRapor(url, judul = 'Rekap Nilai Rapor') {
            const isPdf = true;
            const previewUrl = url.includes('?') ? `${url}&inline=1` : `${url}?inline=1`;
            if (window.Alpine && window.Alpine.store('imagePreview')) {
                window.Alpine.store('imagePreview').buka(previewUrl, judul, isPdf);
            } else {
                window.open(previewUrl, '_blank');
            }
        },

        resetFilters() {
            this.tahunAjaranId = '';
            this.kelasId = '';
            this.semesterId = '';
            this.tahunAjaranTomSelect?.clear(true);
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();
            this.muatUlangDaftar();
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            this.tahunAjaranId ? params.set('tahun_ajaran_id', this.tahunAjaranId) : params.delete('tahun_ajaran_id');
            this.kelasId ? params.set('kelas_id', this.kelasId) : params.delete('kelas_id');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            window.history.pushState({}, '', url);
        },
    };
}

if (typeof window !== 'undefined') {
    window.bukaCetakRapor = function (url, judul = 'Rekap Nilai Rapor') {
        const isPdf = true;
        const previewUrl = url.includes('?') ? `${url}&inline=1` : `${url}?inline=1`;
        if (window.Alpine && window.Alpine.store('imagePreview')) {
            window.Alpine.store('imagePreview').buka(previewUrl, judul, isPdf);
        } else {
            window.open(previewUrl, '_blank');
        }
    };
}
