import TomSelect from 'tom-select';

export function tomSelectPegawai(config) {
    return {
        options: config.options || [],
        oldValue: config.oldValue || '',
        placeholder: config.placeholder || 'Cari nama...',
        tomSelect: null,
        init() {
            if (!this.$refs.selectElement) return;
            if (this.$refs.selectElement.tomselect) {
                this.$refs.selectElement.tomselect.destroy();
            }
            this.tomSelect = new TomSelect(this.$refs.selectElement, {
                valueField: 'id',
                labelField: 'nama',
                searchField: ['nama', 'subtext'],
                options: this.options,
                items: this.oldValue ? [String(this.oldValue)] : [],
                placeholder: this.placeholder,
                maxOptions: 100,
                render: {
                    option: function(data, escape) {
                        return `<div class="py-1">
                                    <span class="block font-semibold text-gray-900">${escape(data.nama)}</span>
                                    ${data.subtext ? `<span class="block text-xs font-mono text-gray-500 mt-0.5">${escape(data.subtext)}</span>` : ''}
                                </div>`;
                    },
                    item: function(data, escape) {
                        return `<div>${escape(data.nama)}</div>`;
                    }
                },
            });
        }
    }
}
