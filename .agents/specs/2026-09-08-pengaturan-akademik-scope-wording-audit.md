# Spec: Kejujuran Wording & UX Akses Tanpa Lembaga Aktif — Menu Pengaturan Akademik

> **Branch**: `rbac-v2`
> **Tanggal**: 8 September 2026
> **Latar belakang**: Audit menu "Pengaturan Akademik" (lanjutan Tahun Ajaran → Kelas → Mata Pelajaran). Backend TERKONFIRMASI SUDAH SANGAT SOLID — `PengaturanAkademikController`/`KalenderAkademikController` sudah pakai `resolveActiveLembagaId()` di semua titik, entri "nasional" vs "lembaga" sudah dipisah dengan permission terpisah, cross-lembaga sudah `abort(404)`. TIDAK ADA perubahan backend logic scope di spec ini. 2 temuan murni UX/frontend: (1) halaman tidak pernah menampilkan nama lembaga yang sedang dikonfigurasi, dan (2) — dikoreksi user secara langsung — aktor yang belum switch lembaga DITOLAK dan diusir ke `/dashboard`, bukan diberi tahu di tempat.

## Ringkasan Temuan (2 item)

| # | Severity | Ringkasan |
|---|---|---|
| 1 | 🔴 Tinggi | Halaman tidak pernah menampilkan `$lembaga->nama` — nol indikasi in-page lembaga mana yang sedang dikonfigurasi (Hari Aktif Sekolah, Batas Edit Presensi, Kalender) |
| 2 | 🟠 Tinggi (UX) | Aktor tanpa lembaga aktif di-redirect paksa ke `/dashboard` (halaman yang sama sekali tidak terkait) alih-alih ditampilkan di halaman yang sama dengan instruksi jelas — TIDAK KONSISTEN dengan pola sisa aplikasi (`create()` Kelas/Mata Pelajaran redirect ke index MODUL YANG SAMA; beberapa halaman lain — `pembayaran/index.blade.php`, `tagihan/index.blade.php` — sudah lama memakai pola "tampilkan halaman + notice inline", TIDAK PERNAH redirect) |

## Keputusan yang Diambil

1. **Item 2 BUKAN sekadar mengganti tujuan redirect** (mis. dari `dashboard` ke halaman lain) — solusinya adalah **index() TIDAK PERNAH redirect lagi**. Halaman `admin.pengaturan.akademik.index` SELALU merender view yang sama; kalau lembaga aktif belum dipilih, ganti konten tab (Hari Aktif/Kalender) dengan 1 kartu empty-state "Pilih Lembaga Aktif Dulu" di tempat yang sama. Pola ini SUDAH PRESEDEN di codebase (`resources/views/portals/lembaga/keuangan/pembayaran/index.blade.php` & `tagihan/index.blade.php` — keduanya pakai flag `$lembagaBelumDipilih` dan TIDAK PERNAH redirect), TIDAK menciptakan pola baru — cuma design system kartunya diperbarui ke konvensi modern (`rounded-2xl border-dashed`, bukan `x-panel`/`bg-signal-amber` lama) karena file Pengaturan Akademik sendiri SUDAH pakai konvensi modern di sisa halamannya.
2. **`updateHariAktif()`/`updateBatasEditAbsen()` (endpoint AJAX) TIDAK diubah** — keduanya sudah benar mengembalikan 422 JSON dengan pesan jelas, bukan redirect halaman; masalah UX yang dikeluhkan user SPESIFIK soal "mengakses halaman" (GET `index()`), bukan soal proses simpan.
3. **Item 1 (badge nama lembaga) HANYA muncul saat `lembaga` tersedia** (`! $lembagaBelumDipilih`) — pola SATU warna (brand), TIDAK PERNAH varian "Semua Lembaga" ungu, karena halaman ini BY DESIGN tidak punya konsep agregat (beda dari index Tahun Ajaran/Kelas/Mata Pelajaran) — begitu lembaga terpilih, SELALU 1 lembaga spesifik.

---

## Item 1 — Badge Nama Lembaga di Header

**File**: `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php` (baris 10-15)

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Akademik</h1>
    <p class="text-sm text-gray-500">
        Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengaturan Akademik</b>
    </p>
</div>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Akademik</h1>
        @if (! ($lembagaBelumDipilih ?? false))
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $lembaga->nama }}
            </span>
        @endif
    </div>
    <p class="text-sm text-gray-500">
        Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengaturan Akademik</b>
    </p>
</div>
```

`$lembaga` SUDAH tersedia di view (sudah dikirim controller sejak awal, cuma `->nama`-nya tidak pernah dirender) — kecuali di kondisi Item 2 (empty-state), di mana `$lembaga` akan `null` — makanya guard `! ($lembagaBelumDipilih ?? false)` WAJIB ada, jangan cuma cek `$lembaga` truthy (supaya konsisten dengan flag yang sama dipakai Item 2, satu sumber kebenaran).

---

## Item 2 — Empty-State In-Page, Bukan Redirect ke Dashboard

### Controller

**File**: `app/Http/Controllers/Admin/PengaturanAkademikController.php`

Kode saat ini:
```php
public function index(Request $request): View|RedirectResponse
{
    $this->authorize('kalender-akademik.view');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('dashboard')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
    }

    $lembaga = Lembaga::findOrFail($lembagaId);

    return view('portals.lembaga.akademik.pengaturan.akademik', [
        'lembaga' => $lembaga,
        'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
            ->orderBy('tanggal')
            ->get(),
        'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
        'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
    ]);
}
```

Fix:
```php
public function index(Request $request): View
{
    $this->authorize('kalender-akademik.view');

    $lembagaId = $this->resolveActiveLembagaId($request->user());

    if ($lembagaId === null) {
        return view('portals.lembaga.akademik.pengaturan.akademik', [
            'lembagaBelumDipilih' => true,
            'lembaga' => null,
            'entriList' => collect(),
            'bolehNasional' => false,
            'bolehKelolaHariAktif' => false,
        ]);
    }

    $lembaga = Lembaga::findOrFail($lembagaId);

    return view('portals.lembaga.akademik.pengaturan.akademik', [
        'lembagaBelumDipilih' => false,
        'lembaga' => $lembaga,
        'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
            ->orderBy('tanggal')
            ->get(),
        'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
        'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
    ]);
}
```

**Catatan**: return type berubah dari `View|RedirectResponse` jadi `View` murni (tidak pernah redirect lagi). Dikonfirmasi lewat grep `RedirectResponse` di file ini — SATU-SATUNYA pemakaian adalah signature `index()` yang sedang diubah ini (`updateHariAktif()`/`updateBatasEditAbsen()` return `JsonResponse`, bukan `RedirectResponse`). Jadi baris `use Illuminate\Http\RedirectResponse;` (baris 14) WAJIB DIHAPUS sekalian di edit yang sama, bukan cuma signature method-nya.

### View

**File**: `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`

Kode saat ini (baris 17, pembuka wrapper tab — dan baris 288, penutupnya di akhir file):
```blade
        <div x-data="{ tab: 'hari-aktif' }">
            <div class="flex items-center gap-1 border-b border-gray-200">
            ...
            </div>
        </div>
    </div>
</x-app-layout>
```

Fix — bungkus SELURUH blok tab (baris 17-288, dari `<div x-data="{ tab: 'hari-aktif' }">` sampai `</div>` penutupnya) dengan `@else`, dan tambahkan kartu empty-state di cabang `@if`:
```blade
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="apartment" class="h-6 w-6" />
                </div>
                <h3 class="mt-4 font-display text-sm font-semibold text-gray-900">Pilih Lembaga Aktif Dulu</h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Hari Aktif Sekolah, Batas Waktu Edit Presensi, dan Kalender Akademik diatur per lembaga. Pilih 1 lembaga lewat pengalih lembaga di pojok kanan atas untuk mulai mengatur.</p>
            </div>
        @else
        <div x-data="{ tab: 'hari-aktif' }">
            <div class="flex items-center gap-1 border-b border-gray-200">
            ...(isi tab TIDAK berubah sama sekali, tetap persis seperti sekarang)...
            </div>
        </div>
        @endif
```

**Verifikasi wajib saat implementasi**: file asli punya indentasi tidak konsisten di blok ini (baris 18 `<div class="flex items-center gap-1...">` tidak sejajar indentasi dengan `<div x-data=...>` pembukanya, dan ada 2 `</div>` penutup bertumpuk di baris 287-288) — SALIN PERSIS struktur closing tag yang ada sekarang (jangan menambah/mengurangi 1 `</div>` pun), cukup sisipkan `@if`/`@else`/`@endif` di sekeliling blok yang sudah ada tanpa mengubah isinya.

---

## Di Luar Scope / Backlog Terpisah

1. **`updateHariAktif()`/`updateBatasEditAbsen()` TIDAK diubah** — sudah benar (422 JSON, bukan redirect halaman).
2. **Rombak visual empty-state jadi identik 1:1 dengan `pembayaran/index.blade.php`/`tagihan/index.blade.php`** — SENGAJA TIDAK dilakukan, karena keduanya pakai design system lama (`x-panel`, `text-brass`, `bg-signal-amber`) yang sudah ditinggalkan; empty-state baru di sini mengikuti konvensi modern yang SUDAH dipakai di sisa halaman Pengaturan Akademik sendiri dan di seluruh audit sesi ini (`rounded-2xl border-dashed`, pola sama seperti empty-state Kelas index "Belum Ada Kelas").
3. **Menyamakan pola `$lembagaBelumDipilih` ke `pembayaran`/`tagihan`** (mis. ekstrak jadi helper/trait bersama) — di luar scope, spec ini cuma menyentuh Pengaturan Akademik.

---

## Ringkasan Test yang Wajib Diperbarui/Ditambahkan

| Item | Test |
|---|---|
| 1 | Feature test — assert `assertSee($lembaga->nama)` saat lembaga aktif dipilih; assert badge TIDAK muncul saat `lembagaBelumDipilih` true (bisa digabung dengan test Item 2 di bawah) |
| 2 | **UBAH** test existing `tests/Feature/Admin/PengaturanAkademikControllerTest.php` baris 194 ("redirects a yayasan-scoped user without an active lembaga away from the pengaturan akademik page") — GANTI assertion dari `assertRedirect(route('dashboard'))` jadi `assertOk()` + `assertSee('Pilih Lembaga Aktif Dulu')` (atau `assertViewHas('lembagaBelumDipilih', true)`), nama test disesuaikan supaya tidak menyesatkan (mis. jadi "shows an in-page prompt instead of redirecting..."). **UBAH** juga test baris 208 ("menolak actor yayasan dengan active_lembaga_id stale...") dengan pola sama (`assertOk()`, bukan `assertRedirect()`) — SKENARIO STALE tetap harus menampilkan empty-state yang SAMA (bukan pesan beda), karena `resolveActiveLembagaId()` mengembalikan `null` untuk KEDUA kasus (tidak ada nilai session, ATAU nilai session sudah tidak valid), controller tidak bisa dan tidak perlu membedakannya. |

Regresi wajib: seluruh test di `tests/Feature/Admin/PengaturanAkademikControllerTest.php` (existing, termasuk 2 test yang diubah di atas), `tests/Feature/Admin/KalenderAkademikCrudTest.php` (TIDAK disentuh logic-nya, harus tetap hijau tanpa perubahan).
