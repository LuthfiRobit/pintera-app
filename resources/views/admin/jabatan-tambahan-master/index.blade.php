<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="jabatanMasterSPA()">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-init="$store.toast ? $store.toast.push('success', @js(session('status'))) : null">{{ session('status') }}</div>
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

        {{-- Statistic Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center justify-between">
                    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Total Jabatan</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="badge" class="h-[18px] w-[18px]" />
                    </span>
                </div>
                <p class="mt-2 font-display text-3xl font-bold text-gray-900" x-text="items.length"></p>
                <p class="mt-1 text-xs text-gray-500">Keseluruhan referensi terdaftar</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center justify-between">
                    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-600">Struktural</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="apartment" class="h-[18px] w-[18px]" />
                    </span>
                </div>
                <p class="mt-2 font-display text-3xl font-bold text-gray-900" x-text="countStruktural"></p>
                <p class="mt-1 text-xs text-gray-500">Kelompok kepemimpinan & manajerial</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center justify-between">
                    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-600">Fungsional</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="checklist" class="h-[18px] w-[18px]" />
                    </span>
                </div>
                <p class="mt-2 font-display text-3xl font-bold text-gray-900" x-text="countFungsional"></p>
                <p class="mt-1 text-xs text-gray-500">Kelompok pembina & koordinator</p>
            </div>
        </div>

        {{-- Table Card with Tabs & Search Toolbar --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div class="flex items-center overflow-x-auto scrollbar-none gap-2 pb-1 sm:pb-0">
                    <button @click="activeFilter = 'semua'" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                        <span>Semua</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                    </button>
                    <button @click="activeFilter = 'struktural'" type="button" :class="activeFilter === 'struktural' ? 'bg-indigo-50 font-semibold text-indigo-700 border-indigo-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                        <span>Struktural</span>
                        <span :class="activeFilter === 'struktural' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countStruktural"></span>
                    </button>
                    <button @click="activeFilter = 'fungsional'" type="button" :class="activeFilter === 'fungsional' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-gray-200'" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap flex items-center gap-1.5">
                        <span>Fungsional</span>
                        <span :class="activeFilter === 'fungsional' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countFungsional"></span>
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 w-full sm:w-64">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="searchQuery" type="text" placeholder="Cari nama jabatan..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                    @can('jabatan-tambahan-master.create')
                    <x-tooltip text="Tambah referensi jabatan struktural atau fungsional baru">
                        <button @click="openModal(false)" type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 whitespace-nowrap">
                            <span class="text-base leading-none">+</span> Tambah Jabatan
                        </button>
                    </x-tooltip>
                    @endcan
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
                        <template x-if="filteredItems.length === 0">
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
                        <template x-for="(item, index) in filteredItems" :key="item.id">
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
