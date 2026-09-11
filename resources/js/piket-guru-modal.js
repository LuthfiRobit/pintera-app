import TomSelect from 'tom-select';

export function piketGuruModal(config) {
    return {
        storeUrlBase: config.storeUrlBase,
        activeLembagaId: config.activeLembagaId ?? null,
        guruList: config.guruList || [],
        lembagaMap: config.lembagaMap || {},
        modalGuruTomSelect: null,
        semesterSelectEl: null,
        showModalForm: false,
        formModal: {
            mode: 'create',
            actionUrl: '',
            guru_id: '',
            hari: '',
            semester_id: '',
            lembagaId: null,
            loading: false,
            errorMessage: '',
            errors: {},
        },

        formModalLembagaNama() {
            return this.formModal.lembagaId ? (this.lembagaMap[this.formModal.lembagaId] ?? '—') : '—';
        },

        openCreateModal() {
            this.formModal.mode = 'create';
            this.formModal.actionUrl = this.storeUrlBase;
            this.formModal.guru_id = '';
            this.formModal.hari = '';
            this.formModal.semester_id = '';
            this.formModal.lembagaId = this.activeLembagaId;
            this.formModal.errorMessage = '';
            this.formModal.errors = {};
            this.showModalForm = true;

            this.$nextTick(() => {
                this.refreshGuruOptions();
                this.refreshSemesterOptions();
            });
        },

        openEditModal(data) {
            this.formModal.mode = 'edit';
            this.formModal.actionUrl = data.url;
            this.formModal.guru_id = String(data.guru_id);
            this.formModal.hari = String(data.hari);
            this.formModal.semester_id = String(data.semester_id);
            this.formModal.lembagaId = data.lembaga_id ?? null;
            this.formModal.errorMessage = '';
            this.formModal.errors = {};
            this.showModalForm = true;

            this.$nextTick(() => {
                this.refreshGuruOptions();
                this.refreshSemesterOptions();
            });
        },

        initModalGuruSelect(el) {
            if (el.tomselect) el.tomselect.destroy();
            this.modalGuruTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: '— Pilih atau cari guru —',
                onChange: (value) => {
                    this.formModal.guru_id = value;
                },
            });
            this.refreshGuruOptions();
        },

        refreshGuruOptions() {
            if (!this.modalGuruTomSelect) return;

            const options = this.formModal.lembagaId
                ? this.guruList.filter((g) => String(g.lembaga_id) === String(this.formModal.lembagaId))
                : this.guruList;

            this.modalGuruTomSelect.clearOptions();
            options.forEach((g) => this.modalGuruTomSelect.addOption({ value: String(g.id), text: g.nama }));
            this.modalGuruTomSelect.refreshOptions(false);

            if (this.formModal.guru_id && options.some((g) => String(g.id) === String(this.formModal.guru_id))) {
                this.modalGuruTomSelect.setValue(this.formModal.guru_id, true);
            } else {
                this.modalGuruTomSelect.clear(true);
            }
        },

        initModalSemesterSelect(el) {
            this.semesterSelectEl = el;
            this.refreshSemesterOptions();
        },

        refreshSemesterOptions() {
            const el = this.semesterSelectEl;
            if (!el) return;

            const lembagaId = this.formModal.lembagaId;
            el.querySelectorAll('option[data-lembaga-id]').forEach((opt) => {
                opt.hidden = lembagaId ? String(opt.dataset.lembagaId) !== String(lembagaId) : false;
            });
            el.querySelectorAll('optgroup').forEach((group) => {
                const adaOpsiTerlihat = Array.from(group.querySelectorAll('option')).some((opt) => ! opt.hidden);
                group.hidden = ! adaOpsiTerlihat;
            });

            if (this.formModal.semester_id) {
                const opt = el.querySelector(`option[value="${this.formModal.semester_id}"]`);
                if (! opt || opt.hidden) {
                    this.formModal.semester_id = '';
                }
            }
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
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.status === 'error') {
                    this.formModal.errors = data.errors || {};
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal piket.');
                    this.formModal.errorMessage = firstError;
                    window.Alpine.store('toast').push('error', this.formModal.errorMessage);
                } else {
                    window.Alpine.store('toast').push('success', data.message || 'Jadwal piket berhasil disimpan.');
                    this.showModalForm = false;
                    await this.muatUlangDaftar();
                }
            } catch (error) {
                this.formModal.errorMessage = 'Terjadi kesalahan jaringan saat menyimpan jadwal piket.';
                window.Alpine.store('toast').push('error', this.formModal.errorMessage);
            } finally {
                this.formModal.loading = false;
            }
        },
    };
}
