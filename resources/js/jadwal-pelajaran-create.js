import TomSelect from 'tom-select';

export function jadwalPelajaranCreateForm() {
    return {
        initJamPelajaranSelect(el) {
            new TomSelect(el, {
                create: false,
                placeholder: 'Pilih satu atau beberapa slot jam pelajaran...',
            });
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initGuruSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari guru...',
            });
        },
    };
}
