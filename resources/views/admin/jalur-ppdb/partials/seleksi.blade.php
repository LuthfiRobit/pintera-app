<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="seleksiList({
        initialItems: @js($jalur->seleksi),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.seleksi.store')),
        deleteUrlTemplate: @js(route('admin.seleksi.destroy', ['seleksi' => '__ID__'])),
        defaultGelombangId: {{ $gelombangList->first()?->id ?? 'null' }},
        defaultJenisTesId: {{ $jenisTesList->first()?->id ?? 'null' }},
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Seleksi &amp; Tes</p>
        <p class="mt-0.5 text-sm text-gray-500">Jadwal tes untuk jalur ini, per gelombang. Boleh dikosongkan jika jalur tidak memakai tes.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada jadwal seleksi.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900" x-text="item.jenis_tes_master.nama"></span>
                    <span class="ml-2 text-xs text-gray-500" x-text="item.gelombang_ppdb.nama + ' · ' + formatJadwal(item.jadwal)"></span>
                    <template x-if="item.kriteria_kelulusan">
                        <p class="mt-0.5 text-xs text-gray-500" x-text="item.kriteria_kelulusan"></p>
                    </template>
                </div>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <x-input-label value="Gelombang" />
                <select x-model="form.gelombang_ppdb_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($gelombangList as $gelombang)
                        <option value="{{ $gelombang->id }}">{{ $gelombang->nama }}</option>
                    @endforeach
                </select>
                <template x-if="errors.gelombang_ppdb_id">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.gelombang_ppdb_id[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Jenis Tes" />
                <select x-model="form.jenis_tes_master_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($jenisTesList as $jenisTes)
                        <option value="{{ $jenisTes->id }}">{{ $jenisTes->nama }}</option>
                    @endforeach
                </select>
                <template x-if="errors.jenis_tes_master_id">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.jenis_tes_master_id[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Jadwal" />
                <x-text-input type="datetime-local" x-model="form.jadwal" class="mt-1.5" />
                <template x-if="errors.jadwal">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.jadwal[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Bobot (%)" />
                <x-text-input type="number" x-model="form.bobot" class="mt-1.5 w-24" />
                <template x-if="errors.bobot">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.bobot[0]"></p>
                </template>
            </div>
        </div>
        <div>
            <x-input-label value="Kriteria Kelulusan (opsional)" />
            <x-text-input type="text" x-model="form.kriteria_kelulusan" class="mt-1.5" />
        </div>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Jadwal Seleksi</x-secondary-button>
    </form>
</div>
