<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Lembaga &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Panel Administrasi Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Kelola data guru, pengguna, dan tahun ajaran di lembaga Anda."
        >
            @if (Auth::user()->widestScopeLevel() === 'yayasan')
                <x-slot name="actions">
                    <a href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}" class="inline-flex items-center rounded-xl border border-white/30 px-4 py-2 text-sm text-paper transition hover:bg-white/10">
                        &larr; Kembali ke Ringkasan Yayasan
                    </a>
                </x-slot>
            @endif
        </x-hero-banner>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar di lembaga Anda" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif di lembaga Anda" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" :hint="$tahunAjaranAktif->nama ?? 'Belum diaktifkan'" icon="calendar_month" />
        </div>

        @if ($spmbStats)
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">SPMB</h3>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-stat-tile label="Total Pendaftar" :value="$spmbStats['total']" icon="groups" />
                    <x-stat-tile label="Menunggu Verifikasi" :value="$spmbStats['menunggu_verifikasi']" icon="hourglass_empty" />
                    <x-stat-tile label="Diterima" :value="$spmbStats['diterima']" icon="check_circle" />
                    <x-stat-tile label="Ditolak" :value="$spmbStats['ditolak']" icon="cancel" />
                </div>
                <x-panel class="mt-4 p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Tren Pendaftaran (30 Hari Terakhir)</p>
                    <div x-data="trenPendaftaranChart(@js($tren['labels']), @js($tren['data']))">
                        <canvas x-ref="canvas" height="90"></canvas>
                    </div>
                </x-panel>
            </div>
        @endif

        @if ($keuanganStats)
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Keuangan</h3>
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-stat-tile label="Rp Terkumpul" value="Rp {{ number_format($keuanganStats['rpTerkumpul'], 0, ',', '.') }}" icon="payments" />
                    <x-stat-tile label="Rp Belum Lunas" value="Rp {{ number_format($keuanganStats['rpBelumLunas'], 0, ',', '.') }}" icon="pending_actions" />
                    <a href="{{ route('admin.pembayaran.index') }}">
                        <x-stat-tile label="Pembayaran Menunggu Verifikasi" :value="$keuanganStats['pembayaranMenungguVerifikasi']" icon="fact_check" />
                    </a>
                </div>
                <x-panel class="mt-4 p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Komposisi Status Tagihan</p>
                    <div
                        x-data="donutTagihanChart(
                            ['Belum Bayar', 'Dicicil', 'Lunas'],
                            @js([$keuanganStats['donut']['belum_bayar'], $keuanganStats['donut']['dicicil'], $keuanganStats['donut']['lunas']])
                        )"
                        class="max-w-xs"
                    >
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </x-panel>
            </div>
        @endif

        @unless ($spmbStats || $keuanganStats)
            <x-panel class="p-6 text-sm text-slate">
                Modul Akademik, E-Sarpra, E-HRD, dan E-BK menyusul di fase berikutnya.
            </x-panel>
        @endunless

        @if ($kasusList !== null)
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Kasus Pendampingan</h3>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <x-stat-tile label="Diajukan" :value="$kasusStats['diajukan']" icon="assignment_late" />
                    <x-stat-tile label="Menunggu Consent" :value="$kasusStats['menunggu_consent']" icon="hourglass_empty" />
                    <x-stat-tile label="Ditugaskan" :value="$kasusStats['ditugaskan']" icon="assignment" />
                    <x-stat-tile label="Berjalan" :value="$kasusStats['berjalan']" icon="pending_actions" />
                    <x-stat-tile label="Eskalasi" :value="$kasusStats['eskalasi']" icon="priority_high" />
                    <x-stat-tile label="Selesai" :value="$kasusStats['selesai']" icon="check_circle" />
                </div>
                <x-panel class="mt-4">
                    @if ($kasusList->isEmpty())
                        <p class="px-6 py-8 text-center text-sm text-slate">Belum ada kasus pendampingan di lembaga ini.</p>
                    @else
                        <ul class="divide-y divide-ink/10">
                            @foreach ($kasusList as $kasus)
                                <li class="px-6 py-3">
                                    <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass">
                                        <div>
                                            <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                            <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                        </div>
                                        <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>
            </div>
        @endif
    </div>
</x-app-layout>
