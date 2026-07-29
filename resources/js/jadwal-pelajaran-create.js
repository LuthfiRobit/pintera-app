import TomSelect from 'tom-select';

export function jadwalPelajaranCreateForm() {
    return {
        initJamPelajaranSelect(el, placeholder = 'Pilih satu atau beberapa slot jam pelajaran...') {
            new TomSelect(el, {
                create: false,
                placeholder,
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
