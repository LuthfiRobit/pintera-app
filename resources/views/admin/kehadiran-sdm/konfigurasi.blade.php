{{-- resources/views/admin/kehadiran-sdm/konfigurasi.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="{
        tab: (new URLSearchParams(window.location.search)).get('tab') || window.location.hash.replace('#', '') || 'metode',
        setTab(t) {
            this.tab = t;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', t);
            window.history.replaceState({}, '', url);
            window.location.hash = t;
        },
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
                const response = await fetch(@js(route('admin.kehadiran-sdm.kalender.hari-kerja')) + '?tab=kalender', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ hari_kerja: this.hariKerja }),
                });
                if (!response.ok) {
                    if (window.confirmDialog) {
                        confirmDialog('Gagal', 'Gagal menyimpan hari kerja mingguan.', { confirmLabel: 'Tutup' });
                    }
                    return;
                }
                if (this.$store && this.$store.toast) {
                    this.$store.toast.push('success', 'Hari kerja mingguan berhasil disimpan.');
                }
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
        },
        showJenisShiftModal: false,
        editingJenisShift: null,
        formJenisShift: { nama: '', jam_masuk: '06:00', jam_pulang: '14:00', is_nasional: false },
        openJenisShiftModal(jenisShift = null, nasional = false) {
            this.editingJenisShift = jenisShift;
            this.formJenisShift = jenisShift
                ? { nama: jenisShift.nama, jam_masuk: jenisShift.jam_masuk.slice(0, 5), jam_pulang: jenisShift.jam_pulang.slice(0, 5), is_nasional: jenisShift.lembaga_id === null }
                : { nama: '', jam_masuk: '06:00', jam_pulang: '14:00', is_nasional: nasional };
            this.showJenisShiftModal = true;
        },
        showPenugasanModal: false,
        editingPenugasan: null,
        formPenugasan: { jenis_shift_id: '', tanggal_mulai: '', tanggal_selesai: '', hari_kerja: [], overrideHariKerja: false },
        openPenugasanModal(penugasan = null) {
            this.editingPenugasan = penugasan;
            this.formPenugasan = penugasan
                ? { jenis_shift_id: penugasan.jenis_shift_id, tanggal_mulai: penugasan.tanggal_mulai.split('T')[0], tanggal_selesai: penugasan.tanggal_selesai ? penugasan.tanggal_selesai.split('T')[0] : '', hari_kerja: penugasan.hari_kerja ?? [], overrideHariKerja: penugasan.hari_kerja !== null }
                : { jenis_shift_id: '', tanggal_mulai: '', tanggal_selesai: '', hari_kerja: [], overrideHariKerja: false };
            this.showPenugasanModal = true;
        },
        togglePenugasanHari(day) {
            this.formPenugasan.hari_kerja = this.formPenugasan.hari_kerja.includes(day) ? this.formPenugasan.hari_kerja.filter((d) => d !== day) : [...this.formPenugasan.hari_kerja, day];
        }
    }">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Konfigurasi Kehadiran SDM</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola metode absensi, kalender kerja, kebijakan jam kerja, dan shift bergilir.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM <span class="mx-1 text-gray-300">&rsaquo;</span> Kehadiran <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Konfigurasi</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Kehadiran SDM</span>
                </a>
            </div>
        </div>

        {{-- 4-Tab Navigation Pills Bar --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-2 shadow-card">
            <div class="flex items-center gap-1 overflow-x-auto scrollbar-none">
                <button 
                    type="button" 
                    @click="setTab('metode')" 
                    :class="tab === 'metode' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span>Metode &amp; Titik Absen</span>
                </button>

                <button 
                    type="button" 
                    @click="setTab('kalender')" 
                    :class="tab === 'kalender' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Kalender Kerja SDM</span>
                </button>

                <button 
                    type="button" 
                    @click="setTab('policy')" 
                    :class="tab === 'policy' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Attendance Policy</span>
                </button>

                <button 
                    type="button" 
                    @click="setTab('shift')" 
                    :class="tab === 'shift' ? 'bg-brand-50 text-brand-700 font-semibold shadow-2xs border border-brand-200' : 'text-gray-600 hover:bg-gray-50 border border-transparent'" 
                    class="rounded-xl px-4 py-2 text-xs transition whitespace-nowrap flex items-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Shift Bergilir</span>
                </button>
            </div>
        </div>

        {{-- Tab 1: Metode & Titik Absen --}}
        <div x-show="tab === 'metode'" class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Metode Absensi Aktif</h2>
                <p class="mt-0.5 text-xs text-gray-500">Input manual admin selalu tersedia sebagai fallback. Metode lain bisa diaktifkan/nonaktifkan per lembaga.</p>

                <div class="mt-4 space-y-2.5">
                    @foreach ($methods as $method)
                        @php
                            $existing = $konfigurasi->firstWhere('method', $method);
                            $isEnabled = $existing?->is_enabled ?? ($method->value === 'admin');
                        @endphp
                        @if ($method->value !== 'system')
                            <form method="POST" action="{{ route('admin.kehadiran-sdm.konfigurasi.metode') }}?tab=metode" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method->value }}">
                                <div>
                                    <p class="text-xs font-bold text-gray-900">{{ $method->label() }}</p>
                                    @if ($method->value === 'admin')
                                        <p class="text-[11px] text-gray-400 mt-0.5">Fallback wajib — tidak dapat dinonaktifkan.</p>
                                    @endif
                                </div>
                                @if ($method->value === 'admin')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Selalu Aktif</span>
                                @else
                                    <button type="submit" name="is_enabled" value="{{ $isEnabled ? '0' : '1' }}" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition {{ $isEnabled ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-gray-200 text-gray-600 hover:bg-gray-300' }}">
                                        {{ $isEnabled ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                @endif
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Titik Absen</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Lokasi pemindaian QR / pos absensi pegawai.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <button type="button" @click="openTitikModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                            <span>+ Tambah Titik Absen</span>
                        </button>
                    @endcan
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($titikAbsen as $titik)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">{{ $titik->nama }}</p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold mt-0.5 {{ $titik->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $titik->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('kehadiran-sdm.kelola-konfigurasi')
                                    <button 
                                        type="button" 
                                        @click="openTitikModal({{ $titik->toJson() }})" 
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs"
                                    >
                                        Edit
                                    </button>

                                    <form 
                                        method="POST" 
                                        action="{{ route('admin.kehadiran-sdm.titik.destroy', $titik) }}?tab=metode" 
                                        @submit.prevent="confirmDialog('Hapus Titik Absen?', 'Apakah Anda yakin ingin menghapus titik absen ini?', { confirmLabel: 'Ya, Hapus Titik', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada titik absen untuk lembaga ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 2: Kalender Kerja --}}
        <div x-show="tab === 'kalender'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <h2 class="font-display text-sm font-bold text-gray-900">Hari Kerja Mingguan SDM</h2>
                <p class="mt-0.5 text-xs text-gray-500">Terpisah dari hari aktif akademik — pegawai (TU, satpam, dst) bisa punya pola kerja beda dari jadwal siswa.</p>

                <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $hari => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 py-2.5 text-xs font-semibold text-gray-800 transition hover:bg-gray-100 cursor-pointer">
                            <input type="checkbox" :checked="hariKerja.includes({{ $hari }})" @change="toggleHari({{ $hari }})" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @can('kehadiran-sdm.kelola-konfigurasi')
                    <div class="mt-4 pt-2">
                        <button 
                            type="button" 
                            x-bind:disabled="savingHariKerja" 
                            @click="simpanHariKerja()" 
                            class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]"
                        >
                            <span>Simpan Hari Kerja</span>
                        </button>
                    </div>
                @endcan
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between border-b border-gray-100 pb-3 gap-2">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Entri Kalender (Libur / Tetap Masuk)</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Daftar hari libur khusus atau hari tetap masuk pegawai.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($bolehKelolaNasional)
                            <button type="button" @click="bukaSalinModal()" class="rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition">Salin dari Kalender Akademik</button>
                        @endif
                        @can('kehadiran-sdm.kelola-konfigurasi')
                            <button type="button" @click="openEntriModal(null, false)" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                                <span>+ Tambah Entri</span>
                            </button>
                        @endcan
                    </div>
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($kalenderEntriList as $entri)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">
                                    {{ $entri->nama }}
                                    @if ($entri->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    <span class="font-mono">{{ $entri->tanggal->format('d M Y') }}{{ $entri->tanggal_selesai ? ' — '.$entri->tanggal_selesai->format('d M Y') : '' }}</span>
                                    &bull; <span class="{{ $entri->tipe->value === 'libur' ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold' }}">{{ $entri->tipe->label() }}</span>
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openEntriModal({{ $entri->toJson() }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs">Edit</button>
                                    <form 
                                        method="POST" 
                                        action="{{ route('admin.kehadiran-sdm.kalender.entri.destroy', $entri) }}?tab=kalender" 
                                        @submit.prevent="confirmDialog('Hapus Entri Kalender?', 'Apakah Anda yakin ingin menghapus entri kalender ini?', { confirmLabel: 'Ya, Hapus Entri', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada entri kalender kerja.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 3: Attendance Policy --}}
        <div x-show="tab === 'policy'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Attendance Policy</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Jam kerja &amp; toleransi keterlambatan per kategori pegawai. Tanpa Policy, pegawai tidak pernah ditandai terlambat.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <button type="button" @click="openPolicyModal(null, false)" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                            <span>+ Tambah Policy</span>
                        </button>
                    @endcan
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($policyList as $policy)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">
                                    {{ $policy->jenis_ptk ? ($jenisPtkOptions[$policy->jenis_ptk] ?? $policy->jenis_ptk) : ($policy->jenisKaryawan->nama ?? '—') }}
                                    @if ($policy->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">Nasional</span>
                                    @endif
                                    @if ($policy->hari_kerja !== null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">Override Hari Kerja</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    Masuk <span class="font-mono font-bold text-gray-800">{{ substr($policy->jam_masuk, 0, 5) }}</span>{{ $policy->jam_pulang ? ' — Pulang '.substr($policy->jam_pulang, 0, 5) : '' }}
                                    &bull; Toleransi <b class="text-gray-800">{{ $policy->toleransi_menit }}</b> menit
                                </p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openPolicyModal({{ $policy->toJson() }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs">Edit</button>
                                    <form 
                                        method="POST" 
                                        action="{{ route('admin.kehadiran-sdm.policy.destroy', $policy) }}?tab=policy" 
                                        @submit.prevent="confirmDialog('Hapus Attendance Policy?', 'Apakah Anda yakin ingin menghapus policy ini?', { confirmLabel: 'Ya, Hapus Policy', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada Attendance Policy.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tab 4: Shift Bergilir --}}
        <div x-show="tab === 'shift'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Jenis Shift</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Master jam kerja untuk sistem kerja shift pegawai.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <button type="button" @click="openJenisShiftModal(null, false)" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                            <span>+ Tambah Jenis Shift</span>
                        </button>
                    @endcan
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($jenisShiftList as $js)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">
                                    {{ $js->nama }}
                                    @if ($js->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-200">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-500 font-mono mt-0.5">{{ substr($js->jam_masuk, 0, 5) }} — {{ substr($js->jam_pulang, 0, 5) }}</p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openJenisShiftModal({{ $js->toJson() }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-brand-200 bg-brand-50 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs">Edit</button>
                                    <form 
                                        method="POST" 
                                        action="{{ route('admin.kehadiran-sdm.jenis-shift.destroy', $js) }}?tab=shift" 
                                        @submit.prevent="confirmDialog('Hapus Jenis Shift?', 'Apakah Anda yakin ingin menghapus jenis shift ini?', { confirmLabel: 'Ya, Hapus Shift', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada jenis shift.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">Penugasan Shift</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Penetapan jadwal shift bergilir ke individu pegawai.</p>
                    </div>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <button type="button" @click="openPenugasanModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition active:scale-[0.98]">
                            <span>+ Tambah Penugasan</span>
                        </button>
                    @endcan
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($penugasanShiftList as $p)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-xs font-bold text-gray-900">{{ $p->pegawai->nama ?? '—' }} &mdash; <span class="text-brand-600">{{ $p->jenisShift->nama }}</span></p>
                                <p class="text-[11px] text-gray-500 font-mono mt-0.5">{{ $p->tanggal_mulai->format('d M Y') }}{{ $p->tanggal_selesai ? ' — '.$p->tanggal_selesai->format('d M Y') : ' (tanpa batas)' }}</p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <form 
                                    method="POST" 
                                    action="{{ route('admin.kehadiran-sdm.penugasan-shift.destroy', $p) }}?tab=shift" 
                                    @submit.prevent="confirmDialog('Hapus Penugasan Shift?', 'Apakah Anda yakin ingin menghapus penugasan shift ini?', { confirmLabel: 'Ya, Hapus Penugasan', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">
                            <p class="text-xs font-semibold text-gray-700">Belum ada penugasan shift untuk lembaga ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Standard Modal 1: Titik Absen --}}
        <div x-show="showTitikModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showTitikModal" class="fixed inset-0 transform transition-all" @click="showTitikModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showTitikModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingTitik ? 'Edit Titik Absen' : 'Tambah Titik Absen'"></h3>
                    <button @click="showTitikModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingTitik ? `/admin/kehadiran-sdm/konfigurasi/titik/${editingTitik.id}?tab=metode` : '{{ route('admin.kehadiran-sdm.titik.store') }}?tab=metode'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingTitik"><input type="hidden" name="_method" value="PUT"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Titik Absen <span class="text-rose-500">*</span></label>
                        <input x-model="formTitik.nama" name="nama" type="text" required placeholder="Contoh: Gerbang Utama" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <template x-if="editingTitik">
                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="true" class="rounded border-gray-300 text-brand-600">
                            <label class="text-xs font-semibold text-gray-700">Status Aktif</label>
                        </div>
                    </template>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showTitikModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 2: Entri Kalender --}}
        <div x-show="showEntriModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showEntriModal" class="fixed inset-0 transform transition-all" @click="showEntriModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showEntriModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingEntri ? 'Edit Entri Kalender' : 'Tambah Entri Kalender'"></h3>
                    <button @click="showEntriModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingEntri ? `/admin/kehadiran-sdm/konfigurasi/kalender/entri/${editingEntri.id}?tab=kalender` : '{{ route('admin.kehadiran-sdm.kalender.entri.store') }}?tab=kalender'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingEntri"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingEntri && formEntri.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Event / Hari Libur <span class="text-rose-500">*</span></label>
                        <input x-model="formEntri.nama" name="nama" type="text" required placeholder="Contoh: Libur Hari Raya" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-rose-500">*</span></label>
                            <input x-model="formEntri.tanggal" name="tanggal" type="date" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Sampai (opsional)</label>
                            <input x-model="formEntri.tanggal_selesai" name="tanggal_selesai" type="date" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tipe Entri <span class="text-rose-500">*</span></label>
                        <select x-model="formEntri.tipe" name="tipe" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($tipeKalenderOptions as $tipe)
                                <option value="{{ $tipe->value }}">{{ $tipe->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Keterangan (opsional)</label>
                        <textarea x-model="formEntri.keterangan" name="keterangan" rows="2" placeholder="Catatan tambahan..." class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showEntriModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 3: Salin dari Kalender Akademik --}}
        <div x-show="showSalinModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showSalinModal" class="fixed inset-0 transform transition-all" @click="showSalinModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showSalinModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <div>
                        <h3 class="font-display text-base font-bold text-gray-900">Salin dari Kalender Akademik</h3>
                        <p class="mt-0.5 text-xs text-gray-500">Hanya entri nasional yang belum disalin.</p>
                    </div>
                    <button @click="showSalinModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.kehadiran-sdm.kalender.salin') }}?tab=kalender" class="mt-4 space-y-3">
                    @csrf
                    <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                        <template x-if="entriTersedia.length === 0">
                            <p class="py-6 text-center text-xs text-gray-400">Tidak ada entri baru untuk disalin.</p>
                        </template>
                        <template x-for="item in entriTersedia" :key="item.id">
                            <label class="flex items-center gap-2 rounded-xl border border-gray-100 bg-gray-50/50 p-3 text-xs cursor-pointer hover:bg-gray-100 transition">
                                <input type="checkbox" name="kalender_akademik_ids[]" :value="item.id" x-model="entriTercentang" class="rounded border-gray-300 text-brand-600">
                                <span x-text="item.nama + ' — ' + item.tanggal.split('T')[0]" class="font-medium text-gray-800"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showSalinModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit" x-bind:disabled="entriTercentang.length === 0">Salin Terpilih</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 4: Attendance Policy --}}
        <div x-show="showPolicyModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showPolicyModal" class="fixed inset-0 transform transition-all" @click="showPolicyModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showPolicyModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingPolicy ? 'Edit Attendance Policy' : 'Tambah Attendance Policy'"></h3>
                    <button @click="showPolicyModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingPolicy ? `/admin/kehadiran-sdm/konfigurasi/policy/${editingPolicy.id}?tab=policy` : '{{ route('admin.kehadiran-sdm.policy.store') }}?tab=policy'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingPolicy"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingPolicy && formPolicy.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>

                    <template x-if="!editingPolicy">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Pegawai <span class="text-rose-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-800"><input type="radio" name="kategori_tipe" value="guru" x-model="formPolicy.kategori_tipe" class="text-brand-600"> Guru (jenis PTK)</label>
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-800"><input type="radio" name="kategori_tipe" value="karyawan" x-model="formPolicy.kategori_tipe" class="text-brand-600"> Karyawan (jenis karyawan)</label>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'guru'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis PTK <span class="text-rose-500">*</span></label>
                            <select name="jenis_ptk" x-model="formPolicy.jenis_ptk" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($jenisPtkOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="!editingPolicy && formPolicy.kategori_tipe === 'karyawan'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Karyawan <span class="text-rose-500">*</span></label>
                            <select name="jenis_karyawan_id" x-model="formPolicy.jenis_karyawan_id" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($jenisKaryawanList as $jk)
                                    <option value="{{ $jk->id }}">{{ $jk->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="editingPolicy">
                        <p class="rounded-xl bg-gray-50 p-3 text-xs text-gray-500" x-text="'Kategori: ' + (editingPolicy.jenis_ptk || 'Karyawan') + ' (tidak dapat diubah — hapus lalu buat baru kalau perlu ganti)'"></p>
                    </template>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Masuk <span class="text-rose-500">*</span></label>
                            <input x-model="formPolicy.jam_masuk" name="jam_masuk" type="time" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Pulang (opsional)</label>
                            <input x-model="formPolicy.jam_pulang" name="jam_pulang" type="time" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Toleransi Keterlambatan (menit) <span class="text-rose-500">*</span></label>
                        <input x-model.number="formPolicy.toleransi_menit" name="toleransi_menit" type="number" min="0" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    </div>

                    <div class="rounded-xl bg-amber-50/50 border border-amber-100 p-3.5">
                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                            <input type="checkbox" x-model="formPolicy.overrideHariKerja" class="rounded border-gray-300 text-brand-600">
                            Override hari kerja kalender lembaga untuk kategori ini
                        </label>
                        <template x-if="formPolicy.overrideHariKerja">
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <template x-for="[hari, label] in Object.entries({1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'})" :key="hari">
                                    <label class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white p-2 text-xs">
                                        <input type="checkbox" :checked="formPolicy.hari_kerja.includes(Number(hari))" @change="togglePolicyHari(Number(hari))" class="rounded border-gray-300 text-brand-600">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-for="day in (formPolicy.overrideHariKerja ? formPolicy.hari_kerja : [])" :key="day">
                            <input type="hidden" name="hari_kerja[]" :value="day">
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showPolicyModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 5: Jenis Shift --}}
        <div x-show="showJenisShiftModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showJenisShiftModal" class="fixed inset-0 transform transition-all" @click="showJenisShiftModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showJenisShiftModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingJenisShift ? 'Edit Jenis Shift' : 'Tambah Jenis Shift'"></h3>
                    <button @click="showJenisShiftModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingJenisShift ? `/admin/kehadiran-sdm/konfigurasi/jenis-shift/${editingJenisShift.id}?tab=shift` : '{{ route('admin.kehadiran-sdm.jenis-shift.store') }}?tab=shift'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingJenisShift"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingJenisShift && formJenisShift.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Shift <span class="text-rose-500">*</span></label>
                        <input x-model="formJenisShift.nama" name="nama" type="text" required placeholder="Contoh: Shift Malam" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Masuk <span class="text-rose-500">*</span></label>
                            <input x-model="formJenisShift.jam_masuk" name="jam_masuk" type="time" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Pulang <span class="text-rose-500">*</span></label>
                            <input x-model="formJenisShift.jam_pulang" name="jam_pulang" type="time" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showJenisShiftModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Standard Modal 6: Penugasan Shift --}}
        <div x-show="showPenugasanModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" style="display: none;">
            <div x-show="showPenugasanModal" class="fixed inset-0 transform transition-all" @click="showPenugasanModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-xs"></div>
            </div>

            <div x-show="showPenugasanModal" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left" x-data="shiftPenugasanForm()"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                    <h3 class="font-display text-base font-bold text-gray-900" x-text="editingPenugasan ? 'Edit Penugasan Shift' : 'Tambah Penugasan Shift'"></h3>
                    <button @click="showPenugasanModal = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" :action="editingPenugasan ? `/admin/kehadiran-sdm/konfigurasi/penugasan-shift/${editingPenugasan.id}?tab=shift` : '{{ route('admin.kehadiran-sdm.penugasan-shift.store') }}?tab=shift'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingPenugasan"><input type="hidden" name="_method" value="PUT"></template>

                    <template x-if="!editingPenugasan">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Pegawai <span class="text-rose-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-800"><input type="radio" name="pegawai_tipe" value="guru" x-model="pegawaiTipe" class="text-brand-600"> Guru</label>
                                <label class="flex items-center gap-2 text-xs font-medium text-gray-800"><input type="radio" name="pegawai_tipe" value="karyawan" x-model="pegawaiTipe" class="text-brand-600"> Karyawan</label>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPenugasan && pegawaiTipe === 'guru'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Guru <span class="text-rose-500">*</span></label>
                            <div x-data="tomSelectPegawai({ options: @js($guruList), placeholder: 'Cari nama guru atau NIP/NUPTK...' })" class="mt-1">
                                <select 
                                    name="pegawai_id" 
                                    x-ref="selectElement" 
                                    :disabled="pegawaiTipe !== 'guru'"
                                    :required="pegawaiTipe === 'guru'"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500" 
                                    autocomplete="off"
                                >
                                    <option value="">Cari nama guru atau NIP/NUPTK...</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPenugasan && pegawaiTipe === 'karyawan'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Karyawan <span class="text-rose-500">*</span></label>
                            <div x-data="tomSelectPegawai({ options: @js($karyawanList), placeholder: 'Cari nama karyawan...' })" class="mt-1">
                                <select 
                                    name="pegawai_id" 
                                    x-ref="selectElement" 
                                    :disabled="pegawaiTipe !== 'karyawan'"
                                    :required="pegawaiTipe === 'karyawan'"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500" 
                                    autocomplete="off"
                                >
                                    <option value="">Cari nama karyawan...</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Shift <span class="text-rose-500">*</span></label>
                        <select x-model="formPenugasan.jenis_shift_id" name="jenis_shift_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih jenis shift...</option>
                            @foreach ($jenisShiftList as $js)
                                <option value="{{ $js->id }}">{{ $js->nama }} ({{ substr($js->jam_masuk, 0, 5) }}-{{ substr($js->jam_pulang, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-rose-500">*</span></label>
                            <input x-model="formPenugasan.tanggal_mulai" name="tanggal_mulai" type="date" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai (opsional)</label>
                            <input x-model="formPenugasan.tanggal_selesai" name="tanggal_selesai" type="date" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50/50 border border-amber-100 p-3.5">
                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                            <input type="checkbox" x-model="formPenugasan.overrideHariKerja" class="rounded border-gray-300 text-brand-600">
                            Batasi ke hari tertentu dalam rentang (opsional)
                        </label>
                        <template x-if="formPenugasan.overrideHariKerja">
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <template x-for="[hari, label] in Object.entries({1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'})" :key="hari">
                                    <label class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white p-2 text-xs">
                                        <input type="checkbox" :checked="formPenugasan.hari_kerja.includes(Number(hari))" @change="togglePenugasanHari(Number(hari))" class="rounded border-gray-300 text-brand-600">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-for="day in (formPenugasan.overrideHariKerja ? formPenugasan.hari_kerja : [])" :key="day">
                            <input type="hidden" name="hari_kerja[]" :value="day">
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                        <x-secondary-button type="button" @click="showPenugasanModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    {{-- Helper Script for TomSelect --}}
    <script>
        function shiftPenugasanForm() {
            return {
                pegawaiTipe: 'guru',
                initSelect(elem) {
                    if (window.TomSelect && elem) {
                        new TomSelect(elem, { create: false, sortField: { field: 'text', order: 'asc' } });
                    }
                }
            }
        }
    </script>
</x-app-layout>
