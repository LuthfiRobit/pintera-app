<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Nilai & Rapor Saya</h1>
                <p class="mt-1 text-sm text-slate">Pantau pencapaian hasil belajar mata pelajaran dan unduh rapor resmi Anda.</p>
            </div>
            @if ($siswa)
                <div class="inline-flex items-center gap-2 rounded-xl bg-paper px-3.5 py-1.5 border border-ink/10 text-xs font-medium text-slate">
                    <x-icon name="school" class="h-4 w-4 text-brand-500" />
                    <span>{{ $siswa->nama_lengkap }}</span>
                    <span class="text-ink/20">&middot;</span>
                    <span class="font-semibold text-ink">{{ $siswa->kelas?->nama ?? 'Belum Ada Kelas' }}</span>
                </div>
            @endif
        </div>

        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.nilai-rapor-saya.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Pilih Semester" class="font-medium text-xs text-slate uppercase tracking-wider" />
                    <select id="semester_id" name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>{{ $sem->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-panel>

        @if ($pengajuanRapor)
            <x-panel class="border border-brand-200 bg-gradient-to-r from-brand-50/70 via-white to-brand-50/30 p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3.5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-md shadow-brand-500/20">
                            <x-icon name="receipt" class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-display text-base font-bold text-ink">Rapor Resmi Tersedia</h3>
                                <x-badge tone="green">Disetujui</x-badge>
                            </div>
                            <p class="mt-0.5 text-xs text-slate">Rapor hasil belajar semester ini sudah disetujui dan siap untuk diunduh dalam format PDF.</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semesterId]) }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-95">
                        <x-icon name="print" class="h-4 w-4" />
                        <span>Unduh Rapor (PDF)</span>
                    </a>
                </div>
            </x-panel>
        @endif

        <x-panel class="p-6">
            <div class="flex items-center justify-between border-b border-ink/10 pb-4">
                <div>
                    <h3 class="font-display text-lg font-bold text-ink">Daftar Nilai Hasil Belajar</h3>
                    <p class="text-xs text-slate">{{ $semesterList->firstWhere('id', $semesterId)?->nama ?? 'Semester Terpilih' }}</p>
                </div>
                <span class="rounded-full bg-paper px-3 py-1 text-xs font-medium text-slate">
                    {{ $nilaiList->count() }} Mata Pelajaran
                </span>
            </div>

            @if ($nilaiList->isEmpty())
                <div class="py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                        <x-icon name="assessment" class="h-7 w-7" />
                    </span>
                    <h4 class="mt-3 font-display text-sm font-semibold text-ink">Belum Ada Nilai</h4>
                    <p class="mt-1 text-xs text-slate">Belum ada nilai yang tercatat untuk semester ini.</p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink/10 text-left text-sm">
                        <thead>
                            <tr class="text-[11px] font-semibold uppercase tracking-wider text-slate">
                                <th class="py-3 px-4">Mata Pelajaran</th>
                                <th class="py-3 px-4">Asesmen / Komponen</th>
                                <th class="py-3 px-4 text-right">Nilai Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            @foreach ($nilaiList as $nilai)
                                <tr class="transition hover:bg-brand-50/20">
                                    <td class="py-3.5 px-4 font-medium text-ink">
                                        {{ $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate">
                                        {{ $nilai->asesmen?->nama ?? $nilai->komponenPenilaian?->nama ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <x-badge :tone="$nilai->nilai_angka >= 85 ? 'green' : ($nilai->nilai_angka >= 75 ? 'brass' : 'amber')">
                                            {{ $nilai->nilai_angka }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>
    </div>
</x-app-layout>
