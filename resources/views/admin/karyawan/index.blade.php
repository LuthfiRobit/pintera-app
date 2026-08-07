<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="karyawanIndexSPA()">
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
                <h1 class="font-display text-lg font-bold text-gray-900">Karyawan & Tenaga Ahli</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data penempatan dan akun login karyawan (mis. Konselor BK dan Psikolog Pool Yayasan).</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Karyawan</b>
            </p>
        </div>

        {{-- Compact Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Karyawan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Terdaftar</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Aktif & Siap Bertugas</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countAktif"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Akun Aktif</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="work_outline" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Pool Yayasan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countPool"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Lintas Lembaga</span>
            </div>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                @can('karyawan.create')
                <x-tooltip text="Tambah data akun dan profil karyawan baru">
                    <x-link-button href="{{ route('admin.karyawan.create') }}" class="inline-flex w-full items-center justify-center gap-1.5 sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Karyawan
                    </x-link-button>
                </x-tooltip>
                @endcan
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input (Mobile: 100%, Desktop: 4 cols) --}}
                <div class="lg:col-span-4">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Karyawan</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="searchQuery" @input="currentPage = 1" type="text" placeholder="Cari nama karyawan atau spesialisasi..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Filter Tabs (Mobile: flex wrap/scroll, Desktop: 8 cols) --}}
                <div class="lg:col-span-8">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Filter Penempatan & Status</label>
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                        <button @click="activeFilter = 'semua'; currentPage = 1" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Semua</span>
                            <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                        </button>
                        <button @click="activeFilter = 'dedicated'; currentPage = 1" type="button" :class="activeFilter === 'dedicated' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Dedicated Lembaga</span>
                            <span :class="activeFilter === 'dedicated' ? 'bg-blue-100/80 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countDedicated"></span>
                        </button>
                        <button @click="activeFilter = 'pool'; currentPage = 1" type="button" :class="activeFilter === 'pool' ? 'bg-indigo-50 font-semibold text-indigo-700 border-indigo-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Pool Yayasan</span>
                            <span :class="activeFilter === 'pool' ? 'bg-indigo-100/80 text-indigo-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countPool"></span>
                        </button>
                        <button @click="activeFilter = 'aktif'; currentPage = 1" type="button" :class="activeFilter === 'aktif' ? 'bg-green-50 font-semibold text-green-700 border-green-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Aktif</span>
                            <span :class="activeFilter === 'aktif' ? 'bg-green-100/80 text-green-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countAktif"></span>
                        </button>
                        <button @click="activeFilter = 'non_aktif'; currentPage = 1" type="button" :class="activeFilter === 'non_aktif' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-3.5 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                            <span>Non-Aktif</span>
                            <span :class="activeFilter === 'non_aktif' ? 'bg-amber-100/80 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countNonAktif"></span>
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
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Karyawan</p>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600" x-text="totalFiltered + ' Data'"></span>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-500">Tampilkan:</label>
                    <select x-model.number="perPage" @change="currentPage = 1" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        <option value="5">5 / hal</option>
                        <option value="10">10 / hal</option>
                        <option value="20">20 / hal</option>
                        <option value="50">50 / hal</option>
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                            <th class="px-5 py-3">Nama Karyawan</th>
                            <th class="px-5 py-3">Jenis / Peran</th>
                            <th class="px-5 py-3">Penempatan</th>
                            <th class="px-5 py-3">Kapasitas Kasus</th>
                            <th class="px-5 py-3">Status Akun</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="paginatedItems.length === 0">
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="group" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Karyawan</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400" x-text="searchQuery || activeFilter !== 'semua' ? 'Tidak ada data karyawan yang cocok dengan filter atau pencarian Anda.' : 'Tambahkan data karyawan dan tenaga ahli pertama di lembaga ini.'"></p>
                                </td>
                            </tr>
                        </template>
                        <template x-for="(item, index) in paginatedItems" :key="item.id">
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        @can('karyawan.edit')
                                        <a :href="item.edit_url" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit Karyawan
                                        </a>
                                        <button @click="toggleStatus(item); $el.closest('[x-data]').__x.$data.open = false;" type="button" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                            <span x-text="'Jadikan ' + item.next_status_label"></span>
                                        </button>
                                        @endcan
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" x-text="item.jenis_nama"></span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span :class="item.is_pool ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'bg-gray-100 text-gray-700 font-medium'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs" x-text="item.lembaga_nama"></span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span :class="item.has_kapasitas ? 'bg-blue-50 text-blue-700 font-bold px-2.5 py-1 rounded-full text-xs' : 'text-gray-400'" x-text="item.kapasitas_label"></span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span :class="item.status_aktif === 'aktif' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200'" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" x-text="item.is_active_label"></span>
                                </td>
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
        $spaItems = $karyawanList->map(function ($k) {
            return [
                'id' => $k->id,
                'nama' => $k->nama,
                'jenis_nama' => $k->jenisKaryawan?->nama ?? '-',
                'lembaga_nama' => $k->lembaga?->nama ?? 'Pool Yayasan',
                'is_pool' => $k->lembaga_id === null,
                'has_kapasitas' => !empty($k->kapasitas_kasus_aktif),
                'kapasitas_label' => $k->kapasitas_kasus_aktif ? $k->kapasitas_kasus_aktif . ' Kasus Maks' : '-',
                'status_aktif' => $k->status_aktif,
                'is_active_label' => $k->status_aktif === 'aktif' ? 'Aktif' : 'Non-aktif',
                'edit_url' => route('admin.karyawan.edit', $k),
                'status_url' => route('admin.karyawan.update-status', $k),
                'next_status' => $k->status_aktif === 'aktif' ? 'non_aktif' : 'aktif',
                'next_status_label' => $k->status_aktif === 'aktif' ? 'Non-aktif' : 'Aktif',
            ];
        })->values();
    @endphp

    <script>
    function karyawanIndexSPA() {
        return {
            items: @json($spaItems),
            activeFilter: 'semua',
            searchQuery: '',
            perPage: 10,
            currentPage: 1,
            
            get countAktif() {
                return this.items.filter(i => i.status_aktif === 'aktif').length;
            },
            
            get countNonAktif() {
                return this.items.filter(i => i.status_aktif !== 'aktif').length;
            },

            get countPool() {
                return this.items.filter(i => i.is_pool).length;
            },

            get countDedicated() {
                return this.items.filter(i => !i.is_pool).length;
            },

            get filteredItems() {
                let res = this.items;
                if (this.activeFilter === 'dedicated') {
                    res = res.filter(i => !i.is_pool);
                } else if (this.activeFilter === 'pool') {
                    res = res.filter(i => i.is_pool);
                } else if (this.activeFilter === 'aktif') {
                    res = res.filter(i => i.status_aktif === 'aktif');
                } else if (this.activeFilter === 'non_aktif') {
                    res = res.filter(i => i.status_aktif !== 'aktif');
                }
                if (this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase();
                    res = res.filter(i => i.nama.toLowerCase().includes(q) || i.jenis_nama.toLowerCase().includes(q) || i.lembaga_nama.toLowerCase().includes(q));
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
            },

            async toggleStatus(item) {
                const confirmed = await (typeof confirmDialog === 'function' ? 
                    confirmDialog('Ubah Status Akun?', `Ubah status akun "${item.nama}" menjadi "${item.next_status_label}"?`, { confirmLabel: 'Ya, Ubah' }) : 
                    Promise.resolve(confirm(`Ubah status akun "${item.nama}" menjadi "${item.next_status_label}"?`)));
                    
                if (!confirmed) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = item.status_url;

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'PATCH';

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status_aktif';
                statusInput.value = item.next_status;

                form.appendChild(csrf);
                form.appendChild(method);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
    </script>
</x-app-layout>
