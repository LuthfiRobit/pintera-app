import flatpickr from 'flatpickr';
import TomSelect from 'tom-select';

// Threshold from the project's standing UI rule: any dropdown offering more than
// 7-10 options must become a searchable select, not a plain native <select>.
const AMBANG_OPSI_SEARCHABLE = 10;

export function formulirTambahanForm() {
    return {
        initDateField(el) {
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j F Y',
                altInputClass: el.className,
            });
        },

        initSelectField(el, jumlahOpsi) {
            if (jumlahOpsi > AMBANG_OPSI_SEARCHABLE) {
                new TomSelect(el, {
                    maxItems: 1,
                    create: false,
                    placeholder: 'Cari...',
                });
            }
        },
    };
}
