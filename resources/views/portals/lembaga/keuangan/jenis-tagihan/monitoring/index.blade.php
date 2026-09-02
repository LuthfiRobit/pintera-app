<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4" x-data="{ activeTab: 'penerima', cancelModalOpen: false, cancelUrl: '' }">
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

        <div>
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
                                            @if ($tagihan->perlu_ditinjau_ulang)
                                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 border border-amber-200">Sedang Ditinjau</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-medium text-gray-900">
                                            Rp{{ number_format($tagihan->net_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-medium text-gray-900">
                                            Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a href="#" class="text-brand-600 hover:text-brand-700">Detail</a>
                                            @if($tagihan->status === 'belum_bayar')
                                                <button type="button" @click="cancelUrl = '{{ route('admin.jenis-tagihan.monitoring.batal', [$jenisTagihan, $tagihan]) }}'; cancelModalOpen = true" class="ml-3 text-error-600 hover:text-error-700">
                                                    Batalkan
                                                </button>
                                            @endif
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

        <!-- Cancel Modal -->
        <div x-show="cancelModalOpen" style="display: none;" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div x-show="cancelModalOpen" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div x-show="cancelModalOpen" @click.away="cancelModalOpen = false" x-transition
                         class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <form :action="cancelUrl" method="POST">
                            @csrf
                            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-error-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-error-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Batalkan Tagihan</h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-500">Anda yakin ingin membatalkan tagihan ini? Tindakan ini tidak dapat diurungkan.</p>
                                            
                                            <div class="mt-4">
                                                <label for="cancel_reason" class="block text-sm font-medium text-gray-700">Alasan Pembatalan <span class="text-error-500">*</span></label>
                                                <input type="text" name="cancel_reason" id="cancel_reason" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 py-2 px-3 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button type="submit" class="inline-flex w-full justify-center rounded-md bg-error-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-error-500 sm:ml-3 sm:w-auto">Batalkan Tagihan</button>
                                <button type="button" @click="cancelModalOpen = false" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Kembali</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
