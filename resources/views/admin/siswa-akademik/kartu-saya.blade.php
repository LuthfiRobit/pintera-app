<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kartu Digital Saya</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tunjukkan kode QR ini ke guru untuk presensi.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-center py-4 sm:py-8">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-card space-y-5">
                <div class="flex flex-col items-center justify-center p-5 bg-gray-50/80 border border-gray-100 rounded-2xl">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->generate($kartu->kode) !!}
                </div>

                <p class="text-xs text-gray-400 leading-relaxed">
                    Kode ini unik untuk Anda dan berlaku sampai Anda membuat kode baru.
                </p>

                <form method="POST" action="{{ route('admin.kartu-saya.generate-ulang') }}" class="pt-2 border-t border-gray-100" x-data
                    @submit.prevent="confirmDialog(
                        'Buat Ulang Kode QR?',
                        'Kode lama akan langsung tidak berlaku. Lanjutkan?',
                        { confirmLabel: 'Ya, Buat Ulang' }
                    ).then(confirmed => { if (confirmed) $el.submit() })"
                >
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Generate Ulang Kode QR
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
