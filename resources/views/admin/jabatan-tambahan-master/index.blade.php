<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="jabatanMasterSPA()">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Master Jabatan Tambahan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola referensi jabatan struktural dan fungsional yang dapat diemban oleh Guru di seluruh lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jabatan Tambahan</b>
            </p>
        </div>

        {{-- Compact Statistic Cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="badge" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Jabatan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Semua Data</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="apartment" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Struktural</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countStruktural"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Kepemimpinan</span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="checklist" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Fungsional</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="countFungsional"></p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Pembina / Koor</span>
            </div>
        </div>

        {{-- Filter Card (Mobile-first responsive architecture matching Guru/Kelas standards) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                @can('jabatan-tambahan-master.create')
                <x-tooltip text="Tambah referensi jabatan struktural atau fungsional baru">
                    <button @click="openModal(false)" type="button" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Jabatan
                    </button>
                </x-tooltip>
                @endcan
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input (Mobile: 100%, Desktop: 5 cols) --}}
                <div class="lg:col-span-5">
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Jabatan</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="searchQuery" @input="currentPage = 1" type="text" placeholder="Cari berdasarkan nama jabatan..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Kelompok Filter Tabs (Mobile: flex wrap/scroll, Desktop: 7 cols) --}}
                <div class="lg:col-span-7">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Kelompok Jabatan</label>
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                        <button @click="activeFilter = 'semua'; currentPage = 1" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-4 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-2">
                            <span>Semua</span>
                            <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                        </button>
                        <button @click="activeFilter = 'struktural'; currentPage = 1" type="button" :class="activeFilter === 'struktural' ? 'bg-indigo-50 font-semibold text-indigo-700 border-indigo-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-4 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-2">
                            <span>Struktural</span>
                            <span :class="activeFilter === 'struktural' ? 'bg-indigo-100/80 text-indigo-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countStruktural"></span>
                        </button>
                        <button @click="activeFilter = 'fungsional'; currentPage = 1" type="button" :class="activeFilter === 'fungsional' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-gray-900 border-gray-200'" class="flex-1 sm:flex-none justify-center px-4 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-2">
                            <span>Fungsional</span>
                            <span :class="activeFilter === 'fungsional' ? 'bg-amber-100/80 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countFungsional"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Clean, Uncluttered Table Header --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jabatan Tambahan</p>
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
                            <th class="px-5 py-3">Nama Jabatan</th>
                            <th class="px-5 py-3">Kelompok</th>
                            <th class="px-5 py-3">Jumlah Guru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="paginatedItems.length === 0">
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="badge" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Jabatan</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400" x-text="searchQuery || activeFilter !== 'semua' ? 'Tidak ada data yang cocok dengan filter atau pencarian Anda.' : 'Tambahkan data referensi jabatan pertama untuk lembaga ini.'"></p>
                                </td>
                            </tr>
                        </template>
                        <template x-for="(item, index) in paginatedItems" :key="item.id">
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        @can('jabatan-tambahan-master.edit')
                                        <button @click="openModal(true, item); $el.closest('[x-data]').__x.$data.open = false;" type="button" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit Jabatan
                                        </button>
                                        @endcan
                                        @can('jabatan-tambahan-master.delete')
                                        <button @click="deleteItem(item); $el.closest('[x-data]').__x.$data.open = false;" type="button" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50/50 focus:bg-error-50/50 focus:outline-none">
                                            <x-icon name="cancel" class="h-4 w-4 text-error-500" />
                                            Hapus Jabatan
                                        </button>
                                        @endcan
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span :class="item.kelompok === 'struktural' ? 'bg-indigo-50 text-indigo-700' : 'bg-warning-50 text-warning-700'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize" x-text="item.kelompok"></span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span :class="item.guru_count > 0 ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" x-text="item.guru_count + ' Guru'"></span>
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

        {{-- Modal Form (Tambah / Edit) --}}
        <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showModal" class="fixed inset-0 transform transition-all" @click="closeModal()"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60"></div>
            </div>

            <div x-show="showModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="isEditing ? 'Edit Jabatan Tambahan' : 'Tambah Jabatan Tambahan'"></h3>
                    <button @click="closeModal()" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <x-icon name="cancel" class="h-5 w-5" />
                    </button>
                </div>
                <form @submit.prevent="submitForm()" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Jabatan <span class="text-error-500">*</span></label>
                        <input x-model="form.nama" type="text" required placeholder="Contoh: Pembina Ekstrakurikuler" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                        <template x-if="errors.nama">
                            <p class="mt-1 text-xs text-error-600" x-text="errors.nama[0]"></p>
                        </template>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kelompok Jabatan <span class="text-error-500">*</span></label>
                        <select x-model="form.kelompok" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">-- Pilih Kelompok --</option>
                            <option value="struktural">Struktural</option>
                            <option value="fungsional">Fungsional</option>
                        </select>
                        <template x-if="errors.kelompok">
                            <p class="mt-1 text-xs text-error-600" x-text="errors.kelompok[0]"></p>
                        </template>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button @click="closeModal()">Batal</x-secondary-button>
                        <x-primary-button x-bind:disabled="loading" type="submit">
                            <span x-text="loading ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Rilis Jabatan')"></span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function jabatanMasterSPA() {
        return {
            items: @json($jabatanList ?? []),
            activeFilter: 'semua',
            searchQuery: '',
            perPage: 10,
            currentPage: 1,
            showModal: false,
            isEditing: false,
            loading: false,
            form: {
                id: null,
                nama: '',
                kelompok: ''
            },
            errors: {},
            
            get countStruktural() {
                return this.items.filter(i => i.kelompok === 'struktural').length;
            },
            
            get countFungsional() {
                return this.items.filter(i => i.kelompok === 'fungsional').length;
            },
            
            get filteredItems() {
                let res = this.items;
                if (this.activeFilter !== 'semua') {
                    res = res.filter(i => i.kelompok === this.activeFilter);
                }
                if (this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase();
                    res = res.filter(i => i.nama.toLowerCase().includes(q));
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

            showToast(message, type = 'success') {
                if (this.$store && this.$store.toast) {
                    this.$store.toast.push(type, message);
                } else {
                    alert(message);
                }
            },

            openModal(editing = false, item = null) {
                this.errors = {};
                this.isEditing = editing;
                if (editing && item) {
                    this.form = { id: item.id, nama: item.nama, kelompok: item.kelompok };
                } else {
                    this.form = { id: null, nama: '', kelompok: 'fungsional' };
                }
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
                this.errors = {};
            },

            async submitForm() {
                this.loading = true;
                this.errors = {};
                const url = this.isEditing ? `/admin/jabatan-tambahan-master/${this.form.id}` : '/admin/jabatan-tambahan-master';
                const method = this.isEditing ? 'PUT' : 'POST';
                
                try {
                    const response = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            nama: this.form.nama,
                            kelompok: this.form.kelompok
                        })
                    });

                    const data = await response.json();

                    if (response.status === 422) {
                        this.errors = data.errors || {};
                        this.showToast('Gagal menyimpan: Periksa kembali isian form Anda.', 'error');
                    } else if (response.ok) {
                        if (this.isEditing) {
                            const idx = this.items.findIndex(i => i.id === this.form.id);
                            if (idx !== -1) this.items[idx] = data.item;
                        } else {
                            this.items.push(data.item);
                        }
                        this.items.sort((a, b) => a.kelompok.localeCompare(b.kelompok) || a.nama.localeCompare(b.nama));
                        
                        this.showToast(data.message || 'Berhasil disimpan', 'success');
                        this.closeModal();
                    } else {
                        this.showToast(data.message || 'Terjadi kesalahan pada sistem.', 'error');
                    }
                } catch (e) {
                    this.showToast('Gagal menghubungi server.', 'error');
                } finally {
                    this.loading = false;
                }
            },

            async deleteItem(item) {
                if (item.guru_count > 0) {
                    this.showToast(`Jabatan ini sedang disandang oleh ${item.guru_count} Guru dan tidak boleh dihapus.`, 'error');
                    return;
                }
                if (!confirm(`Yakin ingin menghapus referensi jabatan "${item.nama}" secara permanen?`)) return;

                try {
                    const response = await fetch(`/admin/jabatan-tambahan-master/${item.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.items = this.items.filter(i => i.id !== item.id);
                        if (this.currentPage > this.totalPages) {
                            this.currentPage = this.totalPages || 1;
                        }
                        this.showToast(data.message || 'Berhasil dihapus permanen', 'success');
                    } else if (response.status === 422) {
                        this.showToast(data.message || 'Jabatan ini masih terikat dengan guru.', 'error');
                    } else {
                        this.showToast(data.message || 'Gagal menghapus data.', 'error');
                    }
                } catch (e) {
                    this.showToast('Gagal menghubungi server.', 'error');
                }
            }
        }
    }
    </script>
</x-app-layout>
