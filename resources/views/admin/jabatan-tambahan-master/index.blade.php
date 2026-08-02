<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Master Jabatan Tambahan') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div x-data="jabatanMasterSPA()" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                <span>Master Jabatan Tambahan</span>
                <span class="inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 rounded-full" x-text="items.length + ' Total'"></span>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Kelola referensi jabatan struktural dan fungsional yang dapat diemban oleh Guru di seluruh lembaga.
            </p>
        </div>
        <div>
            @can('jabatan-tambahan-master.create')
            <button @click="openModal(false)" type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-lg shadow-sm hover:shadow transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900" title="Tambah data referensi jabatan baru">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Jabatan</span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Toast / Alert Notifikasi -->
    <div x-show="toast.show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2" class="fixed bottom-5 right-5 z-50 max-w-md" style="display: none;">
        <div :class="{
            'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-900/80 dark:text-emerald-200 dark:border-emerald-800': toast.type === 'success',
            'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-900/80 dark:text-rose-200 dark:border-rose-800': toast.type === 'error'
        }" class="p-4 rounded-xl border shadow-lg backdrop-blur-sm flex items-start gap-3">
            <template x-if="toast.type === 'success'">
                <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </template>
            <template x-if="toast.type === 'error'">
                <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </template>
            <div class="flex-1 text-sm font-medium" x-text="toast.message"></div>
            <button @click="toast.show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    <!-- Live Statistic Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Jabatan</p>
                <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" x-text="items.length"></h3>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-indigo-500 dark:text-indigo-400 uppercase tracking-wider">Struktural</p>
                <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" x-text="countStruktural"></h3>
            </div>
            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-500 dark:text-amber-400 uppercase tracking-wider">Fungsional</p>
                <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1" x-text="countFungsional"></h3>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <!-- Tabs & Search Toolbar -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center overflow-x-auto scrollbar-none gap-2 pb-1 md:pb-0">
                <button @click="activeFilter = 'semua'" :class="activeFilter === 'semua' ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white font-semibold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50'" class="px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap flex items-center gap-2">
                    <span>Semua Jabatan</span>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200 dark:bg-gray-600" x-text="items.length"></span>
                </button>
                <button @click="activeFilter = 'struktural'" :class="activeFilter === 'struktural' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 font-semibold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50'" class="px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap flex items-center gap-2">
                    <span>Struktural</span>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-800/60 text-indigo-700 dark:text-indigo-300" x-text="countStruktural"></span>
                </button>
                <button @click="activeFilter = 'fungsional'" :class="activeFilter === 'fungsional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300 font-semibold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50'" class="px-4 py-2 rounded-lg text-sm transition-colors whitespace-nowrap flex items-center gap-2">
                    <span>Fungsional</span>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 dark:bg-amber-800/60 text-amber-700 dark:text-amber-300" x-text="countFungsional"></span>
                </button>
            </div>
            <div class="relative w-full md:w-64">
                <input x-model="searchQuery" type="text" placeholder="Cari nama jabatan..." class="w-full pl-9 pr-4 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 dark:text-white placeholder-gray-400 transition-all">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto scrollbar-thin">
            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                <thead class="text-xs text-gray-700 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th scope="col" class="py-3 px-6 w-16 text-center">No</th>
                        <th scope="col" class="py-3 px-6">Nama Jabatan</th>
                        <th scope="col" class="py-3 px-6">Kelompok</th>
                        <th scope="col" class="py-3 px-6 text-center">Jumlah Guru</th>
                        <th scope="col" class="py-3 px-6 text-center w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-if="filteredItems.length === 0">
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400 dark:text-gray-500">
                                <p class="text-base font-medium">Tidak ada data jabatan yang sesuai filter atau pencarian</p>
                            </td>
                        </tr>
                    </template>
                    <template x-for="(item, index) in filteredItems" :key="item.id">
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="py-4 px-6 text-center font-medium text-gray-500 dark:text-gray-400" x-text="index + 1"></td>
                            <td class="py-4 px-6 font-semibold text-gray-900 dark:text-white" x-text="item.nama"></td>
                            <td class="py-4 px-6">
                                <span :class="item.kelompok === 'struktural' ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 dark:border-indigo-800' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-800'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border capitalize" x-text="item.kelompok"></span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <span :class="item.guru_count > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200 font-semibold' : 'text-gray-500 dark:text-gray-400'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" x-text="item.guru_count + ' Guru'"></span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @can('jabatan-tambahan-master.edit')
                                    <button @click="openModal(true, item)" type="button" class="p-1.5 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 transition-colors" title="Edit jabatan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    @endcan
                                    @can('jabatan-tambahan-master.delete')
                                    <button @click="deleteItem(item)" type="button" class="p-1.5 text-gray-600 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400 transition-colors" title="Hapus jabatan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form (Tambah / Edit) -->
    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;">
        <div @click.away="closeModal()" class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700 shadow-xl relative text-left">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white" x-text="isEditing ? 'Edit Jabatan Tambahan' : 'Tambah Jabatan Tambahan'"></h3>
                <button @click="closeModal()" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form @submit.prevent="submitForm()" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Jabatan <span class="text-rose-500">*</span></label>
                    <input x-model="form.nama" type="text" required placeholder="Contoh: Pembina Ekstrakurikuler" class="w-full px-3.5 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm">
                    <template x-if="errors.nama">
                        <p class="text-xs text-rose-500 mt-1" x-text="errors.nama[0]"></p>
                    </template>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kelompok Jabatan <span class="text-rose-500">*</span></label>
                    <select x-model="form.kelompok" required class="w-full px-3.5 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">-- Pilih Kelompok --</option>
                        <option value="struktural">Struktural</option>
                        <option value="fungsional">Fungsional</option>
                    </select>
                    <template x-if="errors.kelompok">
                        <p class="text-xs text-rose-500 mt-1" x-text="errors.kelompok[0]"></p>
                    </template>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 mt-6">
                    <button @click="closeModal()" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Batal</button>
                    <button type="submit" :disabled="loading" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg shadow transition-colors flex items-center gap-2">
                        <span x-text="loading ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Rilis Jabatan')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
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
        toast: {
            show: false,
            message: '',
            type: 'success'
        },
        
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
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            setTimeout(() => { this.toast.show = false; }, 4000);
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
                    // sort by kelompok, then nama
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
