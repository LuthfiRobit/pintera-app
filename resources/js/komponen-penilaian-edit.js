import TomSelect from 'tom-select';

export function komponenPenilaianEditForm() {
    return {
        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initSemesterSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });
        },
    };
}
