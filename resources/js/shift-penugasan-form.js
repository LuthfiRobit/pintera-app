import TomSelect from 'tom-select';

export function shiftPenugasanForm() {
    return {
        pegawaiTipe: 'guru',

        initSelect(el) {
            new TomSelect(el, { maxItems: 1, create: false, allowEmptyOption: true, controlInput: null });
        },
    };
}
