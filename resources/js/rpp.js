export function rppPageManager(config) {
    return {
        filters: config.filters || {},
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        showModalForm: false,
        showModalVerify: false,
        selectedFileName: '',
        formModal: {
            mode: 'create',
            actionUrl: '',
            semester_id: '',
            kelas_id: '',
            mata_pelajaran_id: '',
            judul_topik: '',
            alokasi_waktu: '2 x 35 Menit',
            pertemuan_ke: '1',
            errorMessage: '',
        },
        verifyModal: {
            id: null,
            guruNama: '',
            kelasNama: '',
            mapelNama: '',
            judulTopik: '',
            fileName: '',
            fileUrl: '',
            downloadUrl: '',
            actionUrl: '',
            status: 'disetujui',
            catatanRevisi: '',
        },

        init() {
            this.formModal.actionUrl = this.indexUrlBase;
        },

        async muatUlangDaftar() {
            try {
                window.dispatchEvent(new CustomEvent('ajax-start'));
                const url = new URL(this.indexUrlBase, window.location.origin);
                for (const [key, value] of Object.entries(this.filters)) {
                    if (value) url.searchParams.set(key, value);
                }
                if (this.perPage !== 20) url.searchParams.set('per_page', this.perPage);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.ok) {
                    const html = await response.text();
                    window.history.pushState({}, '', url);
                    if (this.$refs.tableContainer) {
                        this.$refs.tableContainer.innerHTML = html;
                    }
                } else {
                    window.Alpine.store('toast')?.push('error', 'Gagal memuat data.');
                }
            } catch (error) {
                window.Alpine.store('toast')?.push('error', 'Gagal memuat data.');
            } finally {
                window.dispatchEvent(new CustomEvent('ajax-end'));
            }
        },

        openCreateModal(defaultSemesterId = '', defaultKelasId = '') {
            this.formModal.mode = 'create';
            this.formModal.actionUrl = this.indexUrlBase;
            this.formModal.semester_id = defaultSemesterId || this.filters.semester_id || '';
            this.formModal.kelas_id = defaultKelasId || this.filters.kelas_id || '';
            this.formModal.mata_pelajaran_id = this.filters.mata_pelajaran_id || '';
            this.formModal.judul_topik = '';
            this.formModal.alokasi_waktu = '2 x 35 Menit';
            this.formModal.pertemuan_ke = '1';
            this.formModal.errorMessage = '';
            this.selectedFileName = '';
            this.showModalForm = true;
        },

        openEditModal(data) {
            this.formModal.mode = 'edit';
            this.formModal.actionUrl = data.url || data.actionUrl || '';
            this.formModal.semester_id = data.semesterId || data.semester_id || '';
            this.formModal.kelas_id = data.kelasId || data.kelas_id || '';
            this.formModal.mata_pelajaran_id = data.mataPelajaranId || data.mata_pelajaran_id || '';
            this.formModal.judul_topik = data.judulTopik || data.judul_topik || '';
            this.formModal.alokasi_waktu = data.alokasiWaktu || data.alokasi_waktu || '2 x 35 Menit';
            this.formModal.pertemuan_ke = data.pertemuanKe || data.pertemuan_ke || '';
            this.formModal.errorMessage = '';
            this.selectedFileName = '';
            this.showModalForm = true;
        },

        openVerifyModal(data) {
            this.verifyModal.id = data.id;
            this.verifyModal.guruNama = data.guruNama || data.guru_nama || '';
            this.verifyModal.kelasNama = data.kelasNama || data.kelas_nama || '';
            this.verifyModal.mapelNama = data.mapelNama || data.mapel_nama || 'Tematik PAUD';
            this.verifyModal.judulTopik = data.judulTopik || data.judul_topik || '';
            this.verifyModal.fileName = data.fileName || data.file_name || 'Dokumen RPP';
            this.verifyModal.fileUrl = data.fileUrl || data.file_url || data.downloadUrl || data.download_url || '';
            this.verifyModal.downloadUrl = this.verifyModal.fileUrl;
            this.verifyModal.actionUrl = data.actionUrl || data.action_url || '';
            this.verifyModal.status = 'disetujui';
            this.verifyModal.catatanRevisi = '';
            this.showModalVerify = true;
        },

        bukaBerkas(url, fileName = 'Dokumen RPP') {
            const isPdf = fileName.toLowerCase().endsWith('.pdf') || url.toLowerCase().includes('.pdf');
            const previewUrl = url.includes('?') ? `${url}&inline=1` : `${url}?inline=1`;
            if (window.Alpine && window.Alpine.store('imagePreview')) {
                window.Alpine.store('imagePreview').buka(previewUrl, fileName, isPdf);
            } else {
                window.open(previewUrl, '_blank');
            }
        },
    };
}
