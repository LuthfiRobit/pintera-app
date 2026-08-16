<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ selectedProposal: null }">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pencairan Dana Kas Pengadaan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola dan catat realisasi pencairan dana untuk usulan belanja yang telah disetujui.</p>
            </div>
            <p class="text-sm text-gray-500">
                Yayasan <span class="mx-1 text-gray-300">&rsaquo;</span> Pengadaan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pencairan Kas</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="pending_actions" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Siap Dicairkan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalSiapCair ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Menunggu Kas</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Sudah Dicairkan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalSudahCair ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Tercatat</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="payments" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Total Nominal Cair</p>
                        <p class="font-display text-sm font-bold text-gray-900 leading-tight">Rp {{ number_format($totalNominalCair ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', '')),
                    status: @js(request('status', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.pengadaan.disbursement.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Pencairan
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pengajuan</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Nomor proposal, judul usulan, nama unit..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status Pencairan</label>
                        <select x-model="filters.status" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Status</option>
                            <option value="approved">Siap Dicairkan</option>
                            <option value="disbursed">Sudah Dicairkan</option>
                        </select>
                    </div>
                </div>
            </div>

            <div x-ref="tableContainer">
                @include('portals.yayasan.pengadaan.disbursement._daftar')
            </div>
        </div>

        {{-- Modal Pencairan Kas --}}
        <x-modal name="modal-pencairan-kas" maxWidth="md" focusable>
            <form
                :action="selectedProposal ? `/admin/pengadaan/disbursement/${selectedProposal.id}` : '#'"
                method="POST"
                enctype="multipart/form-data"
                class="p-6 space-y-4"
            >
                @csrf

                <div class="border-b border-gray-100 pb-3">
                    <h3 class="font-display text-base font-bold text-gray-900">Catat Realisasi Pencairan Dana Kas</h3>
                    <p class="text-xs text-gray-500 mt-0.5" x-text="selectedProposal ? `${selectedProposal.nomor_pengajuan} - ${selectedProposal.judul_pengajuan}` : ''"></p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nominal Dana yang Dicairkan (Rp) <span class="text-error-600">*</span></label>
                    <input type="number" name="nominal_pencairan" :value="selectedProposal ? selectedProposal.total_estimasi : 0" required min="1" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('nominal_pencairan')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Pencairan <span class="text-error-600">*</span></label>
                    <input type="date" name="tanggal_pencairan" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('tanggal_pencairan')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan / Rekening Kas</label>
                    <textarea name="catatan_pencairan" rows="2" placeholder="Contoh: Transfer via Rekening BSI Yayasan ke Bendahara SMP" class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    <x-input-error :messages="$errors->get('catatan_pencairan')" class="mt-1" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Bukti Transfer / Tanda Terima</label>
                    <input type="file" name="bukti_transfer" accept="image/*,application/pdf" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100">
                    <x-input-error :messages="$errors->get('bukti_transfer')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <x-secondary-button type="button" @click="$dispatch('close')">
                        Batal
                    </x-secondary-button>

                    <x-primary-button type="submit">
                        Simpan Pencairan
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
