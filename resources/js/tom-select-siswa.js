import TomSelect from 'tom-select';

export function tomSelectSiswa(config) {
    return {
        options: config.options,
        oldValue: config.oldValue,
        tomSelect: null,
        init() {
            this.tomSelect = new TomSelect(this.$refs.selectElement, {
                valueField: 'id',
                labelField: 'nama',
                searchField: ['nama', 'nis', 'nisn'],
                options: this.options,
                items: this.oldValue ? [this.oldValue] : [],
                placeholder: 'Ketik nama, NIS, atau NISN...',
                maxOptions: 50,
                render: {
                    option: function(data, escape) {
                        return `<div>
                                    <span class="block font-semibold text-gray-900">${escape(data.nama)}</span>
                                    <span class="block text-xs font-mono text-gray-500">NIS: ${escape(data.nis)} | NISN: ${escape(data.nisn)}</span>
                                </div>`;
                    },
                    item: function(data, escape) {
                        return `<div>${escape(data.nama)} <span class="text-gray-400 font-mono text-xs">(NIS: ${escape(data.nis)})</span></div>`;
                    }
                },
            });
        }
    }
}
