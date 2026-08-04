import TomSelect from 'tom-select';

export function karyawanForm(config) {
    return {
        canCreatePool: config.canCreatePool,
        isPool: false,

        initSelect(el) {
            new TomSelect(el, { maxItems: 1, create: false, allowEmptyOption: true, controlInput: null });
        },
    };
}
