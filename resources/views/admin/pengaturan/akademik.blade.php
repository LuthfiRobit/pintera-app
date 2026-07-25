<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Akademik</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengaturan Akademik</b>
            </p>
        </div>

        <div x-data="{ tab: 'hari-aktif' }">
            <div class="flex items-center gap-1 border-b border-gray-200">
            <button
                type="button"
                @click="tab = 'hari-aktif'"
                :class="tab === 'hari-aktif' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'"
                class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
            >
                Hari Aktif Sekolah
            </button>
            <button
                type="button"
                @click="tab = 'hari-libur'"
                :class="tab === 'hari-libur' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'"
                class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition"
            >
                Hari Libur Akademik
            </button>
        </div>

        <div class="mt-4 space-y-4">
            {{-- Hari Aktif Sekolah --}}
            <div
                x-show="tab === 'hari-aktif'"
                x-cloak
                x-data="{
                    hariAktif: @js(collect(range(0, 6))->reject(fn ($d) => in_array($d, $lembaga->hari_libur_mingguan ?? [], true))->values()->all()),
                    bolehKelola: @js($bolehKelolaHariAktif),
                    submitting: false,
                    toggle(day) {
                        if (!this.bolehKelola) return;
                        this.hariAktif = this.hariAktif.includes(day)
                            ? this.hariAktif.filter((d) => d !== day)
                            : [...this.hariAktif, day];
                    },
                    async simpan() {
                        this.submitting = true;
                        try {
                            const response = await fetch(@js(route('admin.pengaturan.akademik.hari-aktif')), {
                                method: 'PUT',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                body: JSON.stringify({ hari_aktif: this.hariAktif }),
                            });
                            const json = await response.json();
                            if (!response.ok) {
                                Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan hari aktif sekolah.');
                                return;
                            }
                            Alpine.store('toast').push('success', 'Hari aktif sekolah berhasil disimpan.');
                        } catch (error) {
                            Alpine.store('toast').push('error', 'Gagal menyimpan hari aktif sekolah.');
                        } finally {
                            this.submitting = false;
                        }
                    },
                }"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card"
            >
                <p class="font-display text-sm font-bold text-gray-900">Hari Aktif Sekolah</p>
                <p class="mt-1 text-sm text-gray-500">Pilih hari-hari yang menjadi hari aktif (masuk) sekolah pada lembaga ini.</p>
                @unless ($bolehKelolaHariAktif)
                    <p class="mt-1 text-sm text-gray-400">Anda tidak memiliki izin untuk mengubah pengaturan ini.</p>
                @endunless

                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $hari => $label)
                        <label
                            class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700"
                            :class="{ 'opacity-60 cursor-not-allowed': !bolehKelola }"
                        >
                            <input
                                type="checkbox"
                                :checked="hariAktif.includes({{ $hari }})"
                                @change="toggle({{ $hari }})"
                                :disabled="!bolehKelola"
                                class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed"
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @can('pengaturan-akademik.kelola')
                    <div class="mt-4">
                        <x-primary-button type="button" x-bind:disabled="submitting" @click="simpan()">Simpan Hari Aktif</x-primary-button>
                    </div>
                @endcan
            </div>

            {{-- Hari Libur Akademik --}}
            <div
                x-show="tab === 'hari-libur'"
                x-cloak
                x-data="kalenderAkademikTable({
                    initialItems: @js($entriList),
                    storeUrl: @js(route('admin.kalender-akademik.store')),
                    updateUrlTemplate: @js(route('admin.kalender-akademik.update', ['kalenderAkademik' => '__ID__'])),
                    deleteUrlTemplate: @js(route('admin.kalender-akademik.destroy', ['kalenderAkademik' => '__ID__'])),
                    bolehNasional: @js($bolehNasional),
                })"
                class="space-y-4"
            >
                @can('kalender-akademik.kelola')
                    <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                        <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Entri Kalender' : 'Edit Entri Kalender'"></p>
                        <form @submit.prevent="submit()" class="mt-3 grid grid-cols-1 gap-3">
                            <div>
                                <x-input-label value="Nama" />
                                <x-text-input type="text" x-model="form.nama" placeholder="mis. Libur Semester Ganjil" class="mt-1.5" />
                                <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
                            </div>

                            <div x-show="editingId === null" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal Mulai" />
                                    <x-text-input type="date" x-model="form.tanggal" class="mt-1.5" />
                                    <p class="mt-1.5 text-sm text-error-600" x-show="errors.tanggal" x-text="errors.tanggal?.[0]"></p>
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Selesai" />
                                    <x-text-input type="date" x-model="form.tanggal_selesai" class="mt-1.5" />
                                    <p class="mt-1.5 text-sm text-error-600" x-show="errors.tanggal_selesai" x-text="errors.tanggal_selesai?.[0]"></p>
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Tipe" />
                                <select x-model="form.tipe" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="libur">Libur</option>
                                    <option value="kerja">Tetap Masuk (Override)</option>
                                </select>
                                <p class="mt-1.5 text-sm text-error-600" x-show="errors.tipe" x-text="errors.tipe?.[0]"></p>
                            </div>

                            <div>
                                <x-input-label value="Keterangan (opsional)" />
                                <textarea
                                    x-model="form.keterangan"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                ></textarea>
                                <p class="mt-1.5 text-sm text-error-600" x-show="errors.keterangan" x-text="errors.keterangan?.[0]"></p>
                            </div>

                            <div x-show="editingId === null && bolehNasional" x-cloak>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" x-model="form.berlaku_nasional" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                    Berlaku Nasional (untuk semua lembaga)
                                </label>
                            </div>

                            <div class="flex items-center gap-3">
                                <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
                                <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
                            </div>
                        </form>
                    </div>
                @endcan

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <p class="font-display text-sm font-bold text-gray-900">Hari Libur Akademik</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                    <th class="px-5 py-3">Nama</th>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Tipe</th>
                                    <th class="px-5 py-3">Cakupan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-if="items.length === 0">
                                    <tr><td colspan="5" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada entri kalender.</td></tr>
                                </template>
                                <template x-for="item in items" :key="item.id">
                                    <tr class="transition hover:bg-gray-50">
                                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                            <x-table-actions>
                                                @can('kalender-akademik.kelola')
                                                    <x-dropdown-link href="#" @click.prevent="startEdit(item)">
                                                        <span class="inline-flex items-center gap-2.5">
                                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                            Edit
                                                        </span>
                                                    </x-dropdown-link>
                                                    <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                                @endcan
                                            </x-table-actions>
                                        </td>
                                        <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                        <td class="px-5 py-3.5 text-gray-600" x-text="tampilTanggal(item)"></td>
                                        <td class="px-5 py-3.5">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="item.tipe === 'libur' ? 'bg-error-50 text-error-700' : 'bg-success-50 text-success-700'"
                                                x-text="item.tipe === 'libur' ? 'Libur' : 'Tetap Masuk (Override)'"
                                            ></span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="item.lembaga_id === null ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
                                                x-text="item.lembaga_id === null ? 'Nasional' : 'Khusus Lembaga Ini'"
                                            ></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</x-app-layout>
