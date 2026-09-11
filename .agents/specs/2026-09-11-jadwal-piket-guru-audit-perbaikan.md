# Spec: Perbaikan Audit Menyeluruh Jadwal Piket Guru & Akses Guru Pengganti

> **Tanggal**: 11 September 2026
> **Konteks**: Audit menyeluruh (backend/security/bisnis + UI/UX/frontend/wording) pada fitur Jadwal Piket Guru (`app/Http/Controllers/Admin/{JadwalPiketMingguanController,PiketHarianController}.php`, `app/Domains/Akademik/Actions/Piket/*`, `app/Domains/Akademik/Services/PiketAccessChecker.php`, `Guru\Akademik\JurnalKbmController`). Fitur ini adalah **gerbang keamanan** (menentukan siapa boleh mengisi jurnal/presensi sesi guru lain), sudah punya spec desain sebelumnya (`.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md`) yang diikuti dengan sangat setia oleh implementasi — audit ini menemukan gap yang TIDAK diantisipasi spec lama. TIDAK menyentuh `app/Domains/Workflow/*`.

---

## 1. Latar Belakang

Implementasi fitur ini terverifikasi **sangat setia** ke spec 2026-09-06 — 11 dari 12 skenario test wajib di spec itu ada dan lolos. Audit sesi ini (backend/security + UI/UX, verifikasi langsung ke kode, bukan cuma laporan) menemukan 7 gap yang genuinely baru: 1 bug sistemik timezone yang berdampak langsung ke gerbang akses ini, 1 gap UX yang berisiko salah kira (guru piket tidak tahu dia sedang mengisi kelas orang lain), 1 gap visibilitas admin, 1 bug akuntabilitas data, 1 bug kebocoran metadata jadwal, 1 gap konsistensi UI (konfirmasi hapus), dan 1 gap cakupan test.

---

## 2. Temuan & Perbaikan

### 2.1 [HIGH] Timezone UTC vs WIB — Guru Piket Sah Bisa Ditolak di Jam Sekolah Pagi

**Lokasi**: `config/app.php:68` (`'timezone' => 'UTC'`, tidak di-override `APP_TIMEZONE` di `.env`), dipakai transitif oleh `app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php:33`, `RegenerateJadwalPiketHarianAction.php:33`, `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php:80,86`.

**Akar masalah**: server berjalan dengan timezone `UTC`. WIB = UTC+7. Antara pukul **00:00–06:59 WIB** (jam sekolah mulai berkegiatan, ±06:30-07:00 WIB), `now()` versi server (UTC) masih menganggap ini HARI SEBELUMNYA. Konsekuensi konkret: baris `PiketHarian` untuk "hari ini (WIB)" belum dianggap valid oleh `PiketAccessChecker`/query terkait piket selama jendela ~7 jam itu — guru piket yang SAH bisa ditolak akses tepat di jam paling krusial (pagi hari sekolah mulai).

**Kenapa BUKAN mengubah `config/app.php`/`.env` global**: mengubah timezone aplikasi secara global berdampak ke SELURUH sistem (setiap `created_at`/`updated_at`, setiap perbandingan tanggal di modul lain, data yang sudah tersimpan dengan asumsi UTC) — perubahan sebesar itu butuh audit tersendiri yang jauh di luar cakupan fitur piket, lihat §3.

**Perbaikan (scoped, minimal, aman)**: di titik-titik yang menentukan "hari ini" untuk keperluan piket SAJA, panggil `now('Asia/Jakarta')` alih-alih `now()` bare — pola yang SUDAH established di codebase ini (`BriSnapClient.php:35` sudah eksplisit pakai `Asia/Jakarta` untuk kebutuhan lokal serupa tanpa mengubah config global).

`GenerateJadwalPiketHarianAction.php`, baris 33 saat ini:
```php
$tanggalMulai = $semester->tanggal_mulai->isPast() ? now()->startOfDay() : $semester->tanggal_mulai;
```
Ubah jadi:
```php
$tanggalMulai = $semester->tanggal_mulai->isPast() ? now('Asia/Jakarta')->startOfDay() : $semester->tanggal_mulai;
```

`RegenerateJadwalPiketHarianAction.php`, baris 33 saat ini:
```php
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('tanggal', '<=', $semester->tanggal_selesai)
```
Ubah jadi:
```php
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now('Asia/Jakarta')->toDateString())
                ->where('tanggal', '<=', $semester->tanggal_selesai)
```

`JurnalKbmController.php`, baris 76-89 (method `index()`) — perbaikan ini DIGABUNG dengan §2.6 (mismatch tanggal), lihat kode lengkap final di §2.6 di bawah (kedua isu ada di blok kode yang sama, diperbaiki dalam 1 perubahan supaya tidak ada 2 patch bertumpuk).

### 2.2 [HIGH] Halaman Isi Jurnal Tidak Menandai "Mode Piket" — Risiko Salah Kira

**Lokasi**: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php:18-28` (header), `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php:143-161` (method `show()`)

**Akar masalah**: dikonfirmasi lewat pembacaan penuh `show.blade.php` — TIDAK ADA satu pun referensi ke `$sesi->guru` atau kata "piket". Di halaman daftar (`index.blade.php`), pembeda visual guru piket vs guru pemilik sudah baik (badge amber, tombol "Isi Sebagai Piket"). Tapi begitu guru piket klik masuk ke form isi jurnal sesungguhnya, SEMUA penanda itu hilang — guru piket berpotensi salah kira ini kelasnya sendiri, padahal `SesiPembelajaran.diisi_oleh_guru_id` terekam otomatis di background.

**Perbaikan**: eager-load relasi `guru` di `show()`, tampilkan banner kalau guru yang login BUKAN pemilik asli sesi.

`JurnalKbmController.php::show()`, baris 148 saat ini:
```php
        $sesi->loadMissing('kelas.tahunAjaran');
```
Ubah jadi:
```php
        $sesi->loadMissing('kelas.tahunAjaran', 'guru');
```

`show.blade.php`, SETELAH blok `@if ($terkunci)` (baris 11-16 saat ini), SEBELUM blok "Header & Breadcrumb" (baris 18), sisipkan:
```blade
        @if ($sesi->guru_id !== (auth()->user()->guru->id ?? null))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Anda mengisi sebagai Guru Piket untuk kelas milik {{ $sesi->guru?->nama ?? 'guru lain' }}.</p>
                <p class="mt-1 text-xs">Data yang Anda isi akan tercatat sebagai diisi oleh Anda (piket), bukan guru pemilik asli sesi ini.</p>
            </div>
        @endif
```

### 2.3 [HIGH] Admin Buta Total Terhadap Hasil Generate/Regenerate `PiketHarian` Otomatis

**Lokasi**: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php:33-38` (method `index()`), `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`

**Akar masalah**: satu-satunya tabel `PiketHarian` yang ditampilkan di UI di-filter KETAT `where('sumber', 'override_manual')` — baris `dari_jadwal_mingguan` (hasil generate otomatis, MAYORITAS data piket harian nyata) TIDAK PERNAH muncul di UI manapun. Admin tidak punya cara memverifikasi "apakah Budi memang terjadwal piket tiap Senin sepanjang semester" tanpa query database langsung.

**Perbaikan**: tambah query BARU (aditif, TIDAK mengganti `overrides` yang sudah ada dan dipakai form override existing) yang mengambil SEMUA `PiketHarian` mendatang (kedua sumber), tampilkan di tabel baru read-only (kecuali baris `override_manual` yang tetap bisa dihapus lewat form yang sudah ada — TIDAK diduplikasi kontrol hapusnya di tabel baru ini, cukup badge sumber + info).

`JadwalPiketMingguanController.php::index()`, baris 25-41 saat ini:
```php
    public function index(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => JadwalPiketMingguan::where('lembaga_id', $lembagaId)->with(['guru', 'semester.tahunAjaran'])->orderBy('hari')->get(),
            'overrides' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('sumber', 'override_manual')
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->get(),
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderByNama()->get(),
        ]);
    }
```
Ubah jadi (tambah key `piketHarianMendatang`, sisanya TIDAK berubah):
```php
    public function index(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => JadwalPiketMingguan::where('lembaga_id', $lembagaId)->with(['guru', 'semester.tahunAjaran'])->orderBy('hari')->get(),
            'overrides' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('sumber', 'override_manual')
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->get(),
            'piketHarianMendatang' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->limit(60)
                ->get(),
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderByNama()->get(),
        ]);
    }
```

(`limit(60)` — batas wajar ~2 bulan ke depan kalau piket harian 1x/minggu per guru dikali banyak guru, mencegah tabel meledak untuk lembaga besar; ini murni tampilan read-only, TIDAK memengaruhi logic Generate/Regenerate yang tetap query tanpa limit.)

`index.blade.php` — tambah SEKSI BARU setelah seksi "Override Manual Piket Harian" yang sudah ada (setelah penutup `</div>` seksi itu, SEBELUM penutup `</div>` container utama):
```blade
        {{-- Seksi Kalender Piket Harian Mendatang (Semua Sumber, Read-Only) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="border-b border-gray-150 pb-3">
                <h2 class="font-display text-base font-bold text-gray-900">Kalender Piket Harian Mendatang</h2>
                <p class="text-xs text-gray-500 mt-0.5">Hasil generate otomatis dari Jadwal Piket Mingguan di atas, digabung dengan Override Manual. Maksimal 60 baris ke depan ditampilkan. Baris "Otomatis" TIDAK bisa dihapus langsung dari sini — ubah lewat Jadwal Piket Mingguan di atas.</p>
            </div>

            @if ($piketHarianMendatang->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-left text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5">Tanggal</th>
                                <th class="px-4 py-2.5">Guru Piket</th>
                                <th class="px-4 py-2.5">Sumber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-150 bg-white">
                            @foreach ($piketHarianMendatang as $item)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ \Carbon\Carbon::parse($item->tanggal)->isoFormat('dddd, D MMMM Y') }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $item->guru?->nama ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($item->sumber === 'override_manual')
                                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-700">Manual</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-700">Otomatis</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-500">Belum ada baris piket harian mendatang. Buat Jadwal Piket Mingguan di atas untuk mulai generate otomatis.</p>
            @endif
        </div>
```

### 2.4 [MEDIUM] `diisi_oleh_guru_id` Ditimpa Tanpa Syarat — Jejak Akuntabilitas Hilang

**Lokasi**: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php:21-24`

**Akar masalah**: kode saat ini SELALU `update(['diisi_oleh_guru_id' => $diisiOlehGuruId])` — termasuk mengoverwrite jadi `null` kapan pun guru pemilik asli membuka & submit ulang sesi yang sama. Spec desain (2026-09-06 §3.3) menulis kondisional ("Kalau tidak null, ..."), mengisyaratkan field ini seharusnya HANYA disentuh saat memang ada nilai baru dari guru piket — bukan ditimpa balik ke null kapan pun pemilik menyentuhnya lagi.

**Skenario**: guru piket X isi jurnal sesi guru Y hari Senin (`diisi_oleh_guru_id = X`, benar). Beberapa hari kemudian (masih dalam jendela edit), guru pemilik Y sendiri buka sesi yang sama untuk koreksi kecil → field itu tertimpa jadi `null` → jejak "data ini awalnya diinput guru piket" hilang permanen, padahal ini kolom akuntabilitas yang eksplisit disiapkan untuk kebutuhan Fase 2 (pelaporan/Dapodik).

**Perbaikan**: hanya sertakan `diisi_oleh_guru_id` di payload `update()` kalau nilainya TIDAK null (guru piket yang submit) — kalau guru pemilik asli yang submit ($diisiOlehGuruId null), JANGAN sentuh kolom itu sama sekali, biarkan nilai lama (kalau ada, dari pengisian piket sebelumnya) tetap ada.

Baris 21-24 saat ini:
```php
            $sesi->update([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ]);
```
Ubah jadi:
```php
            $sesi->update(array_filter([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ], fn ($value, $key) => $key !== 'diisi_oleh_guru_id' || $value !== null, ARRAY_FILTER_USE_BOTH));
```

**Catatan desain**: ini artinya begitu sebuah sesi PERNAH diisi guru piket, kolom `diisi_oleh_guru_id` akan TETAP menunjuk ke guru piket itu selamanya (bahkan setelah pemilik asli mengedit ulang) — SENGAJA, karena tujuan kolom ini adalah jejak "siapa yang PERTAMA/PERNAH mengisi materi sebagai pengganti", bukan "siapa yang terakhir menyentuh". Kalau nanti ternyata perilaku yang diinginkan berbeda (mis. field ini harus mencerminkan submitter TERAKHIR, bukan pernah-pernah), itu perlu keputusan produk eksplisit — spec 2026-09-06 tidak cukup detail soal ini, jadi perbaikan ini memilih interpretasi yang PALING AMAN (tidak kehilangan jejak), bukan yang paling "clean".

### 2.5 [MEDIUM] Mismatch Tanggal di `JurnalKbmController::index()` — Kebocoran Metadata Jadwal

**Lokasi**: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php:76-89`

**Akar masalah**: baris 80 (cek kelayakan piket) pakai `now()->toDateString()` (anchor ke HARI INI sungguhan), tapi baris 86 (daftar sesi yang ditampilkan di seksi "Sesi Piket Hari Ini") pakai `$hariIni` (variabel `$tanggal` dari query string `?tanggal=`, BISA tanggal lampau — `index()` cuma menolak tanggal MASA DEPAN, bukan masa lalu). Guru piket HARI INI yang membuka `?tanggal=<3 hari lalu>` akan melihat seksi "Sesi Piket Hari Ini" MUNCUL (karena memang piket hari ini), tapi isinya daftar sesi guru lain untuk 3 HARI LALU — bukan wewenangnya untuk tanggal itu. Ini kebocoran metadata jadwal (nama guru/kelas/mapel orang lain) — bukan kebocoran data siswa/nilai (tulis tetap diblokir benar oleh `PiketAccessChecker` yang mengecek tanggal SESI asli, bukan tanggal browse).

**Perbaikan**: SATUKAN dengan §2.1 (timezone) — kedua query WAJIB anchor ke "hari ini WIB sungguhan", TIDAK PERNAH ke `$hariIni` (tanggal yang sedang di-browse). Seksi "Sesi Piket Hari Ini" secara desain memang HARUS selalu tentang hari ini, independen dari tanggal apa pun yang sedang dilihat guru di halaman itu.

Baris 76-90 saat ini:
```php
        $sesiPiket = null;
        if ($guru) {
            $piketHariIni = PiketHarian::where('lembaga_id', $guru->lembaga_id)
                ->where('guru_id', $guru->id)
                ->where('tanggal', now()->toDateString())
                ->exists();

            if ($piketHariIni) {
                $sesiPiket = SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)
                    ->where('guru_id', '!=', $guru->id)
                    ->whereDate('tanggal', $hariIni)
                    ->with('kelas.tahunAjaran', 'mataPelajaran', 'guru')
                    ->get();
            }
        }
```
Ubah jadi:
```php
        $sesiPiket = null;
        if ($guru) {
            $tanggalHariIniSungguhan = now('Asia/Jakarta')->toDateString();
            $piketHariIni = PiketHarian::where('lembaga_id', $guru->lembaga_id)
                ->where('guru_id', $guru->id)
                ->where('tanggal', $tanggalHariIniSungguhan)
                ->exists();

            if ($piketHariIni) {
                $sesiPiket = SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)
                    ->where('guru_id', '!=', $guru->id)
                    ->whereDate('tanggal', $tanggalHariIniSungguhan)
                    ->with('kelas.tahunAjaran', 'mataPelajaran', 'guru')
                    ->get();
            }
        }
```

### 2.6 [MEDIUM] Konfirmasi Hapus Pakai `confirm()` Native Browser, Bukan `confirmDialog` Standar Proyek

**Lokasi**: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php:34,90`

**Akar masalah**: `onsubmit="return confirm('Hapus jadwal piket ini?')"` dan `onsubmit="return confirm('Hapus override manual ini?')"` — satu-satunya modul di seluruh aplikasi yang masih pakai popup browser polos, bukan `confirmDialog(...)` custom bertema yang konsisten dipakai modul lain (Roles, Tahun Ajaran, dll — lihat pola persis di `resources/views/admin/roles/_daftar.blade.php:43` dan `resources/views/admin/tahun-ajaran/index.blade.php:157`). Juga tidak menjelaskan efek samping penghapusan (baris `PiketHarian` terkait akan ikut ter-regenerate).

**Perbaikan** — baris 34 saat ini:
```blade
                                <form method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="inline" onsubmit="return confirm('Hapus jadwal piket ini?')">
```
Ubah jadi:
```blade
                                <form method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="inline" @submit.prevent="confirmDialog('Hapus Jadwal Piket?', @js('Piket ' . ($jadwal->guru?->nama ?? 'guru ini') . ' pada hari ' . ($namaHari[$jadwal->hari] ?? $jadwal->hari) . ' akan dihapus. Baris piket harian mendatang yang terkait juga akan ikut disesuaikan otomatis.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })">
```

Baris 90 saat ini:
```blade
                                        <form method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="inline" onsubmit="return confirm('Hapus override manual ini?')">
```
Ubah jadi:
```blade
                                        <form method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="inline" @submit.prevent="confirmDialog('Hapus Override Piket?', @js('Override piket manual untuk ' . \Carbon\Carbon::parse($override->tanggal)->isoFormat('D MMMM Y') . ' akan dihapus.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })">
```

### 2.7 [MEDIUM] Test Gap — `resolveKartu()` Tidak Pernah Diuji untuk Guru Piket

**Lokasi**: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php`

**Akar masalah**: 4 test existing di file ini SEMUANYA memakai guru PEMILIK sesi. Spec 2026-09-06 §4 skenario 12 secara eksplisit meminta regresi "Scan Presensi Kartu Digital Siswa tetap berfungsi untuk guru pemilik MAUPUN guru piket" — klaim ini TIDAK PERNAH benar-benar diverifikasi test. Secara kode, `resolveKartu()` memang reuse `authorizeMilikGuru()` yang sudah benar mendukung piket (dikonfirmasi lewat audit), tapi tanpa test eksplisit, regresi di masa depan pada titik ini tidak akan terdeteksi otomatis.

**Perbaikan**: tambah 1 test baru memakai pola helper `siapkanSesiDanGuruPiketUntukAksesTest()` yang SUDAH ADA di `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` (reuse, jangan duplikasi setup).

---

## 3. Item Sengaja Tidak Masuk Scope

- **Mengubah `config/app.php`/`APP_TIMEZONE` secara global ke `Asia/Jakarta`** — berdampak ke SELURUH sistem (setiap timestamp, setiap perbandingan tanggal di modul lain, kemungkinan data existing yang sudah tersimpan dengan asumsi UTC). Ini butuh audit tersendiri yang jauh melampaui cakupan fitur piket, TERMASUK peninjauan seluruh titik `now()`/`Carbon::now()`/`today()` di codebase (ratusan pemanggilan), migrasi data existing kalau perlu, dan test regresi menyeluruh. **Direkomendasikan jadi prioritas audit terpisah SEGERA** (bug ini kemungkinan besar berdampak ke modul lain di luar piket juga — presensi, kalender akademik, dsb — TAPI itu perlu dibuktikan lewat audit sendiri, bukan diasumsikan/digabung ke sini). Spec ini HANYA menambal titik yang terverifikasi langsung berdampak ke gerbang akses piket.
- **Validasi rentang tanggal semester tidak boleh tumpang tindih** — ditemukan sebagai asumsi yang tidak dijamin skema/validasi di `RegenerateJadwalPiketHarianAction`, tapi ini soal validasi `Semester`/`TahunAjaran` secara umum (bukan spesifik piket), probabilitas kejadian rendah (butuh kesalahan input admin), dan mengubah validasi Semester berisiko berdampak ke modul lain yang jauh dari piket. Dicatat sebagai risiko yang diketahui, tidak ditambal di sini.
- **`tomSelectPegawai` untuk dropdown pilih guru** (halaman `piket-guru/index.blade.php`, `create.blade.php`, `edit.blade.php`) dan **`<x-select>`** — bukan bug fungsional (dropdown native tetap bekerja benar), murni konsistensi visual dengan pola proyek. TIDAK dimasukkan sebagai task terpisah di spec/plan ini — sebagai gantinya, kickoff nanti akan meminta instruksi eksplisit ke pelaksana untuk melakukan **pass konsistensi UI/UX** membandingkan ke pola established (`admin/kelas/_form.blade.php` utk `tomSelectPegawai`, halaman lain utk `<x-select>`) sebagai bagian dari Task terakhir sebelum penutup, supaya penyesuaian visual ini tetap tertangani tanpa membengkakkan daftar task teknis di atas dengan detail visual yang sifatnya "samakan ke pola yang sudah ada", bukan "bug yang perlu didesain".
- **Race condition locking (`lockForUpdate`) submit bersamaan 2 guru piket** — audit menyimpulkan ini "last-write-wins" biasa, konsisten dengan pola project lain, bukan bug keamanan. YAGNI untuk saat ini.
- **Semua item Fase 2 yang sudah di-exclude spec 2026-09-06 §5** (LaporanPiket, alur verifikasi Kepala Sekolah, cetak dokumen, mekanisme otomatis pembersihan PiketHarian saat kalender berubah, Proyek B) — TETAP di luar cakupan, tidak diulang di sini.

---

## 4. Dampak & Kompatibilitas

- §2.1+§2.5 (timezone scoped + mismatch tanggal) — perubahan perilaku yang DISENGAJA: sebelumnya ada jendela ~7 jam pagi hari di mana guru piket bisa salah ditolak, dan ada kebocoran metadata jadwal lintas-tanggal; sesudahnya keduanya tertutup. Test existing yang men-set `Carbon::setTestNow(...)` dengan tanggal spesifik (banyak dipakai test piket) TIDAK terpengaruh — `now('Asia/Jakarta')` tetap menghormati `Carbon::setTestNow()` (Carbon test-now override berlaku lintas timezone).
- §2.3 (kalender read-only baru) murni ADITIF — tidak mengubah `overrides`/`jadwalList`/`guruList` yang sudah ada, tidak mengubah endpoint Generate/Regenerate.
- §2.4 (`diisi_oleh_guru_id` kondisional) — perubahan perilaku yang DISENGAJA, lihat catatan desain di §2.4. Test existing `JurnalKbmDiisiOlehGuruTest.php` skenario "guru pemilik submit sendiri → tetap null" HARUS diverifikasi ulang tetap lolos (kasus itu: sesi BELUM PERNAH diisi piket, `diisi_oleh_guru_id` sebelumnya sudah null, jadi `array_filter` di atas tetap mengizinkan set ke null karena tidak ada nilai lama yang perlu dilindungi — TAPI perlu dicermati: filter di atas SELALU membuang key itu kalau value null, termasuk kasus pertama kali null→null yang seharusnya tetap boleh "tidak berubah" (no-op aman) — WAJIB diverifikasi test existing ini, lihat §5).
- §2.6 (confirmDialog) — murni UI, tidak mengubah endpoint/logic backend.

---

## 5. Pengujian yang Dibutuhkan

- **§2.1+§2.5**: test baru — `Carbon::setTestNow()` ke jam SANGAT PAGI WIB yang setara MALAM HARI SEBELUMNYA di UTC (mis. set ke `'2026-08-19 05:00:00'` dengan asumsi test environment berjalan di timezone UTC default Laravel testing — WAJIB verifikasi dulu bagaimana `Carbon::setTestNow()` berinteraksi dengan app timezone vs `now('Asia/Jakarta')` secara eksplisit sebelum menulis assertion, JANGAN asumsikan). Assert guru piket TETAP bisa akses (`PiketAccessChecker`/index() seksi piket tetap muncul) pada jam yang SEBELUM perbaikan akan gagal. Test baru — guru piket hari ini browsing `?tanggal=<3 hari lalu>`, assert seksi "Sesi Piket Hari Ini" (kalau muncul) berisi sesi HARI INI, BUKAN sesi 3 hari lalu.
- **§2.2**: test baru — `show()` untuk guru piket (pakai helper `siapkanSesiDanGuruPiketUntukAksesTest()`), assert response mengandung teks banner "Guru Piket" dan nama guru pemilik asli. Test baru — `show()` untuk guru pemilik asli sesinya sendiri, assert banner TIDAK muncul (`assertDontSee`).
- **§2.3**: test baru — `index()` admin dengan campuran `PiketHarian` sumber `dari_jadwal_mingguan` dan `override_manual`, assert `piketHarianMendatang` di view data berisi KEDUANYA (beda dari `overrides` yang cuma `override_manual`).
- **§2.4**: test baru — sesi yang SUDAH pernah diisi guru piket (`diisi_oleh_guru_id` terisi), lalu guru PEMILIK asli submit ulang → assert `diisi_oleh_guru_id` TETAP terisi ID guru piket lama (TIDAK tertimpa null). Regresi WAJIB: jalankan ulang `JurnalKbmDiisiOlehGuruTest.php` existing (skenario "guru pemilik submit sesi sendiri PERTAMA KALI, belum pernah diisi siapa pun → tetap null") — WAJIB tetap lolos, verifikasi `array_filter` di atas tidak merusak kasus null→null yang sudah benar sebelumnya.
- **§2.6, konsistensi visual**: TIDAK ADA automated test untuk perilaku JS Alpine `confirmDialog` murni maupun kosmetik `tomSelectPegawai`/`<x-select>` (konsisten pola sesi ini) — verifikasi manual dev-server.
- **§2.7**: 1 test baru persis seperti dirinci di §2.7.
- **Regresi wajib**: SEMUA 8 file test piket-terkait (`JadwalPiketMingguanControllerTest.php`, `PiketHarianControllerTest.php`, `JurnalKbmPiketAksesTest.php`, `JurnalKbmSesiPiketTest.php`, `GenerateJadwalPiketHarianActionTest.php`, `PiketAccessCheckerTest.php`, `PiketModelsTest.php`, `RegenerateJadwalPiketHarianActionTest.php`) plus SEMUA file `JurnalKbm*Test.php` (9 file) dijalankan ulang setelah semua task selesai, plus full suite proyek di task terakhir.

---

## 6. Struktur Task yang Disarankan (untuk fase plan nanti)

1. **Task 1**: §2.1+§2.5 digabung (timezone scoped + mismatch tanggal `JurnalKbmController::index()`) — saling terkait erat, blok kode yang sama.
2. **Task 2**: §2.2 (badge mode piket) — independen.
3. **Task 3**: §2.3 (kalender read-only admin) — independen.
4. **Task 4**: §2.4 (`diisi_oleh_guru_id` kondisional) — independen, TAPI PALING BERISIKO regresi (lihat catatan §4), WAJIB regresi test existing dijalankan SEBELUM lanjut task lain.
5. **Task 5**: §2.6 (confirmDialog) — independen.
6. **Task 6**: §2.7 (test resolveKartu piket) — independen, bisa kapan saja.
7. **Task 7**: Pass konsistensi UI/UX (item yang di-exclude §3 poin 3 — `tomSelectPegawai`, `<x-select>`) — WAJIB instruksi eksplisit ke pelaksana untuk membandingkan ke pola established sebelum mengubah, BUKAN menebak sendiri pola baru.
8. **Task 8**: Penutup — full regression sweep + Pint + full suite.

Task 1-6 saling independen satu sama lain (tidak ada dependency lintas-task berbasis kode, tapi Task 1 dan Task 5 sama-sama menyentuh `JurnalKbmController::index()`/view terkait secara TIDAK langsung — Task 1 di controller `Guru\Akademik\JurnalKbmController`, Task 5 di view Blade ADMIN `piket-guru/index.blade.php`, FILE BERBEDA, aman paralel). Task 7 dikerjakan setelah Task 1-6 (menyentuh file yang sama seperti Task 2/3/5 secara visual, aman dikerjakan terakhir untuk hindari konflik). Task 8 wajib terakhir.

---

## 7. Self-Review — Putaran 1 (standar: placeholder, konsistensi, cakupan)

- **Placeholder scan**: tidak ada "TBD"/"TODO" — semua kode di §2 lengkap.
- **Konsistensi**: `now('Asia/Jakarta')` dipakai KONSISTEN di 3 titik (§2.1 dua tempat, §2.5 satu tempat) dengan cara yang sama persis, tidak ada variasi penulisan yang bisa membingungkan.
- **Cakupan vs audit**: 7 dari 7 kelompok temuan (timezone, badge mode piket, visibilitas admin, akuntabilitas data, mismatch tanggal, confirmDialog, test gap) masuk §2. 4 item (global timezone, validasi semester overlap, UI polish tomSelect/x-select, race condition) masuk §3 dengan alasan eksplisit. Tidak ada temuan hilang tanpa penjelasan.

## 8. Self-Review — Putaran 2 (verifikasi empiris terhadap kode & spec lama)

- **Ditemukan & dikoreksi SEBELUM draft final**: draft awal §2.4 sempat memakai kondisi `if ($diisiOlehGuruId !== null) { $sesi->update([...]); } else { $sesi->update(['materi' => ...]); }` (2 cabang if/else terpisah, duplikasi call `update()`) — disederhanakan jadi 1 `array_filter` supaya tidak ada 2 titik kode yang bisa saling menyimpang. Perlu WASPADA (dicatat eksplisit di §4 dan §5): filter ini membuang key `diisi_oleh_guru_id` SETIAP KALI value-nya null, termasuk kasus legitimate "belum pernah diisi siapa pun, tetap null" — secara efek AMAN (kolom itu memang sudah null, tidak update ke null = no-op, hasil akhir sama), tapi implementer WAJIB tetap menjalankan test existing untuk membuktikan asumsi ini benar, bukan cuma dipercaya dari pembacaan kode.
- **Dikonfirmasi ulang** lewat pembacaan langsung `config/app.php:68` (`'timezone' => 'UTC'`) dan `.env` (tidak ada `APP_TIMEZONE`) — bukan asumsi.
- **Dikonfirmasi ulang** `BriSnapClient.php:35` MEMANG sudah pakai pola timezone lokal serupa (walau raw `\DateTime`, bukan Carbon) — dasar keputusan §2.1 memilih pola scoped, bukan global config, konsisten dengan preseden yang sudah ada di codebase ini.
- **Dikonfirmasi ulang** helper test `siapkanSesiDanGuruPiketUntukAksesTest()` MEMANG ada di `JurnalKbmPiketAksesTest.php` dan strukturnya cocok untuk di-reuse di §2.7 — dibaca langsung, bukan diasumsikan dari nama file.

## 9. Self-Review — Putaran 3 (dependency antar-perbaikan & risiko regresi)

- **Task 1 (§2.1+§2.5) paling berisiko** karena menyentuh logic "hari ini" yang dipakai banyak test existing dengan `Carbon::setTestNow()` — implementer WAJIB memverifikasi dulu bagaimana `Carbon::setTestNow()` berinteraksi dengan `now('Asia/Jakarta')` SEBELUM menulis assertion test baru (dicatat eksplisit di §5, bukan diasumsikan otomatis benar).
- **Task 4 (§2.4) risiko regresi kedua tertinggi** — WAJIB dijalankan regresi test existing SEBELUM task lain dianggap final, dicatat eksplisit di §6 struktur task.
- **Task 2 dan Task 3 tidak overlap file** — Task 2 di `show.blade.php` (sisi Guru), Task 3 di `piket-guru/index.blade.php` (sisi Admin) — file BERBEDA, aman independen sepenuhnya, dikonfirmasi ulang.
- **Task 5 (confirmDialog) dan Task 7 (UI polish) SAMA-SAMA menyentuh `piket-guru/index.blade.php`** — dicatat eksplisit di §6 bahwa Task 7 dikerjakan SETELAH Task 1-6 untuk menghindari konflik, walau secara baris kode kemungkinan tidak tumpang tindih persis (Task 5 di atribut form `@submit.prevent`, Task 7 di elemen `<select>` guru) — urutan tetap dijaga untuk kehati-hatian.

## 10. Self-Review — Putaran 4 (baca ulang dengan mata segar, cek copy/pesan & instruksi kickoff)

- Pesan banner §2.2 ("Anda mengisi sebagai Guru Piket untuk kelas milik...") sudah actionable dan tidak menuduh/membingungkan — menjelaskan APA yang terjadi (mode piket) dan KONSEKUENSINYA (data tercatat sebagai piket), konsisten prinsip proyek.
- Pesan `confirmDialog` §2.6 sudah menjelaskan efek samping (baris piket harian ikut disesuaikan) — menutup gap wording yang ditemukan audit ("tidak menjelaskan dampak cascade").
- Dicek ulang §3 poin 3 (UI polish instruksi ke kickoff) — SENGAJA tidak dirinci jadi task kode lengkap di plan (beda dari kelompok temuan lain), karena ini murni "samakan ke pola existing" yang instruksinya lebih tepat berupa ARAHAN AUDIT-DAN-SESUAIKAN ke pelaksana (dengan pola pembanding eksplisit `admin/kelas/_form.blade.php`), bukan kode pra-tulis yang mengasumsikan implementasi tanpa verifikasi ulang ke pola terkini — pas dengan permintaan user untuk kickoff ini secara eksplisit meminta instruksi audit UI/UX terpisah.
- Dicek ulang total 8 task di §6 — cocok dengan 7 kelompok temuan §2 + 1 task UI polish tambahan (dari §3 poin 3) + 1 penutup, tidak ada yang tertukar posisi atau hilang.
