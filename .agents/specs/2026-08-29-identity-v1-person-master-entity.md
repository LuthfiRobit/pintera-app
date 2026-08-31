# Identity v1 — Person Master Entity — Design Spec

**Tanggal**: 2026-08-29
**Branch**: baru, disarankan `identity-v1` (terpisah dari `keuangan-v2`)
**Konteks**: Ditemukan saat audit domain Keuangan (redesign konsep Tagihan) bahwa identitas manusia (Guru/Karyawan/OrangTua/Siswa/CalonMurid) tersebar di 5 tabel independen dengan duplikasi kolom, inkonsistensi penamaan, dan TANPA mekanisme deteksi duplikasi identitas lintas peran — dibuktikan lewat 2 putaran audit kode mendalam (bukan asumsi). Spec ini murni tentang **fondasi identitas lintas-modul**; redesign Tagihan Keuangan adalah inisiatif terpisah yang akan memanfaatkan `person_id` setelah spec ini selesai, TIDAK digabung di sini.

---

## 1. Latar Belakang & Bukti Audit

### 1.1 — Duplikasi & inkonsistensi yang sudah terjadi (bukti langsung dari migration)

| Field | `guru` | `karyawan` | `orang_tua` | `siswa` | `calon_murid` |
|---|---|---|---|---|---|
| NIK + hash | `text`+`nik_hash` unique | `text`+`nik_hash` unique | `string(16)`, **tanpa hash** | **tidak ada** | `text`+`nik_hash` unique |
| Nama | `nama` | `nama` | `nama_lengkap` | `nama_lengkap` | `nama_lengkap` |
| Kontak HP | `no_hp` | `no_hp` | `no_hp` | tidak ada | `no_telepon` |
| Kontak email | `email` | `email` | `email` | tidak ada | `email_kontak` |
| Alamat | 8 kolom terstruktur | tidak ada | 1 kolom `text` | tidak ada | tabel satelit terpisah |
| `user_id` | NOT NULL unique | NOT NULL unique | NOT NULL unique | **nullable** | tidak punya |

**Temuan kritis**: `nik_hash` di `guru`/`karyawan`/`calon_murid` masing-masing punya unique constraint SENDIRI-SENDIRI, tidak lintas tabel — satu orang dengan NIK sama bisa terdaftar sebagai `Guru` DAN `Karyawan` tanpa sistem menyadarinya. **Tidak ada satu pun mekanisme deteksi duplikasi identitas lintas peran di seluruh sistem sekarang.**

### 1.2 — Batas tenant yang berlaku (dari pembacaan langsung `app/Models/Scopes/TenantScope.php`)

Tidak ada konsep "Provider" — batas tenant tertinggi yang nyata adalah **`Yayasan`** (`Lembaga` selalu bersarang di satu `Yayasan`). `Platform` adalah level operator SaaS di ATAS semua yayasan, bukan tenant. `TenantScope::apply()` menegakkan isolasi lewat `lembaga_id`/`yayasan_id`.

**Prinsip terkunci**: **`Person` unik per (orang, `yayasan_id`), BUKAN per orang secara global.** Orang yang sama terdaftar di dua yayasan berbeda (ortu dengan anak di 2 yayasan, siswa pindah lintas-yayasan, guru mengajar lintas-yayasan sebagai 2 tenant berbeda) = **dua `Person` record independen, by design** — demi isolasi tenant SaaS. Konsekuensi: `UNIQUE(yayasan_id, nik_hash)`, BUKAN `UNIQUE(nik_hash)` global. Kalau dibuat global, admin Yayasan X yang input NIK yang kebetulan sama dengan orang di Yayasan Y akan memicu pesan dedup yang **membocorkan nama asli orang tersebut ke tenant yang tidak berwenang** — kelas bug yang persis pernah ditemukan+diperbaiki di `TenantScope` itu sendiri (komentar kode: bug "yayasan orWhere" yang dulu bocor lintas-yayasan).

`orang_tua.person_id` **tetap `UNIQUE`** (constraint TIDAK berubah) — tapi maknanya otomatis bergeser jadi "unik per yayasan" karena `person_id` itu sendiri sudah yayasan-scoped sejak lahir. Tidak perlu composite unique tambahan di tabel `orang_tua`.

### 1.3 — Radius kerja kode (audit menyeluruh, bukan sampel)

Dikonfirmasi lewat grep sistematis (Eloquent method chain DAN raw SQL — **nol hasil raw SQL** `DB::table()`/`DB::select()`/`whereRaw()`/`selectRaw()` yang menyentuh 5 tabel role ini di seluruh `app/`, jadi daftar di bawah ini FINAL, tidak ada kategori tersembunyi):

- **19 titik create/update** identitas (lihat §5.1, file:baris persis).
- **~30 titik query-builder** search/orderBy yang query kolom identitas langsung (lihat §5.2) — **TIDAK terlindungi accessor shim**, akan error SQL kalau tidak disesuaikan.
- **3 model** dengan `static::saving()` hook yang menghitung `nik_hash` dari kolom lokal (§5.3).
- **1 file** (`User.php`) yang mendefinisikan 4 relasi `hasOne` yang harus diubah jadi `hasOneThrough` (§5.4).
- **0 test** `assertDatabaseHas`/`assertDatabaseMissing` menyentuh 5 tabel ini di SELURUH test suite (dari 45 pemanggilan yang ada, semua menyasar tabel lain) — tidak ada jaring pengaman existing, test baru WAJIB ditulis (§7).
- Hanya **1 importer** (`SiswaImportController`) yang menulis field identitas massal — importer lain untuk Guru/Karyawan/OrangTua/CalonMurid tidak ada.

## 2. Prinsip Desain Terkunci

1. **Class Table Inheritance, bukan Single Table Inheritance**: `persons` (identitas umum) + tabel role (`guru`/`karyawan`/`orang_tua`/`siswa`) masing-masing FK `person_id` — BUKAN satu tabel gemuk dengan kolom semua peran.
2. **Multi-role via baris independen, bukan state-machine**: satu `person_id` bisa punya baris di `guru` DAN `karyawan` DAN `orang_tua` sekaligus — tidak saling eksklusif, tidak ada "role aktif tunggal". Siswa yang jadi karyawan (alumni direkrut) TIDAK mengubah baris `siswa` lama — baris itu tetap ada apa adanya (status `lulus`), ditambah baris BARU di `karyawan` untuk `person_id` yang sama.
3. **Arah FK: `persons.user_id`, BUKAN `users.person_id`** — ini keputusan teknis penting hasil audit, arahnya HARUS seperti ini: `persons.user_id` (nullable, unique) → `users.id`. Dengan arah ini, `User::guru()` dkk bisa didefinisikan sebagai `hasOneThrough(Guru::class, Person::class, 'user_id', 'person_id', 'id', 'id')` — relasi Eloquent BAWAAN — sehingga **87 file yang sudah memanggil `$user->guru`/`Auth::user()->karyawan` TIDAK PERLU DIUBAH SAMA SEKALI**. Kalau arahnya dibalik (`users.person_id`), pola akses ini wajib diubah jadi `$user->person->guru` di 87 titik — jangan lakukan itu.
4. **Accessor shim untuk backward-compat baca**: 5 model role dapat accessor (`getNamaAttribute`/`getNamaLengkapAttribute` sesuai konvensi masing-masing) yang proxy ke `$this->person->nama_lengkap` — supaya ~40+ titik baca existing (`$siswa->nama_lengkap`, `$guru->nama`, termasuk akses polymorphic `$pegawai->nama` di 6 view kehadiran yang diam-diam mengasumsikan Guru & Karyawan sama-sama punya kolom `nama`) tetap jalan tanpa disentuh.
5. **`PersonService` sebagai satu-satunya pintu masuk** — tidak ada modul lain yang boleh `Person::create()`/`update()` langsung.
6. **`yayasan_id` TIDAK PERNAH jadi input bebas** — selalu diturunkan transitif (§4.2), tidak pernah diterima sebagai field form yang bisa dimanipulasi.
7. **Tidak ada hard-delete pada `persons` atau `users`** — merge/deactivate selalu soft (§6).

## 3. Skema Database (DDL Final)

```sql
CREATE TABLE persons (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    yayasan_id      BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,          -- ARAH INI, bukan users.person_id -- lihat §2.3
    nik             TEXT NULL,                      -- cast 'encrypted', pola sama seperti Guru sekarang
    nik_hash        CHAR(64) NULL,                  -- SHA-256 lookup key
    nama_lengkap    VARCHAR(255) NOT NULL,
    jenis_kelamin   ENUM('L','P') NULL,
    tempat_lahir    VARCHAR(255) NULL,
    tanggal_lahir   DATE NULL,
    agama           VARCHAR(50) NULL,
    kewarganegaraan VARCHAR(50) NOT NULL DEFAULT 'WNI',
    no_hp           VARCHAR(20) NULL,
    email           VARCHAR(255) NULL,
    alamat_jalan    TEXT NULL,
    rt VARCHAR(5) NULL, rw VARCHAR(5) NULL,
    desa_kelurahan VARCHAR(255) NULL, kecamatan VARCHAR(255) NULL,
    kabupaten_kota VARCHAR(255) NULL, provinsi VARCHAR(255) NULL, kode_pos VARCHAR(10) NULL,
    merged_into_person_id BIGINT UNSIGNED NULL,
    deactivated_at  TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,

    CONSTRAINT fk_persons_yayasan FOREIGN KEY (yayasan_id) REFERENCES yayasan(id) ON DELETE CASCADE,
    CONSTRAINT fk_persons_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_persons_merged_into FOREIGN KEY (merged_into_person_id) REFERENCES persons(id) ON DELETE SET NULL,
    UNIQUE KEY uq_persons_user (user_id),
    UNIQUE KEY uq_persons_yayasan_nik (yayasan_id, nik_hash),   -- BUKAN unique(nik_hash) global
    INDEX idx_persons_nama (nama_lengkap)
);

ALTER TABLE users ADD COLUMN merged_into_user_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE users ADD CONSTRAINT fk_users_merged_into FOREIGN KEY (merged_into_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Role tables: TAMBAH person_id, KOLOM LAMA (user_id + identitas) DIPERTAHANKAN dulu selama migrasi
-- (lihat §8 urutan migrasi) -- DDL final SETELAH backfill+cutover selesai:

ALTER TABLE guru
    ADD COLUMN person_id BIGINT UNSIGNED NOT NULL AFTER id,
    ADD CONSTRAINT fk_guru_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
    ADD UNIQUE KEY uq_guru_person_lembaga (person_id, lembaga_id),
    DROP COLUMN user_id, DROP COLUMN nik, DROP COLUMN nik_hash, DROP COLUMN nama,
    DROP COLUMN jenis_kelamin, DROP COLUMN tempat_lahir, DROP COLUMN tanggal_lahir,
    DROP COLUMN agama, DROP COLUMN kewarganegaraan, DROP COLUMN no_hp, DROP COLUMN email,
    DROP COLUMN alamat_jalan, DROP COLUMN rt, DROP COLUMN rw, DROP COLUMN desa_kelurahan,
    DROP COLUMN kecamatan, DROP COLUMN kabupaten_kota, DROP COLUMN provinsi, DROP COLUMN kode_pos;
    -- SISA kolom guru: id, person_id, lembaga_id, nuptk, nip, jenis_ptk, kapasitas_kasus_aktif,
    --                  status_kepegawaian, golongan_pangkat, tmt_tugas, tmt_pns, status_aktif, timestamps

ALTER TABLE karyawan
    ADD COLUMN person_id BIGINT UNSIGNED NOT NULL AFTER id,
    ADD CONSTRAINT fk_karyawan_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
    DROP COLUMN user_id, DROP COLUMN nik, DROP COLUMN nik_hash, DROP COLUMN nama,
    DROP COLUMN no_hp, DROP COLUMN email;
    -- SISA kolom karyawan: id, person_id, yayasan_id, lembaga_id, jenis_karyawan_id,
    --                      status_aktif, kapasitas_kasus_aktif, timestamps

ALTER TABLE orang_tua
    ADD COLUMN person_id BIGINT UNSIGNED NOT NULL AFTER id,
    ADD CONSTRAINT fk_orang_tua_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
    ADD UNIQUE KEY uq_orang_tua_person (person_id),
    DROP COLUMN user_id, DROP COLUMN nama_lengkap, DROP COLUMN nik, DROP COLUMN no_hp,
    DROP COLUMN email, DROP COLUMN alamat;
    -- SISA kolom orang_tua: id, person_id, pekerjaan, timestamps

ALTER TABLE siswa
    ADD COLUMN person_id BIGINT UNSIGNED NOT NULL AFTER id,
    ADD CONSTRAINT fk_siswa_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
    DROP COLUMN user_id, DROP COLUMN nama_lengkap, DROP COLUMN jenis_kelamin,
    DROP COLUMN tempat_lahir, DROP COLUMN tanggal_lahir, DROP COLUMN agama;
    -- SISA kolom siswa: id, person_id, lembaga_id, kelas_id, calon_murid_id, pendaftaran_asal_id,
    --                   sumber_data, nis, nisn, status, timestamps
    -- CATATAN: siswa.user_id DIHAPUS SEPENUHNYA -- akun login (kalau ada) sekarang
    -- ditemukan lewat persons.user_id, bukan kolom lokal. Siswa TANPA akun login
    -- tetap valid (persons.user_id nullable).

-- calon_murid: identitas SEPENUHNYA pindah ke persons. Tabel calon_murid jadi murni
-- penghubung person_id <-> data spesifik proses SPMB (kalau ada sisa kolom non-identitas;
-- audit menunjukkan hampir semua kolom calon_murid adalah identitas -- kemungkinan besar
-- tabel calon_murid akhirnya HANYA berisi id, person_id, yayasan_id, timestamps setelah
-- migrasi, dengan satelit (AlamatCalonMurid dkk) juga migrasi ke persons.alamat_*.
-- Detail final DILUAR SCOPE spec ini -- lihat §9 Non-Goals (SPMB deliberately tidak
-- disentuh sekarang, sesuai keputusan sebelumnya di SP2 Keuangan).
```

## 4. `PersonService` — Satu-satunya Pintu Masuk

```
App\Domains\Identity\
  Models\Person.php                      -- BelongsToTenant (scope by yayasan_id)
  Actions\CreatePersonAction.php
  Actions\UpdatePersonAction.php
  Actions\MergePersonsAction.php
  Actions\DeactivatePersonAction.php
  Actions\ReactivatePersonAction.php
  Services\PersonDuplicateFinder.php     -- fuzzy match nama+ttl kalau NIK kosong
```

### 4.1 — Alur create

```php
final class CreatePersonAction
{
    public function execute(array $identityData, ?int $lembagaId, ?int $actingYayasanId): Person
    {
        $yayasanId = $this->resolveYayasanId($lembagaId, $actingYayasanId); // §4.2

        if (!empty($identityData['nik'])) {
            $nikHash = hash('sha256', $identityData['nik']);
            $existing = Person::where('nik_hash', $nikHash)->first(); // TenantScope aktif -> otomatis ter-filter ke $yayasanId actor
            if ($existing) {
                throw new PersonAlreadyExistsException($existing); // caller tampilkan "sudah terdaftar sebagai person_id X di yayasan ini, tambah peran baru?"
            }
        } else {
            $candidates = app(PersonDuplicateFinder::class)->find($identityData['nama_lengkap'], $identityData['tanggal_lahir'] ?? null);
            // caller TAMPILKAN warning non-blocking kalau ada kandidat -- admin putuskan lanjut buat baru atau pakai yang sudah ada
        }

        return Person::create([...$identityData, 'yayasan_id' => $yayasanId]);
    }
}
```

### 4.2 — Resolusi `yayasan_id` — TIDAK PERNAH input bebas

```php
private function resolveYayasanId(?int $lembagaId, ?int $actingYayasanId): int
{
    if ($lembagaId !== null) {
        // Entity level-lembaga (Guru, Siswa, Karyawan ber-lembaga): turunkan dari lembaga yang dipilih
        return Lembaga::findOrFail($lembagaId)->yayasan_id;
    }

    // Entity level-yayasan tanpa lembaga (pool Karyawan, OrangTua): pakai yayasan admin yang login
    abort_if($actingYayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

    return $actingYayasanId;
}
```
Pola ini PERSIS meniru cara `GuruController`/`AkunKaryawanGenerator` sekarang me-resolve `$lembagaId` dari session (`active_lembaga_id`) — tidak ada konsep baru, cuma dipindah ke satu titik terpusat. **Lapisan pertahanan kedua**: kalau ada pemanggil yang (di masa depan) tetap mengirim `yayasan_id` eksplisit BERSAMA `lembagaId`, `CreatePersonAction` WAJIB assert `Lembaga::find($lembagaId)->yayasan_id === $yayasanId` dan `abort(422)` kalau tidak cocok — defense-in-depth, bukan jalur utama.

### 4.3 — Alur Merge

```php
final class MergePersonsAction
{
    public function execute(Person $losing, Person $winning): void
    {
        abort_if($losing->yayasan_id !== $winning->yayasan_id, 422,
            'Tidak bisa merge Person lintas yayasan -- itu dua identitas yang MEMANG independen by design (lihat §1.2).');

        if ($losing->user_id !== null && $winning->user_id !== null) {
            // KEDUANYA punya akun login -- TIDAK BOLEH auto-resolve, lihat §6
            throw new ConflictingUserAccountsException($losing, $winning);
        }

        DB::transaction(function () use ($losing, $winning) {
            foreach (['guru', 'karyawan', 'orang_tua', 'siswa'] as $table) {
                DB::table($table)->where('person_id', $losing->id)->update(['person_id' => $winning->id]);
            }
            if ($losing->user_id !== null && $winning->user_id === null) {
                $winning->update(['user_id' => $losing->user_id]);
            }
            $losing->update(['merged_into_person_id' => $winning->id]);
            $losing->delete(); // soft delete
        });
    }
}
```

## 5. Daftar Lengkap Titik Kerja (dari audit, bukan perkiraan)

### 5.1 — Titik create/update identitas (19 titik, WAJIB diarahkan lewat `PersonService`)

**Guru:**
- `app/Http/Controllers/Admin/GuruController.php:99-107` — `User::create([...'name','email'...])`
- `app/Http/Controllers/Admin/GuruController.php:110-114` — `Guru::create([...$data,'user_id'=>...])`
- `app/Http/Controllers/Admin/GuruController.php:145-148` — `$guru->user()->update(['name','email'])`
- `app/Http/Controllers/Admin/GuruController.php:150` — `$guru->update($data)`

**Karyawan:**
- `app/Services/AkunKaryawanGenerator.php:22-32` — `User::create(...)`
- `app/Services/AkunKaryawanGenerator.php:35-45` — `Karyawan::create(...)`
- `app/Http/Controllers/Admin/KaryawanController.php:104` — cek unik NIK→username (baca, tapi bagian alur yang berubah)
- `app/Http/Controllers/Admin/KaryawanController.php:122-129` — panggil generator
- `app/Http/Controllers/Admin/KaryawanController.php:164` — `$karyawan->user()->update(['name'])`
- `app/Http/Controllers/Admin/KaryawanController.php:165` — `$karyawan->update($data)`

**OrangTua:**
- `app/Services/AkunOrangTuaGenerator.php:21-30` — `User::create(...)`
- `app/Services/AkunOrangTuaGenerator.php:33-41` — `OrangTua::create(...)`
- `app/Http/Controllers/Admin/OrangTuaController.php:78-85` — panggil generator
- `app/Http/Controllers/Admin/OrangTuaController.php:116` — `$orangTua->user()->update(['name'])`
- `app/Http/Controllers/Admin/OrangTuaController.php:117` — `$orangTua->update($data)`

**Siswa:**
- `app/Http/Controllers/Admin/SiswaController.php:114` — `Siswa::create([...,'user_id'=>...])`
- `app/Http/Controllers/Admin/SiswaController.php:152` — `$siswa->update($data)`
- `app/Http/Controllers/Admin/SiswaImportController.php:75-87` — `Siswa::create(...)` (import massal)
- `app/Http/Controllers/Admin/PendaftaranSiswaController.php:87-101` — `Siswa::create([...copy dari CalonMurid...])` — titik PALING PENTING: setelah refactor, ini jadi **link ke `person_id` yang sama**, BUKAN copy field.

**CalonMurid:**
- `app/Http/Controllers/Spmb/ReviewSubmitController.php:80-83` — `CalonMurid::updateOrCreate(['nik_hash'=>...], [...])`

### 5.2 — Titik query-builder (Eloquent, ~30 titik, TIDAK terlindungi accessor shim)

Guru (`nama`): `GuruController.php:50,53`; `AttendanceController.php:48`; `AttendanceConfigurationController.php:103`; `JadwalPelajaranController.php:78-79,154,303`; `KelasController.php:87,126`; `ListRppAction.php:65`. Plus validasi `nik_hash`: `GuruController.php:193-194`.

Karyawan (`nama`): `KaryawanController.php:61-62`; `AttendanceController.php:52`; `AttendanceConfigurationController.php:107`. Plus validasi `nik_hash`: `KaryawanController.php:199-200`.

OrangTua (`nama_lengkap`,`nik`): `OrangTuaController.php:36-39`.

Siswa (`nama_lengkap`): `SiswaController.php:31,36`; `KasusAksesLogController.php:43`; `KasusTerhapusController.php:37`; `KasusController.php:54-55`; `Guru/RaporController.php:71,82,94,138`; `Guru/AsesmenController.php:101`; `Lembaga/Keuangan/VirtualAccountController.php:39-40,126-127,135`; `Lembaga/Keuangan/ManualPaymentController.php:34`; `RaporCalculationService.php:27`; `PresensiAggregationService.php:17`.

CalonMurid (`nama_lengkap`,`nik_hash`): `Lembaga/Keuangan/TagihanController.php:59`; `PendaftaranAdminController.php:73`; `CalonMurid.php:50` (`findByNik()`).

Perlakuan: `where('nama', ...)`/`orderBy('nama')` → `whereHas('person', fn($q) => $q->where('nama_lengkap', ...))` / `orderBy(Person::select('nama_lengkap')->whereColumn('persons.id', 'guru.person_id'))` atau join eksplisit — mekanis, bukan business-logic rewrite, tapi HARUS eksplisit disentuh satu-satu (daftar di atas dipakai sebagai checklist implementasi).

### 5.3 — `static::saving()` hook nik_hash (3 model, disesuaikan bukan dihapus)

`app/Models/Guru.php:47-52`, `app/Models/Karyawan.php:35-40`, `app/Models/CalonMurid.php:41-46` — hook ini pindah konsepnya ke `Person` model (nik_hash dihitung di `Person::saving()`, bukan lagi di 3 model role ini karena `nik` sudah tidak ada di sana).

### 5.4 — `User.php` — redefinisi 4 relasi (`app/Models/User.php:73-91`)

```php
public function guru(): HasOneThrough
{
    return $this->hasOneThrough(Guru::class, Person::class, 'user_id', 'person_id', 'id', 'id');
}
// pola sama untuk karyawan(), orangTua(), siswa()
```

## 6. Kebijakan Merge — Konflik 2 Akun Login

Kalau `MergePersonsAction` menemukan KEDUA `Person` sama-sama punya `user_id` non-null (dua akun login berbeda untuk orang yang ternyata sama):

1. **TIDAK auto-resolve.** Lempar `ConflictingUserAccountsException`, UI WAJIB minta admin memilih akun mana yang jadi akun utama secara eksplisit.
2. Akun yang KALAH **tidak dihapus** — tetap ada demi integritas ~21 kolom audit-trail (`created_by`/`reviewed_by`/`diverifikasi_oleh_user_id` dkk, semua FK ke `users.id`, ditemukan lewat query `information_schema` saat audit). Set `is_active=false` (gerbang login yang sudah ada) + `users.merged_into_user_id` = akun pemenang.
3. `persons.user_id` (setelah merge Person) diarahkan ke akun pemenang saja.

## 7. Test Plan — Menutup Gap "0 Test Existing"

Dikonfirmasi: **nol** `assertDatabaseHas`/`assertDatabaseMissing` menyentuh `guru`/`karyawan`/`orang_tua`/`siswa`/`calon_murid` di seluruh suite — tidak ada regresi lama yang perlu diupdate, TAPI juga tidak ada jaring pengaman. Test BARU wajib (per kategori, ditulis penuh saat writing-plans, minimal):

1. `CreatePersonAction` menolak NIK duplikat DALAM satu yayasan.
2. `CreatePersonAction` **mengizinkan** NIK yang sama di DUA yayasan berbeda (membuktikan §1.2 — ini bukan bug, ini kontrak).
3. `CreatePersonAction` tidak pernah menerima `yayasan_id` yang tidak cocok dengan `lembaga_id` (assert exception §4.2).
4. Satu `person_id` bisa punya baris `guru` DAN `karyawan` sekaligus (multi-role).
5. `$user->guru`/`$user->karyawan`/`$user->orangTua`/`$user->siswa` tetap berfungsi via `hasOneThrough` (regresi krusial — bukti 87 file existing tidak pecah).
6. Accessor shim `$guru->nama`/`$siswa->nama_lengkap` mengembalikan `person->nama_lengkap` yang benar.
7. `MergePersonsAction` re-parent semua FK role table dengan benar + soft-delete Person yang kalah.
8. `MergePersonsAction` melempar exception (bukan auto-resolve) saat kedua Person punya `user_id`.
9. Minimal 1 test per titik query-builder di §5.2 yang paling berisiko tinggi (search Siswa, search Guru) — memastikan search tetap berfungsi setelah kolom pindah.

## 8. Urutan Migrasi (bertahap, aman)

1. **Schema tambahan TANPA hapus kolom lama**: buat `persons`, tambah `person_id` (nullable dulu) di 5 tabel role, tambah `users.merged_into_user_id`.
2. **Backfill data**: untuk tiap baris `guru`/`karyawan`/`orang_tua`/`siswa`/`calon_murid`, buat `Person` (derive `yayasan_id` dari `lembaga_id`→`lembaga.yayasan_id`, atau `yayasan_id` langsung kalau ada), isi `person_id`. **Deteksi duplikat NIK lintas tabel DALAM satu yayasan saat backfill** — laporkan sebagai temuan manual (JANGAN auto-merge saat backfill, terlalu berisiko tanpa review manusia).
3. **Verifikasi count**: pastikan setiap baris role table punya `person_id` non-null, tidak ada yang tertinggal.
4. **Ganti pemakaian kode**: 19 titik §5.1 (lewat `PersonService`), ~30 titik §5.2 (join/whereHas), 3 hook §5.3, `User.php` §5.4, accessor shim di 5 model.
5. **`person_id` jadi NOT NULL**, test suite full hijau.
6. **BARU hapus kolom lama** (`user_id` lama + kolom identitas) di 5 tabel role — irreversible, HARUS setelah §4-5 diverifikasi produksi stabil (rekomendasi: tunggu minimal satu siklus rilis penuh sebelum langkah ini, atau jalankan sebagai migration terpisah yang sengaja ditunda).

## 9. Non-Goals (eksplisit di luar scope)

- **Tidak** menyentuh redesign konsep Tagihan Keuangan (`Domains\Keuangan`) — itu inisiatif terpisah yang BOLEH memanfaatkan `person_id` SETELAH spec ini selesai, bukan bagian pekerjaan ini.
- **Tidak** membangun Payroll, Buku Kas/BOS, atau fitur bisnis baru apa pun — spec ini murni fondasi identitas.
- **Tidak** menyentuh struktur `CalonMurid` satelit (`AlamatCalonMurid`, `KeluargaCalonMurid`, dll) secara mendalam — migrasi `calon_murid` inti (identitas → `persons`) termasuk scope, tapi restrukturisasi tabel satelit SPMB didetailkan saat writing-plans, bukan di sini (konsisten dengan keputusan sebelumnya: SPMB deliberately tidak ikut migrasi domain Keuangan SP1-5).
- **Tidak** membangun UI admin baru untuk "kelola Person" secara mandiri — `PersonService` dipanggil TRANSPARAN dari form Guru/Karyawan/OrangTua/Siswa yang SUDAH ADA, bukan halaman baru.
- **Tidak** menangani skenario cross-yayasan merge (dua Person di yayasan BERBEDA yang ternyata satu orang) — `MergePersonsAction` eksplisit menolak ini (§4.3); kalau dibutuhkan nanti, itu tool level-Platform terpisah, bukan bagian spec ini.

## 10. Risiko & Trade-off

- **Migrasi backfill NIK duplikat**: kalau ditemukan 2+ baris role dengan NIK sama DALAM satu yayasan saat backfill (mis. Guru dan Karyawan yang sebenarnya orang yang sama, belum pernah ketahuan), backfill TIDAK BOLEH auto-merge — harus keluar sebagai laporan untuk direview manusia sebelum lanjut, supaya tidak salah gabung identitas.
- **~30 titik query-builder** adalah pekerjaan mekanis tapi harus DISENTUH SATU-SATU — melewatkan satu titik berarti bug 500 senyap sampai fitur itu dipakai.
- **Radius test 0 → banyak**: karena tidak ada test lama untuk dijadikan regression baseline, kualitas migrasi ini SEPENUHNYA bergantung pada test baru yang ditulis dengan disiplin di §7 — tidak ada "aman karena test lama tetap hijau" sebagai sinyal palsu.
- **`calon_murid` kemungkinan jadi tabel yang sangat kurus** (nyaris cuma `id`+`person_id`+`yayasan_id`) — perlu dipastikan saat writing-plans apakah tabel itu masih layak ada sebagai entity terpisah atau `Pendaftaran` bisa langsung FK ke `persons` (didiskusikan saat plan, bukan dikunci di sini).

## 11. Ringkasan Perubahan File (level tinggi, detail penuh di plan)

```text
database/migrations/*_create_persons_table.php                    [BARU]
database/migrations/*_add_person_id_to_{guru,karyawan,orang_tua,siswa}_table.php  [BARU, per tabel]
database/migrations/*_backfill_persons_from_role_tables.php        [BARU]
database/migrations/*_drop_identity_columns_from_role_tables.php   [BARU, dijalankan TERAKHIR, §8 langkah 6]
app/Domains/Identity/Models/Person.php                              [BARU]
app/Domains/Identity/Actions/{Create,Update,Merge,Deactivate,Reactivate}PersonAction.php  [BARU]
app/Domains/Identity/Services/PersonDuplicateFinder.php             [BARU]
app/Models/{Guru,Karyawan,OrangTua,Siswa,CalonMurid}.php            [MODIFIKASI: +person_id relation, +accessor shim, -kolom identitas lama]
app/Models/User.php                                                 [MODIFIKASI: 4 relasi jadi hasOneThrough]
app/Http/Controllers/Admin/{Guru,Karyawan,OrangTua,Siswa}Controller.php  [MODIFIKASI: panggil PersonService]
app/Http/Controllers/Admin/{SiswaImportController,PendaftaranSiswaController}.php  [MODIFIKASI]
app/Http/Controllers/Spmb/ReviewSubmitController.php                [MODIFIKASI]
app/Services/{AkunKaryawanGenerator,AkunOrangTuaGenerator}.php       [MODIFIKASI]
~19 file controller/service lain (§5.2)                             [MODIFIKASI: query-builder ke persons]
tests/Feature/Identity/*.php                                        [BARU, sesuai §7]
```
