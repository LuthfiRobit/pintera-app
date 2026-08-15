# Halaman Admin Kelola Virtual Account — Design Spec

**Tanggal:** 2026-08-15
**Terkait:** `docs/superpowers/specs/2026-08-15-bri-snap-va-inbound-design.md` (arsitektur VA inbound yang jadi dasar fitur ini)

## Latar Belakang

Integrasi BRI SNAP VA Inbound (sub-project sebelumnya) membuat setiap siswa bisa punya satu nomor Virtual Account BRI permanen, dibuat via `PaymentService::getOrCreatePermanentVa()` saat orang tua pertama kali klik "Buat Kode Virtual Account BRI" di checkout. Belum ada halaman admin untuk melihat, mencari, atau mengelola nomor VA ini secara terpusat — item ini sengaja ditunda sebagai spec terpisah saat brainstorming sub-project itu.

## Tujuan

Halaman admin untuk:
1. Melihat & mencari daftar siswa yang **sudah punya** nomor VA, beserta saldo wallet dan riwayat pembayaran masuk.
2. Generate VA secara manual untuk siswa yang belum punya — satu per satu (pilih manual, multi-select) atau massal (semua siswa aktif tanpa VA sekaligus).
3. Export daftar VA ke Excel.

## Arsitektur

### Controller
`app/Http/Controllers/Admin/VirtualAccountController.php` — pola sama persis `ManualPaymentController` (scope lembaga via `TenantScope` + `widestScopeLevel()`/`session('active_lembaga_id')`, AJAX fragment untuk search/filter tanpa reload).

Method:
- `index(Request $request): View` — tabel siswa yang sudah punya VA, scope lembaga, search (nama/NIS), filter kelas, pagination (10/20/25/50, pola `ManualPaymentController`). Query dasar dari `BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')` join `Wallet` → `Siswa`.
- `riwayat(Request $request, Siswa $siswa)` — AJAX fragment/modal, list `BriInboundPaymentLog` milik siswa (tanggal, nominal, `payment_request_id`), read-only, terurut terbaru dulu.
- `calonGenerate(Request $request)` — AJAX, list siswa `status='aktif'` tanpa VA di scope lembaga, untuk tabel pilih-manual di modal (search + filter kelas + checkbox).
- `generate(Request $request)` — terima `mode: 'semua'|'manual'` + `siswa_ids[]` (kalau manual). Loop siswa target, panggil `getOrCreatePermanentVa($siswa)` per siswa dalam try/catch individual (satu gagal tidak menghentikan yang lain). Redirect balik dengan flash ringkasan "X berhasil dibuat, Y gagal" (+ daftar nama gagal kalau ada). Kegagalan per-siswa di-`Log::error()`.
- `export(Request $request)` — `Excel::download(new VirtualAccountExport($lembagaId, filter), 'virtual-account.xlsx')`, kolom: nama siswa, NIS, kelas, lembaga, nomor VA, tanggal dibuat, saldo wallet.

Semua method diproteksi `$this->authorize('pembayaran.virtual-account')`. Untuk `generate` mode manual, setiap `siswa_id` di payload divalidasi ada di scope lembaga admin yang login (404 kalau tidak — pola sama `ManualPaymentController::siswaLembagaId()`); target `status='aktif'` selalu difilter otomatis di server (tidak dipercaya dari input klien).

### Export
`app/Exports/VirtualAccountExport.php` — pola sama `SiswaImportTemplateExport`, implements `FromCollection`/`WithHeadings` dari `maatwebsite/excel` (sudah terpasang).

### Model
Tidak ada model/migration baru — reuse `BriVirtualAccount`, `BriInboundPaymentLog`, `Wallet`, `Siswa` yang sudah ada.

### Route
`routes/admin.php`, grup dekat `manual-payment.*`:
```php
Route::get('virtual-account', [VirtualAccountController::class, 'index'])->name('virtual-account.index');
Route::get('virtual-account/calon', [VirtualAccountController::class, 'calonGenerate'])->name('virtual-account.calon');
Route::get('virtual-account/{siswa}/riwayat', [VirtualAccountController::class, 'riwayat'])->name('virtual-account.riwayat');
Route::post('virtual-account/generate', [VirtualAccountController::class, 'generate'])->name('virtual-account.generate');
Route::get('virtual-account/export', [VirtualAccountController::class, 'export'])->name('virtual-account.export');
```

### Permission
Permission baru `pembayaran.virtual-account`, mengikuti konvensi penamaan granular yang sudah ada (`tagihan.view`, `pembayaran.verifikasi`). Ditambahkan ke `database/seeders/PermissionSeeder.php` dan di-assign ke role `admin_keuangan` di `database/seeders/RoleSeeder.php`.

## Tampilan (UI)

**Menu sidebar:** "Virtual Account" — item **paling atas** di grup Keuangan (sebelum Transfer Manual, Tagihan, dst).

**Halaman utama** (`resources/views/admin/virtual-account/index.blade.php`), pola sama `manual-payment/index.blade.php` (server-render + AJAX fragment):
- Header: search (nama/NIS) + filter kelas, tombol "Export Excel", tombol "Generate VA".
- Tabel (hanya siswa yang sudah punya VA): Nama Siswa, Kelas, Nomor VA, Saldo Wallet, Tanggal Dibuat, tombol "Lihat Riwayat" (buka modal AJAX).

**Modal "Generate VA"**:
- Dropdown "Bank" — 1 opsi "BRI", terpilih & terkunci (disabled), untuk future-proofing struktur form kalau ada bank lain nanti.
- Radio "Pilihan Generate":
  - **"Semua Siswa Tanpa VA"** (default) — generate untuk seluruh siswa aktif tanpa VA di scope lembaga.
  - **"Pilih Manual"** — menampilkan tabel siswa aktif tanpa VA (AJAX dari `calonGenerate`), dengan search + filter kelas + checkbox multi-select.
- Tidak ada field status aktif/nonaktif — generate selalu terbatas ke `status='aktif'` di level server.
- Tombol "Simpan" → POST ke `generate` dengan `mode` + (kalau manual) `siswa_ids[]`.

## Error Handling

- Generate massal/manual: kegagalan per-siswa (try/catch individual) tidak menghentikan proses siswa lain; hasil akhir selalu berupa ringkasan berhasil/gagal ke admin, bukan diam-diam gagal sebagian.
- Siswa yang sudah punya VA tidak muncul di daftar "Pilih Manual" maupun ikut ter-generate ulang saat mode "Semua" (idempotent by construction — filter `whereDoesntHave('wallet.briVirtualAccounts')`).
- Otorisasi lembaga: admin non-yayasan tidak bisa generate/lihat siswa di luar lembaganya (404).

## Testing

`tests/Feature/Admin/VirtualAccountControllerTest.php`:
1. Index hanya menampilkan siswa yang sudah punya VA, terscope lembaga.
2. Search & filter kelas bekerja di index.
3. `calonGenerate` hanya mengembalikan siswa aktif tanpa VA, scope lembaga.
4. Generate manual (`mode=manual`, `siswa_ids[]` terpilih) — VA dibuat hanya untuk yang dipilih.
5. Generate massal (`mode=semua`) — VA dibuat untuk semua siswa aktif tanpa VA di scope lembaga; siswa lembaga lain atau nonaktif tidak ikut.
6. Idempotency — siswa yang sudah punya VA tidak ter-generate ulang di kedua mode.
7. `riwayat` menampilkan log `BriInboundPaymentLog` yang benar milik siswa tsb, siswa lain tidak bocor.
8. Export menghasilkan file dengan kolom & baris yang benar.
9. Otorisasi: request tanpa permission `pembayaran.virtual-account` ditolak; admin lembaga lain tidak bisa generate/lihat siswa di luar scope-nya (404).
10. Manual browser verification: modal buka/tutup, toggle radio "Semua"/"Pilih Manual", search+filter+checkbox di tabel pilih-manual, flash ringkasan berhasil/gagal setelah generate.
