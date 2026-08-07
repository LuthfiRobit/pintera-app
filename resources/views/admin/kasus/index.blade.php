<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="kasusTriaseIndexSPA()">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Triase Kasus Pendampingan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Evaluasi awal dan penugasan konselor ahli untuk pengajuan kasus baru.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Triase Kasus</b>
            </p>
        </div>

        {{-- Compact Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="pending_actions" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Triase</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-amber-600 font-semibold">Perlu Tindakan</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="sync" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-blue-600">Dalam Proses</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalProses ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Aktif Ditangani</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="folder_open" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Kasus</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalSemua ?? $kasusList->count() }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Akumulasi Laporan</span>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input --}}
                <div class="lg:col-span-4">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Kasus Triase</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="searchQuery" @input="currentPage = 1" type="text" placeholder="Cari nama siswa atau kategori..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Filter Tabs --}}
                <div class="lg:col-span-8">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Filter Kategori Masalah</label>
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                        <button @click="activeFilter = 'semua'; currentPage = 1" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Semua Menunggu</span>
                            <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                        </button>
                        <button @click="activeFilter = 'akademik'; currentPage = 1" type="button" :class="activeFilter === 'akademik' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Akademik</span>
                            <span :class="activeFilter === 'akademik' ? 'bg-blue-100/80 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countAkademik"></span>
                        </button>
                        <button @click="activeFilter = 'perilaku'; currentPage = 1" type="button" :class="activeFilter === 'perilaku' ? 'bg-purple-50 font-semibold text-purple-700 border-purple-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Perilaku</span>
                            <span :class="activeFilter === 'perilaku' ? 'bg-purple-100/80 text-purple-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countPerilaku"></span>
                        </button>
                        <button @click="activeFilter = 'sosial'; currentPage = 1" type="button" :class="activeFilter === 'sosial' ? 'bg-teal-50 font-semibold text-teal-700 border-teal-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Sosial & Pribadi</span>
                            <span :class="activeFilter === 'sosial' ? 'bg-teal-100/80 text-teal-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countSosial"></span>
                        </button>
                        <button @click="activeFilter = 'lainnya'; currentPage = 1" type="button" :class="activeFilter === 'lainnya' ? 'bg-slate-100 font-semibold text-slate-700 border-slate-300 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Lainnya</span>
                            <span :class="activeFilter === 'lainnya' ? 'bg-slate-200 text-slate-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countLainnya"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table Header --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Kasus Diajukan</p>
                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700" x-text="totalFiltered + ' Data Menunggu'"></span>
                </div>

                <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
                    <span class="shrink-0">Tampilkan</span>
                    <select x-model.number="perPage" @change="currentPage = 1" class="rounded-lg border-gray-200 bg-gray-50 py-1 pl-2.5 pr-8 text-xs font-semibold text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <span class="shrink-0">per halaman</span>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3 w-36">Aksi</th>
                            <th class="px-5 py-3">Siswa</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Diajukan Pada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="paginatedItems.length === 0">
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="assignment_turned_in" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700" x-text="searchQuery || activeFilter !== 'semua' ? 'Tidak ada kasus yang cocok dengan filter atau pencarian Anda.' : 'Tidak Ada Kasus yang Menunggu Triase'"></p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Seluruh pengajuan kasus saat ini sudah dipelajari dan ditangani dengan konselor yang sesuai.</p>
                                </td>
                            </tr>
                        </template>
                        <template x-for="(item, index) in paginatedItems" :key="item.id">
                            <tr :class="item.urgensi_tinggi ? 'bg-amber-50/70 border-l-4 border-amber-400 hover:bg-amber-100/70 transition' : 'transition hover:bg-gray-50'">
                                <td class="sticky left-0 z-10 px-5 py-3" :class="item.urgensi_tinggi ? 'bg-amber-50/70' : 'bg-white'">
                                    <x-table-actions>
                                        <a :href="item.triase_url" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm font-medium leading-5 text-brand-600 transition duration-150 ease-in-out hover:bg-brand-50/50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="assignment" class="h-4 w-4 text-brand-500" />
                                            Triase & Tugaskan
                                        </a>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-gray-900" x-text="item.siswa_nama"></p>
                                    <span x-show="item.urgensi_tinggi" style="display: none;" class="inline-block mt-0.5 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">Urgensi Tinggi</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700" x-text="item.kategori"></span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span :class="{
                                        'bg-amber-50 text-amber-700 border-amber-200': item.badge_tone === 'amber',
                                        'bg-blue-50 text-blue-700 border-blue-200': item.badge_tone === 'blue',
                                        'bg-green-50 text-green-700 border-green-200': item.badge_tone === 'green',
                                        'bg-red-50 text-red-700 border-red-200': item.badge_tone === 'red',
                                        'bg-gray-100 text-gray-700 border-gray-300': !item.badge_tone || item.badge_tone === 'slate'
                                    }" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold" x-text="item.status_label"></span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-500 font-mono text-xs" x-text="item.tanggal"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Reactive Pagination Footer --}}
            <div class="border-t border-gray-200 px-5 py-4" x-show="totalFiltered > 0" style="display: none;">
                <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
                    <span>
                        Menampilkan <b class="font-semibold text-gray-700" x-text="startIndex"></b>&ndash;<b class="font-semibold text-gray-700" x-text="endIndex"></b> dari <b class="font-semibold text-gray-700" x-text="totalFiltered"></b> entri
                    </span>

                    <div class="flex items-center gap-1.5" x-show="totalPages > 1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" type="button" :disabled="currentPage === 1" :class="currentPage === 1 ? 'border-gray-200 text-gray-300 cursor-not-allowed' : 'border-gray-200 text-gray-500 transition hover:bg-gray-50'" class="flex h-8 w-8 items-center justify-center rounded-lg border bg-white">
                            <x-icon name="expand_more" class="h-3.5 w-3.5 rotate-90" />
                        </button>

                        <template x-for="page in totalPages" :key="page">
                            <button @click="currentPage = page" type="button" :class="currentPage === page ? 'bg-brand-500 font-semibold text-white border-transparent' : 'border-gray-200 text-gray-600 transition hover:bg-gray-50 bg-white'" class="flex h-8 w-8 items-center justify-center rounded-lg border text-xs" x-text="page"></button>
                        </template>

                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" type="button" :disabled="currentPage === totalPages" :class="currentPage === totalPages ? 'border-gray-200 text-gray-300 cursor-not-allowed' : 'border-gray-200 text-gray-500 transition hover:bg-gray-50'" class="flex h-8 w-8 items-center justify-center rounded-lg border bg-white">
                            <x-icon name="expand_more" class="h-3.5 w-3.5 -rotate-90" />
                        </button>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    @php
        $spaItems = $kasusList->map(function ($item) {
            $kat = strtolower($item->kategori_masalah ?? '');
            $filterKat = 'lainnya';
            if (str_contains($kat, 'akad')) {
                $filterKat = 'akademik';
            } elseif (str_contains($kat, 'rilak') || str_contains($kat, 'laku') || str_contains($kat, 'disip')) {
                $filterKat = 'perilaku';
            } elseif (str_contains($kat, 'sosi') || str_contains($kat, 'prib') || str_contains($kat, 'emos') || str_contains($kat, 'teman')) {
                $filterKat = 'sosial';
            }
            return [
                'id' => $item->id,
                'siswa_nama' => $item->siswa->nama_lengkap ?? '-',
                'kategori' => $item->kategori_masalah ?? '-',
                'filter_kategori' => $filterKat,
                'urgensi_tinggi' => ($item->tingkat_urgensi === 'tinggi'),
                'badge_tone' => $item->status->badgeTone(),
                'status_label' => $item->status->label(),
                'tanggal' => $item->created_at->format('d M Y'),
                'triase_url' => route('admin.kasus.triase', $item),
            ];
        })->values();
    @endphp

    <script>
    function kasusTriaseIndexSPA() {
        return {
            items: @json($spaItems),
            activeFilter: 'semua',
            searchQuery: '',
            perPage: 10,
            currentPage: 1,
            
            get countAkademik() {
                return this.items.filter(i => i.filter_kategori === 'akademik').length;
            },
            
            get countPerilaku() {
                return this.items.filter(i => i.filter_kategori === 'perilaku').length;
            },

            get countSosial() {
                return this.items.filter(i => i.filter_kategori === 'sosial').length;
            },

            get countLainnya() {
                return this.items.filter(i => i.filter_kategori === 'lainnya').length;
            },

            get filteredItems() {
                let res = this.items;
                if (this.activeFilter !== 'semua') {
                    res = res.filter(i => i.filter_kategori === this.activeFilter);
                }
                if (this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase();
                    res = res.filter(i => i.siswa_nama.toLowerCase().includes(q) || i.kategori.toLowerCase().includes(q));
                }
                return res;
            },

            get totalFiltered() {
                return this.filteredItems.length;
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.totalFiltered / this.perPage));
            },

            get paginatedItems() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.filteredItems.slice(start, start + Number(this.perPage));
            },

            get startIndex() {
                return this.totalFiltered === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
            },

            get endIndex() {
                return Math.min(this.currentPage * this.perPage, this.totalFiltered);
            }
        }
    }
    </script>
</x-app-layout>
