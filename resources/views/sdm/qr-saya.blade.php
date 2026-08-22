<x-app-layout>
    <div class="mx-auto max-w-md space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">QR Kehadiran Saya</h1>
            <p class="mt-1 text-sm text-gray-500">Tunjukkan kode ini ke petugas untuk dicatat kehadiran Anda.</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-card">
            @if ($qrCode)
                <p class="break-all rounded-lg bg-gray-50 p-4 font-mono text-sm text-gray-800">{{ $qrCode->token }}</p>
                <p class="mt-3 text-xs text-gray-400">Kode ini unik untuk {{ $pegawai->nama }} dan berlaku sampai Anda meminta perubahan baru.</p>
            @else
                <p class="text-sm text-gray-500">Anda belum memiliki QR kehadiran.</p>
            @endif

            <form method="POST" action="{{ route('sdm.qr-saya.generate') }}" class="mt-4" onsubmit="return confirm('{{ $qrCode ? 'Kode lama akan langsung tidak berlaku. Lanjutkan?' : 'Buat QR kehadiran baru?' }}')">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ $qrCode ? 'Buat Ulang QR' : 'Buat QR Kehadiran' }}
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
