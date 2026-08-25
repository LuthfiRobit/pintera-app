<x-app-layout>
    <h1 class="sr-only">Dashboard Platform</h1>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Platform &middot; Pengawasan Lintas Yayasan"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Ringkasan seluruh yayasan yang terdaftar di platform ini."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Yayasan" :value="$stats['yayasan']" hint="Total tenant terdaftar" icon="domain" />
            <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Lintas semua yayasan" icon="apartment" />
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Lintas semua yayasan" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
        </div>

        <x-panel class="p-6">
            <p class="mb-3 text-sm font-medium text-ink">Tren Pertumbuhan Yayasan (6 Bulan Terakhir)</p>
            <div x-data="trenTenantChart(@js($trenTenant['labels']), @js($trenTenant['data']))">
                <canvas x-ref="canvas" height="90"></canvas>
            </div>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Ringkasan per Yayasan</h3>
                <p class="mt-0.5 text-sm text-slate">Data agregat setiap yayasan yang terdaftar di platform.</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-6 py-3 font-display font-semibold">Yayasan</th>
                        <th class="px-6 py-3 font-display font-semibold">Lembaga</th>
                        <th class="px-6 py-3 font-display font-semibold">Guru</th>
                        <th class="px-6 py-3 font-display font-semibold">Pengguna</th>
                        <th class="px-6 py-3 font-display font-semibold">TA Aktif?</th>
                        <th class="px-6 py-3 font-display font-semibold">Akun Nonaktif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ($ringkasanPerYayasan as $ringkasan)
                        <tr>
                            <td class="px-6 py-3.5 font-display font-medium text-ink">{{ $ringkasan['yayasan']->nama }}</td>
                            <td class="px-6 py-3.5">{{ $ringkasan['lembaga'] }}</td>
                            <td class="px-6 py-3.5">{{ $ringkasan['guru'] }}</td>
                            <td class="px-6 py-3.5">{{ $ringkasan['pengguna'] }}</td>
                            <td class="px-6 py-3.5">
                                <x-badge tone="{{ $ringkasan['adaTahunAjaranAktif'] ? 'green' : 'amber' }}">{{ $ringkasan['adaTahunAjaranAktif'] ? 'Ya' : 'Tidak' }}</x-badge>
                            </td>
                            <td class="px-6 py-3.5">{{ $ringkasan['akunNonaktif'] }}</td>
                        </tr>
                    @endforeach
                    @if ($ringkasanPerYayasan->isEmpty())
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate">Belum ada yayasan terdaftar.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </x-panel>
    </div>
</x-app-layout>
