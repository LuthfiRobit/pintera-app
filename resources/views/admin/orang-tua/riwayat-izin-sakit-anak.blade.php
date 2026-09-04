<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Riwayat Izin/Sakit Anak</h1>
            <p class="mt-1 text-sm text-slate">Catatan izin dan sakit anak Anda dalam rentang tanggal tertentu.</p>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Pilih Anak" />
                        <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Dari Tanggal" />
                        <input type="date" name="dari_tanggal" value="{{ $dariTanggal->toDateString() }}" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Sampai Tanggal" />
                        <input type="date" name="sampai_tanggal" value="{{ $sampaiTanggal->toDateString() }}" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </form>
            </x-panel>

            <x-panel class="p-6">
                @if ($riwayatList->isEmpty())
                    <div class="py-10 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                            <x-icon name="history" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-xs font-medium text-slate">Tidak ada riwayat izin/sakit pada rentang tanggal ini.</p>
                    </div>
                @else
                    <ul class="divide-y divide-ink/10">
                        @foreach ($riwayatList as $presensi)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <div>
                                    <p class="text-ink font-medium">{{ $presensi->sesiPembelajaran?->tanggal?->translatedFormat('d F Y') }}</p>
                                    <p class="text-xs text-slate mt-0.5">{{ $presensi->sesiPembelajaran?->mataPelajaran?->nama ?? 'Tematik' }} &middot; {{ $presensi->keterangan ?: '-' }}</p>
                                </div>
                                <x-badge tone="{{ $presensi->status->value === 'sakit' ? 'red' : 'amber' }}">{{ $presensi->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        @endif
    </div>
</x-app-layout>
