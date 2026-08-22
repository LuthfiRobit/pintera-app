import TomSelect from 'tom-select';

export function attendanceManualForm() {
    return {
        pegawaiTipe: 'guru',

        initSelect(el, placeholder = 'Cari nama...') {
            if (!el) return;
            if (el.tomselect) {
                el.tomselect.destroy();
            }
            new TomSelect(el, {
                create: false,
                allowEmptyOption: true,
                placeholder: placeholder,
                sortField: { field: 'text', order: 'asc' },
            });
        },
    };
}
