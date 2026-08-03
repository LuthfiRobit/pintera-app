import TomSelect from 'tom-select';

export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        storeUrlBase: config.storeUrlBase ?? '',
        kelasTomSelect: null,
        modalJamCreateTomSelect: null,
        modalJamEditTomSelect: null,
        modalMapelTomSelect: null,
        modalGuruTomSelect: null,
        viewMode: 'matrix',
        showModalForm: false,
        showModalDuplicate: false,
        formModal: {
            mode: 'create',
            actionUrl: '',
            jam_ids: [],
            jam_id: '',
            mapel_id: '',
            guru_id: '',
            loading: false,
            errorMessage: '',
        },
        duplicateForm: {
            source_kelas_id: '',
            source_semester_id: '',
            target_kelas_id: '',
            target_semester_id: '',
            loading: false,
            errorMessage: '',
        },

        openCreateModal(data = {}) {
            this.formModal.mode = 'create';
            this.formModal.actionUrl = this.storeUrlBase;
            this.formModal.jam_ids = data && data.jam_ids ? data.jam_ids.map(String) : [];
            this.formModal.mapel_id = '';
            this.formModal.guru_id = '';
            this.formModal.errorMessage = '';
            this.showModalForm = true;

            this.$nextTick(() => {
                if (this.modalJamCreateTomSelect) {
                    if (this.formModal.jam_ids && this.formModal.jam_ids.length > 0) {
                        this.modalJamCreateTomSelect.setValue(this.formModal.jam_ids, true);
                    } else {
                        this.modalJamCreateTomSelect.clear(true);
                    }
                }
                if (this.modalMapelTomSelect) {
                    this.modalMapelTomSelect.clear(true);
                }
                if (this.modalGuruTomSelect) {
                    this.modalGuruTomSelect.clear(true);
                }
            });
        },

        openEditModal(data) {
            this.formModal.mode = 'edit';
            this.formModal.actionUrl = data.url;
            this.formModal.jam_id = String(data.jam_id);
            this.formModal.mapel_id = data.mapel_id ? String(data.mapel_id) : '';
            this.formModal.guru_id = String(data.guru_id);
            this.formModal.errorMessage = '';
            this.showModalForm = true;

            this.$nextTick(() => {
                if (this.modalJamEditTomSelect) {
                    if (this.formModal.jam_id) {
                        this.modalJamEditTomSelect.setValue(this.formModal.jam_id, true);
                    } else {
                        this.modalJamEditTomSelect.clear(true);
                    }
                }
                if (this.modalMapelTomSelect) {
                    if (this.formModal.mapel_id) {
                        this.modalMapelTomSelect.setValue(this.formModal.mapel_id, true);
                    } else {
                        this.modalMapelTomSelect.clear(true);
                    }
                }
                if (this.modalGuruTomSelect) {
                    if (this.formModal.guru_id) {
                        this.modalGuruTomSelect.setValue(this.formModal.guru_id, true);
                    } else {
                        this.modalGuruTomSelect.clear(true);
                    }
                }
            });
        },

        openDuplicateModal() {
            this.duplicateForm.target_kelas_id = this.kelasId;
            this.duplicateForm.target_semester_id = this.semesterId;
            this.duplicateForm.source_semester_id = this.semesterId;
            this.duplicateForm.source_kelas_id = '';
            this.duplicateForm.errorMessage = '';
            this.showModalDuplicate = true;
        },

        async submitForm(event) {
            event.preventDefault();
            this.formModal.loading = true;
            this.formModal.errorMessage = '';
            try {
                const url = event.target.action;
                const formData = new FormData(event.target);
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.status === 'error') {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal.');
                    this.formModal.errorMessage = firstError;
                    Alpine.store('toast').push('error', this.formModal.errorMessage);
                } else {
                    Alpine.store('toast').push('success', data.message || 'Jadwal berhasil disimpan.');
                    this.showModalForm = false;
                    await this.muatUlangDaftar();
                }
            } catch (e) {
                this.formModal.errorMessage = 'Terjadi kesalahan jaringan saat menyimpan jadwal.';
                Alpine.store('toast').push('error', this.formModal.errorMessage);
            } finally {
                this.formModal.loading = false;
            }
        },

        async submitDuplicate(event) {
            event.preventDefault();
            this.duplicateForm.loading = true;
            this.duplicateForm.errorMessage = '';
            try {
                const url = event.target.action;
                const formData = new FormData(event.target);
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.status === 'error') {
                    this.duplicateForm.errorMessage = data.message || 'Gagal menyalin jadwal pelajaran.';
                    Alpine.store('toast').push('error', this.duplicateForm.errorMessage);
                } else {
                    Alpine.store('toast').push('success', data.message || 'Jadwal berhasil disalin.');
                    this.showModalDuplicate = false;
                    await this.muatUlangDaftar();
                }
            } catch (e) {
                this.duplicateForm.errorMessage = 'Terjadi kesalahan jaringan saat menyalin jadwal.';
                Alpine.store('toast').push('error', this.duplicateForm.errorMessage);
            } finally {
                this.duplicateForm.loading = false;
            }
        },

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

        initModalCreateJamSelect(el) {
            if (el.tomselect) el.tomselect.destroy();
            this.modalJamCreateTomSelect = new TomSelect(el, {
                plugins: ['remove_button'],
                create: false,
                placeholder: 'Pilih slot jam pelajaran (bisa pilih lebih dari satu)...',
                onChange: (value) => {
                    this.formModal.jam_ids = Array.isArray(value) ? value : (value ? [value] : []);
                },
            });
            if (this.formModal.jam_ids && this.formModal.jam_ids.length > 0) {
                this.modalJamCreateTomSelect.setValue(this.formModal.jam_ids, true);
            } else {
                this.modalJamCreateTomSelect.clear(true);
            }
        },

        initModalEditJamSelect(el) {
            if (el.tomselect) el.tomselect.destroy();
            this.modalJamEditTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: '— Pilih slot jam —',
                onChange: (value) => {
                    this.formModal.jam_id = value;
                },
            });
            if (this.formModal.jam_id) {
                this.modalJamEditTomSelect.setValue(this.formModal.jam_id, true);
            }
        },

        initModalMapelSelect(el) {
            if (el.tomselect) el.tomselect.destroy();
            this.modalMapelTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari atau pilih mata pelajaran...',
                onChange: (value) => {
                    this.formModal.mapel_id = value;
                },
            });
            if (this.formModal.mapel_id) {
                this.modalMapelTomSelect.setValue(this.formModal.mapel_id, true);
            } else {
                this.modalMapelTomSelect.clear(true);
            }
        },

        initModalGuruSelect(el) {
            if (el.tomselect) el.tomselect.destroy();
            this.modalGuruTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari atau pilih guru pengampu...',
                onChange: (value) => {
                    this.formModal.guru_id = value;
                },
            });
            if (this.formModal.guru_id) {
                this.modalGuruTomSelect.setValue(this.formModal.guru_id, true);
            } else {
                this.modalGuruTomSelect.clear(true);
            }
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
                this.modalJamCreateTomSelect = null;
                this.modalJamEditTomSelect = null;
                this.modalMapelTomSelect = null;
                this.modalGuruTomSelect = null;
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
