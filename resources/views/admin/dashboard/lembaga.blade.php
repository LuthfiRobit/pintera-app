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
        />

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
    </div>
</x-app-layout>
