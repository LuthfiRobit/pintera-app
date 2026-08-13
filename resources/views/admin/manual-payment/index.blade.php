<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Verifikasi Transfer Manual</h1>
                <p class="mt-0.5 text-xs text-gray-500">Setujui atau tolak bukti transfer manual yang diajukan orang tua.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Verifikasi Transfer Manual</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="hourglass_empty" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Verifikasi</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalMenunggu }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="payments" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Total Nominal Menunggu</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">Rp{{ number_format($totalNominalMenunggu, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="space-y-4"
            x-data="manualPaymentFilter({
                search: @js(request('search', '')),
                dari: @js(request('dari', '')),
                sampai: @js(request('sampai', '')),
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.manual-payment.index')),
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Nama Siswa</label>
                        <div class="flex h-[42px] items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-50 px-3.5">
                            <x-icon name="search" class="h-[14px] w-[14px] shrink-0 text-gray-400" />
                            <input type="text" x-model="search" @input.debounce.400ms="muatUlangDaftar()" placeholder="Nama siswa..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Dari Tanggal Transfer</label>
                        <input type="date" x-model="dari" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Sampai Tanggal Transfer</label>
                        <input type="date" x-model="sampai" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 text-sm">
                    </div>
                </div>
            </div>

            <div x-ref="daftarManualPayment">
                @include('admin.manual-payment._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
