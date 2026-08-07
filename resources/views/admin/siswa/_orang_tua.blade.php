<div x-data="{ openAdd: false }" class="space-y-6">
    {{-- Header & Tombol Tambah --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
        <div>
            <h2 class="font-display text-lg font-bold text-gray-900">Orang Tua/Wali Tertaut</h2>
            <p class="text-sm text-gray-500">Daftar orang tua atau wali yang bertanggung jawab atas siswa ini.</p>
        </div>
        @can('orang-tua.create')
            <button type="button" @click="openAdd = !openAdd" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                <x-icon name="add" class="h-4 w-4" />
                <span x-text="openAdd ? 'Tutup Formulir' : 'Tautkan Orang Tua'">Tautkan Orang Tua</span>
            </button>
        @endcan
    </div>

    {{-- Form Pencarian & Penambahan --}}
    @can('orang-tua.create')
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <div x-data="orangTuaCari({ searchUrl: @js(route('admin.siswa.orang-tua.cari', $siswa)) })">
                <h3 class="mb-4 font-bold text-gray-900">Cari & Tautkan Profil Orang Tua</h3>
                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[200px]">
                        <x-input-label value="NIK (Nomor Induk Kependudukan)" />
                        <input type="text" x-model="nik" maxlength="16" placeholder="16 digit NIK" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <button type="button" @click="cari()" :disabled="searching" class="inline-flex h-[42px] items-center gap-1.5 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50">
                        <x-icon name="search" class="h-[13px] w-[13px]" />
                        <span x-text="searching ? 'Mencari...' : 'Cari'"></span>
                    </button>
                </div>

                <template x-if="searched && found">
                    <form method="POST" :action="@js(route('admin.siswa.orang-tua.store', $siswa))" class="mt-4 space-y-3 border-t border-brand-100 pt-4">
                        @csrf
                        <input type="hidden" name="orang_tua_id" :value="orangTua.id">
                        <p class="text-sm text-gray-700">Profil ditemukan: <b x-text="orangTua.nama_lengkap"></b> (<span x-text="orangTua.no_hp"></span>)</p>
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
                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button type="submit">Simpan Tautan</x-primary-button>
                        </div>
                    </form>
                </template>

                <template x-if="searched && !found">
                    <form method="POST" :action="@js(route('admin.siswa.orang-tua.store', $siswa))" class="mt-4 space-y-3 border-t border-brand-100 pt-4">
                        @csrf
                        <p class="text-sm font-medium text-brand-700">NIK belum terdaftar. Lengkapi data di bawah untuk membuat profil sekaligus akun baru.</p>
                        <input type="hidden" name="nik" :value="nik">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Nama Lengkap *" />
                                <input type="text" name="nama_lengkap" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div>
                                <x-input-label value="No. HP / WhatsApp *" />
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
                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button type="submit">Buat Profil &amp; Tautkan</x-primary-button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    @endcan

    {{-- Tabel Data --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
        @if ($siswa->orangTua->isEmpty())
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                    <x-icon name="family_restroom" class="h-8 w-8" />
                </div>
                <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Profil Tertaut</h3>
                <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol "Tautkan Orang Tua" di atas untuk menautkan profil yang sudah ada atau membuat baru.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-4">Nama Profil</th>
                            <th class="px-6 py-4">Hubungan</th>
                            <th class="px-6 py-4">Kontak</th>
                            @can('orang-tua.edit')
                                <th class="px-6 py-4 text-right">Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-normal">
                        @foreach ($siswa->orangTua as $orangTua)
                            <tr class="transition hover:bg-gray-50/50">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $orangTua->nama_lengkap }}
                                    @if ($orangTua->pivot->is_kontak_utama)
                                        <span class="ml-1 inline-flex items-center gap-1 rounded bg-green-50 px-2 py-0.5 text-[10px] font-bold uppercase text-green-700">
                                            Utama
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge tone="slate" class="text-xs">{{ ucfirst($orangTua->pivot->hubungan) }}</x-badge>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs">{{ $orangTua->no_hp }}</td>
                                @can('orang-tua.edit')
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            @unless ($orangTua->pivot->is_kontak_utama)
                                                <form method="POST" action="{{ route('admin.siswa.orang-tua.kontak-utama', [$siswa, $orangTua]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Set Utama</button>
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
                                                <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Tautan">
                                                    <x-icon name="delete" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
