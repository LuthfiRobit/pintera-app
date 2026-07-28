import TomSelect from 'tom-select';

export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        createUrlBase: config.createUrlBase,
        kelasTomSelect: null,

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

        async gantiTahunAjaran(tahunAjaranId) {
            this.kelasId = '';
            this.semesterId = '';
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();
            if (this.$refs.semesterSelect) {
                this.$refs.semesterSelect.innerHTML = '<option value="">— Pilih Semester —</option>';
            }

            if (tahunAjaranId) {
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

                        json.semesterList.forEach((semester) => {
                            const option = document.createElement('option');
                            option.value = semester.id;
                            option.textContent = semester.nama;
                            this.$refs.semesterSelect.appendChild(option);
                        });
                    }
                } catch (error) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
                }
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            try {
                const url = new URL(this.indexUrlBase, window.location.origin);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat daftar jadwal.');
                    return;
                }

                const html = await response.text();
                this.perbaruiUrl();
                this.$refs.daftarJadwal.innerHTML = html;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat daftar jadwal.');
            }
        },

        perbaruiUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;
            this.tahunAjaranId ? params.set('tahun_ajaran_id', this.tahunAjaranId) : params.delete('tahun_ajaran_id');
            this.kelasId ? params.set('kelas_id', this.kelasId) : params.delete('kelas_id');
            this.semesterId ? params.set('semester_id', this.semesterId) : params.delete('semester_id');
            window.history.pushState({}, '', url);
        },

        tambahSlotUrl() {
            const url = new URL(this.createUrlBase, window.location.origin);
            url.searchParams.set('kelas_id', this.kelasId);
            url.searchParams.set('semester_id', this.semesterId);
            return url.toString();
        },
    };
}
