# Spec: Audit & Perbaikan Modul Pola Jam & Jam Pelajaran

**Tanggal**: 2026-09-11
**Branch**: `rbac-v2`
**Cakupan file**:
- `resources/views/portals/lembaga/akademik/pola-jam/{index,_modal-pola,_modal-edit-slot,_modal-assign-kelas}.blade.php`
- `resources/views/components/icon.blade.php` (tambah 1 case baru, TIDAK mengubah case lain)
- `app/Http/Controllers/Admin/PolaJamController.php`

## 1. Latar Belakang

Modul Pola Jam adalah *bell schedule* — template ritme harian sekolah (Pola Jam) berisi slot-slot Jam Pelajaran (urutan, jam mulai/selesai, label, jenis sesi), ditautkan ke satu/lebih `Kelas`. Backend (`CreatePolaJamAction`, `UpdatePolaJamAction`, `AssignKelasToPolaJamAction`, `CreateJamPelajaranAction`, `UpdateJamPelajaranAction`, validasi FormRequest) sudah diverifikasi solid lewat pembacaan kode — TIDAK ada perubahan domain layer di spec ini. Semua temuan murni presentational, dengan 1 temuan yang levelnya **regresi visual nyata** (bukan preferensi desain): ikon rusak.

## 2. Temuan & Perbaikan

### §2.1 [KRITIS] Ikon Rusak — Tampil Sebagai "?" di 5 Titik

**Diverifikasi langsung**: `resources/views/components/icon.blade.php` punya `@switch($name)` dengan daftar `@case` tertentu, dan `@default` sengaja merender ikon tanda-tanya (lingkaran + `?`) untuk nama yang tidak dikenal — "Nama ikon tidak dikenal — tampilkan placeholder yang terlihat, bukan diam-diam kosong" (komentar asli di file). Di-grep 5 nama yang dipakai `pola-jam/index.blade.php`, dan dikonfirmasi TIDAK SATUPUN ada di daftar `@case` yang valid:

| Dipakai di | Nama ikon rusak | Baris (index.blade.php) |
|---|---|---|
| Label "Tautan Kelas:" | `class` | 145 |
| Tombol "Duplikat" | `content_copy` | 118 |
| Header "Input Slot Jam Pelajaran Baru" | `playlist_add` | 176 |
| Tombol "Kelola Tautan" | `add_circle` | 166 |
| Toggle "Matriks Mingguan" | `grid_view` | 250 |

**Fix — 4 dari 5 diganti ke nama valid yang sudah ada** (dicek satu-satu cocok semantiknya):
- `class` → `school` (sudah ada, cocok untuk representasi "kelas/akademik")
- `playlist_add` → `assignment_add` (sudah ada, cocok untuk "tambah item ke daftar")
- `add_circle` → `groups` (sudah ada, lebih cocok untuk "Kelola Tautan" — aksi kelola relasi ke banyak kelas, bukan sekadar "tambah satu")
- `grid_view` → `data_table` (sudah ada, cocok persis untuk representasi tabel/matriks)

**1 dari 5 (`content_copy`) TIDAK PUNYA padanan semantik yang cocok** di daftar ikon yang ada — aksi "Duplikat" secara semantik butuh ikon copy/salin yang sebenarnya, memaksakan salah satu icon lain (misal `description`) akan menyesatkan. Tambahkan 1 case baru ke `resources/views/components/icon.blade.php`, ikuti gaya SVG yang sama (viewBox 24x24, stroke-width 1.8, rounded cap/join) dengan case-case lain di file itu — sisipkan di mana saja di antara case yang ada (urutan tidak signifikan secara fungsional):
```blade
    @case('content_copy')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        @break

```
**Catatan lintas-modul (BUKAN bagian scope spec ini, JANGAN diperbaiki di sini)**: `content_copy` ternyata JUGA dipakai (dan JUGA rusak) di `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php` (tombol "Salin dari Kelas Lain", sudah ada sebelum sesi ini). Menambahkan case `content_copy` ke komponen global otomatis MEMPERBAIKI tombol itu juga sebagai efek samping positif (komponen dipakai bersama) — tapi TIDAK PERLU ada perubahan lain di file `jadwal-pelajaran` untuk ini, cukup laporkan di ringkasan hasil kerja.

---

### §2.2 Tautan Kelas — Visual Clutter Saat Kelas Banyak

**Current** (`index.blade.php:150-161`): semua pill kelas (lintas tahun ajaran, aktif dan arsip campur) ditampilkan mentah dalam satu baris wrapping tanpa ringkasan/batas.

**Fix**: tambahkan ringkasan jumlah di header baris (dihitung dari koleksi `$pola->kelas` yang SUDAH di-fetch, TANPA query tambahan — controller `index()` sudah eager-load `kelas.tahunAjaran`), dan batasi tampilan default ke kelas tahun ajaran AKTIF saja, dengan tombol expand untuk kelas arsip:
```blade
@can('kelas.edit')
    <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3.5" x-data="{ tampilkanArsip: false }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1">
                    <x-icon name="school" class="h-3.5 w-3.5 text-gray-400" />
                    <span>Tautan Kelas:</span>
                </span>
                @php
                    $kelasAktif = $pola->kelas->filter(fn ($k) => $k->tahunAjaran?->status_aktif);
                    $kelasArsip = $pola->kelas->reject(fn ($k) => $k->tahunAjaran?->status_aktif);
                @endphp
                <span class="text-xs text-gray-600 font-medium">
                    {{ $kelasAktif->count() }} kelas aktif
                    @if ($kelasArsip->isNotEmpty())
                        &bull; {{ $kelasArsip->count() }} arsip
                    @endif
                </span>
            </div>
            <button type="button"
                    @click="openAssignModal({{ $pola->toJson() }}, {{ $pola->kelas->pluck('id')->values()->toJson() }}, '{{ route('admin.pola-jam.assign-kelas', $pola) }}')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition active:scale-95 shrink-0">
                <x-icon name="groups" class="h-4 w-4 text-brand-500" />
                <span>Kelola Tautan</span>
            </button>
        </div>

        @if ($pola->kelas->isEmpty())
            <p class="mt-2 text-xs text-gray-400 italic">Belum ada kelas yang ditautkan pada pola ini.</p>
        @else
            <div class="mt-2.5 flex flex-wrap gap-2">
                @foreach ($kelasAktif as $kelasTerikat)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white border-brand-200 text-brand-700 ring-1 ring-brand-500/10 border px-3 py-1 text-xs font-semibold shadow-2xs">
                        <span>{{ $kelasTerikat->nama }}</span>
                    </span>
                @endforeach
                @if ($kelasArsip->isNotEmpty())
                    <button type="button" @click="tampilkanArsip = !tampilkanArsip" class="inline-flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-3 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100 transition">
                        <span x-text="tampilkanArsip ? 'Sembunyikan arsip' : '+{{ $kelasArsip->count() }} arsip'"></span>
                    </button>
                @endif
            </div>
            @if ($kelasArsip->isNotEmpty())
                <div x-show="tampilkanArsip" x-cloak class="mt-2 flex flex-wrap gap-2">
                    @foreach ($kelasArsip as $kelasTerikat)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-200/60 border-gray-300 text-gray-600 border px-3 py-1 text-xs font-semibold shadow-2xs">
                            <span>{{ $kelasTerikat->nama }}</span>
                            <span class="text-[10px] opacity-70">(&bull; {{ $kelasTerikat->tahunAjaran->nama ?? 'N/A' }})</span>
                        </span>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endcan
```
(icon `class`→`school` dan `add_circle`→`groups` dari §2.1 sudah termasuk langsung di kode ini.)

---

### §2.3 Form Input Slot — Shortcut Hari, Preset Label, Live Duration

**Current** (`index.blade.php:180-227`): checkbox hari polos tanpa shortcut, input label teks kosong tanpa preset, tidak ada preview durasi, tombol Simpan di kolom `sm:col-span-1` sempit.

**Fix** — ganti `<form>` di `index.blade.php:180-227` (SELURUH isi form, termasuk `x-data` lokalnya):
```blade
<form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-4" x-data="{
    hariTerpilih: [],
    jamMulai: '',
    jamSelesai: '',
    get durasiMenit() {
        if (! this.jamMulai || ! this.jamSelesai) return null;
        const [h1, m1] = this.jamMulai.split(':').map(Number);
        const [h2, m2] = this.jamSelesai.split(':').map(Number);
        const menit = (h2 * 60 + m2) - (h1 * 60 + m1);
        return menit > 0 ? menit : null;
    },
    hariAktifValues: @js(collect($hariAktifPola)->pluck('value')->all()),
    pilihPreset(preset) {
        if (preset === 'semua') {
            this.hariTerpilih = [...this.hariAktifValues];
        } else if (preset === 'senin_kamis') {
            this.hariTerpilih = this.hariAktifValues.filter(h => ['senin', 'selasa', 'rabu', 'kamis'].includes(h));
        }
    }
}">
    @csrf
    <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label class="block text-xs font-semibold text-gray-700">Pilih Hari <span class="text-gray-400 font-normal">(bisa lebih dari satu)</span></label>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="pilihPreset('senin_kamis')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Senin–Kamis</button>
                <button type="button" @click="pilihPreset('semua')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Semua Hari</button>
                <button type="button" @click="hariTerpilih = []" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-400 hover:bg-gray-50 transition">Reset</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($hariAktifPola as $hari)
                <label class="flex cursor-pointer select-none items-center justify-center rounded-lg border px-3.5 py-1.5 text-xs font-semibold transition duration-150 active:scale-[0.97]"
                       :class="hariTerpilih.includes('{{ $hari->value }}') ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500/20 shadow-2xs' : 'border-gray-200 bg-gray-50/50 text-gray-600 hover:border-brand-300 hover:bg-white'">
                    <input type="checkbox" name="hari[]" value="{{ $hari->value }}" x-model="hariTerpilih" class="sr-only">
                    {{ $hari->label() }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Urutan Ke- <span class="text-error-500">*</span></label>
            <input type="number" name="urutan" placeholder="Ke-" min="1" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Mulai <span class="text-error-500">*</span></label>
            <input x-model="jamMulai" type="time" name="jam_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
            <input x-model="jamSelesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
            <p x-show="durasiMenit" class="mt-1 text-[11px] font-semibold text-brand-600">Durasi: <span x-text="durasiMenit"></span> menit</p>
        </div>
        <div class="sm:col-span-3">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Label Slot <span class="text-error-500">*</span></label>
            <input type="text" name="label" required placeholder="mis. Jam ke-1 / Istirahat" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500" list="preset-label-{{ $pola->id }}">
            <datalist id="preset-label-{{ $pola->id }}">
                <option value="Istirahat">
                <option value="Upacara Bendera">
                <option value="Sholat Dzuhur">
                <option value="Literasi">
                <option value="Sholat Dhuha">
            </datalist>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi</label>
            <select name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                <option value="1">Jam Belajar</option>
                <option value="0">Non-pelajaran</option>
            </select>
        </div>
        <div class="sm:col-span-12 flex justify-end">
            <x-primary-button type="submit" class="px-6 py-2 text-xs">
                Simpan Slot
            </x-primary-button>
        </div>
    </div>
</form>
```
**Keputusan desain** (dicatat eksplisit supaya tidak disalahpahami sebagai kelalaian):
- **Preset label pakai `<datalist>` native HTML**, BUKAN pill button seperti preset hari. Alasan: field Label adalah teks BEBAS (banyak sekolah punya nama sesi custom di luar 5 preset ini, misal "Kegiatan Ekstrakurikuler"), `<datalist>` memberi saran cepat TANPA mengunci pengguna hanya ke opsi preset — pill button cocok untuk field dengan domain nilai TERBATAS (seperti Hari), tidak cocok untuk field teks bebas.
- **Auto-increment urutan/jam TIDAK diimplementasikan** di spec ini (berbeda dari usulan laporan audit awal) — dikeluarkan dari scope, lihat §4 alasannya.
- **Tombol Simpan dipindah ke baris penuh (`sm:col-span-12`) di bawah grid**, bukan dipaksa masuk ke kolom sempit `sm:col-span-1` — memperbaiki masalah teks terpotong tanpa perlu mengubah lebar kolom lain.

---

### §2.4 Daftar Harian — Tab Navigasi, Format Waktu Tanpa Detik, Tombol Aksi

**Current** (`index.blade.php:257-313`): semua hari (Senin-Sabtu) dijabarkan berturut-turut ke bawah tanpa navigasi, waktu ditampilkan mentah `{{ $slot->jam_mulai }}`/`{{ $slot->jam_selesai }}` (berpotensi menampilkan detik `07:30:00` tergantung cast kolom — TIDAK KONSISTEN dengan Mode Matriks di baris 338/356 yang SUDAH benar pakai `Carbon::parse(...)->format('H:i')`), tombol Edit/Hapus berupa teks polos.

**Fix — tab navigasi per hari** (ganti pembuka blok Mode 1 di `index.blade.php:256-257`, tambah `hariAktif` ke `x-data` level `viewMode` yang sudah ada baris 232):
```blade
<div x-data="{ viewMode: 'list', hariAktif: '{{ $hariAktifPola->first()?->value }}' }" class="divide-y divide-gray-100 bg-white">
```
Ganti pembuka Mode 1 (`index.blade.php:257`) — tambahkan strip tab SEBELUM `@foreach`:
```blade
<div x-show="viewMode === 'list'">
    <div class="flex gap-1 overflow-x-auto border-b border-gray-100 bg-gray-50/30 px-6 py-2">
        @foreach ($hariAktifPola as $hariTab)
            @php $adaSlotHariIni = $pola->jamPelajaran->where('hari', $hariTab)->isNotEmpty(); @endphp
            <button type="button" @click="hariAktif = '{{ $hariTab->value }}'"
                :class="hariAktif === '{{ $hariTab->value }}' ? 'bg-white text-gray-900 shadow-2xs font-bold border-gray-200' : 'text-gray-500 hover:text-gray-800 font-medium border-transparent'"
                class="shrink-0 rounded-lg border px-3 py-1.5 text-xs transition {{ ! $adaSlotHariIni ? 'opacity-40' : '' }}">
                {{ $hariTab->label() }}
                @if ($adaSlotHariIni)
                    <span class="ml-1 text-[10px] text-gray-400">({{ $pola->jamPelajaran->where('hari', $hariTab)->count() }})</span>
                @endif
            </button>
        @endforeach
    </div>
    <div class="divide-y divide-gray-100">
        @foreach (\App\Enums\Hari::cases() as $hari)
            @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
            <div x-show="hariAktif === '{{ $hari->value }}'">
                @if ($slotHariIni->isEmpty())
                    <p class="px-6 py-8 text-center text-xs text-gray-400 italic">Belum ada slot untuk hari {{ $hari->label() }}.</p>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($slotHariIni as $slot)
                            <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3 transition hover:bg-gray-50/60">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700">{{ $slot->urutan }}</span>
                                    <div class="flex items-center gap-1.5 font-mono text-xs">
                                        <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-500/20">{{ \Carbon\Carbon::parse($slot->jam_mulai)->format('H:i') }}</span>
                                        <span class="text-gray-400">&rarr;</span>
                                        <span class="rounded bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">{{ \Carbon\Carbon::parse($slot->jam_selesai)->format('H:i') }}</span>
                                    </div>
                                    <span class="text-[10px] font-semibold text-gray-400">({{ \Carbon\Carbon::parse($slot->jam_mulai)->diffInMinutes(\Carbon\Carbon::parse($slot->jam_selesai)) }} mnt)</span>
                                    <span class="text-gray-300 hidden md:inline">&bull;</span>
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-bold text-gray-900">{{ $slot->label }}</span>
                                        @if ($slot->is_pelajaran)
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-[11px] font-semibold text-success-700 ring-1 ring-inset ring-success-600/20">Belajar</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 ring-1 ring-inset ring-gray-400/20">Non-pelajaran</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @can('jam-pelajaran.edit')
                                        <button type="button"
                                                @click="openEditSlot({{ $slot->toJson() }}, '{{ $slot->hari->value }}', '{{ route('admin.jam-pelajaran.update', $slot) }}')"
                                                class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                                            Edit
                                        </button>
                                    @endcan
                                    @can('jam-pelajaran.delete')
                                        <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\"?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-error-200 bg-error-50/30 px-2.5 py-1 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition">Hapus</button>
                                        </form>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
```
**Catatan**: header ringkasan per-hari lama (`bg-gray-50/40 ... {{ $hari->label() }} ... sesi terdaftar`) DIHILANGKAN karena fungsinya sudah digantikan tab (badge jumlah slot per hari sudah ada di tab, lihat `({{ ... count() }})`).

---

### §2.5 Matriks Mingguan — Koreksi Klaim Laporan Audit + Perbaikan Kecil

**Koreksi penting**: laporan audit awal menyebut "kolom kiri hanya menampilkan jam dari sampel hari pertama, sehingga di hari Jumat yang durasinya beda, informasi matriks jadi keliru dan menyesatkan." Setelah dibaca langsung (`index.blade.php:341-358`), klaim ini **BERLEBIHAN** — SETIAP SEL matriks (bukan cuma kolom kiri) SUDAH menampilkan jam mulai-selesai yang akurat milik sel itu sendiri (`{{ \Carbon\Carbon::parse($cellSlot->jam_mulai)->format('H:i') }} - {{ ...jam_selesai... }}`, baris 356). Yang benar-benar jadi masalah HANYA baris ringkasan di kolom kiri (`$sampleSlot`, baris 333-338) yang mengambil slot PERTAMA yang cocok urutan-nya (biasanya hari Senin) sebagai representasi — ini SEKADAR label ringkas kolom, BUKAN sumber informasi utama (yang tetap akurat per-sel). Dampaknya jauh lebih kecil dari yang dilaporkan.

**Fix minimal** (bukan redesain besar): ganti label kolom kiri dari waktu spesifik jadi label netral "bervariasi per hari", menghindari kesan itu berlaku untuk semua kolom:
```blade
{{-- current baris 335-339 --}}
<td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">
    <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
    <div class="text-[11px] text-gray-400 font-medium mt-0.5">lihat per hari &rarr;</div>
</td>
```
(Menghapus `$sampleSlot` sepenuhnya dari kolom kiri — variabel itu jadi tidak terpakai lagi di blok ini, boleh dihapus dari `@php` baris 332-334 kalau tidak dipakai di tempat lain dalam loop yang sama; per-baca-ulang blok itu HANYA dipakai untuk kolom kiri ini, aman dihapus.)

**Pembedaan warna Jam Belajar vs Non-KBM** — SUDAH ADA (baris 348: `$cellSlot->is_pelajaran ? 'bg-brand-50/40 border-brand-200 ...' : 'bg-gray-50 border-gray-200 ...'`). Tidak perlu perubahan, laporan audit tidak salah soal ini tapi juga bukan gap — sudah terimplementasi.

---

### §2.6 Modal Kelola Tautan Kelas — Pencarian & Pilih Semua

**Current** (`_modal-assign-kelas.blade.php`): sudah dikelompokkan per tahun ajaran dan sudah ada badge peringatan kelas-tertaut-pola-lain (KEDUANYA sudah bagus, TIDAK perlu diulang) — tapi TIDAK ADA input pencarian maupun tombol pilih-semua per grup.

**Fix** — tambah state `pencarianKelas` di `x-data` level modal (`index.blade.php` baris 10-11, `formAssign`), dan render input pencarian + tombol pilih-semua per grup di `_modal-assign-kelas.blade.php`:

Tambahkan di `index.blade.php`, DI DALAM objek `x-data` yang sudah ada (baris 10-11), tambah 1 properti baru setelah `formAssign`:
```blade
        showModalAssign: false,
        formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },
        pencarianKelas: '',
```
Reset `pencarianKelas` di `openAssignModal()` (`index.blade.php` baris 39-48), tambah 1 baris:
```blade
        openAssignModal(pola, kelasIds, url) {
            this.formAssign = {
                polaId: pola.id,
                polaNama: pola.nama,
                lembagaId: pola.lembaga_id || null,
                selectedKelasIds: Array.from(kelasIds || []).map(Number),
                actionUrl: url
            };
            this.pencarianKelas = '';
            this.showModalAssign = true;
        }
```
Di `_modal-assign-kelas.blade.php`, tambahkan input pencarian SETELAH header (`<div class="flex items-center justify-between pb-3.5 ...">...</div>`, sebelum `<form>` baris 27):
```blade
<div class="mt-3 relative">
    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
    <input type="text" x-model="pencarianKelas" placeholder="Cari nama kelas..." class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
</div>
```
Tambah filter pencarian dan tombol pilih-semua per grup — ganti header grup (`_modal-assign-kelas.blade.php:41-54`):
```blade
<div
    x-show="(formAssign.lembagaId === null || {{ $classes->pluck('lembaga_id')->unique()->values()->toJson() }}.includes(formAssign.lembagaId)) && (pencarianKelas === '' || {{ $classes->pluck('nama')->values()->toJson() }}.some(n => n.toLowerCase().includes(pencarianKelas.toLowerCase())))"
    class="rounded-xl border border-gray-200 overflow-hidden"
>
    <div class="bg-gray-50/80 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between gap-2">
        <span class="font-display text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
            @if (str_contains($groupTitle, '(Aktif)'))
                <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                <span class="text-success-800">{{ $groupTitle }}</span>
            @else
                <span class="inline-block h-2 w-2 rounded-full bg-gray-300"></span>
                <span>{{ $groupTitle }}</span>
            @endif
        </span>
        <button type="button"
            @click="
                const idGrup = {{ $classes->pluck('id')->values()->toJson() }};
                const semuaTerpilih = idGrup.every(id => formAssign.selectedKelasIds.includes(id));
                formAssign.selectedKelasIds = semuaTerpilih
                    ? formAssign.selectedKelasIds.filter(id => !idGrup.includes(id))
                    : [...new Set([...formAssign.selectedKelasIds, ...idGrup])];
            "
            class="shrink-0 text-[11px] font-semibold text-brand-600 hover:text-brand-800 transition"
        >
            Pilih Semua di Grup Ini
        </button>
    </div>
```
(Penutup `</div>` grup dan isi checkbox `@foreach ($classes as $kelasOpsi)` TIDAK berubah dari kode saat ini — tetap seperti aslinya, HANYA header grup dan wrapper `x-show` yang berubah.)

---

### §2.7 Modal Tambah/Edit Pola Jam & Edit Slot — Konteks Lembaga, `<x-select>`, Durasi

**`_modal-pola.blade.php`**: tambah badge lembaga di header modal (hanya render kalau `isYayasan` DAN `activeLembaga` ada — data ini SUDAH tersedia di scope `index.blade.php` yang meng-`@include` partial ini, jadi otomatis ter-inherit tanpa perlu passing eksplisit):
```blade
<div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
    <div>
        <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
            <x-icon name="schedule" class="h-5 w-5 text-brand-500" />
            <span x-text="modalPolaMode === 'create' ? 'Tambah Pola Jam Baru' : 'Edit Nama Pola Jam'"></span>
        </h3>
        @if (($isYayasan ?? false) && ($activeLembaga ?? null))
            <p class="mt-0.5 text-xs text-gray-500">Untuk lembaga <strong class="font-semibold text-gray-700">{{ $activeLembaga->nama }}</strong>.</p>
        @endif
    </div>
    <button @click="showModalPola = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
        <x-icon name="cancel" class="h-5 w-5" />
    </button>
</div>
```

**`_modal-edit-slot.blade.php`**: ganti `<select name="hari">` dan `<select name="is_pelajaran">` (baris 31 dan 61) jadi `<x-select>`, tambah live duration preview:
```blade
{{-- ganti baris 30-35 --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Hari <span class="text-error-500">*</span></label>
    <x-select x-model="formSlot.hari" name="hari" required>
        @foreach (\App\Enums\Hari::cases() as $hariOpsi)
            <option value="{{ $hariOpsi->value }}">{{ $hariOpsi->label() }}</option>
        @endforeach
    </x-select>
</div>
```
```blade
{{-- ganti baris 59-65 --}}
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi <span class="text-error-500">*</span></label>
    <x-select x-model="formSlot.is_pelajaran" name="is_pelajaran">
        <option :value="1">Jam Belajar</option>
        <option :value="0">Non-pelajaran</option>
    </x-select>
</div>
```
Tambah duration preview di bawah field Jam Selesai (`_modal-edit-slot.blade.php` baris 48-51, ganti):
```blade
<div>
    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
    <input x-model="formSlot.jam_selesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 font-mono text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
    <p x-show="formSlot.jam_mulai && formSlot.jam_selesai" class="mt-1 text-[11px] font-semibold text-brand-600" x-text="(() => {
        if (!formSlot.jam_mulai || !formSlot.jam_selesai) return '';
        const [h1, m1] = formSlot.jam_mulai.split(':').map(Number);
        const [h2, m2] = formSlot.jam_selesai.split(':').map(Number);
        const menit = (h2 * 60 + m2) - (h1 * 60 + m1);
        return menit > 0 ? `Durasi: ${menit} menit` : '';
    })()"></p>
</div>
```
(`<x-select>` sudah punya styling standar bawaan komponennya sendiri — TIDAK perlu class tambahan mengulang `rounded-lg border-gray-200 ...` seperti native `<select>` lama.)

---

### §2.8 KPI Cards Ringkas di Header Index

**Fix** — tambah 2 KPI (BUKAN 3 seperti usulan awal — "Rata-rata Jam Belajar/Hari" DIKELUARKAN, lihat §4 alasannya), dihitung dari `$polaJamList` yang SUDAH di-fetch controller (`with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])`) — TANPA query tambahan:
```blade
{{-- sisipkan SETELAH blok Header & Breadcrumb (index.blade.php baris 88), SEBELUM "Daftar Card Pola Jam" --}}
@php
    $totalPola = $polaJamList->count();
    $totalKelasTertaut = $polaJamList->flatMap->kelas->pluck('id')->unique()->count();
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Pola Jam</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalPola }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Kelas Tertaut</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalKelasTertaut }}</p>
    </div>
</div>
```

---

## 3. Ringkasan Perubahan File

| File | Jenis Perubahan |
|---|---|
| `resources/views/components/icon.blade.php` | Tambah 1 `@case('content_copy')` baru, tidak mengubah case lain |
| `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` | Icon fix, KPI, tautan kelas ringkas+expand, form slot (shortcut hari+preset label+durasi), tab navigasi daftar harian, koreksi label kolom kiri matriks, wiring pencarian modal assign |
| `resources/views/portals/lembaga/akademik/pola-jam/_modal-pola.blade.php` | Badge konteks lembaga |
| `resources/views/portals/lembaga/akademik/pola-jam/_modal-edit-slot.blade.php` | `<x-select>`, live duration preview |
| `resources/views/portals/lembaga/akademik/pola-jam/_modal-assign-kelas.blade.php` | Input pencarian, tombol pilih-semua per grup |
| `app/Http/Controllers/Admin/PolaJamController.php` | TIDAK DIUBAH — semua data yang dibutuhkan (`kelas.tahunAjaran`, `jamPelajaran`, `lembaga`) SUDAH di-eager-load `index()` saat ini |

## 4. Di Luar Cakupan (Sengaja Tidak Dikerjakan)

- **Auto-increment urutan & jam mulai** (usul laporan audit §3) — dikeluarkan. Alasan: kalau slot terakhir hari Senin selesai jam `08:05` lalu sistem otomatis menyarankan urutan+1 & mulai `08:05` untuk slot BARU, itu MENGASUMSIKAN slot baru selalu lanjutan hari yang SAMA dan urutan yang SAMA — padahal form ini dipakai untuk multi-hari sekaligus (checkbox banyak hari) DAN admin sering sengaja mengisi slot di luar urutan (mis. isi Istirahat belakangan). Auto-suggest yang salah asumsi based lebih berisiko menyesatkan (silently prefill nilai yang salah) daripada field kosong yang jelas kosong. Kalau dibutuhkan, ini didesain terpisah dengan mempertimbangkan hari-mana yang jadi acuan, bukan ditempelkan sebagai quick-win di spec ini.
- **Redesain warna/kontras Jam Belajar vs Non-KBM di Matriks** — SUDAH ADA, dikonfirmasi lewat pembacaan kode (`index.blade.php:348`), bukan gap.
- **KPI "Rata-rata Jam Belajar per Hari"** (usul laporan audit §8) — dikeluarkan. Definisi "jam efektif KBM per hari" ambigu untuk pola dengan jumlah hari aktif berbeda per kelas/tingkat (PAUD vs SD vs SMP bisa beda hari aktif), dan pola jam TIDAK selalu 1-lembaga-1-pola (bisa banyak pola per lembaga, tiap pola punya cakupan hari-aktif sendiri lewat `Hari::aktifDari()`) — menghitung rata-rata yang bermakna butuh definisi bisnis yang belum disepakati, bukan sekadar agregasi angka. 2 KPI yang tersisa (Total Pola, Kelas Tertaut) tetap bernilai dan TIDAK ambigu.
- **Klaim "matriks kolom kiri menyesatkan"** — dikoreksi jadi temuan minor (§2.5), BUKAN bug data seperti klaim awal. Per-sel matriks SUDAH akurat.
- **Perubahan domain layer** (`CreatePolaJamAction`, `UpdatePolaJamAction`, `AssignKelasToPolaJamAction`, `CreateJamPelajaranAction`, `UpdateJamPelajaranAction`, FormRequest validation) — semua sudah diverifikasi solid lewat pembacaan kode, di luar scope spec ini sepenuhnya.
- **Perbaikan `content_copy` di halaman `jadwal-pelajaran`** — otomatis ikut terbenerin sebagai efek samping (komponen ikon dipakai bersama), TAPI tidak ada perubahan lain yang disengaja untuk modul itu, di luar scope spec ini.

## 5. Self-Review (5 Putaran)

**Putaran 1 — Verifikasi klaim laporan audit vs kode asli**: SEMUA klaim ikon rusak diverifikasi via grep + baca `icon.blade.php` langsung (bukan percaya laporan) — akurat, 5/5 nama memang tidak ada di `@case`. Klaim "matriks kolom kiri menyesatkan" (§2.5) diverifikasi BERLEBIHAN — per-sel matriks sudah akurat, dikoreksi jadi temuan minor. Klaim "warna Jam Belajar vs Non-KBM belum dibedakan" salah total — SUDAH ada di kode, dikeluarkan dari scope (§4).

---

## 6. Addendum Update: Tooltip Pintera, KPI Card SVG Icons, & CRUD Tanpa Reload

Berdasarkan review lanjutan user:
1. **Poin 1: Tooltip Menggunakan Style Standar Pintera (`<x-tooltip>`)**
   - Mengganti semua atribut native browser `title="..."` dengan komponen resmi `<x-tooltip text="...">` (dark slate badge `bg-[#1E293B]`, teleport Alpine `x-anchor`).
   - Diterapkan pada:
     - Tombol disabled *+ Tambah Pola Jam* (saat aktor yayasan belum memilih lembaga aktif).
     - Tombol aksi per-card: *Duplikat* (`Salin / Duplikasi Pola Jam`), *Edit Nama* (`Edit Nama Pola Jam`), *Hapus* (`Hapus Pola Jam`).
     - Tombol aksi per-slot (Daftar Harian): *Edit* (`Edit Slot Jam Pelajaran`), *Hapus* (`Hapus Slot Jam Pelajaran`).

2. **Poin 2: Icon SVG pada KPI Cards Ringkas**
   - Memperbarui 2 KPI Card (Total Pola Jam & Kelas Tertaut) agar menggunakan pola visual KPI Pintera:
     - Container: `flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated`.
     - Card Total Pola Jam: Badge icon `bg-brand-50 text-brand-600` dengan `<x-icon name="schedule" class="h-5 w-5" />`, angka tebal, subtitle pill `Pola Jadwal`.
     - Card Kelas Tertaut: Badge icon `bg-blue-50 text-blue-600` dengan `<x-icon name="school" class="h-5 w-5" />`, angka tebal, subtitle pill `Kelas Aktif & Arsip`.

3. **Poin 3: Alur CRUD Tanpa Reload Seluruh Halaman (AJAX / Fetch + Toast)**
   - **Tujuan**: Mencegah browser refresh penuh, hilangnya posisi scroll, dan reset tab ketika pengguna melakukan aksi mutasi data.
   - **Struktur Blade**:
     - Memisahkan blok KPI cards dan loop kartu pola jam ke partial `resources/views/portals/lembaga/akademik/pola-jam/_daftar.blade.php`.
     - Di `index.blade.php`, bungkus dengan `<div x-ref="cardsContainer" id="pola-jam-cards-container">@include('..._daftar')</div>`.
     - Modals (`_modal-pola`, `_modal-edit-slot`, `_modal-assign-kelas`) tetap berada di root `index.blade.php` agar tidak ikut ter-render ulang saat AJAX reload.
   - **Backend Controllers**:
     - `PolaJamController::index()`: Mengembalikan partial `_daftar.blade.php` jika `$request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest'`.
     - `PolaJamController` (`store`, `update`, `destroy`, `assignKelas`, `duplicate`): Mendukung dual response `RedirectResponse|JsonResponse`. Jika request AJAX/JSON, mengembalikan `{ "status": "success", "message": "..." }` (atau JSON 422 jika error validasi).
     - `JamPelajaranController` (`store`, `update`, `destroy`): Mendukung dual response `RedirectResponse|JsonResponse`. Jika request AJAX/JSON, mengembalikan JSON 200/201 atau JSON 422.
   - **Frontend Alpine.js**:
     - Menyediakan method `submitAjaxForm(formEl, successCallback)` dan `muatUlangDaftar()` di root `x-data`.
     - Form submit dicegat via `@submit.prevent="submitAjaxForm($el, ...)"`.
     - Feedback sukses / error ditampilkan via sistem Toast global Pintera (`window.Alpine.store('toast').push('success'|'error', msg)`).
     - Setelah mutasi berhasil, modal otomatis tertutup (jika form modal) dan `muatUlangDaftar()` memperbarui inner HTML `#pola-jam-cards-container` lalu memanggil `window.Alpine.initTree(container)` untuk re-binding state Alpine secara mulus.
