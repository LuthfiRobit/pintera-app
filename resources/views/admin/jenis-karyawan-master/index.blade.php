{{-- resources/views/admin/jenis-karyawan-master/index.blade.php --}}
<x-app-layout>
    <div
        x-data="{
            items: @js($jenisList),
            showForm: false,
            editing: null,
            nama: '',
            error: '',
            csrfToken() { return document.querySelector('meta[name=csrf-token]').content; },
            openCreate() { this.editing = null; this.nama = ''; this.error = ''; this.showForm = true; this.$dispatch('open-modal', 'jenis-karyawan-form'); },
            openEdit(item) { this.editing = item; this.nama = item.nama; this.error = ''; this.showForm = true; this.$dispatch('open-modal', 'jenis-karyawan-form'); },
            closeForm() { this.showForm = false; this.$dispatch('close-modal', 'jenis-karyawan-form'); },
            async submit() {
                const url = this.editing ? `{{ url('admin/jenis-karyawan-master') }}/${this.editing.id}` : @js(route('admin.jenis-karyawan-master.store'));
                const method = this.editing ? 'PUT' : 'POST';
                const response = await fetch(url, {
                    method,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                    body: JSON.stringify({ nama: this.nama }),
                });
                const json = await response.json();
                if (!response.ok) { this.error = json.message ?? 'Gagal menyimpan.'; return; }
                if (this.editing) {
                    this.items = this.items.map(i => i.id === json.item.id ? json.item : i);
                } else {
                    this.items.push(json.item);
                }
                this.closeForm();
            },
            async remove(item) {
                const confirmed = await confirmDialog('Hapus Jenis Karyawan?', `Hapus '${item.nama}'?`, { confirmLabel: 'Ya, Hapus' });
                if (!confirmed) return;
                const response = await fetch(`{{ url('admin/jenis-karyawan-master') }}/${item.id}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                });
                const json = await response.json();
                if (!response.ok) { $store.toast.push('error', json.message ?? 'Gagal menghapus.'); return; }
                this.items = this.items.filter(i => i.id !== item.id);
                $store.toast.push('success', json.message);
            },
        }"
        class="mx-auto max-w-4xl space-y-4"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Jenis Karyawan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola daftar jenis karyawan (mis. Psikolog, Konselor BK).</p>
            </div>
            <x-primary-button type="button" @click="openCreate()">+ Tambah Jenis Karyawan</x-primary-button>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Karyawan</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5" x-text="items.length + ' Data'"></x-badge>
            </div>
            <ul class="divide-y divide-gray-100">
                <template x-for="item in items" :key="item.id">
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="item.nama"></p>
                            <p class="text-xs text-gray-400" x-text="item.karyawan_count + ' karyawan'"></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="text-xs font-semibold text-brand-600 hover:text-brand-700" @click="openEdit(item)">Edit</button>
                            <button type="button" class="text-xs font-semibold text-error-600 hover:text-error-700" @click="remove(item)">Hapus</button>
                        </div>
                    </li>
                </template>
                <li x-show="items.length === 0" class="px-5 py-12 text-center text-gray-400 text-sm">Belum ada jenis karyawan.</li>
            </ul>
        </div>

        <x-modal name="jenis-karyawan-form" @close="closeForm()">
            <div class="p-5 space-y-4">
                <p class="font-display text-sm font-bold text-gray-900" x-text="editing ? 'Edit Jenis Karyawan' : 'Tambah Jenis Karyawan'"></p>
                <div>
                    <x-input-label value="Nama *" />
                    <input type="text" x-model="nama" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1.5 text-xs text-error-600" x-show="error" x-text="error"></p>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button type="button" @click="submit()">Simpan</x-primary-button>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeForm()">Batal</button>
                </div>
            </div>
        </x-modal>
    </div>
</x-app-layout>
