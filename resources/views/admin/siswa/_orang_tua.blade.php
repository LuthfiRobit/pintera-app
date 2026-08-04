<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
        <x-icon name="groups" class="h-[15px] w-[15px] text-gray-400" />
        Orang Tua/Wali Tertaut
    </p>

    @if ($siswa->orangTua->isNotEmpty())
        <ul class="mb-4 space-y-2">
            @foreach ($siswa->orangTua as $orangTua)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 px-3 py-2">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-semibold text-gray-900">{{ $orangTua->nama_lengkap }}</span>
                        <x-badge tone="slate" class="text-xs">{{ ucfirst($orangTua->pivot->hubungan) }}</x-badge>
                        @if ($orangTua->pivot->is_kontak_utama)
                            <x-badge tone="green" class="text-xs">Kontak Utama</x-badge>
                        @endif
                        <span class="text-xs text-gray-400">{{ $orangTua->no_hp }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @can('orang-tua.edit')
                            @unless ($orangTua->pivot->is_kontak_utama)
                                <form method="POST" action="{{ route('admin.siswa.orang-tua.kontak-utama', [$siswa, $orangTua]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Jadikan Kontak Utama</button>
                                </form>
                            @endunless
                            <form
                                method="POST"
                                action="{{ route('admin.siswa.orang-tua.destroy', [$siswa, $orangTua]) }}"
                                x-data
                                @submit.prevent="confirmDialog('Hapus Tautan?', @js('Hapus tautan dengan \"' . $orangTua->nama_lengkap . '\"? Profil orang tua tidak akan terhapus.'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus Tautan</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @can('orang-tua.create')
    <div x-data="orangTuaCari({ searchUrl: @js(route('admin.siswa.orang-tua.cari', $siswa)) })" class="rounded-lg border border-dashed border-gray-200 p-4">
        <p class="mb-2 text-xs font-semibold text-gray-500">Tautkan Orang Tua</p>

        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <x-input-label value="NIK" />
                <input type="text" x-model="nik" maxlength="16" placeholder="16 digit NIK" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="button" @click="cari()" :disabled="searching" class="inline-flex h-[42px] items-center gap-1.5 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50">
                <x-icon name="search" class="h-[13px] w-[13px]" />
                <span x-text="searching ? 'Mencari...' : 'Cari'"></span>
            </button>
        </div>

        <template x-if="searched && found">
            <form method="POST" :action="@js(route('admin.siswa.orang-tua.store', $siswa))" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                @csrf
                <input type="hidden" name="orang_tua_id" :value="orangTua.id">
                <p class="text-sm text-gray-700">Ditemukan: <b x-text="orangTua.nama_lengkap"></b> (<span x-text="orangTua.no_hp"></span>)</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Hubungan *" />
                        <select name="hubungan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="ayah">Ayah</option>
                            <option value="ibu">Ibu</option>
                            <option value="wali">Wali</option>
                        </select>
                    </div>
                    <label class="mt-6 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_kontak_utama" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        Jadikan Kontak Utama
                    </label>
                </div>
                <x-primary-button type="submit">Tautkan</x-primary-button>
            </form>
        </template>

        <template x-if="searched && !found">
            <form method="POST" :action="@js(route('admin.siswa.orang-tua.store', $siswa))" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                @csrf
                <p class="text-sm text-gray-500">NIK belum terdaftar. Lengkapi data untuk membuat profil &amp; akun baru.</p>
                <input type="hidden" name="nik" :value="nik">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nama Lengkap *" />
                        <input type="text" name="nama_lengkap" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="No. HP *" />
                        <input type="text" name="no_hp" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Hubungan *" />
                        <select name="hubungan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="ayah">Ayah</option>
                            <option value="ibu">Ibu</option>
                            <option value="wali">Wali</option>
                        </select>
                    </div>
                    <label class="mt-6 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_kontak_utama" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        Jadikan Kontak Utama
                    </label>
                </div>
                <x-primary-button type="submit">Buat &amp; Tautkan</x-primary-button>
            </form>
        </template>
    </div>
    @endcan
</div>
