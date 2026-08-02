<div x-show="activeTab === 'data-periodik'" x-data="{ openAdd: false, openEdit: false, editUrl: '', editData: {} }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Data Periodik &amp; Fasilitas Sekolah</h2>
                <p class="text-sm text-gray-500">Pencatatan sarana listrik, akses internet, sanitasi, dan stratifikasi UKS berbasis semester.</p>
            </div>
            @can('lembaga.edit')
                <button type="button" @click="openAdd = !openAdd; openEdit = false;" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                    <x-icon name="add" class="h-4 w-4" />
                    <span x-text="openAdd ? 'Tutup Formulir' : 'Atur Data Periodik'">Atur Data Periodik</span>
                </button>
            @endcan
        </div>

        {{-- Form Inline Tambah Data Periodik --}}
        <div x-show="openAdd" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-brand-200 bg-brand-50/20 p-6 shadow-card" style="display: none;">
            <h3 class="mb-4 font-bold text-gray-900">Formulir Pengaturan Data Periodik &amp; Fasilitas</h3>
            <form method="POST" action="{{ route('admin.lembaga.data-periodik.store', $lembaga) }}" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Semester *" />
                        <select name="semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled selected>Pilih Semester</option>
                            @foreach ($semesters as $smt)
                                <option value="{{ $smt->id }}">{{ $smt->tahunAjaran->nama ?? '-' }} - {{ $smt->nama }} @if($smt->status_aktif)(Aktif)@endif</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Waktu Penyelenggaraan" />
                        <select name="waktu_penyelenggaraan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih Waktu</option>
                            @foreach (['Pagi', 'Siang', 'Kombinasi Pagi-Siang', 'Sore'] as $waktu)
                                <option value="{{ strtolower($waktu) }}">{{ $waktu }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Sumber Listrik" />
                        <select name="sumber_listrik" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih Sumber Listrik</option>
                            @foreach (['PLN', 'Diesel', 'Tenaga Surya', 'PLN & Tenaga Surya', 'Tidak Ada'] as $listrik)
                                <option value="{{ $listrik }}">{{ $listrik }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Daya Listrik (Watt)" />
                        <input type="number" name="daya_listrik" placeholder="Contoh: 5500" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Akses Internet" />
                        <input type="text" name="akses_internet" placeholder="Contoh: Indihome 100Mbps / Telkomsel" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Sertifikasi ISO" />
                        <select name="sertifikasi_iso" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="Belum Bersertifikat">Belum Bersertifikat</option>
                            <option value="ISO 9001:2008">ISO 9001:2008</option>
                            <option value="ISO 9001:2015">ISO 9001:2015</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Stratifikasi UKS" />
                        <select name="stratifikasi_uks" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="Belum Ada">Belum Ada</option>
                            <option value="Minimal">Minimal</option>
                            <option value="Standar">Standar</option>
                            <option value="Optimal">Optimal</option>
                            <option value="Paripurna">Paripurna</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Jumlah Tempat Cuci Tangan" />
                        <input type="number" name="jumlah_tempat_cuci_tangan" placeholder="0" min="0" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Jumlah Jamban/Toilet Sehat" />
                        <input type="number" name="jumlah_jamban" placeholder="0" min="0" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2 border-t border-gray-100">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="status_bos" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" checked>
                            Menerima dana BOSP
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="ketersediaan_air_bersih" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" checked>
                            Tersedia Air Bersih
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="kecukupan_air_bersih" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" checked>
                            Air Bersih Mencukupi
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="media_kie_sanitasi" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" checked>
                            Memiliki Media KIE Sanitasi
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openAdd = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Simpan Data Periodik</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Form Inline Edit Data Periodik --}}
        <div x-show="openEdit" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="rounded-2xl border border-amber-200 bg-amber-50/30 p-6 shadow-card" style="display: none;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-amber-900">Edit Data Periodik Semester</h3>
                <button type="button" @click="openEdit = false" class="text-gray-400 hover:text-gray-600"><x-icon name="close" class="h-5 w-5" /></button>
            </div>
            <form method="POST" x-bind:action="editUrl" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="semester_id" x-model="editData.semester_id">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Semester" />
                        <input type="text" disabled x-bind:value="editData.semester_label" class="mt-1.5 block w-full rounded-lg bg-gray-100 border-gray-200 text-sm font-semibold text-gray-600">
                    </div>

                    <div>
                        <x-input-label value="Waktu Penyelenggaraan" />
                        <select name="waktu_penyelenggaraan" x-model="editData.waktu_penyelenggaraan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih Waktu</option>
                            @foreach (['Pagi', 'Siang', 'Kombinasi Pagi-Siang', 'Sore'] as $waktu)
                                <option value="{{ strtolower($waktu) }}">{{ $waktu }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Sumber Listrik" />
                        <select name="sumber_listrik" x-model="editData.sumber_listrik" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih Sumber Listrik</option>
                            @foreach (['PLN', 'Diesel', 'Tenaga Surya', 'PLN & Tenaga Surya', 'Tidak Ada'] as $listrik)
                                <option value="{{ $listrik }}">{{ $listrik }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Daya Listrik (Watt)" />
                        <input type="number" name="daya_listrik" x-model="editData.daya_listrik" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Akses Internet" />
                        <input type="text" name="akses_internet" x-model="editData.akses_internet" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Sertifikasi ISO" />
                        <select name="sertifikasi_iso" x-model="editData.sertifikasi_iso" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="Belum Bersertifikat">Belum Bersertifikat</option>
                            <option value="ISO 9001:2008">ISO 9001:2008</option>
                            <option value="ISO 9001:2015">ISO 9001:2015</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Stratifikasi UKS" />
                        <select name="stratifikasi_uks" x-model="editData.stratifikasi_uks" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="Belum Ada">Belum Ada</option>
                            <option value="Minimal">Minimal</option>
                            <option value="Standar">Standar</option>
                            <option value="Optimal">Optimal</option>
                            <option value="Paripurna">Paripurna</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Jumlah Tempat Cuci Tangan" />
                        <input type="number" name="jumlah_tempat_cuci_tangan" x-model="editData.jumlah_tempat_cuci_tangan" min="0" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div>
                        <x-input-label value="Jumlah Jamban/Toilet Sehat" />
                        <input type="number" name="jumlah_jamban" x-model="editData.jumlah_jamban" min="0" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-mono text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2 border-t border-gray-100">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="status_bos" value="1" x-bind:checked="editData.status_bos" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            Menerima dana BOSP
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="ketersediaan_air_bersih" value="1" x-bind:checked="editData.ketersediaan_air_bersih" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            Tersedia Air Bersih
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="kecukupan_air_bersih" value="1" x-bind:checked="editData.kecukupan_air_bersih" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            Air Bersih Mencukupi
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="media_kie_sanitasi" value="1" x-bind:checked="editData.media_kie_sanitasi" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            Memiliki Media KIE Sanitasi
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="openEdit = false" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <x-primary-button type="submit">Perbarui Data Periodik</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Daftar Data Periodik --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($lembaga->dataPeriodik->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="analytics" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Data Periodik</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Silakan klik tombol "Atur Data Periodik" untuk melengkapi data infrastruktur, listrik, dan sanitasi per semester.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Semester &amp; Waktu</th>
                                <th class="px-6 py-4">Listrik &amp; Internet</th>
                                <th class="px-6 py-4">Sanitasi &amp; UKS</th>
                                <th class="px-6 py-4">BOSP &amp; ISO</th>
                                @can('lembaga.edit')
                                    <th class="px-6 py-4 text-right">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($lembaga->dataPeriodik as $periodik)
                                @php
                                    $semesterName = ($periodik->semester?->tahunAjaran?->nama ?? '') . ' - ' . ($periodik->semester?->nama ?? '');
                                @endphp
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-gray-900">{{ $semesterName ?: 'Semester #' . $periodik->semester_id }}</span>
                                        <span class="block text-xs font-semibold text-brand-600 capitalize">Waktu: {{ $periodik->waktu_penyelenggaraan ?: '-' }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-800">{{ $periodik->sumber_listrik ?: 'Tanpa Listrik' }} @if($periodik->daya_listrik) ({{ $periodik->daya_listrik }} W) @endif</span>
                                        <span class="block text-xs text-gray-500">Internet: {{ $periodik->akses_internet ?: 'Tidak Ada' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-xs space-y-1">
                                        <div>Cuci Tangan: <strong class="text-gray-800">{{ $periodik->jumlah_tempat_cuci_tangan ?: 0 }}</strong> &bull; Toilet: <strong class="text-gray-800">{{ $periodik->jumlah_jamban ?: 0 }}</strong></div>
                                        <div class="text-gray-500">UKS: <strong class="text-indigo-600">{{ $periodik->stratifikasi_uks ?: '-' }}</strong></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            @if($periodik->status_bos)
                                                <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">BOSP Aktif</span>
                                            @else
                                                <span class="text-xs text-gray-400">Non-BOSP</span>
                                            @endif
                                        </div>
                                        <span class="block text-xs text-gray-500 mt-1">ISO: {{ $periodik->sertifikasi_iso ?: '-' }}</span>
                                    </td>
                                    @can('lembaga.edit')
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button" @click="editUrl = @js(route('admin.lembaga.data-periodik.update', [$lembaga, $periodik])); editData = @js([
                                                    'semester_id' => $periodik->semester_id,
                                                    'semester_label' => $semesterName,
                                                    'waktu_penyelenggaraan' => $periodik->waktu_penyelenggaraan,
                                                    'sumber_listrik' => $periodik->sumber_listrik,
                                                    'daya_listrik' => $periodik->daya_listrik,
                                                    'akses_internet' => $periodik->akses_internet,
                                                    'sertifikasi_iso' => $periodik->sertifikasi_iso,
                                                    'stratifikasi_uks' => $periodik->stratifikasi_uks,
                                                    'jumlah_tempat_cuci_tangan' => $periodik->jumlah_tempat_cuci_tangan,
                                                    'jumlah_jamban' => $periodik->jumlah_jamban,
                                                    'status_bos' => $periodik->status_bos,
                                                    'ketersediaan_air_bersih' => $periodik->ketersediaan_air_bersih,
                                                    'kecukupan_air_bersih' => $periodik->kecukupan_air_bersih,
                                                    'media_kie_sanitasi' => $periodik->media_kie_sanitasi,
                                                ]); openEdit = true; openAdd = false; window.scrollTo({ top: 0, behavior: 'smooth' });" class="rounded-lg p-1.5 text-gray-500 transition hover:bg-amber-50 hover:text-amber-600 active:scale-95" title="Edit Data Periodik">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                </button>

                                                <form method="POST" action="{{ route('admin.lembaga.data-periodik.destroy', [$lembaga, $periodik]) }}" class="inline" x-data @submit.prevent="confirmDialog('Hapus Data Periodik?', @js('Apakah Anda yakin ingin menghapus data periodik semester ' . $semesterName . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-error-50 hover:text-error-600 active:scale-95" title="Hapus Data">
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
</div>
