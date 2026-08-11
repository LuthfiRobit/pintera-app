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
                    <div class="border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                        <p class="font-display text-sm font-bold text-gray-900">Daftar Penerima Tagihan</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-3">Penerima</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Total Tagihan</th>
                                    <th class="px-5 py-3 text-right">Sisa Tagihan</th>
                                    <th class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($tagihanPenerima as $tagihan)
                                    <tr class="transition hover:bg-gray-50">
                                        <td class="px-5 py-3.5">
                                            <p class="font-semibold text-gray-900">{{ $tagihan->tagihable->nama_lengkap ?? $tagihan->tagihable->nama ?? 'Unknown' }}</p>
                                            <p class="text-xs text-gray-500">{{ class_basename($tagihan->tagihable_type) }}</p>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                                @if($tagihan->status === 'lunas') bg-success-50 text-success-700
                                                @elseif($tagihan->status === 'sebagian') bg-warning-50 text-warning-700
                                                @elseif($tagihan->status === 'belum_bayar') bg-error-50 text-error-700
                                                @else bg-gray-100 text-gray-700 @endif
                                            ">
                                                {{ str_replace('_', ' ', Str::title($tagihan->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-medium text-gray-900">
                                            Rp{{ number_format($tagihan->net_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-medium text-gray-900">
                                            Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a href="#" class="text-brand-600 hover:text-brand-700">Detail</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-gray-500">
                                            Belum ada data penerima tagihan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($tagihanPenerima->hasPages())
                        <div class="border-t border-gray-200 px-5 py-4">
                            {{ $tagihanPenerima->appends(['tunggakan_page' => request('tunggakan_page')])->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <div x-show="activeTab === 'tunggakan'" class="mt-5" style="display: none;">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                    <div class="border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                        <p class="font-display text-sm font-bold text-gray-900">Daftar Tunggakan</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-3">Siswa</th>
                                    <th class="px-5 py-3 text-center">Jumlah Tagihan</th>
                                    <th class="px-5 py-3 text-right">Total Tunggakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($tagihanTunggakan as $tunggakan)
                                    <tr class="transition hover:bg-gray-50">
                                        <td class="px-5 py-3.5">
                                            <p class="font-semibold text-gray-900">{{ $tunggakan->tagihable->nama_lengkap ?? $tunggakan->tagihable->nama ?? 'Unknown' }}</p>
                                        </td>
                                        <td class="px-5 py-3.5 text-center text-gray-600">
                                            {{ $tunggakan->jumlah_tunggakan }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-bold text-error-600">
                                            Rp{{ number_format($tunggakan->total_tunggakan, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-8 text-center text-gray-500">
                                            Tidak ada data tunggakan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($tagihanTunggakan->hasPages())
                        <div class="border-t border-gray-200 px-5 py-4">
                            {{ $tagihanTunggakan->appends(['penerima_page' => request('penerima_page')])->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
