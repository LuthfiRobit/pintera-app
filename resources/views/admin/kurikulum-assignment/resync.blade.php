<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div>
            <h1 class="font-display text-lg font-bold text-gray-900">Cek & Perbaiki Kurikulum/Fase Kelas</h1>
            <p class="text-xs text-gray-500">Alat koreksi manual untuk kelas yang kurikulum/fase tersimpannya sudah tidak sesuai dengan assignment terbaru. Tidak ada yang berubah otomatis -- pilih kelas yang mau disinkronkan.</p>
        </div>

        <form method="GET" action="{{ route('admin.kurikulum-assignment.resync') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            @if ($isPlatformOrYayasan)
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Lembaga</label>
                    <select name="lembaga_id" class="mt-1 rounded-lg border-gray-200 text-sm" onchange="this.form.submit()">
                        <option value="">— Pilih Lembaga —</option>
                        @foreach ($lembagaList as $l)
                            <option value="{{ $l->id }}" @selected($lembagaId === $l->id)>{{ $l->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-700">Tahun Ajaran</label>
                <select name="tahun_ajaran_id" class="mt-1 rounded-lg border-gray-200 text-sm">
                    <option value="">— Pilih Tahun Ajaran —</option>
                    @foreach ($tahunAjaranList as $ta)
                        <option value="{{ $ta->id }}" @selected($tahunAjaranId === $ta->id)>{{ $ta->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Cek Drift</button>
        </form>

        @if ($lembagaId !== null && $tahunAjaranId !== null)
            <form method="POST" action="{{ route('admin.kurikulum-assignment.resync.apply') }}" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                @csrf
                <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
                <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

                @if (empty($diff))
                    <p class="p-6 text-sm text-gray-500">Tidak ada kelas yang perlu disinkronkan -- semua kelas di kombinasi ini sudah sesuai dengan assignment terbaru.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600"><input type="checkbox" onclick="document.querySelectorAll('.resync-row').forEach(c => c.checked = this.checked)"></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($diff as $row)
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" class="resync-row"></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['kurikulumLama'] ?? '-' }} → {{ $row['kurikulumBaru'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['faseLamaId'] ?? '-' }} → {{ $row['faseBaruNama'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-4">
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Sinkronkan yang Dicentang</button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-app-layout>
