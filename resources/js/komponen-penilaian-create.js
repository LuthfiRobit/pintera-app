import TomSelect from 'tom-select';

export function komponenPenilaianCreateForm(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        opsiUrl: config.opsiUrl,
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
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
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
        },
    };
}
