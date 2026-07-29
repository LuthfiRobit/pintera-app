import TomSelect from 'tom-select';

export function komponenPenilaianFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        semesterId: config.semesterId ?? '',
        mataPelajaranId: config.mataPelajaranId ?? '',
        search: config.search ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        semesterTomSelect: null,

        initTahunAjaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    this.tahunAjaranId = value;
                    this.gantiTahunAjaran(value);
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

            if (!this.tahunAjaranId) {
                this.semesterTomSelect.disable();
            }
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
                onChange: (value) => {
                    this.mataPelajaranId = value;
                    this.muatUlangDaftar();
                },
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.semesterId = '';
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
                await this.muatUlangDaftar();
                return;
            }

            this.semesterTomSelect?.enable();

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
                } else {
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId ?? '');
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);
                if (this.mataPelajaranId) url.searchParams.set('mata_pelajaran_id', this.mataPelajaranId);
                if (this.search) url.searchParams.set('search', this.search);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar komponen penilaian.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl();
                this.$refs.daftarKomponen.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar komponen penilaian.');
            }
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            params.set('tahun_ajaran_id', this.tahunAjaranId ?? '');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            this.mataPelajaranId ? params.set('mata_pelajaran_id', this.mataPelajaranId) : params.delete('mata_pelajaran_id');
            this.search ? params.set('search', this.search) : params.delete('search');
            window.history.pushState({}, '', url);
        },
    };
}
