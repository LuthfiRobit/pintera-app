<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Formulir Field</p>
        <p class="mt-0.5 text-sm text-gray-500">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->formulirField as $field)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900">{{ $field->label }}</span>
                    <span class="ml-2 text-xs uppercase text-gray-500">{{ $field->field_type }}</span>
                    @if ($field->is_required)
                        <x-badge tone="brass">Wajib</x-badge>
                    @endif
                    @if ($field->field_type === 'select' && $field->options)
                        <p class="mt-0.5 text-xs text-gray-500">Opsi: {{ implode(', ', $field->options) }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.formulir-field.destroy', $field) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada field tambahan.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.formulir-field.store') }}" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" name="label" placeholder="Contoh: Nomor WhatsApp Orang Tua" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select name="field_type" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
                <input type="checkbox" name="is_required" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea name="options" rows="2" placeholder="Opsi 1&#10;Opsi 2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <x-secondary-button type="submit">Tambah Field</x-secondary-button>
    </form>
</div>
