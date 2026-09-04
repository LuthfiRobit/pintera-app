<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Nilai & Rapor Anak</h1>
                <p class="mt-1 text-sm text-slate">Pantau nilai per mata pelajaran dan unduh rapor resmi anak Anda.</p>
            </div>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Pilih Anak" />
                        <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }} &middot; {{ $anakOpsi->kelas?->nama ?? 'Belum Ditentukan' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Semester" />
                        <select name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($semesterList as $semesterOpsi)
                                <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </x-panel>

            @if ($pengajuanRapor)
                <x-panel class="p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <x-icon name="receipt" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-display font-bold text-sm text-ink">Rapor Resmi Tersedia</h3>
                                <p class="text-xs text-slate">Rapor semester ini sudah disetujui dan siap diunduh.</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.nilai-anak.unduh-rapor', ['siswa' => $anak->id, 'semester_id' => $semesterId]) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition">
                            <x-icon name="print" class="h-4 w-4" />
                            Unduh Rapor
                        </a>
                    </div>
                </x-panel>
            @endif

            <x-panel class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                    <div>
                        <h3 class="font-display font-bold text-lg text-ink">Daftar Nilai</h3>
                        <p class="text-xs text-slate">{{ $anak?->nama_lengkap }} &middot; {{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '-' }}</p>
                    </div>
                </div>

                @if ($nilaiList->isEmpty())
                    <div class="py-10 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                            <x-icon name="assessment" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-xs font-medium text-slate">Belum ada nilai yang tercatat untuk semester ini.</p>
                    </div>
                @else
                    <ul class="mt-4 divide-y divide-ink/10">
                        @foreach ($nilaiList as $nilai)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <span class="text-ink">{{ $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '-' }}</span>
                                <x-badge tone="brass">{{ $nilai->nilai_angka }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        @endif
    </div>
</x-app-layout>
