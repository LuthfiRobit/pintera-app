# Sub-project 3a — Dashboard (SPMB Account-First Redesign)

## Konteks

Bagian terakhir dari 4 sub-project inisiatif SPMB account-first redesign. Sub-project 1 (Welcome & Katalog), 2 (Registrasi & Verifikasi), dan 3b (Wizard Ter-otentikasi) sudah SHIPPED ke `main`. Route `portal.wizard.*` (10 route, guard `auth:portal`+`portal.verified`) sudah live tapi TIDAK punya entry point normal dalam alur — hanya bisa diakses lewat URL langsung, karena Sub-project 3a-lah yang seharusnya jadi jembatan dari "baru login" ke "masuk wizard".

`portal.dashboard` (`GET /portal/dashboard`, `Portal\DashboardController@index`) sudah ada dan sudah berada di middleware group yang benar (`auth:portal`+`portal.verified`), tapi isinya masih versi lama:
- Controller hanya mengambil `$user->pendaftaran()->with([...])->latest('submitted_at')->get()` lalu render — tidak ada logika percabangan sama sekali.
- View (`resources/views/portal/dashboard.blade.php`) masih pakai layout & token desain lama (`x-portal-layout`, `spmb-primary`, `slate`, `ink` — bukan sistem navy `portal-*`/`gray-*` yang sudah dipakai di Sub-project 1/2/3b).
- Badge status hanya menangani 3 dari 5 nilai enum riil kolom `pendaftaran.status` (`menunggu_verifikasi`, `diterima`, `ditolak`, `daftar_ulang`, `aktif`) — `daftar_ulang` dan `aktif` jatuh ke bucket generik "Menunggu Verifikasi" yang salah. Diperbaiki sebagai bagian dari sub-project ini karena langsung berada di area yang disentuh.

## Tujuan

Membuat `/portal/dashboard` menjadi entry point nyata dari alur account-first: yang baru saja login/verifikasi otomatis diarahkan lanjut ke wizard, yang punya riwayat pendaftaran melihatnya dalam desain navy, dan yang belum memilih apa-apa mendapat ajakan yang jelas — bukan dilempar keluar dari dashboard-nya sendiri.

## Global Constraints

- Palet & token: `portal-50`/`portal-500`/`portal-600`, skala `gray-*` (Untitled-UI), semantic `success-*`/`warning-*`/`error-*` — sama seperti Sub-project 1/2/3b, BUKAN token lama (`spmb-primary`, `slate`, `ink`, dst).
- Font `Outfit` (sudah default app-wide, tidak perlu load ulang).
- Breakpoint navbar mobile: ≤720px (`min-[721px]:` sudah dipakai konsisten di `<x-portal-authenticated-navbar>` — ikuti pola yang sama, jangan pakai breakpoint Tailwind default `md:`).
- Reuse existing komponen, jangan bikin ulang: `<x-portal-authenticated-navbar active="dashboard|riwayat">` (sudah ada dari Sub-project 3b), `<x-portal-footer :yayasan="...">`.
- Controller & view path TIDAK pindah: `Portal\DashboardController` dan `resources/views/portal/dashboard.blade.php` dimodifikasi in-place (bukan file baru), mengikuti pola Sub-project 3b (route/nama tetap, isi ditulis ulang).

## Desain

### 1. Branching logic — `DashboardController::index()`

```
GET /portal/dashboard
├── State 1 — TIDAK ada Pendaftaran + session('spmb_pilihan.lembaga_id'/'jalur_id') LENGKAP
│     → redirect ke route('portal.wizard.data-diri')
│       (dashboard tidak pernah benar-benar render di state ini)
│
├── State 2 — ADA Pendaftaran milik akun ini
│     → render dashboard: daftar "Riwayat Pendaftaran"
│       PENGECUALIAN: jika session JUGA punya lembaga_id+jalur_id yang valid DAN
│       kombinasi (lembaga_id, jalur_id) itu BELUM pernah didaftarkan oleh akun ini
│       (dicek terhadap Pendaftaran::where('lembaga_id', ...)->where('jalur_ppdb_id', ...)
│       milik akun yang sama), maka State 1 (redirect ke wizard) didahulukan —
│       akun ini sedang mendaftar jalur BARU meski sudah punya riwayat.
│
└── State 3 — TIDAK ada Pendaftaran + session kosong/tidak lengkap
      → render dashboard dengan empty-state: ajakan pilih lembaga & jalur,
        CTA ke /spmb. TIDAK redirect — dashboard tetap jadi "rumah" pengguna.
```

Validitas session `spmb_pilihan.*` diperiksa dengan cara yang sama seperti `ResolvesWizardContext::resolveWizardContext()` (Lembaga & JalurPpdb harus benar-benar ditemukan by ID, bukan cuma dicek `isset`) — tapi dashboard TIDAK melempar redirect exception seperti trait itu, karena dashboard yang justru jadi tujuan fallback-nya. `DashboardController` tidak `use ResolvesWizardContext` (trait itu untuk controller wizard yang mengasumsikan konteks harus ada); dashboard menulis pengecekan lookup-nya sendiri secara inline (lookup gagal/null diperlakukan sama seperti "session kosong" → State 3, bukan error).

Pseudocode:

```php
public function index(): View|RedirectResponse
{
    $akun = Auth::guard('portal')->user();

    $pendaftaranList = $akun->pendaftaran()
        ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
        ->latest('submitted_at')
        ->get();

    $lembaga = session('spmb_pilihan.lembaga_id')
        ? Lembaga::find(session('spmb_pilihan.lembaga_id'))
        : null;
    $jalur = session('spmb_pilihan.jalur_id')
        ? JalurPpdb::find(session('spmb_pilihan.jalur_id'))
        : null;

    if ($lembaga && $jalur) {
        $sudahDidaftarkan = $pendaftaranList->contains(
            fn (Pendaftaran $p) => $p->lembaga_id === $lembaga->id && $p->jalur_ppdb_id === $jalur->id
        );

        if (! $sudahDidaftarkan) {
            return redirect()->route('portal.wizard.data-diri');
        }
    }

    return view('portal.dashboard', [
        'pendaftaranList' => $pendaftaranList,
    ]);
}
```

Poin penting: tidak ada state di mana view menerima "ada jalur baru tapi tetap render daftar" — begitu kombinasi lembaga+jalur baru terdeteksi, controller SELALU redirect sebelum sampai ke `view()`, tidak pernah setengah-setengah. View karena itu tidak butuh flag apa pun untuk kasus ini.

### 2. View — `resources/views/portal/dashboard.blade.php`

Struktur top-to-bottom:

1. `<x-portal-authenticated-navbar active="dashboard" />` (reuse, sudah ada).
2. Header sapaan: `"Halo, {nama depan akun}"` + subtitle satu baris ("Pantau status pendaftaranmu di sini.").
3. **State 2 (ada Pendaftaran)** — daftar "Riwayat Pendaftaran": satu card per `Pendaftaran`, retokenisasi dari struktur lama (nama calon murid, lembaga · jalur · gelombang, kode pendaftaran mono, badge status, link "Unduh Bukti (PDF)" → `portal.pendaftaran.bukti`) ke card style `rounded-xl border border-gray-200 p-5` (pola sama seperti section card di `review.blade.php`), badge status 5-state:
   - `menunggu_verifikasi` → amber (`warning-50`/`warning-700`), "Menunggu Verifikasi"
   - `diterima` → hijau (`success-50`/`success-700`), "Diterima"
   - `ditolak` → merah (`error-50`/`error-700`), "Ditolak"
   - `daftar_ulang` → navy (`portal-50`/`portal-500`), "Daftar Ulang"
   - `aktif` → hijau (`success-50`/`success-700`), "Aktif"

   Di atas daftar: tombol "Daftar Lagi" (`rounded-[10px] bg-portal-500 px-5 py-2.5 text-white`, ikon `add`) → `route('spmb.welcome')` (route Welcome page dari Sub-project 1).

4. **State 3 (empty-state)** — card terpusat (`rounded-2xl border border-dashed border-gray-200 p-10 text-center`, pola sama seperti empty-state Welcome page Sub-project 1): ikon besar (`school` atau `add_circle`, `h-10 w-10 text-portal-300`), heading "Kamu belum memilih lembaga & jalur", body text "Pilih lembaga dan jalur pendaftaran untuk mulai mengisi formulir.", tombol CTA "Pilih Lembaga & Jalur" (`bg-portal-500`, ikon `arrow_forward`) → `route('spmb.welcome')`.
5. `<x-portal-footer :yayasan="null" />` (akun pendaftar tidak terikat ke satu lembaga/yayasan tertentu sampai punya `Pendaftaran` — beda dengan wizard shell yang selalu punya `$lembaga` dari context; footer di sini render tanpa data yayasan spesifik, sama seperti footer Welcome page untuk visitor umum).

Layout pembungkus: `<x-layouts.portal-public>` TIDAK dipakai (itu untuk halaman publik/guest, punya navbar guest). Dashboard butuh layout baru — `<x-layouts.portal-dashboard title="Dashboard">` (baru, mirip strukturnya dengan `layouts.portal-wizard.blade.php` tapi TANPA strip "Mendaftar sebagai" dan TANPA stepper+sidebar wizard, karena dashboard tidak selalu berada dalam konteks lembaga/jalur tertentu — beda dari wizard yang selalu punya `$lembaga`/`$jalur`). Isinya: `<x-portal-authenticated-navbar>` + `<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-10">{{ $slot }}</main>` + `<x-portal-footer>`.

### 3. Route `spmb.welcome`

Dipakai sebagai target CTA "Daftar Lagi" dan "Pilih Lembaga & Jalur". Dikonfirmasi ada (`php artisan route:list --name=spmb.welcome` → `GET /spmb`, `Spmb\WelcomeController@index`, dibuat di Sub-project 1) — dipakai apa adanya, tidak ada perubahan pada route itu sendiri.

## Testing Plan

- State 1: akun login, tidak ada `Pendaftaran`, session `spmb_pilihan.*` lengkap & valid → assert redirect ke `portal.wizard.data-diri`.
- State 1 (session invalid): session `spmb_pilihan.lembaga_id` mengarah ke Lembaga yang tidak ada (ID acak) → assert TIDAK redirect ke wizard, dashboard render dengan empty-state (diperlakukan sama seperti session kosong).
- State 2: akun login, punya 1+ `Pendaftaran`, session kosong → assert dashboard render, daftar riwayat muncul, tombol "Daftar Lagi" ada dan mengarah ke `spmb.welcome`.
- State 2 dengan badge 5-state: buat 5 `Pendaftaran` dengan masing-masing status berbeda → assert masing-masing badge text & warna class yang benar muncul di response.
- State 2 + session jalur baru: akun punya `Pendaftaran` existing di (lembaga A, jalur A), session `spmb_pilihan.*` menunjuk ke (lembaga A, jalur B — beda jalur) → assert redirect ke wizard (bukan render daftar), karena kombinasi lembaga+jalur ini belum pernah didaftarkan akun ini.
- State 2 + session jalur SAMA yang sudah pernah didaftarkan: session menunjuk ke (lembaga A, jalur A) yang PERSIS sama dengan `Pendaftaran` yang sudah ada → assert TIDAK redirect, dashboard render daftar riwayat seperti biasa (bukan mendaftar ulang jalur yang sama).
- State 3: akun login, tidak ada `Pendaftaran`, session kosong → assert dashboard render (bukan redirect ke `/spmb`), empty-state muncul dengan CTA ke `spmb.welcome`.
- Unauthenticated request ke `/portal/dashboard` → assert redirect ke `portal.login` (perilaku middleware existing, regression check saja — tidak ada perubahan kode di sini).
- Navbar `active="dashboard"` benar-benar ter-highlight di halaman dashboard (assertion sederhana, cek class/atribut).

## Di luar scope (deferred, konsisten dengan keputusan sebelumnya)

Status pembayaran per-item, jumlah dokumen terverifikasi, info jadwal tes — bagian dari mockup awal yang sudah diputuskan tidak pernah benar-benar render di sistem nyata (karena `Pendaftaran` hanya tercipta di submit terakhir wizard, bukan progres bertahap) dan merupakan wilayah Sub-project 4 (Pembayaran Biaya Pendaftaran & Info Jadwal Tes).
