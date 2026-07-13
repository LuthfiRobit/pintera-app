<x-panel>
    <div class="border-b border-ink/10 px-6 py-4">
        <h3 class="font-display font-semibold text-ink">Formulir Field</h3>
        <p class="mt-0.5 text-sm text-slate">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-ink/10 px-6">
        @forelse ($jalur->formulirField as $field)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-medium text-ink">{{ $field->label }}</span>
                    <span class="ml-2 text-xs uppercase text-slate">{{ $field->field_type }}</span>
                    @if ($field->is_required)
                        <x-badge tone="brass">Wajib</x-badge>
                    @endif
                    @if ($field->field_type === 'select' && $field->options)
                        <p class="mt-0.5 text-xs text-slate">Opsi: {{ implode(', ', $field->options) }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.formulir-field.destroy', $field) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate">Belum ada field tambahan.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.formulir-field.store') }}" class="space-y-3 border-t border-ink/10 bg-paper/50 px-6 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" name="label" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select name="field_type" class="mt-1.5 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-ink">
                <input type="checkbox" name="is_required" value="1" class="rounded border-ink/25 text-brass focus:ring-brass">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea name="options" rows="2" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"></textarea>
        </div>
        <x-secondary-button type="submit">Tambah Field</x-secondary-button>
    </form>
</x-panel>
