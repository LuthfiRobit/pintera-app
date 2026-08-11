<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="font-display text-lg font-bold text-gray-900">Monitoring: {{ $jenisTagihan->nama }}</h1>
            <a href="{{ route('admin.jenis-tagihan.index') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-500">&larr; Kembali</a>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Penerima</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($ringkasan['total_penerima'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-gray-500">Siswa</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Tertagih</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">Rp{{ number_format($ringkasan['total_tertagih'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-gray-500">Selain dibatalkan</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Masuk</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">Rp{{ number_format($ringkasan['total_masuk'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-gray-500">Pembayaran terverifikasi</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status Pembayaran</p>
                <div class="mt-2 space-y-1 text-xs">
                    <div class="flex justify-between text-success-700"><span>Lunas:</span> <span class="font-semibold">{{ $ringkasan['lunas'] }}</span></div>
                    <div class="flex justify-between text-warning-700"><span>Sebagian:</span> <span class="font-semibold">{{ $ringkasan['sebagian'] }}</span></div>
                    <div class="flex justify-between text-error-700"><span>Belum Bayar:</span> <span class="font-semibold">{{ $ringkasan['belum_bayar'] }}</span></div>
                </div>
            </div>
        </div>

        <div x-data="{ activeTab: 'penerima', modalBatalkan: false, selectedTagihan: null }">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button
                        @click="activeTab = 'penerima'"
                        :class="activeTab === 'penerima' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                        class="whitespace-nowrap border-b-2 px-1 py-3.5 text-sm font-semibold"
                    >
                        Daftar Penerima
                    </button>
                    <button
                        @click="activeTab = 'tunggakan'"
                        :class="activeTab === 'tunggakan' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                        class="whitespace-nowrap border-b-2 px-1 py-3.5 text-sm font-semibold"
                    >
                        Daftar Tunggakan
                    </button>
                </nav>
            </div>

            <div x-show="activeTab === 'penerima'" class="mt-5">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <p class="font-display text-sm font-bold text-gray-900">Daftar Penerima Tagihan</p>
                    </div>
                    <div class="p-5 text-sm text-gray-500">Tabel Penerima (Task 4)</div>
                </div>
            </div>

            <div x-show="activeTab === 'tunggakan'" class="mt-5" style="display: none;">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <p class="font-display text-sm font-bold text-gray-900">Daftar Tunggakan</p>
                    </div>
                    <div class="p-5 text-sm text-gray-500">Tabel Tunggakan (Task 5)</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
