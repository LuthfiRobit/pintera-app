<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Dokumen Syarat</p>
        <p class="mt-0.5 text-sm text-gray-500">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->dokumenSyarat as $dokumen)
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-gray-900">
                    {{ $dokumen->nama_dokumen }}
                    @if ($dokumen->wajib)
                        <x-badge tone="brass">Wajib</x-badge>
                    @else
                        <x-badge tone="slate">Opsional</x-badge>
                    @endif
                </span>
                <form method="POST" action="{{ route('admin.dokumen-syarat.destroy', $dokumen) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada dokumen syarat.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.dokumen-syarat.store') }}" class="flex flex-wrap items-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" name="nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
            <input type="hidden" name="wajib" value="0">
            <input type="checkbox" name="wajib" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" checked>
            Wajib
        </label>
        <x-secondary-button type="submit">Tambah Dokumen</x-secondary-button>
    </form>
</div>
