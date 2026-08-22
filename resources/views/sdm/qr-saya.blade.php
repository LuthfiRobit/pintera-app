{{-- resources/views/sdm/qr-saya.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">QR Kehadiran Saya</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tunjukkan kode QR ini ke petugas untuk mencatat kehadiran Anda.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM &amp; Kepegawaian <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">QR Saya</b>
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Center Container for QR Ticket Card --}}
        <div class="flex items-center justify-center py-4 sm:py-8">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-card space-y-5">
            @if ($qrCode)
                {{-- Scannable Image Generator --}}
                <div class="flex flex-col items-center justify-center p-5 bg-gray-50/80 border border-gray-100 rounded-2xl max-w-xs mx-auto">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrCode->token) }}" alt="QR Kehadiran {{ $pegawai->nama }}" class="h-44 w-44 object-contain rounded-xl bg-white p-2 shadow-xs">
                    <span class="mt-3 text-[10px] uppercase font-bold text-gray-400 tracking-widest">Scan QR Presensi SDM</span>
                </div>

                {{-- Copyable Token Container --}}
                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 border border-gray-100">
                    <div class="text-left overflow-hidden mr-2">
                        <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider">Token Presensi</p>
                        <p class="font-mono text-xs font-bold text-gray-800 truncate mt-0.5">{{ $qrCode->token }}</p>
                    </div>
                    <button 
                        @click="
                            navigator.clipboard.writeText('{{ $qrCode->token }}'); 
                            $store.toast ? $store.toast.push('success', 'Token QR berhasil disalin!') : alert('Token QR berhasil disalin!');
                        " 
                        type="button" 
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-2xs shrink-0"
                        title="Salin Token"
                    >
                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002-2h2a2 2 0 002-2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <span>Salin Token</span>
                    </button>
                </div>

                <p class="text-xs text-gray-400 leading-relaxed">
                    Kode ini unik untuk <b class="text-gray-700">{{ $pegawai->nama }}</b> dan berlaku sampai Anda meminta pembuatan kode baru.
                </p>
            @else
                <div class="py-8 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-500 mx-auto mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-700">Anda belum memiliki QR kehadiran.</p>
                    <p class="text-xs text-gray-400 mt-1 max-w-xs mx-auto">Silakan buat kode QR kehadiran baru di bawah ini.</p>
                </div>
            @endif

            <form 
                method="POST" 
                action="{{ route('sdm.qr-saya.generate') }}" 
                class="pt-2 border-t border-gray-100" 
                x-data
                @submit.prevent="confirmDialog(
                    '{{ $qrCode ? 'Buat Ulang QR Kehadiran?' : 'Buat QR Kehadiran Baru?' }}',
                    '{{ $qrCode ? 'Kode lama akan langsung tidak berlaku. Lanjutkan?' : 'Apakah Anda yakin ingin membuat QR kehadiran baru?' }}',
                    { confirmLabel: '{{ $qrCode ? 'Ya, Buat Ulang' : 'Ya, Buat QR' }}' }
                ).then(confirmed => { if (confirmed) $el.submit() })"
            >
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    {{ $qrCode ? 'Buat Ulang QR Kehadiran' : 'Buat QR Kehadiran Baru' }}
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
