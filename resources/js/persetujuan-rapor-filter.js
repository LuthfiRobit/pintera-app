export function persetujuanRaporFilter(config) {
    return {
        tab: config.tab ?? 'menunggu',
        tahunAjaranId: config.tahunAjaranId ?? '',
        semesterId: config.semesterId ?? '',
        search: config.search ?? '',
        stats: config.stats ?? {},
        isLoading: false,
        opsiUrl: config.opsiUrl,
        indexUrl: config.indexUrl,
        tahunAjaranTomSelect: null,
        semesterTomSelect: null,

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

        async gantiTahunAjaran(tahunAjaranId) {
            this.tahunAjaranId = tahunAjaranId;
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

                if (response.ok && json.semesterList) {
                    this.semesterTomSelect.addOption({ value: '', text: 'Semua Semester' });
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                } else {
                    window.Alpine?.store('toast')?.push('error', 'Gagal memuat opsi semester.');
                }
            } catch (error) {
                window.Alpine?.store('toast')?.push('error', 'Gagal memuat opsi semester.');
            }

            await this.muatUlangDaftar();
        },

        setTab(newTab) {
            if (this.tab === newTab) return;
            this.tab = newTab;
            this.muatUlangDaftar();
        },

        resetFilters() {
            this.search = '';
            this.tahunAjaranId = '';
            this.semesterId = '';
            this.tahunAjaranTomSelect?.clear(true);
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.disable();
            this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            this.isLoading = true;
            try {
                const url = new URL(this.indexUrl, window.location.origin);
                if (this.tab) url.searchParams.set('tab', this.tab);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
                if (this.semesterId) url.searchParams.set('semester_id', this.semesterId);
                if (this.search) url.searchParams.set('search', this.search);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    window.Alpine?.store('toast')?.push('error', 'Gagal memuat daftar persetujuan rapor.');
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
