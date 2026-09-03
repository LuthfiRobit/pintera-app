# Spec: Peringatan Validasi Tingkat di Kenaikan Kelas (Kelompok B)

**Tanggal**: 2026-09-03
**Branch**: `akademik-v2` (lanjut di branch yang sama)
**Konteks**: Ditemukan saat audit bisnis lanjutan fitur Kenaikan Kelas (lihat `.agents/logs/2026-09-03-audit-akademik-perbaikan.md`, bagian "Audit Bisnis Lanjutan"), dipisah dari Kelompok A (`.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`, sudah SELESAI diimplementasikan) karena independen secara teknis. `ProsesKenaikanKelasAction` dan UI Kenaikan Kelas tidak memvalidasi/memperingatkan hubungan tingkat sama sekali antara kelas asal dan kelas tujuan — admin bisa memetakan ke tingkat yang sama, lompat, atau mundur tanpa sinyal apapun.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Cakupan: warning non-blocking saja (Opsi A dari brainstorming)** — TIDAK ada tabel/kolom/laporan baru untuk melacak "siapa yang tinggal kelas". Kalau nanti dibutuhkan laporan resmi, itu jadi spec terpisah di masa depan (dicatat sebagai referensi, bukan bagian pekerjaan ini).
- **Backend TIDAK divalidasi/ditolak sama sekali** — keputusan eksplisit setelah didiskusikan: mismatch tingkat bukan pelanggaran integritas data (tidak ada fitur/laporan lain yang bergantung pada asumsi "tingkat selalu naik +1", dikonfirmasi lewat audit kode sebelumnya). Backend tetap menerima SEMUA kombinasi tingkat seperti sekarang — perubahan ini murni di sisi tampilan (Alpine.js), tidak menyentuh `ProsesKenaikanKelasAction` atau validasi request sama sekali. Kasus ekstrem seperti "mundur tingkat" pun harus tetap bisa dieksekusi manual oleh admin untuk menyelaraskan data riil lapangan (mis. koreksi data siswa pindahan).
- **Perbandingan berbasis INDEX terhadap `BentukPendidikan::validTingkatValues()`, BUKAN aritmatika angka** — dikonfirmasi lewat pembacaan kode: `validTingkatValues()` untuk jenjang KB/TPA/SPS/TK adalah `['A', 'B']` (bukan numerik), sehingga `tingkat + 1` tidak valid untuk jenjang itu. Solusi: cari index `tingkatAsal` dan `tingkatTujuan` di dalam array `validTingkatValues()` milik `bentuk_pendidikan` LEMBAGA (bukan kelas — Kelas tidak punya kolom bentuk_pendidikan sendiri, selalu ikut lembaga induknya), lalu bandingkan SELISIH INDEX: `0` = tinggal kelas, `1` = naik wajar (tidak ada warning), selain itu = tidak wajar (lompat/mundur). Ini otomatis benar untuk SEMUA jenjang (numerik maupun alfabet) tanpa kasus khusus, karena 1 lembaga selalu 1 `bentuk_pendidikan` — kelas asal dan tujuan dalam 1 Kenaikan Kelas PASTI berbagi `validTingkatValues()` yang sama.
- **Kasus tingkat akhir TIDAK butuh penanganan khusus** — dikonfirmasi: `kelasTujuanList` di controller sudah ter-tenant-scope ke 1 lembaga yang sama, jadi kalau admin override tindakan default "Lulus" balik ke "Naik Kelas" untuk siswa tingkat akhir, TIDAK ADA pilihan kelas tujuan dengan index+1 yang tersedia di dropdown (jenjang berikutnya ada di lembaga lain). Logic index-based di atas otomatis menghasilkan warning yang benar (sama atau tidak-wajar) tanpa kode tambahan. Pesan generik cukup, TIDAK perlu pesan khusus "ini tingkat akhir" — edge case ini dianggap terlalu jarang untuk menambah kompleksitas.
- **2 gaya pesan berbeda, bukan 1 gaya generik seperti pola kurikulum**: kasus "tinggal kelas" (selisih index = 0) tampil sebagai info NETRAL (bukan warna alarm) karena ini skenario SAH dan RUTIN terjadi setiap tahun ajaran. Kasus "tidak wajar" (selisih index selain 0 dan 1) tampil sebagai warning AMBER (sama level keseriusan dengan warning kurikulum-berbeda yang sudah ada) karena kasus ini hampir selalu berarti admin salah pilih. Alasan pemisahan: tingkat punya ARAH (naik/sama/turun) yang membawa makna bisnis berbeda, beda dengan kurikulum yang cuma "beda atau tidak" tanpa arah — kalau digabung jadi 1 gaya, kasus "tinggal kelas" yang sering muncul akan menumpulkan kepekaan admin terhadap kasus "tidak wajar" yang jarang tapi krusial (alert fatigue).
- **Test coverage**: mengikuti pola ESTABLISHED di project ini (Pest Feature test yang assert HTML markup mentah dari respons HTTP, BUKAN JS test runner seperti Jest/Cypress/Vitest — dikonfirmasi project ini tidak punya toolchain JS test sama sekali di `package.json`). Test mencakup 2 jenjang: numerik (SD) dan alfabet (TK), supaya logic berbasis-index teruji untuk kedua tipe data.

## 2. Non-Goals (eksplisit)

- **Tabel/kolom/laporan "siswa tinggal kelas"** — ditunda, dicatat sebagai kemungkinan spec masa depan kalau ada kebutuhan compliance/pelaporan resmi (mis. dari Dinas Pendidikan) yang terkonfirmasi. Catatan teknis untuk saat itu tiba: `ProsesKenaikanKelasAction` memakai mass-update (`Siswa::where(...)->update(...)`) yang TIDAK memicu Eloquent model events — Activitylog TIDAK menangkap perubahan `kelas_id` lewat Kenaikan Kelas massal. Kalau laporan resmi dibutuhkan nanti, sumber datanya harus tabel riwayat baru yang ditulis eksplisit di action tersebut (bukan mengandalkan Activitylog retroaktif), dan histori SEBELUM tabel itu dibuat tidak bisa direkonstruksi.
- **Backend validation/hard-block untuk kombinasi tingkat manapun** — sudah final, murni tampilan.
- **Gap `ProsesKenaikanKelasAction`'s cabang `lulus` tidak mengisi `kelas_terakhir_id`** (ditemukan saat cross-check spec ini) — siswa yang lulus lewat Kenaikan Kelas MASSAL tidak mendapat snapshot `kelas_terakhir_id` seperti siswa yang di-Lulus-kan manual lewat `UpdateStatusSiswaAction` (Kelompok A). Ini gap TERPISAH milik Kelompok A (`kelas_efektif`/`kelas_terakhir_id`), BUKAN bagian pekerjaan spec ini — dicatat untuk dibahas di sesi/spec lain supaya tidak scope creep.

## 3. Desain: Perbandingan Berbasis Index

**File**: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`

Tambahkan ke `x-data` blok `<tr>` (baris 74-83 saat ini) — `tingkatAsal` dan `daftarTingkat` (array `validTingkatValues()` milik lembaga, dikirim dari PHP), plus 1 computed property `selisihIndexTingkat`:

```blade
<tr class="transition hover:bg-gray-50/60" x-data="{
    kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
    kurikulumTujuan: null,
    tingkatTujuan: null,
    tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
    daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
    onKelasTujuanChange(event) {
        const opt = event.target.selectedOptions[0];
        this.kurikulumTujuan = opt?.dataset.kurikulum || null;
        this.tingkatTujuan = opt?.dataset.tingkat || null;
    },
    get selisihIndexTingkat() {
        if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
        const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
        const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
        if (indexAsal === -1 || indexTujuan === -1) return null;
        return indexTujuan - indexAsal;
    },
}">
```

Tambahkan 2 baris `<p>` baru setelah baris peringatan kurikulum yang sudah ada (setelah baris 120 saat ini), TIDAK mengubah baris "Tingkat tujuan: ..." yang sudah ada:

```blade
<p x-show="selisihIndexTingkat === 0" class="mt-1 text-xs text-gray-400" x-text="'↔ Tinggal kelas: tingkat tidak berubah (' + tingkatAsal + ')'"></p>
<p x-show="selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1"
   class="mt-1 text-xs font-medium text-amber-600"
   x-text="'⚠ Tingkat tidak wajar: dari tingkat ' + tingkatAsal + ' ke ' + tingkatTujuan + ' — periksa kembali pilihan kelas tujuan'"></p>
```

**Tidak ada perubahan lain** — tidak ada perubahan controller, action, request, atau migration.

## 4. Test Plan

### 4.1. Backend — pembuktian eksplisit non-blocking

**File**: `tests/Feature/Admin/KenaikanKelasControllerTest.php`

Tambah 1 test baru: buat kelas asal dan kelas tujuan dengan `tingkat` SAMA (tinggal kelas), post ke `admin.kenaikan-kelas.store` dengan `tindakan: 'naik'` mengarah ke kelas tujuan itu, assert `assertRedirect` sukses (BUKAN error validasi) dan `kelas_id` siswa benar-benar berpindah ke kelas tujuan — membuktikan backend TIDAK PERNAH menolak kombinasi tingkat apapun, sesuai keputusan final.

### 4.2. Frontend — markup assertion, mengikuti pola `KenaikanKelasControllerUxTest.php`

**File**: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`

2 test baru, memakai helper `htmlSelectByName`/pola pencarian `<tr>` chunk yang SUDAH ADA di file ini:

1. **Jenjang numerik (SD)**: lembaga `bentuk_pendidikan = 'SD'`, kelas asal `tingkat = '3'`, kelas tujuan tersedia dengan `tingkat` `'2'`, `'3'`, `'4'`, `'6'`. Assert:
   - Setiap `<option>` kelas tujuan punya `data-tingkat` sesuai nilainya.
   - `x-data` mengandung `daftarTingkat` terserialisasi sebagai array numerik (`["1","2","3","4","5","6"]`).
   - Ekspresi `selisihIndexTingkat` dan kedua `x-text` (tinggal kelas & tidak wajar) muncul sebagai string di dalam chunk `<tr>` tersebut.
2. **Jenjang alfabet (TK)**: lembaga `bentuk_pendidikan = 'TK'`, kelas asal `tingkat = 'A'`, kelas tujuan `tingkat = 'B'`. Assert:
   - `daftarTingkat` terserialisasi sebagai `["A","B"]` (BUKTI bahwa pendekatan index-based, bukan aritmatika, benar-benar dipakai — kalau memakai `tingkat + 1`, kode ini tidak mungkin berfungsi untuk data alfabet).
   - Markup warning yang sama tetap ter-wire dengan benar untuk data non-numerik.

Kedua test HANYA memverifikasi markup/data ter-wire dengan benar (pola yang sudah diterima project ini untuk fitur Alpine serupa) — TIDAK menjalankan JS sungguhan, TIDAK menambah dependency testing JS baru.
