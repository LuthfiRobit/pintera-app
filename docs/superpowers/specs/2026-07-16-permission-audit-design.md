# Permission Audit — Design Spec

**Tanggal:** 2026-07-16
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Diadaptasi dari pola `SyncPermissions` artisan command yang pernah diimplementasikan di project lain, disesuaikan dengan struktur akses pintera-app (permission `modul.aksi` dicek lewat `$this->authorize()` di controller dan `->can()` di Blade, bukan lewat middleware route). Halaman Tambah/Edit Role sudah punya tombol "Sync Permission" (`resources/views/admin/roles/_permission-matrix.blade.php`, logic di `resources/js/role-form.js`), tapi saat ini cuma refresh ulang daftar checkbox dari `PermissionCatalog::grouped()` — yaitu label/pengelompokan atas permission yang **sudah ada di database**, bukan perbandingan terhadap permission yang benar-benar dipakai di kode.

Ditemukan lewat pengecekan langsung: ada 46 pemanggilan `authorize('modul.aksi')` unik di controller, sementara `RolePermissionSeeder` mendaftarkan 51 permission — selisih ini yang jadi motivasi konkret fitur ini: menutup risiko permission baru yang dipakai di kode tapi lupa didaftarkan (fitur baru berjalan tanpa hak akses terdaftar), dan permission basi di database yang tidak lagi dipakai kode manapun.

## 2. Lingkup

**Termasuk:**
- `App\Services\PermissionAuditService` — memindai `app/Http/Controllers/**/*.php` dan `resources/views/**/*.blade.php` untuk pola `authorize('...')`/`->can('...')` (argumen string literal saja), membandingkan hasil unik dengan `Permission::pluck('name')` di database.
- Dua arah temuan: **missingFromDatabase** (dipakai di kode, belum di DB — otomatis dibuat lewat `Permission::findOrCreate()` saat audit dijalankan) dan **unusedInCode** (ada di DB, tidak dipakai kode manapun — cuma dilaporkan, tidak pernah dihapus otomatis).
- Extend endpoint yang sudah ada (`RoleController::permissionsCatalog()`, route `admin.roles.permissions-catalog`) untuk menyertakan hasil audit di response JSON, dipicu on-demand saat tombol "Sync Permission" yang sudah ada diklik (bukan endpoint/command baru, bukan otomatis tiap page load).
- UI: banner inline dismissible di panel permission-matrix yang sama, di atas daftar checkbox, menampilkan kedua daftar temuan. Checkbox permission baru yang otomatis dibuat langsung tersedia untuk dicentang pada role yang sedang diedit.

**Tidak termasuk (sengaja ditunda):**
- Penghapusan otomatis permission `unusedInCode` (setara `--prune` di reference code) — berisiko menghapus penugasan role-permission tanpa sengaja; penghapusan tetap manual lewat halaman Role yang sudah ada.
- Deteksi permission dengan argumen dinamis (`authorize($variabel)`) — dilewati, sama seperti batasan pola serupa di reference code.
- Audit otomatis terjadwal (cron/scheduler) — di luar scope, bisa jadi permintaan terpisah nanti.
- Command artisan CLI terpisah — audit ini murni dipicu lewat UI (tombol yang sudah ada), tidak ada `php artisan app:audit-permissions` yang bisa dijalankan independen di luar HTTP request. (Catatan implementasi: service-nya sendiri tetap reusable kalau nanti command CLI dibutuhkan, tapi tidak dibangun di plan ini.)

## 3. Cara Kerja Pemindaian

Regex tunggal `\b(?:authorize|can)\(\s*\'([a-z0-9\-\.]+)\'` diterapkan ke isi setiap file `.php` di `app/Http/Controllers` (rekursif) dan `.blade.php` di `resources/views` (rekursif) — cocok untuk `$this->authorize('modul.aksi')` maupun `->can('modul.aksi')` (termasuk `auth()->user()->can(...)`), pola yang sudah dikonfirmasi konsisten dipakai di seluruh codebase (tidak ada `@can()` directive atau Laravel Policy class yang perlu ditangani terpisah). `\b` di depan grup mencegah kecocokan semu pada method lain yang kebetulan mengandung substring "authorize"/"can" (misalnya method bernama `reauthorize(`). Semua match dikumpulkan jadi satu set unik lintas kedua direktori.

## 4. Perilaku Tiap Temuan

- **missingFromDatabase**: untuk tiap nama permission yang ditemukan di kode tapi tidak ada di `Permission::pluck('name')`, panggil `Permission::findOrCreate($name, 'web')` — aman karena murni additive, tidak ada risiko kehilangan data, dan permission baru itu otomatis ikut ke response `PermissionCatalog::grouped()` berikutnya (jadi langsung muncul sebagai checkbox baru di form yang sedang dibuka).
- **unusedInCode**: untuk tiap `Permission` di database yang namanya tidak ditemukan di hasil pemindaian kode, masukkan ke daftar laporan saja — **tidak pernah dihapus otomatis oleh service ini**. Alasan pembagian ini: menambah itu selalu aman, tapi menghapus otomatis berisiko (kalau ada route/view yang sempat nonaktif saat deploy, penugasan role-permission yang valid bisa lenyap tanpa sengaja) — jadi penghapusan tetap keputusan manual admin lewat halaman Role yang sudah ada.

## 5. Endpoint & UI

`RoleController::permissionsCatalog()` (sudah ada, route `admin.roles.permissions-catalog`) diperluas:

```php
public function permissionsCatalog()
{
    $audit = $this->permissionAudit->audit();

    return response()->json([
        'modules' => PermissionCatalog::grouped(),
        'audit' => $audit,
    ]);
}
```

`resources/js/role-form.js`'s `syncPermissions()` (sudah ada, dipanggil oleh tombol "Sync Permission" yang sudah ada) diperluas: setelah fetch sukses, simpan `json.audit` ke state Alpine baru; kalau `missingFromDatabase` atau `unusedInCode` tidak kosong, tampilkan banner kuning dismissible di `_permission-matrix.blade.php`, tepat di atas daftar checkbox modul — sesuai tata letak yang sudah divalidasi lewat mockup visual (Opsi A: banner inline, bukan modal terpisah). Banner berisi dua baris: daftar nama permission yang baru ditambahkan, dan daftar nama permission yang terdeteksi tidak terpakai.

**Hak akses**: dibatasi permission `roles.create` yang sudah ada (permission yang sama dipakai untuk mengakses halaman Tambah Role) — tidak ada permission baru yang perlu didaftarkan untuk fitur ini sendiri.

**Verifikasi visual wajib**: karena ini murni perubahan UI di halaman yang sudah ada, implementasi HARUS diverifikasi dengan benar-benar menjalankan dev server, login sebagai admin, membuka halaman Tambah/Edit Role, memicu tombol Sync, dan mengambil screenshot hasilnya — bukan cuma mengandalkan `npm run build` berhasil. Ini bagian eksplisit dari definisi selesai untuk plan ini, bukan langkah opsional.

## 6. Rencana Pengujian

- `PermissionAuditService::audit()`: permission yang ada di kode DAN di DB tidak masuk kategori manapun; permission yang cuma ada di kode masuk `missingFromDatabase` DAN benar-benar tersimpan ke DB setelah `audit()` dipanggil (idempotent — panggil dua kali tidak membuat duplikat, tidak error pada percobaan kedua); permission yang cuma ada di DB (tidak direferensikan file manapun) masuk `unusedInCode` dan TIDAK terhapus dari DB setelah `audit()` dipanggil.
- Argumen dinamis (`authorize($variabel)`) tidak menyebabkan error saat pemindaian, dan tidak menghasilkan entri palsu.
- `RoleController::permissionsCatalog()`: response JSON menyertakan key `audit` dengan struktur yang benar; user tanpa `roles.create` mendapat 403.
- Regresi: perilaku existing (checkbox ter-refresh dari katalog terbaru) tetap berfungsi untuk kasus tanpa selisih ditemukan sama sekali (kedua daftar audit kosong, banner tidak muncul).
- Verifikasi visual manual (browser + screenshot) sebagai bagian dari proses implementasi, dicatat di laporan task, bukan cuma automated test.

## 7. Non-Tujuan / Catatan

- Kalau nanti dibutuhkan command CLI terpisah (`php artisan app:audit-permissions`) untuk dijalankan di CI/pipeline, `PermissionAuditService` sudah reusable untuk itu — tinggal tambah Console Command tipis yang memanggil service yang sama, tidak perlu menulis ulang logic pemindaian.
- Penghapusan permission basi (`unusedInCode`) tetap manual selamanya kecuali ada keputusan eksplisit baru untuk mengotomatisasinya — desain ini sengaja menghindari pola `--prune` yang destruktif.
