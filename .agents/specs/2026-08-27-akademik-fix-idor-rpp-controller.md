# Fix Kritis: IDOR Lintas-Guru pada RppController — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Temuan paling serius dari audit ulang total modul Akademik (4 layer paralel). `RppController` dipakai dokumen RPP milik guru, tapi 4 dari 5 method mutasi tidak pernah memverifikasi kepemilikan — hanya permission generik yang lazim dimiliki semua guru dalam satu lembaga.

---

## 1. Latar Belakang & Masalah

`app/Http/Controllers/Admin/RppController.php` dipakai oleh **2 aktor berbeda**: guru pemilik dokumen (`store`, `update`, `submit`, `destroy`, `download`) dan verifikator/waka kurikulum (`verify`, dan `download` juga dipakai untuk meninjau sebelum approve).

Karena `Rpp` dan `Kelas` sama-sama memakai `BelongsToTenant`, celah **lintas-lembaga** sudah otomatis diblokir TenantScope. Celah nyatanya adalah **lintas-guru DALAM satu lembaga yang sama**:

1. `update()` (baris 192-214), `submit()` (216-236), `destroy()` (269-289) — TIDAK PERNAH mengecek `$rpp->guru_id` cocok dengan guru yang login. Hanya `rpp.kelola` (permission generik yang dimiliki semua guru). Guru A bisa mengubah/mengajukan/menghapus dokumen RPP milik guru B di lembaga yang sama hanya dengan mengganti `{rpp}` di URL.
2. `download()` (baris 172-190) — hanya `$this->authorize('rpp.view')`, permission generik yang juga dimiliki semua guru. Guru mana pun bisa mengunduh berkas RPP siapa pun di lembaga yang sama.
3. `store()` (baris 138-170) — `Kelas::findOrFail($request->input('kelas_id'))` tenant-scoped (jadi tidak bisa lintas-lembaga), TAPI tidak ada verifikasi bahwa guru yang membuat RPP benar-benar mengajar kelas tsb. Guru mana pun di lembaga itu bisa membuat RPP atas nama kelas yang tidak diajarnya.
4. `VerifyRppAction` (temuan terpisah dari audit Administrasi, sekalian dibereskan di sini karena satu domain) — tidak ada cross-check `lembaga_id` verifier vs `$rpp->lembaga_id` secara eksplisit, berbeda dari pola `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` yang sudah punya lapis ini. Saat ini aman HANYA karena route-model-binding `{rpp}` otomatis ter-TenantScope — tidak ada lapis pertahanan kedua kalau suatu saat ada refactor query yang lupa mempertahankan scope itu.

Pola yang BENAR sudah ada di controller lain di domain yang sama (`AsesmenController::authorizeMilikGuru()`, `JurnalKbmController`) — RPP saja yang terlewat.

## 2. Keputusan Desain

### 2.1 — Ownership check untuk `update()`, `submit()`, `destroy()`

Tambah private method di `RppController`, mengikuti pola persis `AsesmenController::authorizeMilikGuru()`:

```php
    private function authorizeMilikGuru(Rpp $rpp): void
    {
        $guru = auth()->user()->guru;
        abort_if($guru === null || $rpp->guru_id !== $guru->id, 403);
    }
```

Dipanggil di baris pertama tiap method:

```php
    public function update(UpdateRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorizeMilikGuru($rpp);

        $kelas = Kelas::findOrFail($request->input('kelas_id'));
        // ... sisanya TIDAK BERUBAH
```

```php
    public function submit(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');
        $this->authorizeMilikGuru($rpp);

        // ... sisanya TIDAK BERUBAH
```

```php
    public function destroy(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');
        $this->authorizeMilikGuru($rpp);

        // ... sisanya TIDAK BERUBAH
```

**Catatan**: `$this->authorize('rpp.kelola')` di `submit()`/`destroy()` TETAP DIPERTAHANKAN (bukan diganti) — permission generik sbg lapis pertama ("apakah user ini boleh mengelola RPP sama sekali"), `authorizeMilikGuru()` sbg lapis kedua ("apakah RPP spesifik ini miliknya"). Dua lapis, bukan pengganti satu sama lain.

### 2.2 — `download()`: guru pemilik ATAU reviewer (`rpp.verify`)

Ubah dari `$this->authorize('rpp.view')` generik menjadi:

```php
    public function download(Request $request, Rpp $rpp): Response
    {
        $guru = auth()->user()->guru;
        $isPemilik = $guru !== null && $rpp->guru_id === $guru->id;
        abort_unless($isPemilik || auth()->user()->can('rpp.verify'), 403);

        // ... sisanya TIDAK BERUBAH
```

Ini memenuhi 2 kebutuhan legitimate: guru pemilik mengunduh dokumennya sendiri, DAN waka kurikulum/kepsek (yang punya `rpp.verify`) membuka file untuk meninjau sebelum approve/reject — keduanya tetap otomatis terbatas ke lembaga yang sama karena `Rpp` route-model-binding ter-TenantScope.

### 2.3 — `store()`: verifikasi guru benar-benar mengajar kelas yang dipilih

Mirror pola `AsesmenController::store()`. Tambah setelah resolusi `$guruId` (setelah baris 150, sebelum `$dto = $request->toDTO(...)`):

```php
        if ($guru !== null) {
            if ($request->filled('mata_pelajaran_id')) {
                $mengajarKombinasiIni = \App\Models\JadwalPelajaran::where('guru_id', $guru->id)
                    ->where('kelas_id', $kelas->id)
                    ->where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))
                    ->where('semester_id', $semester->id)
                    ->exists();

                abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');
            } else {
                abort_unless((int) $kelas->wali_kelas_guru_id === $guru->id, 403, 'RPP tematik tanpa mata pelajaran hanya dapat dibuat oleh wali kelas.');
            }
        }
```

**Rasional percabangan**: RPP dengan `mata_pelajaran_id` diisi → guru mapel biasa, diverifikasi lewat kombinasi jadwal mengajar (identik pola Asesmen). RPP tanpa `mata_pelajaran_id` (kasus PAUD tematik, sesuai dukungan existing "RPP tematik tanpa mata pelajaran" yang sudah ada di sistem) → hanya wali kelas kelas tsb yang berwenang, karena tidak ada `JadwalPelajaran` untuk dicocokkan pada kasus tematik.

**Pengecualian**: seluruh percabangan verifikasi ini dibungkus `if ($guru !== null)`, memakai variabel `$guru` yang SUDAH ADA di `store()` baris 141 (`$guru = Guru::where('user_id', $user->id)->first();`) — TIDAK membuat variabel/pemanggilan baru. Kalau `$guru` null (aktor BUKAN guru — kemungkinan admin/staf yang membuatkan RPP atas nama guru lain lewat fallback `guru_id` di baris 146), verifikasi kombinasi mengajar dilewati sepenuhnya, karena "mengajar kombinasi ini" hanya bermakna untuk aktor yang benar-benar guru.

### 2.4 — `VerifyRppAction`: tambah cross-check `lembaga_id` eksplisit

Tambah parameter baru `int $verifierLembagaId`, mengikuti pola `VerifyPengajuanRaporAction`:

```php
    public function execute(Rpp $rpp, StatusRpp $targetStatus, int $verifierUserId, int $verifierLembagaId, ?string $catatanRevisi = null): Rpp
    {
        if ((int) $rpp->lembaga_id !== $verifierLembagaId) {
            throw ValidationException::withMessages([
                'lembaga' => 'Anda tidak berwenang memverifikasi dokumen RPP lembaga lain.',
            ]);
        }

        if ($rpp->status !== StatusRpp::Diajukan) {
            // ... sisanya TIDAK BERUBAH
```

`RppController::verify()` diubah untuk meneruskan parameter baru:

```php
            $this->verifyRppAction->execute(
                rpp: $rpp,
                targetStatus: $targetStatus,
                verifierUserId: (int) $request->user()->id,
                verifierLembagaId: (int) $request->user()->lembaga_id,
                catatanRevisi: $catatanRevisi
            );
```

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction`/`SubmitRppAction`/`DeleteRppAction` — semua fix ada di layer Controller + 1 Action (`VerifyRppAction`).
- Tidak mengubah skema, tidak ada migration.
- Tidak menyentuh `PresensiAggregationService` (gap terpisah dari audit ini, akan jadi spec lain).
- Tidak mengubah permission `rpp.view`/`rpp.kelola`/`rpp.verify` yang sudah ada di seeder — fix ini murni menambah pengecekan di level kode, bukan mengubah definisi permission.
- Tidak mengubah `StoreKurikulumAssignmentRequest` (temuan Data Master, terpisah, spec lain).

## 4. Testing (acceptance criteria wajib)

**4.1 — `update()`/`submit()`/`destroy()` IDOR (test baru)**:
- Guru B (di lembaga sama dengan Guru A) mencoba `update`/`submit`/`destroy` RPP milik Guru A → `assertForbidden()` (403). Data RPP di database TIDAK berubah/terhapus.
- Guru A (pemilik) melakukan hal yang sama pada RPP miliknya sendiri → tetap sukses seperti biasa (regresi negatif, pastikan `authorizeMilikGuru()` tidak menolak pemilik sah).

**4.2 — `download()` (test baru)**:
- Guru B mencoba download RPP milik Guru A (Guru B tidak punya `rpp.verify`) → `assertForbidden()`.
- User dengan `rpp.verify` (waka kurikulum) mencoba download RPP milik guru mana pun di lembaga yang sama → sukses (200, bukan pemilik tapi berwenang verifikasi).
- Guru pemilik download dokumennya sendiri → tetap sukses seperti biasa.

**4.3 — `store()` verifikasi kombinasi mengajar (test baru)**:
- Guru yang TIDAK mengajar kombinasi kelas+mapel+semester tertentu → `store()` ditolak 403, tidak ada `Rpp` baru tersimpan.
- Guru yang mengajar kombinasi itu (dibuktikan lewat `JadwalPelajaran` yang sesuai) → `store()` sukses seperti biasa.
- RPP tematik PAUD tanpa `mata_pelajaran_id`: wali kelas kelas tsb → sukses. Guru BUKAN wali kelas kelas tsb (tanpa mata_pelajaran_id) → 403.
- Regresi wajib: test existing `RppWorkflowTest.php` (termasuk skenario "mendukung upload RPP tematik PAUD tanpa mata pelajaran") HARUS tetap PASS — guru di test itu sudah difixturekan sbg wali kelas dari kelasnya sendiri, verifikasi ini tidak boleh menolaknya.

**4.4 — `VerifyRppAction` cross-check lembaga (test baru)**:
- Verifier dari lembaga LAIN (bukan lembaga pemilik RPP) mencoba `verify()` → gagal validasi dengan pesan error field `lembaga`, status RPP tidak berubah.
- Verifier dari lembaga yang sama → tetap sukses seperti biasa (regresi negatif).

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/RppController.php      [+authorizeMilikGuru(), +guard download(), +verifikasi kombinasi mengajar di store(), +teruskan verifierLembagaId di verify()]
app/Domains/Akademik/Actions/Rpp/VerifyRppAction.php  [+parameter verifierLembagaId, +cross-check lembaga_id]
tests/Feature/Akademik/RppControllerIdorTest.php  [BARU]
```
