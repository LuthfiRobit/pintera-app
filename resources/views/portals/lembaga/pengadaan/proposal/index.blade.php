<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
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
                <h1 class="font-display text-lg font-bold text-gray-900">Pengadaan Sarana & Prasarana</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola proposal usulan belanja fasilitas, pelacakan alur persetujuan, dan LPJ realisasi.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengadaan</b>
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="assignment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Pengajuan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Usulan</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                        <x-icon name="schedule" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-purple-600">Sedang Direview</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['in_review'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-purple-400">Proses</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="payments" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Dana Cair (Isi LPJ)</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['disbursed'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-indigo-400">Belanja</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Selesai (Inventaris)</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ number_format($stats['completed'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-emerald-400">Tuntas</span>
            </div>
        </div>

        {{-- Interactive Filter & AJAX Table Container --}}
        <div
            class="space-y-4"
            x-data="dataTableFilter({
                filters: {
                    search: @js(request('search', '')),
                    status: @js(request('status', '')),
                    urgensi: @js(request('urgensi', ''))
                },
                perPage: @js($perPage ?? 20),
                indexUrlBase: @js(route('admin.pengadaan.proposal.index'))
            })"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data Usulan
                    </p>
                    <div class="flex items-center gap-2">
                        @can('pengadaan.proposal.create')
                            <x-link-button href="{{ route('admin.pengadaan.proposal.create') }}">
                                <span class="text-base leading-none">+</span> Buat Pengajuan Baru
                            </x-link-button>
                        @endcan
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {{-- Search --}}
                    <div>
                        <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pengajuan</label>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                            <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                            <input
                                type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
                                placeholder="Nomor pengajuan, judul..."
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                            >
                        </div>
                    </div>

                    {{-- Filter Status --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Status Tahapan</label>
                        <select x-model="filters.status" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Status</option>
                            <option value="draft">Draft Usulan</option>
                            <option value="submitted">Diajukan</option>
                            <option value="in_review">Sedang Direview</option>
                            <option value="revision_required">Perlu Revisi</option>
                            <option value="approved">Disetujui</option>
                            <option value="disbursed">Dana Cair</option>
                            <option value="completed">Selesai (LPJ Terverifikasi)</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>

                    {{-- Filter Urgensi --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tingkat Urgensi</label>
                        <select x-model="filters.urgensi" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Tingkat</option>
                            <option value="biasa">Biasa / Rutin</option>
                            <option value="mendesak">Mendesak</option>
                            <option value="kritis">Kritis / Darurat</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Table Partial Wrapper --}}
            <div id="wadah-daftar-tabel" class="relative">
                @include('portals.lembaga.pengadaan.proposal._daftar', ['proposals' => $proposals])
            </div>
        </div>
    </div>
</x-app-layout>
