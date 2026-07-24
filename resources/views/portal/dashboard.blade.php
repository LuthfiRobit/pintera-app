{{-- resources/views/portal/dashboard.blade.php --}}
<x-layouts.portal-dashboard title="Dashboard">
    <p class="text-[11px] font-bold uppercase tracking-wide text-portal-500">Portal Calon Siswa</p>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">Halo, {{ Str::of(auth('portal')->user()->nama)->before(' ') }}</h1>
    <p class="mt-1 text-[13px] text-gray-500">Pantau status pendaftaranmu di sini.</p>

    @if ($pendaftaranList->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-gray-200 p-10 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-portal-50 text-portal-300">
                <x-icon name="school" class="h-8 w-8" />
            </span>
            <h2 class="mt-4 text-[15px] font-bold text-gray-900">Kamu belum memilih lembaga & jalur</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-[13px] text-gray-500">Pilih lembaga dan jalur pendaftaran untuk mulai mengisi formulir.</p>
            <a href="{{ route('spmb.welcome') }}" class="mt-5 inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-5 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Pilih Lembaga &amp; Jalur
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </a>
        </div>
    @else
        <div class="mt-6 flex items-center justify-between">
            <h2 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Riwayat Pendaftaran</h2>
            <a href="{{ route('spmb.welcome') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-4 py-2 text-[12.5px] font-bold text-white transition hover:bg-portal-600">
                <x-icon name="add" class="h-3.5 w-3.5" />
                Daftar Lagi
            </a>
        </div>

        <div class="mt-3 space-y-3">
            @foreach ($pendaftaranList as $pendaftaran)
                @php
                    $badge = match ($pendaftaran->status) {
                        'diterima' => ['success', 'check_circle', 'Diterima'],
                        'ditolak' => ['error', 'cancel', 'Ditolak'],
                        'daftar_ulang' => ['portal', 'autorenew', 'Daftar Ulang'],
                        'aktif' => ['success', 'check_circle', 'Aktif'],
                        default => ['warning', 'hourglass_empty', 'Menunggu Verifikasi'],
                    };
                    [$tone, $icon, $label] = $badge;
                @endphp
                <div class="rounded-xl border border-gray-200 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $pendaftaran->calonMurid->nama_lengkap }}</p>
                            <p class="mt-0.5 text-[13px] text-gray-500">{{ $pendaftaran->lembaga->nama }} &middot; {{ $pendaftaran->jalurPpdb->nama }} &middot; {{ $pendaftaran->gelombangPpdb->nama }}</p>
                            <p class="mt-0.5 font-mono text-[12px] text-gray-400">{{ $pendaftaran->kode_pendaftaran }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $tone }}-50 px-2.5 py-1 text-[11.5px] font-bold text-{{ $tone }}-{{ $tone === 'portal' ? '500' : '700' }}">
                                <x-icon name="{{ $icon }}" class="h-2.5 w-2.5" />
                                {{ $label }}
                            </span>
                            <a href="{{ route('portal.pendaftaran.bukti', $pendaftaran) }}" target="_blank" class="text-[12.5px] font-bold text-portal-500 hover:underline">
                                Unduh Bukti (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.portal-dashboard>
