<x-app-layout>
    <h1 class="sr-only">Dashboard Yayasan</h1>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Yayasan &middot; Pengawasan Lintas Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Pantau seluruh lembaga di bawah yayasan dari satu tempat — data, staf, dan tahun ajaran yang sedang berjalan."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Total unit pendidikan" icon="apartment" />
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar lintas lembaga" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" hint="Berjalan saat ini" icon="calendar_month" />
        </div>

        @if (isset($ringkasanPerLembaga))
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Konsolidasi SPMB &amp; Keuangan (Tahun Ajaran Aktif per Lembaga)</h3>
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-stat-tile label="Total Pendaftar" :value="$totalPendaftar" icon="groups" />
                    <x-stat-tile label="Total Diterima" :value="$totalDiterima" icon="check_circle" />
                    <x-stat-tile label="Total Rp Terkumpul" value="Rp {{ number_format($totalRpTerkumpul, 0, ',', '.') }}" icon="payments" />
                </div>

                @if ($ringkasanPerLembaga->isNotEmpty())
                    <x-panel class="mt-4 p-6">
                        <p class="mb-3 text-sm font-medium text-ink">Pendaftar per Lembaga</p>
                        <div
                            x-data="perLembagaBarChart(
                                @js($ringkasanPerLembaga->pluck('lembaga.nama')),
                                @js($ringkasanPerLembaga->map(fn ($r) => $r['spmb']['total']))
                            )"
                        >
                            <canvas x-ref="canvas" height="90"></canvas>
                        </div>
                    </x-panel>
                @endif

                <x-panel class="mt-4">
                    <div class="border-b border-ink/10 px-6 py-4">
                        <h3 class="font-display font-semibold text-ink">Tinjau sebagai lembaga tertentu</h3>
                        <p class="mt-0.5 text-sm text-slate">Klik salah satu lembaga untuk menyaring seluruh data di sistem ke lembaga itu.</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                <th class="px-6 py-3 font-display font-semibold">Lembaga</th>
                                <th class="px-6 py-3 font-display font-semibold">Pendaftar</th>
                                <th class="px-6 py-3 font-display font-semibold">Diterima</th>
                                <th class="px-6 py-3 font-display font-semibold">Terkumpul</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($ringkasanPerLembaga as $ringkasan)
                                <tr class="cursor-pointer hover:bg-paper/60" onclick="window.location='{{ route('dashboard', ['switch_lembaga' => $ringkasan['lembaga']->id]) }}'">
                                    <td class="px-6 py-3.5 font-display font-medium text-ink">{{ $ringkasan['lembaga']->nama }}</td>
                                    <td class="px-6 py-3.5">{{ $ringkasan['spmb']['total'] }}</td>
                                    <td class="px-6 py-3.5">{{ $ringkasan['spmb']['diterima'] }}</td>
                                    <td class="px-6 py-3.5">Rp {{ number_format($ringkasan['keuangan']['rpTerkumpul'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @if ($ringkasanPerLembaga->isEmpty())
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-slate">Belum ada lembaga di bawah yayasan ini.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </x-panel>
            </div>
        @endif

        @if (isset($presensiSdmHariIni))
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Kehadiran SDM Hari Ini (Semua Lembaga)</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                        <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                        <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                        <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                        <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                    </div>
                </x-panel>
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Kasus Eskalasi Belum Ditangani</p>
                    <x-stat-tile label="Menunggu Konselor" :value="$kasusEskalasiUnassigned" icon="priority_high" hint="Lintas semua lembaga di yayasan ini" />
                </x-panel>
            </div>
        @endif
    </div>
</x-app-layout>
