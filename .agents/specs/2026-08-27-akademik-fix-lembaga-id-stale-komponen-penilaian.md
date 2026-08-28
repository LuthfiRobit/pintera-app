# Fix: `lembaga_id` Basi pada Update Komponen Penilaian — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Temuan dari audit mendalam Semester/TahunAjaran/ElemenCp (menutup celah yang eksplisit dilaporkan lolos di audit-audit sebelumnya). `UpdateKomponenPenilaianAction` tidak menghitung ulang `lembaga_id` saat `semester_id` komponen berubah, berbeda dari `CreateKomponenPenilaianAction` yang sudah benar.

---

## 1. Latar Belakang & Masalah

`CreateKomponenPenilaianAction.php:44` sudah benar — `lembaga_id` selalu di-derive eksplisit dari `Semester::findOrFail($data->semesterId)->lembaga_id` (komentar kode menjelaskan ini WAJIB karena `ElemenCp` tidak punya `lembaga_id` sendiri). Tapi `UpdateKomponenPenilaianAction.php:20-27` mengubah `subjek_type`/`subjek_id`/`semester_id` TANPA pernah menyentuh `lembaga_id` — kolom itu tetap nilai lama meski `semester_id` sudah pindah ke semester lembaga lain.

**Kenapa ini reachable**: guard di `KomponenPenilaianController::update()` (baris 166-176) hanya memvalidasi konsistensi `subjek` vs `semester` untuk `subjek_type='mata_pelajaran'` (`abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404)`) — TIDAK PERNAH membandingkan ke `lembaga_id` ASLI milik `$komponenPenilaian` itu sendiri, dan TIDAK ADA guard sama sekali untuk `subjek_type='elemen_cp'` (karena `ElemenCp` tidak punya `lembaga_id` untuk dibandingkan).

`Semester::find($data['semester_id'])` di controller ter-scope `TenantScope` — untuk aktor lembaga-scoped biasa, ini otomatis mengembalikan `null` untuk semester lembaga lain (sehingga `abort_if($semester === null, 404)` sudah menutup celah untuk mereka). TAPI untuk **aktor level yayasan** (bisa mengakses semester dari beberapa lembaga sekaligus di bawah yayasannya yang sama, via cabang khusus di `TenantScope`), `Semester::find()` bisa berhasil menemukan semester milik lembaga LAIN dalam yayasan yang sama — dan update tetap lolos karena tidak ada perbandingan ke `lembaga_id` asli komponen.

**Dampak**: baris `komponen_penilaian` bisa berakhir dengan `lembaga_id` yang tidak konsisten dengan `semester_id`-nya sendiri — berlaku untuk KEDUA `subjek_type` (mata_pelajaran maupun elemen_cp), meski `elemen_cp` yang paling rentan karena benar-benar tanpa guard sama sekali.

## 2. Keputusan Desain

**Fix minimal, mirror pola `CreateKomponenPenilaianAction`**: di dalam blok `if (! $dipakai && ...)` yang sudah ada di `UpdateKomponenPenilaianAction` (satu-satunya tempat `semester_id` bisa berubah), tambah 1 baris menghitung ulang `lembaga_id`:

```php
public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
{
    $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

    if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
        $komponen->subjek_type = $data->subjekType;
        $komponen->subjek_id = $data->subjekId;
        $komponen->semester_id = $data->semesterId;
        $komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;
        if ($data->assessmentType !== null) {
            $komponen->assessment_type = $data->assessmentType;
        }
    }

    // ... sisanya TIDAK BERUBAH
}
```

Tambah import `use App\Models\Semester;`.

**Kenapa cukup di dalam blok ini saja**: di luar blok `if (! $dipakai && ...)`, `semester_id` tidak pernah berubah (guard `! $dipakai` + 3 kondisi non-null semuanya harus benar), jadi `lembaga_id` tidak perlu dihitung ulang di jalur lain.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak menambah guard cross-lembaga BARU untuk `subjek_type='elemen_cp'` di controller (`ElemenCp` memang tidak punya `lembaga_id`, tidak ada yang bisa dibandingkan) — kalau nanti mau membatasi "komponen tidak boleh pindah lembaga sama sekali", itu keputusan produk terpisah, bukan bug-fix murni.
- Tidak mengubah guard existing untuk `mata_pelajaran` di controller (baris 173-175) — tetap dipertahankan sebagai lapis validasi tambahan (mencegah subjek dan semester saling tidak cocok), independen dari fix `lembaga_id` ini.
- Tidak mengubah `CreateKomponenPenilaianAction` (sudah benar).
- Tidak mengubah skema, tidak ada migration.

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib (test existing di `tests/Feature/Admin/KomponenPenilaianCrudTest.php`)**: 4 test update existing (baris 254-352: update sukses, terkunci saat dipakai asesmen, terkunci saat dipakai nilai siswa, ditolak kombinasi lembaga campur) HARUS tetap PASS tanpa modifikasi assertion.

**4.2 — Bug reproduction untuk aktor yayasan (test baru, WAJIB pakai aktor scope `yayasan`, bukan `lembaga`)**: aktor lembaga-scoped biasa TIDAK BISA mereproduksi bug ini (`Semester::find()` sudah mengembalikan `null` untuk semester lembaga lain berkat `TenantScope`, sehingga `abort_if($semester === null, 404)` sudah menutupnya lebih dulu). Test HARUS pakai aktor `scope_level: 'yayasan'` dengan 2 lembaga di bawah yayasan yang SAMA:
- Komponen `subjek_type='elemen_cp'` dibuat di bawah Lembaga A (`lembaga_id` = Lembaga A, via `CreateKomponenPenilaianAction` yang benar). Aktor yayasan meng-update `semester_id`-nya ke semester milik Lembaga B (yayasan sama). SEBELUM fix: `lembaga_id` komponen tetap Lembaga A (basi) meski `semester_id` sudah Lembaga B. SESUDAH fix: `lembaga_id` ikut berubah jadi Lembaga B, konsisten dengan `semester_id` barunya.
- Test yang sama untuk `subjek_type='mata_pelajaran'`, dengan `subjek_id` DAN `semester_id` sama-sama dipindah ke Lembaga B (memenuhi guard existing subjek-vs-semester), buktikan `lembaga_id` ikut mengikuti ke Lembaga B.

**4.3 — Kasus tidak berubah (regresi negatif)**: update yang TIDAK mengubah `semester_id` (misal cuma ganti `deskripsi`/`bobot`) → `lembaga_id` tidak berubah sama sekali.

## 5. Ringkasan Perubahan File

```text
app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php   [+recompute lembaga_id saat semester_id berubah]
tests/Feature/Admin/KomponenPenilaianCrudTest.php                          [+3 test baru]
```
