<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Review Rapor — {{ $pengajuanRapor->kelas->nama }}</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Semester: {{ $pengajuanRapor->semester->nama }}</p>
            </div>
            <a href="{{ route('admin.rapor.persetujuan.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">&larr; Kembali ke Daftar</a>
        </div>

        @if ($pengajuanRapor->status === \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan && $pengajuanRapor->catatan_revisi)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                <p class="font-semibold">Catatan revisi dari siklus sebelumnya (jika masih relevan):</p>
                <p class="mt-1">{{ $pengajuanRapor->catatan_revisi }}</p>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-display text-sm font-bold text-gray-900">Rekap Nilai Per Mapel</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm min-w-[600px]">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-600">
                            <th class="px-4 py-3">Nama Siswa</th>
                            @foreach ($mapelList as $subjekKey => $mapel)
                                <th class="px-3 py-3 text-center">{{ $mapel->nama }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($siswaList as $siswa)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                @foreach ($mapelList as $subjekKey => $mapel)
                                    @php $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null; @endphp
                                    <td class="px-3 py-3 text-center">
                                        @if ($sel === null)
                                            —
                                        @elseif ($sel->tuntas !== null)
                                            <span class="inline-block rounded-lg px-2 py-0.5 text-xs font-semibold {{ $sel->tuntas ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $sel->label }}</span>
                                        @else
                                            <span class="inline-block rounded-lg px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">{{ $sel->label }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="font-display text-sm font-bold text-gray-900 px-1">Catatan Wali Kelas Per Siswa</h2>
            @foreach ($siswaList as $siswa)
                @php($catatan = $catatanList->get($siswa->id))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</p>
                        <a href="{{ route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuanRapor->id, 'siswa' => $siswa->id]) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:underline">
                            Cetak PDF
                        </a>
                    </div>
                    @if ($catatan)
                        <dl class="grid grid-cols-1 gap-2 text-xs text-gray-600 sm:grid-cols-2">
                            <div><dt class="font-semibold text-gray-500">Catatan Sikap</dt><dd>{{ $catatan->catatan_sikap ?: '—' }}</dd></div>
                            <div><dt class="font-semibold text-gray-500">Catatan Perkembangan</dt><dd>{{ $catatan->catatan_perkembangan ?: '—' }}</dd></div>
                        </dl>
                    @else
                        <p class="text-xs text-error-600">Belum ada catatan wali kelas untuk siswa ini.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.rapor.persetujuan.decision', $pengajuanRapor) }}" x-data="{ action: 'APPROVE' }" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            @csrf
            <label class="block text-xs font-semibold text-gray-700">Keputusan</label>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-xs font-medium text-gray-800 cursor-pointer">
                    <input type="radio" name="action" value="APPROVE" x-model="action" class="text-brand-600 focus:ring-brand-500">
                    <span>Setujui</span>
                </label>
                <label class="flex items-center gap-2 text-xs font-medium text-rose-800 cursor-pointer">
                    <input type="radio" name="action" value="REJECT" x-model="action" class="text-rose-600 focus:ring-rose-500">
                    <span>Tolak, Minta Revisi Wali Kelas</span>
                </label>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan (Opsional)</label>
                <textarea name="catatan" rows="2" placeholder="Catatan untuk wali kelas..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div class="flex items-center justify-end">
                <x-primary-button type="submit">Kirim Keputusan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
