<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Seleksi &amp; Tes</p>
        <p class="mt-0.5 text-sm text-gray-500">Jadwal tes untuk jalur ini, per gelombang. Boleh dikosongkan jika jalur tidak memakai tes.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->seleksi as $seleksi)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900">{{ $seleksi->jenisTesMaster->nama }}</span>
                    <span class="ml-2 text-xs text-gray-500">{{ $seleksi->gelombangPpdb->nama }} &middot; {{ $seleksi->jadwal->format('d M Y H:i') }}</span>
                    @if ($seleksi->kriteria_kelulusan)
                        <p class="mt-0.5 text-xs text-gray-500">{{ $seleksi->kriteria_kelulusan }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.seleksi.destroy', $seleksi) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada jadwal seleksi.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.seleksi.store') }}" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <x-input-label value="Gelombang" />
                <select name="gelombang_ppdb_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($gelombangList as $gelombang)
                        <option value="{{ $gelombang->id }}">{{ $gelombang->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jenis Tes" />
                <select name="jenis_tes_master_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($jenisTesList as $jenisTes)
                        <option value="{{ $jenisTes->id }}">{{ $jenisTes->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jadwal" />
                <x-text-input type="datetime-local" name="jadwal" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Bobot (%)" />
                <x-text-input type="number" name="bobot" class="mt-1.5 w-24" />
            </div>
        </div>
        <div>
            <x-input-label value="Kriteria Kelulusan (opsional)" />
            <x-text-input type="text" name="kriteria_kelulusan" class="mt-1.5" />
        </div>
        <x-secondary-button type="submit">Tambah Jadwal Seleksi</x-secondary-button>
    </form>
</div>
