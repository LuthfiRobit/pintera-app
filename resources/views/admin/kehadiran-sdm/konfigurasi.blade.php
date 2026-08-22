<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        tab: 'metode',
        showTitikModal: false,
        editingTitik: null,
        formTitik: { nama: '' },
        openTitikModal(titik = null) {
            this.editingTitik = titik;
            this.formTitik = { nama: titik ? titik.nama : '' };
            this.showTitikModal = true;
        },
        showEntriModal: false,
        editingEntri: null,
        formEntri: { nama: '', tanggal: '', tanggal_selesai: '', tipe: 'libur', keterangan: '', is_nasional: false },
        openEntriModal(entri = null, nasional = false) {
            this.editingEntri = entri;
            this.formEntri = entri
                ? { nama: entri.nama, tanggal: entri.tanggal.split('T')[0], tanggal_selesai: entri.tanggal_selesai ? entri.tanggal_selesai.split('T')[0] : '', tipe: entri.tipe, keterangan: entri.keterangan ?? '', is_nasional: entri.lembaga_id === null }
                : { nama: '', tanggal: '', tanggal_selesai: '', tipe: 'libur', keterangan: '', is_nasional: nasional };
            this.showEntriModal = true;
        },
        hariKerja: @js($lembaga ? collect(range(0, 6))->reject(fn ($d) => in_array($d, $lembaga->hari_libur_mingguan_sdm ?? [], true))->values()->all() : [1,2,3,4,5]),
        savingHariKerja: false,
        toggleHari(day) {
            this.hariKerja = this.hariKerja.includes(day) ? this.hariKerja.filter((d) => d !== day) : [...this.hariKerja, day];
        },
        async simpanHariKerja() {
            this.savingHariKerja = true;
            try {
                const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.hari-kerja')), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ hari_kerja: this.hariKerja }),
                });
                if (!response.ok) { alert('Gagal menyimpan hari kerja.'); return; }
                alert('Hari kerja mingguan berhasil disimpan.');
            } finally {
                this.savingHariKerja = false;
            }
        },
        showSalinModal: false,
        entriTersedia: [],
        entriTercentang: [],
        async bukaSalinModal() {
            const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.salin-tersedia')), { headers: { Accept: 'application/json' } });
            const json = await response.json();
            this.entriTersedia = json.items ?? [];
            this.entriTercentang = [];
            this.showSalinModal = true;
        },
        showPolicyModal: false,
        editingPolicy: null,
        formPolicy: { kategori_tipe: 'guru', jenis_ptk: 'guru_kelas', jenis_karyawan_id: '', jam_masuk: '07:00', jam_pulang: '', toleransi_menit: 0, hari_kerja: [], overrideHariKerja: false, is_nasional: false },
        openPolicyModal(policy = null, nasional = false) {
            this.editingPolicy = policy;
            this.formPolicy = policy
                ? { kategori_tipe: policy.jenis_ptk ? 'guru' : 'karyawan', jenis_ptk: policy.jenis_ptk ?? 'guru_kelas', jenis_karyawan_id: policy.jenis_karyawan_id ?? '', jam_masuk: policy.jam_masuk.slice(0, 5), jam_pulang: policy.jam_pulang ? policy.jam_pulang.slice(0, 5) : '', toleransi_menit: policy.toleransi_menit, hari_kerja: policy.hari_kerja ?? [], overrideHariKerja: policy.hari_kerja !== null, is_nasional: policy.lembaga_id === null }
                : { kategori_tipe: 'guru', jenis_ptk: 'guru_kelas', jenis_karyawan_id: '', jam_masuk: '07:00', jam_pulang: '', toleransi_menit: 0, hari_kerja: [], overrideHariKerja: false, is_nasional: nasional };
            this.showPolicyModal = true;
        },
        togglePolicyHari(day) {
            this.formPolicy.hari_kerja = this.formPolicy.hari_kerja.includes(day) ? this.formPolicy.hari_kerja.filter((d) => d !== day) : [...this.formPolicy.hari_kerja, day];
        }
    }">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Konfigurasi Kehadiran SDM</h1>
        </div>

        <div class="flex items-center gap-1 border-b border-gray-200">
            <button type="button" @click="tab = 'metode'" :class="tab === 'metode' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Metode &amp; Titik Absen</button>
            <button type="button" @click="tab = 'kalender'" :class="tab === 'kalender' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Kalender Kerja</button>
            <button type="button" @click="tab = 'policy'" :class="tab === 'policy' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Attendance Policy</button>
        </div>

        {{-- Tab: Metode & Titik Absen --}}
        <div x-show="tab === 'metode'" class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Metode Absensi Aktif</h2>
                <p class="mt-1 text-xs text-gray-500">Input manual admin selalu tersedia sebagai fallback. Metode lain bisa diaktifkan/nonaktifkan per lembaga.</p>

                <div class="mt-4 space-y-3">
                    @foreach ($methods as $method)
                        @php
                            $existing = $konfigurasi->firstWhere('method', $method);
                            $isEnabled = $existing?->is_enabled ?? ($method->value === 'admin');
                        @endphp
                        @if ($method->value !== 'system')
                            <form method="POST" action="{{ route('admin.kehadiran-sdm.konfigurasi.metode') }}" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-3">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method->value }}">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $method->label() }}</p>
                                    @if ($method->value === 'admin')
                                        <p class="text-[11px] text-gray-400">Fallback wajib — tidak dapat dinonaktifkan.</p>
                                    @endif
                                </div>
                                @if ($method->value === 'admin')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Selalu Aktif</span>
                                @else
                                    <button type="submit" name="is_enabled" value="{{ $isEnabled ? '0' : '1' }}" class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $isEnabled ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                @endif
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-sm font-bold text-gray-900">Titik Absen</h2>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openTitikModal()">+ Tambah Titik Absen</x-primary-button>
                    @endcan
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($titikAbsen as $titik)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $titik->nama }}</p>
                                <span class="text-[11px] {{ $titik->is_active ? 'text-emerald-600' : 'text-gray-400' }}">{{ $titik->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('kehadiran-sdm.kelola-konfigurasi')
                                    <button type="button" @click="openTitikModal({{ $titik->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.titik.destroy', $titik) }}" onsubmit="return confirm('Hapus titik absen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada titik absen untuk lembaga ini.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Kalender Kerja --}}
        <div x-show="tab === 'kalender'" x-cloak class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Hari Kerja Mingguan SDM</h2>
                <p class="mt-1 text-xs text-gray-500">Terpisah dari hari aktif akademik — pegawai (TU, satpam, dst) bisa punya pola kerja beda dari jadwal siswa.</p>

                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $hari => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" :checked="hariKerja.includes({{ $hari }})" @change="toggleHari({{ $hari }})" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @can('kehadiran-sdm.kelola-konfigurasi')
                    <div class="mt-4">
                        <x-primary-button type="button" x-bind:disabled="savingHariKerja" @click="simpanHariKerja()">Simpan Hari Kerja</x-primary-button>
                    </div>
                @endcan
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-sm font-bold text-gray-900">Entri Kalender (Libur / Tetap Masuk)</h2>
                    <div class="flex items-center gap-2">
                        @if ($bolehKelolaNasional)
                            <button type="button" @click="bukaSalinModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Salin dari Kalender Akademik</button>
                        @endif
                        @can('kehadiran-sdm.kelola-konfigurasi')
                            <x-primary-button type="button" @click="openEntriModal(null, false)">+ Tambah Entri</x-primary-button>
                        @endcan
                    </div>
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($kalenderEntriList as $entri)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ $entri->nama }}
                                    @if ($entri->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    {{ $entri->tanggal->format('d M Y') }}{{ $entri->tanggal_selesai ? ' — '.$entri->tanggal_selesai->format('d M Y') : '' }}
                                    · <span class="{{ $entri->tipe->value === 'libur' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $entri->tipe->label() }}</span>
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openEntriModal({{ $entri->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.kalender.entri.destroy', $entri) }}" onsubmit="return confirm('Hapus entri kalender ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada entri kalender kerja.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab: Attendance Policy --}}
        <div x-show="tab === 'policy'" x-cloak class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Attendance Policy</h2>
                        <p class="mt-1 text-xs text-gray-500">Jam kerja &amp; toleransi keterlambatan per kategori pegawai. Tanpa Policy, pegawai tidak pernah ditandai terlambat.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openPolicyModal(null, false)">+ Tambah Policy</x-primary-button>
                    @endcan
                </div>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($policyList as $policy)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ $policy->jenis_ptk ? ($jenisPtkOptions[$policy->jenis_ptk] ?? $policy->jenis_ptk) : ($policy->jenisKaryawan->nama ?? '—') }}
                                    @if ($policy->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Nasional</span>
                                    @endif
                                    @if ($policy->hari_kerja !== null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Override Hari Kerja</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    Masuk {{ substr($policy->jam_masuk, 0, 5) }}{{ $policy->jam_pulang ? ' — Pulang '.substr($policy->jam_pulang, 0, 5) : '' }}
                                    · Toleransi {{ $policy->toleransi_menit }} menit
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openPolicyModal({{ $policy->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.policy.destroy', $policy) }}" onsubmit="return confirm('Hapus Attendance Policy ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada Attendance Policy.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Modal Titik Absen (sudah ada dari Sub-project 1) --}}
        <div x-show="showTitikModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showTitikModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingTitik ? 'Edit Titik Absen' : 'Tambah Titik Absen'"></h3>
                <form method="POST" :action="editingTitik ? `/admin/kehadiran-sdm/konfigurasi/titik/${editingTitik.id}` : '{{ route('admin.kehadiran-sdm.titik.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingTitik"><input type="hidden" name="_method" value="PUT"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Titik Absen</label>
                        <input x-model="formTitik.nama" name="nama" type="text" required placeholder="Contoh: Gerbang Utama" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <template x-if="editingTitik">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="true" class="rounded border-gray-300">
                            <label class="text-xs font-semibold text-gray-700">Aktif</label>
                        </div>
                    </template>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showTitikModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Entri Kalender --}}
        <div x-show="showEntriModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showEntriModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingEntri ? 'Edit Entri Kalender' : 'Tambah Entri Kalender'"></h3>
                <form method="POST" :action="editingEntri ? `/admin/kehadiran-sdm/konfigurasi/kalender/entri/${editingEntri.id}` : '{{ route('admin.kehadiran-sdm.kalender.entri.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingEntri"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingEntri && formEntri.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama</label>
                        <input x-model="formEntri.nama" name="nama" type="text" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal</label>
                            <input x-model="formEntri.tanggal" name="tanggal" type="date" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Sampai (opsional)</label>
                            <input x-model="formEntri.tanggal_selesai" name="tanggal_selesai" type="date" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tipe</label>
                        <select x-model="formEntri.tipe" name="tipe" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                            @foreach ($tipeKalenderOptions as $tipe)
                                <option value="{{ $tipe->value }}">{{ $tipe->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Keterangan (opsional)</label>
                        <textarea x-model="formEntri.keterangan" name="keterangan" rows="2" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showEntriModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Salin dari Kalender Akademik --}}
        <div x-show="showSalinModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showSalinModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900">Salin dari Kalender Akademik</h3>
                <p class="mt-1 text-xs text-gray-500">Hanya entri nasional yang belum pernah disalin.</p>
                <form method="POST" action="{{ route('admin.kehadiran-sdm.kalender.salin') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <template x-if="entriTersedia.length === 0">
                            <p class="py-4 text-center text-sm text-gray-400">Tidak ada entri baru untuk disalin.</p>
                        </template>
                        <template x-for="item in entriTersedia" :key="item.id">
                            <label class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm">
                                <input type="checkbox" name="kalender_akademik_ids[]" :value="item.id" x-model="entriTercentang">
                                <span x-text="item.nama + ' — ' + item.tanggal.split('T')[0]"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showSalinModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit" x-bind:disabled="entriTercentang.length === 0">Salin Terpilih</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Attendance Policy --}}
        <div x-show="showPolicyModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showPolicyModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingPolicy ? 'Edit Attendance Policy' : 'Tambah Attendance Policy'"></h3>
                <form method="POST" :action="editingPolicy ? `/admin/kehadiran-sdm/konfigurasi/policy/${editingPolicy.id}` : '{{ route('admin.kehadiran-sdm.policy.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingPolicy"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingPolicy && formPolicy.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>

                    <template x-if="!editingPolicy">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Pegawai</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="kategori_tipe" value="guru" x-model="formPolicy.kategori_tipe"> Guru (jenis PTK)</label>
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="kategori_tipe" value="karyawan" x-model="formPolicy.kategori_tipe"> Karyawan (jenis karyawan)</label>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'guru'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis PTK</label>
                            <select name="jenis_ptk" x-model="formPolicy.jenis_ptk" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                                @foreach ($jenisPtkOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'karyawan'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Karyawan</label>
                            <select name="jenis_karyawan_id" x-model="formPolicy.jenis_karyawan_id" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                                @foreach ($jenisKaryawanList as $jk)
                                    <option value="{{ $jk->id }}">{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="editingPolicy">
                        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500" x-text="'Kategori: ' + (editingPolicy.jenis_ptk || 'Karyawan') + ' (tidak dapat diubah — hapus lalu buat baru kalau perlu ganti kategori)'"></p>
                    </template>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Masuk</label>
                            <input x-model="formPolicy.jam_masuk" name="jam_masuk" type="time" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Pulang (opsional)</label>
                            <input x-model="formPolicy.jam_pulang" name="jam_pulang" type="time" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Toleransi Keterlambatan (menit)</label>
                        <input x-model.number="formPolicy.toleransi_menit" name="toleransi_menit" type="number" min="0" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-100 px-3.5 py-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                            <input type="checkbox" x-model="formPolicy.overrideHariKerja" class="rounded border-gray-300">
                            Override hari kerja kalender lembaga untuk kategori ini
                        </label>
                        <template x-if="formPolicy.overrideHariKerja">
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <template x-for="[hari, label] in Object.entries({1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'})" :key="hari">
                                    <label class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-xs">
                                        <input type="checkbox" :checked="formPolicy.hari_kerja.includes(Number(hari))" @change="togglePolicyHari(Number(hari))">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-for="day in (formPolicy.overrideHariKerja ? formPolicy.hari_kerja : [])" :key="day">
                            <input type="hidden" name="hari_kerja[]" :value="day">
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showPolicyModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
