export function raporWaliKelasFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        semesterId: config.semesterId ?? '',
        kelasId: config.kelasId ?? '',
        search: config.search ?? '',
        statusFilter: config.statusFilter ?? 'all',
        stats: config.stats ?? {},
        isLoading: false,
        opsiUrl: config.opsiUrl,
        indexUrl: config.indexUrl,
        tahunAjaranTomSelect: null,
        semesterTomSelect: null,
        kelasTomSelect: null,

        initTahunAjaranSelect(el) {
            this.$nextTick(() => {
                if (!window.TomSelect) return;
                this.tahunAjaranTomSelect = new window.TomSelect(el, {
                    maxItems: 1,
                    create: false,
                    placeholder: 'Cari tahun ajaran...',
                    onChange: (value) => {
                        if (this.tahunAjaranId !== value) {
                            this.gantiTahunAjaran(value);
                        }
                    },
                });
            });
        },

        initSemesterSelect(el) {
            this.$nextTick(() => {
                if (!window.TomSelect) return;
                this.semesterTomSelect = new window.TomSelect(el, {
                    maxItems: 1,
                    create: false,
                    placeholder: 'Cari semester...',
                    onChange: (value) => {
                        if (this.semesterId !== value) {
                            this.semesterId = value;
                            this.muatUlangDaftar();
                        }
                    },
                });

                if (!this.tahunAjaranId) {
                    this.semesterTomSelect.disable();
                }
            });
        },

        initKelasSelect(el) {
            this.$nextTick(() => {
                if (!window.TomSelect) return;
                this.kelasTomSelect = new window.TomSelect(el, {
                    maxItems: 1,
                    create: false,
                    placeholder: 'Cari kelas perwalian...',
                    onChange: (value) => {
                        if (this.kelasId !== value) {
                            this.kelasId = value;
                            this.muatUlangDaftar();
                        }
                    },
                });

                if (!this.tahunAjaranId) {
                    this.kelasTomSelect.disable();
                }
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.tahunAjaranId = tahunAjaranId;
            this.semesterId = '';
            this.kelasId = '';
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
                this.kelasTomSelect?.disable();
                await this.muatUlangDaftar();
                return;
            }

            this.semesterTomSelect?.enable();
            this.kelasTomSelect?.enable();

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (response.ok) {
                    if (json.semesterList) {
                        this.semesterTomSelect.addOption({ value: '', text: 'Semua Semester' });
                        json.semesterList.forEach((semester) => {
                            this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                        });
                        this.semesterTomSelect.refreshOptions(false);
                    }

                    if (json.kelasList) {
                        json.kelasList.forEach((kelas) => {
                            this.kelasTomSelect.addOption({ value: String(kelas.id), text: kelas.nama });
                        });
                        this.kelasTomSelect.refreshOptions(false);
                    }
                } else {
                    window.Alpine?.store('toast')?.push('error', 'Gagal memuat opsi kelas/semester.');
                }
            } catch (error) {
                window.Alpine?.store('toast')?.push('error', 'Gagal memuat opsi kelas/semester.');
            }

            await this.muatUlangDaftar();
        },

        setStatusFilter(newFilter) {
            if (this.statusFilter === newFilter) return;
            this.statusFilter = newFilter;
            this.muatUlangDaftar();
        },

        resetFilters() {
            this.search = '';
            this.statusFilter = 'all';
            this.tahunAjaranId = '';
            this.semesterId = '';
            this.kelasId = '';
            this.tahunAjaranTomSelect?.clear(true);
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.disable();
            this.kelasTomSelect?.clear(true);
            this.kelasTomSelect?.disable();
            this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            this.isLoading = true;
            try {
                const url = new URL(this.indexUrl, window.location.origin);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);
                if (this.kelasId) url.searchParams.set('kelas_id', this.kelasId);
                if (this.search) url.searchParams.set('search', this.search);
                if (this.statusFilter && this.statusFilter !== 'all') {
                    url.searchParams.set('status_catatan', this.statusFilter);
                }

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    window.Alpine?.store('toast')?.push('error', 'Gagal memuat daftar rapor siswa.');
                    return;
                }

                const html = await response.text();

                window.history.pushState({}, '', url.toString());

                if (this.$refs.tableContent) {
                    this.$refs.tableContent.innerHTML = html;
                    window.Alpine?.initTree(this.$refs.tableContent);

                    const statsEl = this.$refs.tableContent.querySelector('[data-rapor-stats]');
                    if (statsEl) {
                        try {
                            this.stats = JSON.parse(statsEl.dataset.raporStats);
                        } catch (error) {
                            // Keep the previous stats rather than crash the filter on malformed payload.
                        }
                    }
                }
            } catch (error) {
                window.Alpine?.store('toast')?.push('error', 'Terjadi kesalahan jaringan.');
            } finally {
                this.isLoading = false;
            }
        },
    };
}
