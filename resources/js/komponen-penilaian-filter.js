import TomSelect from 'tom-select';

export function komponenPenilaianFilter(config) {
    return {
        search: config.search ?? '',
        statusFilter: 'all', // 'all', 'incomplete', 'complete'
        tahunAjaranId: config.tahunAjaranId ?? '',
        semesterId: config.semesterId ?? '',
        mataPelajaranId: config.mataPelajaranId ?? '',
        expandedCards: {},
        isLoading: false,
        opsiUrl: config.opsiUrl,
        indexUrl: config.indexUrl,
        tahunAjaranTomSelect: null,
        semesterTomSelect: null,
        mataPelajaranTomSelect: null,

        init() {
            this.initAccordionKeys();
        },

        initAccordionKeys() {
            this.$nextTick(() => {
                this.$el.querySelectorAll('[data-accordion-key]').forEach((el) => {
                    const key = el.getAttribute('data-accordion-key');
                    if (key && !(key in this.expandedCards)) {
                        this.expandedCards[key] = true;
                    }
                });
            });
        },

        initTahunAjaranSelect(el) {
            this.tahunAjaranTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    if (this.tahunAjaranId !== value) {
                        this.gantiTahunAjaran(value);
                    }
                },
            });
        },

        initSemesterSelect(el) {
            this.semesterTomSelect = new TomSelect(el, {
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
        },

        initMataPelajaranSelect(el) {
            this.mataPelajaranTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
                onChange: (value) => {
                    if (this.mataPelajaranId !== value) {
                        this.mataPelajaranId = value;
                        this.muatUlangDaftar();
                    }
                },
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
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                } else {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
            }

            await this.muatUlangDaftar();
        },

        async muatUlangDaftar() {
            this.isLoading = true;
            try {
                const url = new URL(this.indexUrl, window.location.origin);
                if (this.tahunAjaranId) url.searchParams.set('tahun_ajaran_id', this.tahunAjaranId);
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

                window.history.pushState({}, '', url.toString());

                if (this.$refs.daftarKomponen) {
                    this.$refs.daftarKomponen.innerHTML = html;
                    Alpine.initTree(this.$refs.daftarKomponen);
                    this.initAccordionKeys();
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Terjadi kesalahan jaringan.');
            } finally {
                this.isLoading = false;
            }
        },

        resetFilters() {
            this.search = '';
            this.tahunAjaranId = '';
            this.semesterId = '';
            this.mataPelajaranId = '';
            this.tahunAjaranTomSelect?.clear(true);
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.disable();
            this.mataPelajaranTomSelect?.clear(true);
            this.muatUlangDaftar();
        },

        toggleCard(key) {
            this.expandedCards[key] = !this.expandedCards[key];
        },

        expandAll() {
            Object.keys(this.expandedCards).forEach((k) => (this.expandedCards[k] = true));
        },

        collapseAll() {
            Object.keys(this.expandedCards).forEach((k) => (this.expandedCards[k] = false));
        },

        isCardVisible(key, subjectName, totalBobot, tps) {
            if (this.statusFilter === 'complete' && totalBobot !== 100) return false;
            if (this.statusFilter === 'incomplete' && totalBobot === 100) return false;

            if (!this.search || this.search.trim() === '') return true;
            const q = this.search.toLowerCase().trim();
            if (subjectName.toLowerCase().includes(q)) return true;
            return tps.some(
                (tp) =>
                    (tp.kode && tp.kode.toLowerCase().includes(q)) ||
                    (tp.deskripsi && tp.deskripsi.toLowerCase().includes(q)) ||
                    (tp.kktp && tp.kktp.toLowerCase().includes(q))
            );
        },

        isTpVisible(tp, subjectName) {
            if (!this.search || this.search.trim() === '') return true;
            const q = this.search.toLowerCase().trim();
            if (subjectName.toLowerCase().includes(q)) return true;
            return (
                (tp.kode && tp.kode.toLowerCase().includes(q)) ||
                (tp.deskripsi && tp.deskripsi.toLowerCase().includes(q)) ||
                (tp.kktp && tp.kktp.toLowerCase().includes(q))
            );
        },
    };
}
