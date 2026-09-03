# Peringatan Validasi Tingkat Kenaikan Kelas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambahkan peringatan non-blocking di halaman Kenaikan Kelas saat admin memetakan kelas ke tingkat yang tidak wajar (sama/lompat/mundur), berbasis perbandingan index terhadap daftar tingkat valid milik jenjang lembaga.

**Architecture:** Perubahan murni Alpine.js di 1 file Blade — tambah 2 properti data (`tingkatAsal`, `daftarTingkat`) dan 1 computed property (`selisihIndexTingkat`) ke `x-data` yang sudah ada per baris kelas, plus 2 elemen `<p>` kondisional baru. Tidak ada perubahan backend/controller/action/migration.

**Tech Stack:** Laravel 12 Blade, Alpine.js, Pest.

## Global Constraints

- TIDAK ADA perubahan backend/controller/action/migration/schema sama sekali — murni Blade+Alpine.
- Perbandingan WAJIB berbasis index terhadap `BentukPendidikan::validTingkatValues()`, BUKAN aritmatika angka — `validTingkatValues()` untuk jenjang KB/TPA/SPS/TK berisi `['A', 'B']` (bukan numerik), jadi `tingkat + 1` tidak valid untuk jenjang itu.
- TIDAK ADA tabel/kolom/laporan baru untuk "siswa tinggal kelas" — di luar scope total plan ini.
- TIDAK ADA library testing JS baru (Jest/Cypress/Vitest) — project ini tidak punya toolchain itu (dikonfirmasi lewat `package.json`). Test WAJIB Pest Feature test yang assert HTML markup mentah dari respons HTTP, mengikuti pola `tests/Feature/Admin/KenaikanKelasControllerUxTest.php` yang sudah ada untuk warning kurikulum-berbeda.
- Baris `<p x-show="tingkatTujuan !== null" ...>Tingkat tujuan: ...</p>` yang sudah ada TIDAK diubah/dihapus — 2 baris pesan baru ditambahkan, bukan menggantikan.
- 2 temuan sampingan (gap Activitylog untuk kemungkinan laporan "siswa tinggal kelas" di masa depan; gap `ProsesKenaikanKelasAction`'s cabang `lulus` tidak mengisi `kelas_terakhir_id`) TIDAK ditambal di plan ini — di luar scope, sudah dicatat di spec §2 sebagai catatan terpisah untuk sesi/spec lain.

---

## Task 1: Perbandingan Berbasis Index + Markup Peringatan

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php:74-83` (x-data) dan setelah baris 120 (markup peringatan)
- Test: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Enums\BentukPendidikan::validTingkatValues(): array` (method sudah ada, sudah `use`-import di baris 1 file blade ini).
- Produksi: tidak ada interface baru untuk task lain — task ini berdiri sendiri, Task 2 tidak bergantung padanya.

- [ ] **Step 1: Baca state file saat ini untuk konfirmasi baris**

Baca `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` baris 74-121 dan pastikan masih sama persis dengan yang didokumentasikan di spec (`x-data` di baris 74-83, blok peringatan kurikulum di baris 117-120). Kalau nomor baris bergeser, sesuaikan lokasi edit di step berikutnya tapi isi kodenya tetap identik.

- [ ] **Step 2: Tulis test yang gagal — jenjang numerik (SD)**

Buka `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`. File ini sudah punya helper `siapkanKenaikanKelasUxUser()`, `htmlSelectByName()`, dan pola ekstraksi chunk `<tr>` (lihat test `'renders the kurikulum-asal value...'` sebagai contoh pola yang harus diikuti persis). Tambahkan test baru di akhir file:

```php
it('wires up index-based tingkat comparison data for a numeric jenjang (SD)', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Tingkat 3', 'tingkat' => '3',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 2', 'tingkat' => '2']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 4', 'tingkat' => '4']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 6', 'tingkat' => '6']);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas Tingkat 3');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);

    expect($trChunk)->toContain('tingkatAsal: "3"');
    expect($trChunk)->toContain('daftarTingkat: ["1","2","3","4","5","6"]');
    expect($trChunk)->toContain('data-tingkat="2"');
    expect($trChunk)->toContain('data-tingkat="4"');
    expect($trChunk)->toContain('data-tingkat="6"');
    expect($html)->toContain('get selisihIndexTingkat()');
    expect($html)->toContain('↔ Tinggal kelas: tingkat tidak berubah');
    expect($html)->toContain('⚠ Tingkat tidak wajar: dari tingkat');
});
```

Sesuaikan nama import `TahunAjaran`/`Kelas` dengan yang sudah ada di `use` block file ini (sudah ter-import dari test lain di file yang sama).

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="wires up index-based tingkat comparison data for a numeric jenjang"`
Expected: FAIL — `tingkatAsal`, `daftarTingkat`, `selisihIndexTingkat`, dan kedua teks peringatan belum ada di markup.

- [ ] **Step 4: Tulis test yang gagal — jenjang alfabet (TK)**

Tambahkan test kedua di file yang sama:

```php
it('wires up index-based tingkat comparison data for an alphabetic jenjang (TK), proving it is not numeric arithmetic', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaTk = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_kenaikan_ux_tk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);
    $manager = User::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $manager->assignRole($role);

    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembagaTk->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas TK A Uji', 'tingkat' => 'A',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembagaTk->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas TK B Uji', 'tingkat' => 'B']);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas TK A Uji');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);

    expect($trChunk)->toContain('tingkatAsal: "A"');
    expect($trChunk)->toContain('daftarTingkat: ["A","B"]');
    expect($trChunk)->toContain('data-tingkat="B"');
});
```

- [ ] **Step 5: Jalankan test kedua, pastikan gagal**

Run: `php artisan test --filter="wires up index-based tingkat comparison data for an alphabetic jenjang"`
Expected: FAIL — sama seperti Step 3.

- [ ] **Step 6: Update `x-data` di view**

Baca `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` baris 74-83. Ganti:

```blade
                                    <tr class="transition hover:bg-gray-50/60" x-data="{
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumTujuan: null,
                                        tingkatTujuan: null,
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
                                    }">
```

Menjadi:

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

- [ ] **Step 7: Tambah markup peringatan baru**

Baca baris 117-120 (blok peringatan kurikulum yang sudah ada). Tepat SETELAH baris itu (sebelum penutup `</td>`), tambahkan:

```blade
                                            <p x-show="selisihIndexTingkat === 0" class="mt-1 text-xs text-gray-400" x-text="'↔ Tinggal kelas: tingkat tidak berubah (' + tingkatAsal + ')'"></p>
                                            <p x-show="selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Tingkat tidak wajar: dari tingkat ' + tingkatAsal + ' ke ' + tingkatTujuan + ' — periksa kembali pilihan kelas tujuan'"></p>
```

Baris `<p x-show="tingkatTujuan !== null" ...>Tingkat tujuan: ...</p>` yang sudah ada TIDAK disentuh — 2 baris baru ini ditambahkan setelahnya, dalam `<td>` yang sama.

- [ ] **Step 8: Jalankan kedua test lagi, pastikan lolos**

Run: `php artisan test --filter=KenaikanKelasControllerUxTest`
Expected: SEMUA test PASS (termasuk 2 test baru dan test existing yang tidak boleh regresi).

- [ ] **Step 9: Verifikasi manual (opsional tapi disarankan untuk perubahan UI)**

Kalau memungkinkan menjalankan `npm run dev`/`npm run build` dan membuka halaman `admin/kenaikan-kelas` di browser dengan data nyata: pilih kelas tujuan dengan tingkat sama seperti kelas asal, konfirmasi pesan "↔ Tinggal kelas" muncul dengan warna netral (bukan amber); pilih kelas tujuan tingkat lain (bukan +1), konfirmasi pesan "⚠ Tingkat tidak wajar" muncul warna amber. Kalau tidak ada akses browser, lewati langkah ini dan andalkan test otomatis di atas — sebutkan eksplisit di laporan task kalau verifikasi manual dilewati.

- [ ] **Step 10: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php
git commit -m "feat(akademik): peringatan tingkat tidak wajar di Kenaikan Kelas berbasis index validTingkatValues()"
```

---

## Task 2: Pembuktian Backend Non-Blocking

**Files:**
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php`

**Interfaces:**
- Tidak ada interface baru — task ini murni menambah 1 test pembuktian, tidak bergantung pada Task 1.

- [ ] **Step 1: Tulis test**

Tambahkan test baru di `tests/Feature/Admin/KenaikanKelasControllerTest.php` (mengikuti pola test lain di file ini, mis. `'moves siswa to the target kelas when mapped to promotion'`):

```php
it('does not block promoting a siswa into a kelas at the same tingkat (tinggal kelas) -- backend never validates tingkat by design', function () {
    // Pembuktian eksplisit keputusan spec: backend TIDAK PERNAH menolak kombinasi
    // tingkat apapun (sama/lompat/mundur) -- warning hanya di frontend (Task 1).
    // Test ini HARUS lolos tanpa perubahan kode apapun di ProsesKenaikanKelasAction,
    // karena action itu memang tidak pernah memvalidasi tingkat sejak awal.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '3A', 'tingkat' => '3']);
    $kelasBaruSamaTingkat = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '3B', 'tingkat' => '3']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaruSamaTingkat->id],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    expect($siswa->fresh()->kelas_id)->toBe($kelasBaruSamaTingkat->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan LANGSUNG lolos**

Run: `php artisan test --filter="does not block promoting a siswa into a kelas at the same tingkat"`
Expected: **PASS pada percobaan pertama** — ini BUKAN alur TDD merah-dulu biasa, karena tujuan test ini justru membuktikan perilaku yang SUDAH ADA (backend memang tidak pernah validasi tingkat). Kalau test ini GAGAL, itu berarti ada validasi tingkat tersembunyi di `ProsesKenaikanKelasAction` yang tidak diketahui — STOP, jangan lanjutkan, laporkan temuan itu (bertentangan dengan premis spec §1 yang menyatakan backend tidak pernah validasi tingkat).

- [ ] **Step 3: Regresi file test**

Run: `php artisan test --filter=KenaikanKelasControllerTest`
Expected: semua PASS, tidak ada regresi.

- [ ] **Step 4: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "test(akademik): buktikan backend Kenaikan Kelas tidak pernah blokir kombinasi tingkat apapun"
```

---

## Task 3: Full Test Suite Final

**Files:** Tidak ada file diubah — verifikasi akhir.

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong.

- [ ] **Step 2: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures (kecuali kegagalan yang SUDAH DIKETAHUI tidak berkaitan, mis. test SPMB dengan data Faker acak yang mengandung apostrof — kalau muncul, jalankan ulang test itu sendirian untuk konfirmasi flaky, bukan regresi dari plan ini).

- [ ] **Step 3: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau auto-fix tanpa error.

---

## Self-Review

**1. Spec coverage**: §3 (perbandingan index + markup) → Task 1. §4.1 (backend non-blocking) → Task 2. §4.2 (test frontend numerik+alfabet) → Task 1 Step 2-5. §1/§2 Global Constraints → tercermin di semua task (tidak ada task yang menyentuh backend/migration). Non-Goals (Activitylog, `kelas_terakhir_id` di cabang lulus) — sengaja tidak ada task untuknya.

**2. Placeholder scan**: tidak ada TBD — semua kode lengkap persis dari spec §3.

**3. Type consistency**: `daftarTingkat`, `tingkatAsal`, `selisihIndexTingkat` dipakai dengan nama identik di Task 1 Step 6-7 (implementasi) dan Step 2/4 (test assertion string).
