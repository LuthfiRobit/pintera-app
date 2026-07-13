<x-panel>
    <div class="border-b border-ink/10 px-6 py-4">
        <h3 class="font-display font-semibold text-ink">Dokumen Syarat</h3>
        <p class="mt-0.5 text-sm text-slate">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-ink/10 px-6">
        @forelse ($jalur->dokumenSyarat as $dokumen)
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-ink">
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
                    <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate">Belum ada dokumen syarat.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.dokumen-syarat.store') }}" class="flex flex-wrap items-end gap-2 border-t border-ink/10 bg-paper/50 px-6 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" name="nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-ink">
            <input type="hidden" name="wajib" value="0">
            <input type="checkbox" name="wajib" value="1" class="rounded border-ink/25 text-brass focus:ring-brass" checked>
            Wajib
        </label>
        <x-secondary-button type="submit">Tambah Dokumen</x-secondary-button>
    </form>
</x-panel>
