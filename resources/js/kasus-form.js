import TomSelect from 'tom-select';

export function kasusForm() {
    return {
        initSelect(el) {
            new TomSelect(el, { maxItems: 1, create: false, allowEmptyOption: true, controlInput: null });
        },
    };
}
