# Halaman Error Custom Bergaya Pintera — Spec

## 1. Latar Belakang

Repo Pintera saat ini TIDAK punya `resources/views/errors/` sama sekali — dikonfirmasi lewat `ls resources/views/errors/` (direktori tidak ada). Akibatnya semua kode error HTTP (403, 404, 419, 422, 429, 500, 503) jatuh ke halaman default bawaan Laravel — polos, tidak berbrand, dan terasa seperti "keluar" dari aplikasi (user menunjukkan screenshot halaman 403 default: teks putih di atas background gelap polos, tanpa identitas Pintera sama sekali).

Tujuan: buat 7 halaman error custom (403, 404, 419, 422, 429, 500, 503) yang terasa seperti bagian asli dari platform edukasi Pintera — bukan template error Laravel generik.

## 2. Prinsip Desain (dari brief user, verbatim intent)

- Layout tetap sederhana: ikon kecil relevan konteks → kode error besar → judul → pesan singkat → 1 tombol aksi utama.
- Ikon: gaya outline/rounded dengan stroke lembut, modern-ramah-profesional-edukatif — BUKAN emoji, BUKAN ikon teknis/menyeramkan, BUKAN ilustrasi ramai.
- Pesan error tetap jadi fokus utama; ikon cuma elemen pendukung.
- Branding Pintera konsisten tapi tidak berlebihan.
- Halaman ringan, bersih, mudah dipahami user dari berbagai jenjang pendidikan (termasuk orang tua siswa PAUD/TK, bukan cuma staf teknis).
- Hasil akhir harus terasa seperti bagian asli platform Pintera, bukan halaman error Laravel/teknis generik.

## 3. Sistem Ikon yang Dipakai (WAJIB reuse yang sudah ada)

Repo ini SUDAH punya sistem ikon SVG inline di `resources/views/components/icon.blade.php` — dipanggil sebagai `<x-icon name="..." class="..." />`, setiap `@case` berisi 1 SVG dengan konvensi gaya SERAGAM: `viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"`. Ini SUDAH PERSIS gaya outline/rounded-stroke-lembut yang diminta user — WAJIB dipakai, JANGAN buat sistem ikon baru atau load Material Symbols font (font itu di-load di `guest.blade.php` tapi TIDAK dipakai lagi di komponen manapun setelah migrasi ke SVG inline — biarkan seperti itu, di luar cakupan spec ini).

### 3.1 Mapping Kode Error → Ikon

| Kode | `<x-icon name="...">` | Status |
|---|---|---|
| 403 | `lock` | Sudah ada di `icon.blade.php` — pakai langsung |
| 404 | `book_search` | **BARU** — ditambahkan ke `icon.blade.php` sebagai `@case` baru (lihat §3.2) |
| 419 | `schedule` | Sudah ada — pakai langsung |
| 422 | `checklist` | Sudah ada — pakai langsung |
| 429 | `hourglass_top` | Sudah ada — pakai langsung |
| 500 | `server` | **BARU** — ditambahkan ke `icon.blade.php` (lihat §3.2) |
| 503 | `build` | **BARU** — ditambahkan ke `icon.blade.php` (lihat §3.2) |

### 3.2 3 Ikon Baru — SVG Lengkap (gaya identik dengan ikon existing)

**`book_search`** (buku terbuka + aksen kaca pembesar — untuk 404, "halaman yang dicari tidak ditemukan" dengan nuansa platform pembelajaran):

```blade
@case('book_search')
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M2 5.5C2 4 3.5 3 5.5 3H11v14H5.5C3.5 17 2 18 2 19.5V5.5Z"/><path d="M22 5.5C22 4 20.5 3 18.5 3H13v14h5.5c2 0 3.5 1 3.5 2.5V5.5Z"/><circle cx="17.3" cy="14.3" r="2.2"/><path d="M19 15.9 20.6 17.5"/></svg>
    @break
```

**`server`** (rak server dengan lampu indikator + celah tengah — untuk 500, "gangguan sistem" yang tetap tidak menyeramkan):

```blade
@case('server')
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><circle cx="7" cy="7" r="0.8" fill="currentColor" stroke="none"/><circle cx="7" cy="17" r="0.8" fill="currentColor" stroke="none"/><path d="M12 10.5v3"/></svg>
    @break
```

**`build`** (kunci pas — untuk 503, "sedang dalam perawatan"):

```blade
@case('build')
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 1 5.4-5.4l-2.6 2.6-2-2 2.6-2.6Z"/></svg>
    @break
```

Ketiganya disisipkan ke `resources/views/components/icon.blade.php` sebagai `@case` baru, di posisi manapun sebelum `@default` (urutan tidak penting secara fungsional, tapi untuk kerapian taruh berurutan sebagai satu blok, mis. setelah `@case('receipt')` yang merupakan case terakhir sebelum `@default`).

## 4. Copy per Halaman (Bahasa Indonesia, nada hangat-institusional konsisten dengan copy aplikasi yang sudah ada)

| Kode | Judul | Pesan |
|---|---|---|
| 403 | Akses Dibatasi | Halaman ini khusus untuk peran tertentu. Kalau menurut Anda ini keliru, hubungi admin sekolah Anda. |
| 404 | Halaman Tidak Ditemukan | Halaman yang Anda cari mungkin sudah dipindahkan atau tidak tersedia lagi. |
| 419 | Sesi Anda Berakhir | Demi keamanan, sesi otomatis berakhir setelah tidak aktif. Silakan masuk kembali untuk melanjutkan. |
| 422 | Periksa Kembali Data Anda | Beberapa data yang dikirim belum sesuai. Silakan periksa kembali formulirnya. |
| 429 | Terlalu Banyak Permintaan | Sistem sedang menerima banyak aktivitas dari perangkat Anda. Mohon tunggu sebentar lalu coba lagi. |
| 500 | Ada Gangguan di Sistem | Tim kami sedang menangani masalah ini. Silakan coba lagi dalam beberapa saat. |
| 503 | Sedang Dalam Perawatan | Kami sedang melakukan pemeliharaan untuk pengalaman yang lebih baik. Silakan kembali sebentar lagi. |

## 5. Layout & Struktur Visual

Reuse identitas visual `resources/views/layouts/guest.blade.php` (SUDAH ADA, dipakai halaman login/register) — bukan bikin desain baru dari nol:

- Full-viewport background: `bg-gradient-to-br from-ink via-[#123363] to-ink`, dengan 2 lingkaran blur dekoratif (`bg-white/10` dan `bg-brass/20`, posisi pojok, `blur-3xl`) — SAMA PERSIS dengan `guest.blade.php`.
- Brand mark di atas: kotak rounded border brass berisi huruf pertama nama app, plus nama app + tagline "Sistem Administrasi" — SAMA PERSIS dengan `guest.blade.php`.
- Kartu putih rounded-2xl (`shadow-elevated`, `border border-white/10`) berisi, dari atas ke bawah, center-aligned, spacing lega:
  1. Badge lingkaran lembut (`bg-ink/5` atau `bg-brass/10`, rounded-full, padding cukup) berisi `<x-icon>` ukuran ~32-40px, warna ikon `text-ink` atau `text-brass`.
  2. Kode error dalam angka besar (`font-display`, bold, ukuran besar mis. `text-5xl`/`text-6xl`, warna `text-ink`).
  3. Judul (`font-display`, bold, ukuran sedang, `text-ink`).
  4. Pesan (`text-sm` atau `text-base`, warna abu netral, max-width supaya tidak terlalu lebar, `text-center`).
  5. Satu tombol aksi (lihat §6).

Semua 7 halaman berbagi 1 struktur/komponen yang sama — parameter yang berbeda cuma kode, ikon, judul, pesan. Implementasi: buat 1 partial/component reusable (`resources/views/components/error-page.blade.php`) yang menerima props (`code`, `icon`, `title`, `message`), dipanggil dari 7 view tipis di `resources/views/errors/{403,404,419,422,429,500,503}.blade.php`.

## 6. Tombol Aksi (Auth-Aware)

- Kalau `auth()->check()` → tombol "Kembali ke Dashboard", `href="{{ route('dashboard') }}"`. Route `dashboard` sudah ada di `routes/web.php` (named route top-level, redirect berdasarkan role — dikonfirmasi lewat `grep -n "name('dashboard')" routes/*.php`, TIDAK PERLU dibuat baru).
- Kalau belum login (guest) → tombol "Ke Halaman Login", `href="{{ route('login') }}"`. Route `login` sudah ada (`routes/auth.php`, dikonfirmasi lewat grep).
- Style tombol: reuse pattern tombol utama yang sudah ada di aplikasi (cek `resources/views/components/link-button.blade.php` atau `danger-button.blade.php` untuk konvensi kelas Tailwind yang konsisten — WAJIB dicek isinya sebelum implementasi, JANGAN reka kelas baru kalau sudah ada komponen tombol yang bisa dipakai).

## 7. Laravel Exception Handling — Tidak Perlu Registrasi Manual

Laravel SECARA OTOMATIS me-render `resources/views/errors/{code}.blade.php` untuk HTTP exception dengan kode status yang cocok (`HttpException`, `NotFoundHttpException`, `TokenMismatchException` → 419, `ValidationException` di request non-JSON → 422 fallback halaman kalau relevan, `ThrottleRequestsException` → 429, dll.) — TIDAK PERLU registrasi custom di `bootstrap/app.php` atau `app/Exceptions/Handler.php`. Cukup membuat file view dengan nama kode statusnya, framework yang urus render otomatis. Verifikasi ini WAJIB dilakukan lewat test nyata (bukan asumsi), lihat §9.

Catatan: 422 (Validation Error) HANYA akan menampilkan halaman ini kalau request-nya BUKAN request AJAX/JSON (Laravel default balikin JSON untuk request `Accept: application/json`). Untuk aplikasi ini yang campuran (ada portal AJAX-fragment pattern per konvensi `laravel-feature-standard/SKILL.md` §27a poin 4), halaman 422 ini kemungkinan JARANG terlihat user secara langsung (submit form biasa via browser non-AJAX baru akan melihatnya) — tetap dibuat sesuai permintaan user, tapi task testing WAJIB mendokumentasikan kondisi ini, bukan mengasumsikan semua submit form akan memicu halaman ini.

## 8. Yang TIDAK Berubah / Di Luar Cakupan

- Tidak ada perubahan logic aplikasi, controller, middleware, atau route.
- Tidak ada perubahan pada `guest.blade.php` (cuma DICONTOH gaya visualnya, filenya sendiri tidak disentuh).
- Tidak menghapus Material Symbols font loading di `guest.blade.php` (di luar cakupan, itu keputusan terpisah).
- Halaman `401 Unauthorized` TIDAK dibuat (user memutuskan cakupan 403/404/419/422/429/500/503 saja — 401 jarang terjadi karena middleware auth biasanya redirect ke login, bukan render 401).
- Tidak ada perubahan pada 3 file test yang memakai domain `@permata.sch.id` sebagai fixture independen (`GuruBkFieldsTest`, `GuruCrudTest`, `KaryawanCrudTest`) — tidak relevan dengan spec ini.

## 9. Testing

- Test feature baru per kode: request yang memicu status tersebut (mis. `$this->get('/route-tidak-ada')->assertStatus(404)`) HARUS `assertSee()` judul & sebagian pesan halaman baru, MEMBUKTIKAN Laravel benar-benar me-render view custom (bukan fallback ke default) — ini verifikasi §7 secara nyata, bukan asumsi.
- Minimal 1 test membuktikan tombol auth-aware: user login → assertSee teks "Kembali ke Dashboard"; guest → assertSee teks "Ke Halaman Login".
- Grep menyeluruh sebelum & sesudah implementasi untuk memastikan tidak ada test lain yang sudah meng-assert teks/markup default Laravel (`->assertSee('Forbidden')`, `->assertSee('Page Not Found')`, dsb) yang akan bentrok — SUDAH DICEK saat spec ini ditulis (grep `assertStatus(403|404|419|429|500|503)` di `tests/`, tidak ada yang dipasangkan dengan assertion teks default Laravel), tapi plan implementasi WAJIB grep ulang sebelum eksekusi untuk berjaga-jaga kalau ada commit baru.
- Full suite HANYA di task terakhir, minta izin user dulu sebelum dijalankan.

## 10. Asumsi

- Baseline: commit terakhir di branch `rbac-v2` saat spec ini ditulis (`4f4aa3e`). Plan implementasi WAJIB verifikasi ulang isi `icon.blade.php`, `guest.blade.php`, dan route `dashboard`/`login` kalau ada commit baru sebelum dieksekusi.
- `route('dashboard')` mengarah ke controller yang SUDAH menangani redirect berbeda per role (bukan halaman generik) — tidak perlu logic tambahan di halaman error untuk menentukan tujuan spesifik per role, cukup arahkan ke route itu.
