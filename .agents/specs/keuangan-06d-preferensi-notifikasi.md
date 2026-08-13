# Spec — Keuangan Sub-project 6d: Preferensi Notifikasi & Mark-as-Read

**Status:** Draft, menunggu review.
**Depends on:** Sub-project 05 (`UserNotificationPreference`, `NotificationDispatcher`, `FinanceNotification`), Sub-project 6a (`NotificationFeedResolver`, notification bell topbar, dashboard "Notifikasi Terbaru" panel).
**Ini adalah sub-project TERAKHIR dari Modul Keuangan Sekolah Dinamis** — setelah ini seluruh modul (Sub-project 1-6d) tuntas.

## 1. Ringkasan

Menutup 3 open item yang tercatat sejak Sub-project 6a:

1. **Perbaikan arsitektural mendesak**: `NotificationDispatcher::send()` saat ini TIDAK PERNAH membaca preferensi channel untuk notifikasi Keuangan, karena semua notifikasi dikirim ke objek `OrangTua`, sementara lookup preferensi hanya berlaku untuk `instanceof \App\Models\User`. Tanpa perbaikan ini, fitur preferensi notifikasi (poin 2) tidak akan pernah punya efek nyata.
2. **Halaman preferensi channel notifikasi** (WhatsApp/Email) — ditambahkan sebagai partial baru di halaman akun umum `/profile`, bukan halaman terpisah di portal Keuangan (preferensi bersifat per-akun, bukan per-modul — `UserNotificationPreference.module` sudah didesain untuk mendukung modul lain di masa depan).
3. **Mekanisme mark-as-read** untuk notifikasi — belum ada sama sekali sejak 6a; badge unread di topbar bell tidak pernah berkurang.
4. **Pemindahan namespace** `NotificationFeedResolver` dari `App\Services\Finance` ke `App\Services\Notifications` — technical debt tercatat sejak 6a (dipakai lintas modul, bukan cuma Keuangan).

## 2. Keputusan Arsitektur (dikonfirmasi user)

- **Preferensi tetap berlaku per-akun (`User`), bukan per-anak (`OrangTua`)** — perbaikan dispatcher resolve preferensi `OrangTua` lewat `$orangTua->user_id` (kolom FK yang sudah ada), TIDAK menambah tabel/skema baru. Konsisten dengan pola `NotificationDispatcher::logAttempt()`'s existing `$notifiable->user_id ?? null` yang sudah menangani kasus sama.
- **Mark-as-read: individual (klik satu notifikasi) DAN "Tandai semua terbaca"** — keduanya, bukan salah satu.
- **Halaman preferensi ditempatkan di `/profile`** (halaman akun umum existing, dipakai lintas role), bukan di portal Keuangan — sebagai partial baru mengikuti pola `update-profile-information-form.blade.php`/`update-password-form.blade.php` yang sudah ada di halaman itu.
- **Namespace `NotificationFeedResolver` dipindah** ke `App\Services\Notifications` — murni rename/pindah, nol perubahan behavior.
- `channel_push` (kolom yang sudah ada di `UserNotificationPreference`, default `false`) TIDAK diekspos di UI — tidak ada infrastruktur push notification di proyek ini, tetap `false` selamanya untuk sub-project ini.

## 3. Arsitektur & Routing

**Perbaikan dispatcher** — tidak ada route baru, murni perubahan logic di file existing:
- Modify: `app/Services/Finance/NotificationDispatcher.php`

**Halaman preferensi** — route baru di grup `auth` middleware existing (`routes/web.php`, dekat route `profile.*` yang sudah ada):

| Method | Path | Name |
|---|---|---|
| PATCH | `/profile/notification-preference` | `profile.notification-preference.update` |

- Modify: `app/Http/Controllers/ProfileController.php` — tambah method `updateNotificationPreference()`.
- Modify: `app/Models/User.php` — tambah relasi `hasOne(UserNotificationPreference::class)`.
- Create: `resources/views/profile/partials/update-notification-preference-form.blade.php`.
- Modify: `resources/views/profile/edit.blade.php` — `@include` partial baru, gated `Auth::user()->orangTua !== null`.

**Mark-as-read** — route baru di grup `keuangan.*` yang sudah ada:

| Method | Path | Name |
|---|---|---|
| POST | `/keuangan/notifikasi/{notification}/baca` | `keuangan.notifikasi.baca` |
| POST | `/keuangan/notifikasi/baca-semua` | `keuangan.notifikasi.baca-semua` |

- Create: `app/Http/Controllers/Keuangan/NotifikasiController.php`.
- Modify: `resources/views/layouts/topbar.blade.php` — item notifikasi jadi klik-untuk-baca (AJAX), tombol "Tandai semua terbaca".
- Modify: `resources/views/keuangan/dashboard.blade.php` — panel "Notifikasi Terbaru" mendapat interaksi yang sama.

**Pemindahan namespace:**
- Move: `app/Services/Finance/NotificationFeedResolver.php` → `app/Services/Notifications/NotificationFeedResolver.php`.
- Modify: `resources/views/layouts/topbar.blade.php`, `app/Http/Controllers/Keuangan/DashboardController.php` (update referensi `use`/fully-qualified-name).
- Test class `tests/Feature/Keuangan/NotificationFeedResolverTest.php` tetap di lokasi file yang sama, hanya `use` statement di dalamnya yang di-update mengikuti namespace baru.

## 4. Halaman & Komponen

### 4.1 Partial "Preferensi Notifikasi" (`/profile`)

```blade
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Preferensi Notifikasi</h2>
        <p class="mt-1 text-sm text-gray-600">Atur channel notifikasi Keuangan yang ingin Anda terima. Notifikasi mendesak (misal saldo tidak cukup) tetap dikirim lewat semua channel apa pun pengaturan ini.</p>
    </header>

    <form method="post" action="{{ route('profile.notification-preference.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_wa" value="1" @checked($preference->channel_wa ?? true) class="rounded border-gray-300">
            <span class="text-sm text-gray-700">WhatsApp</span>
        </label>
        <label class="flex items-center gap-3">
            <input type="checkbox" name="channel_email" value="1" @checked($preference->channel_email ?? true) class="rounded border-gray-300">
            <span class="text-sm text-gray-700">Email</span>
        </label>

        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan Preferensi</button>

        @if (session('status') === 'notification-preference-updated')
            <p class="text-sm text-success-600">Preferensi notifikasi berhasil disimpan.</p>
        @endif
    </form>
</section>
```

Di-`@include` di `profile/edit.blade.php` setelah partial `update-profile-information-form`, dibungkus:
```blade
@if (Auth::user()->orangTua !== null)
    @include('profile.partials.update-notification-preference-form', ['preference' => Auth::user()->notificationPreference])
@endif
```

### 4.2 Topbar bell — klik untuk baca + tandai semua

- Header panel notifikasi: tambah tombol "Tandai semua terbaca" (muncul hanya jika `$unreadCount > 0`), submit ke `keuangan.notifikasi.baca-semua` via `fetch()`.
- Tiap item notifikasi: bungkus dengan elemen yang bisa diklik (`<button type="button">` di dalam `<div>` item, bukan mengubah struktur `<div>` yang sudah ada), `@click` Alpine memanggil `fetch()` ke `keuangan.notifikasi.baca` dengan `notification.id`, lalu update state lokal (hilangkan highlight `bg-brand-50/40`, kurangi badge counter).
- State unread count di-track via Alpine `x-data` di level dropdown (`x-data="{ unreadCount: {{ $unreadCount }} }"`), badge di trigger button pakai `x-text`/`x-show` alih-alih server-rendered `@if` murni — supaya update real-time tanpa reload.

### 4.3 Dashboard "Notifikasi Terbaru" panel — interaksi sama

Pola identik 4.2, diterapkan ke panel yang sudah ada di `keuangan/dashboard.blade.php`.

## 5. Data Flow & Validasi

### 5.1 `NotificationDispatcher::send()`
Ganti baris resolusi `$preference`:
```php
$userId = match (true) {
    $notifiable instanceof \App\Models\User => $notifiable->id,
    $notifiable instanceof \App\Models\OrangTua => $notifiable->user_id,
    default => null,
};

$preference = $userId !== null
    ? UserNotificationPreference::where('user_id', $userId)->where('module', $module)->first()
    : null;
```
Baris `$allowWa`/`$allowEmail` dan seterusnya **tidak berubah** — perbaikan ini murni memperluas kondisi resolusi `$userId`/`$preference`.

### 5.2 `ProfileController::updateNotificationPreference()`
```php
public function updateNotificationPreference(Request $request): RedirectResponse
{
    $data = $request->validate([
        'channel_wa' => ['sometimes', 'boolean'],
        'channel_email' => ['sometimes', 'boolean'],
    ]);

    UserNotificationPreference::updateOrCreate(
        ['user_id' => $request->user()->id, 'module' => 'finance'],
        [
            'channel_wa' => $request->boolean('channel_wa'),
            'channel_email' => $request->boolean('channel_email'),
        ]
    );

    return Redirect::route('profile.edit')->with('status', 'notification-preference-updated');
}
```
Checkbox HTML yang tidak dicentang tidak terkirim di request — `$request->boolean(...)` menangani ini dengan benar (return `false` kalau key absent), bukan `$request->validated()` mentah yang akan skip key yang hilang.

### 5.3 `NotifikasiController@bacaSatu`
```php
public function bacaSatu(Request $request, string $id): JsonResponse
{
    $user = $request->user();
    $notification = $user->notifications()->find($id)
        ?? $user->orangTua?->notifications()->find($id);

    abort_if($notification === null, 403);

    $notification->markAsRead();

    $unreadCount = $this->hitungUnread($user);

    return response()->json(['unread_count' => $unreadCount]);
}

public function bacaSemua(Request $request): JsonResponse
{
    $user = $request->user();
    $user->unreadNotifications->markAsRead();
    $user->orangTua?->unreadNotifications->markAsRead();

    return response()->json(['unread_count' => 0]);
}

private function hitungUnread($user): int
{
    return $user->unreadNotifications()->count() + ($user->orangTua?->unreadNotifications()->count() ?? 0);
}
```
Otorisasi `bacaSatu()`: mencari notifikasi HANYA di dalam `$user->notifications()` atau `$user->orangTua->notifications()` (query scoped, bukan `DatabaseNotification::find($id)` lalu cek kepemilikan setelahnya) — kalau ID tidak ditemukan di salah satu scope itu, `abort_if` 403.

## 6. Error Handling

| Skenario | Penanganan |
|---|---|
| Preferensi belum pernah diset (baris `UserNotificationPreference` belum ada) | Default `channel_wa=true, channel_email=true` (lewat `?? true` di dispatcher, sudah ada) — user baru tidak kehilangan notifikasi apa pun |
| Notifikasi urgent | Selalu semua channel, preferensi diabaikan — perilaku existing, tidak berubah |
| Klik "tandai terbaca" pada notifikasi yang sudah dibaca | No-op aman, `markAsRead()` Laravel idempotent |
| Percobaan mark-as-read notifikasi milik user lain | 403, scoped query mencegah notifikasi ditemukan sama sekali |
| Checkbox preferensi tidak dicentang saat submit | `$request->boolean()` menangani dengan benar sebagai `false`, bukan hilang dari validated data |
| Referensi lama ke namespace `NotificationFeedResolver` lama tertinggal | Grep eksplisit sebelum commit tugas pemindahan, pastikan nol hasil |

## 7. Testing Strategy

- **`NotificationDispatcherOrangTuaPreferenceTest`**: matikan `channel_wa` di `UserNotificationPreference` milik `User` pemilik `OrangTua`, kirim notifikasi non-urgent ke `OrangTua` tersebut, assert channel WA benar-benar tidak terpakai — regression test langsung untuk gap yang ditemukan, harus gagal di kode lama.
- **Regresi wajib**: seluruh test `NotificationDispatcherTest` existing (untuk notifiable `User` murni) tetap hijau tanpa modifikasi assertion.
- **`ProfileNotificationPreferenceControllerTest`**: update tersimpan benar (termasuk kasus checkbox unchecked → `false`), preferensi lama ter-preserve saat reload halaman.
- **`NotifikasiMarkAsReadTest`**: tandai satu → unread count berkurang 1 tepat; tandai semua → unread count 0; user A tidak bisa mark-as-read notifikasi user B (403, pola dua-pihak).
- **Namespace move regression**: `NotificationFeedResolverTest` 100% hijau tanpa assertion diubah, plus grep-check nol referensi lama tersisa di seluruh codebase.
- **Manual Playwright minimal**: 1 check — klik notifikasi di bell → badge berkurang; buka `/profile` → toggle WA off → simpan → reload → toggle masih off.
- **Regression selama proses**: `tests/Feature/Keuangan/` + file test baru dari sub-project ini — **tidak ada full-suite run**, sesuai keputusan user di sub-project sebelumnya (hemat token), tetap berlaku untuk sub-project ini.

## 8. Eksplisit di Luar Scope 6d

- Push notification (kolom `channel_push` tetap `false`, tidak ada infrastruktur push di proyek ini).
- Preferensi per-anak/per-`OrangTua` (Opsi B yang dipertimbangkan lalu ditolak — lihat §2).
- Preferensi untuk modul selain Finance (kolom `module` sudah mendukung, tapi tidak ada modul lain yang memakainya saat ini).
- Perubahan pada `FinanceNotification`, kelas notifikasi individual (`PembayaranBerhasilNotification`, dst.), atau `NotificationLog`/`logAttempt()` — sudah benar, tidak disentuh.
- Perubahan pada `AutoAllocationEngine`, `PaymentService`, `PaymentAllocationService`, atau modul checkout/kwitansi manapun dari 6b/6c/6c2.
