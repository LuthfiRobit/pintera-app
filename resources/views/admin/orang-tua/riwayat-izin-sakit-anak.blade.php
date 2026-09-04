<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Riwayat Izin/Sakit Anak</h1>
                <p class="mt-1 text-sm text-slate">Catatan riwayat izin dan sakit anak Anda dalam rentang tanggal tertentu.</p>
            </div>
            @if ($anak)
                <div class="inline-flex items-center gap-2 rounded-xl bg-paper px-3.5 py-1.5 border border-ink/10 text-xs font-medium text-slate">
                    <x-icon name="school" class="h-4 w-4 text-brand-500" />
                    <span>{{ $anak->nama_lengkap }}</span>
                    <span class="text-ink/20">&middot;</span>
                    <span class="font-semibold text-ink">{{ $anak->kelas?->nama ?? 'Belum Ditentukan' }}</span>
                </div>
            @endif
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-12 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                    <x-icon name="person" class="h-7 w-7" />
                </span>
                <h4 class="mt-3 font-display text-sm font-semibold text-ink">Belum Ada Data Siswa</h4>
                <p class="mt-1 text-xs text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    <div>
                        <x-input-label for="siswa_id" value="Pilih Anak" class="font-medium text-xs text-slate uppercase tracking-wider" />
                        <select id="siswa_id" name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="dari_tanggal" value="Dari Tanggal" class="font-medium text-xs text-slate uppercase tracking-wider" />
                        <input type="date" id="dari_tanggal" name="dari_tanggal" value="{{ $dariTanggal->toDateString() }}" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label for="sampai_tanggal" value="Sampai Tanggal" class="font-medium text-xs text-slate uppercase tracking-wider" />
                        <input type="date" id="sampai_tanggal" name="sampai_tanggal" value="{{ $sampaiTanggal->toDateString() }}" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-95">
                            <x-icon name="filter" class="h-4 w-4" />
                            <span>Filter Riwayat</span>
                        </button>
                    </div>
                </form>
            </x-panel>

            @php
                $totalIzin = $riwayatList->filter(fn($p) => ($p->status instanceof \BackedEnum ? $p->status->value : (string)$p->status) === 'izin')->count();
                $totalSakit = $riwayatList->filter(fn($p) => ($p->status instanceof \BackedEnum ? $p->status->value : (string)$p->status) === 'sakit')->count();
                $totalSemua = $riwayatList->count();
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-panel class="p-4 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <x-icon name="assignment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-medium text-slate">Total Izin</p>
                        <h4 class="font-display text-lg font-bold text-ink">{{ $totalIzin }} <span class="text-xs font-normal text-slate">Hari</span></h4>
                    </div>
                </x-panel>

                <x-panel class="p-4 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <x-icon name="medical_services" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-medium text-slate">Total Sakit</p>
                        <h4 class="font-display text-lg font-bold text-ink">{{ $totalSakit }} <span class="text-xs font-normal text-slate">Hari</span></h4>
                    </div>
                </x-panel>

                <x-panel class="p-4 flex items-center gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-paper text-slate">
                        <x-icon name="history" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-medium text-slate">Total Riwayat</p>
                        <h4 class="font-display text-lg font-bold text-ink">{{ $totalSemua }} <span class="text-xs font-normal text-slate">Catatan</span></h4>
                    </div>
                </x-panel>
            </div>

            <x-panel class="p-6">
                <div class="flex items-center justify-between border-b border-ink/10 pb-4">
                    <div>
                        <h3 class="font-display text-lg font-bold text-ink">Daftar Catatan Izin & Sakit</h3>
                        <p class="text-xs text-slate">
                            Periode: {{ $dariTanggal->translatedFormat('d M Y') }} - {{ $sampaiTanggal->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <span class="rounded-full bg-paper px-3 py-1 text-xs font-medium text-slate">
                        {{ $totalSemua }} Catatan
                    </span>
                </div>

                @if ($riwayatList->isEmpty())
                    <div class="py-12 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                            <x-icon name="history" class="h-7 w-7" />
                        </span>
                        <h4 class="mt-3 font-display text-sm font-semibold text-ink">Tidak Ada Catatan Izin/Sakit</h4>
                        <p class="mt-1 text-xs text-slate">Tidak ada catatan izin maupun sakit pada rentang tanggal yang dipilih.</p>
                    </div>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-ink/10 text-left text-sm">
                            <thead>
                                <tr class="text-[11px] font-semibold uppercase tracking-wider text-slate">
                                    <th class="py-3 px-4">Tanggal & Waktu</th>
                                    <th class="py-3 px-4">Mata Pelajaran</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/5">
                                @foreach ($riwayatList as $presensi)
                                    @php
                                        $statusVal = $presensi->status instanceof \BackedEnum ? $presensi->status->value : (string) $presensi->status;
                                        $statusLabel = $presensi->status instanceof \App\Domains\Akademik\Enums\StatusPresensi ? $presensi->status->label() : ucfirst($statusVal);
                                        $jamMulai = optional($presensi->sesiPembelajaran)->jam_mulai;
                                        $jamSelesai = optional($presensi->sesiPembelajaran)->jam_selesai;
                                    @endphp
                                    <tr class="transition hover:bg-brand-50/20">
                                        <td class="py-3.5 px-4">
                                            <div class="font-medium text-ink">
                                                {{ optional($presensi->sesiPembelajaran)->tanggal?->translatedFormat('d M Y') ?? '-' }}
                                            </div>
                                            @if ($jamMulai)
                                                <div class="text-[11px] font-mono text-slate">
                                                    {{ substr($jamMulai, 0, 5) }} - {{ substr($jamSelesai, 0, 5) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-medium text-ink">
                                                {{ $presensi->sesiPembelajaran?->mataPelajaran?->nama ?? 'Tematik' }}
                                            </div>
                                            @if (optional($presensi->sesiPembelajaran)->guru)
                                                <div class="text-xs text-slate">
                                                    {{ $presensi->sesiPembelajaran->guru->nama }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <x-badge :tone="$statusVal === 'sakit' ? 'red' : 'amber'">{{ $statusLabel }}</x-badge>
                                        </td>
                                        <td class="py-3.5 px-4 text-xs text-slate">
                                            {{ $presensi->keterangan ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>
        @endif
    </div>
</x-app-layout>
