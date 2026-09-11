# Jadwal Pelajaran Audit Perbaikan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki modul Jadwal Pelajaran (`resources/views/portals/lembaga/akademik/jadwal-pelajaran/*`) sesuai temuan audit UI/UX: perbaiki bug URL rusak pada tombol Tambah Slot, konversi aksi Hapus ke AJAX tanpa reload, perbaiki 4 nama icon yang tidak terdaftar, rapikan teks & filter, kunci slot non-pelajaran di matriks agar tidak bisa diisi, tambah tooltip, dan samakan breadcrumb.

**Architecture:** Perubahan MURNI presentational (Blade + Alpine.js client-side). TIDAK ADA perubahan controller, action, atau domain layer — `JadwalPelajaranController` sudah mengirim semua data yang dibutuhkan (termasuk `scopeHeaderData()`) dan sudah mendukung JSON response untuk `destroy()`.

**Tech Stack:** Laravel 12 (Blade), Alpine.js + TomSelect (pola yang sudah dipakai file ini sendiri), Pest untuk test.

## Global Constraints

- JANGAN implementasikan "preview jumlah sesi kelas sumber" di modal duplikasi — butuh endpoint/query tambahan, disproporsional untuk siklus ini.
- JANGAN tambah KPI "Total Sesi Belajar (X/Y slot terisi)" atau "Pola Jam Kelas" — keduanya butuh query tambahan di luar prinsip "KPI ringan tanpa query tambahan" yang dipakai spec ini.
- JANGAN implementasikan color-coding/aksen warna chip per mata pelajaran di Matriks — definisi bisnis kategorisasi warna belum jelas, butuh didiskusikan terpisah.
- JANGAN tambah badge "Ruang Bersama" di dropdown Ruangan Sarpras — kolom `is_shared` memang ADA di model `Ruangan`, tapi sengaja dikeluarkan murni soal prioritas/scope siklus ini.
- JANGAN sentuh `app/Http/Controllers/Admin/JadwalPelajaranController.php` sama sekali — `scopeHeaderData()` sudah ada dan sudah terkirim ke `index()` (dikonfirmasi langsung dari kode), tidak ada perubahan controller yang dibutuhkan untuk seluruh plan ini.
- JANGAN sentuh `DuplicateJadwalAction` atau domain layer manapun — sudah diverifikasi solid, di luar scope.
- Setiap aksi Hapus lewat AJAX (Task 3) WAJIB menyertakan CSRF token manual dari `<meta name="csrf-token">` di body `FormData` — `fetch()` + `FormData` tanpa hidden input `@csrf` TIDAK otomatis membawa token seperti `<form>` asli. Terlewat = response 419.

## ⚠️ Catatan Wajib Soal Urutan Eksekusi — BACA SEBELUM MULAI TASK APA PUN

Beberapa file disentuh lebih dari satu task dan **WAJIB dikerjakan sekuensial dalam urutan task**, TIDAK BOLEH paralel:

- **`resources/js/jadwal-pelajaran-filter.js`**: disentuh Task 1, 3, 5 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`**: disentuh Task 5, 7 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`**: disentuh Task 2, 3, 4, 6 — sekuensial.
- **`resources/views/portals/lembaga/akademik/jadwal-pelajaran/_matrix-roster.blade.php`**: disentuh Task 2, 3 — sekuensial.

**HANYA Task 8** (`create.blade.php`, `edit.blade.php` — file sama sekali berbeda) **AMAN dikerjakan paralel** dengan task lain kalau memakai subagent-driven-development.

**Task 9 (regression sweep) WAJIB paling akhir**, setelah SEMUA task lain (termasuk Task 8) selesai.

**Untuk setiap task yang mengedit file yang SUDAH disentuh task sebelumnya: implementer WAJIB membaca ulang isi file TERKINI dulu** (pakai Read tool) sebelum mengedit, BUKAN berasumsi dari kode "Current" yang dikutip di task sebelumnya. Kode "cari blok ini" yang dikutip di task-task di bawah mengacu ke **state file SETELAH task-task sebelumnya di urutan itu selesai**.

---

## Task 1: Fix Bug `createUrlBase` Undefined

**Files:**
- Modify: `resources/js/jadwal-pelajaran-filter.js`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `config.createUrlBase` yang SUDAH dikirim `index.blade.php:30` (`createUrlBase: @js(route('admin.jadwal-pelajaran.create'))`) — TIDAK diubah, hanya belum ditangkap di JS.
- Produces: `this.createUrlBase` tersedia untuk method `tambahSlotUrl()` yang SUDAH ADA (baris 390-395).

- [ ] **Step 1: Tulis test yang gagal — halaman render dan config createUrlBase muncul benar di HTML**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('halaman index mengirim createUrlBase yang benar ke Alpine (bukti fix bug /undefined pada tombol Tambah Slot)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('createUrlBase:', false);
    $response->assertSee(route('admin.jadwal-pelajaran.create'), false);
});
```
(Test ini membuktikan config yang DIKIRIM ke Alpine sudah benar sejak awal — bug-nya ada di sisi JS yang TIDAK MENANGKAP config itu, bukan di Blade. Test ini akan PASS baik sebelum maupun sesudah fix, karena Blade-nya memang sudah benar. Pembuktian utama fix ada di Step 2-4: cross-check langsung ke isi file JS.)

- [ ] **Step 2: Jalankan test, pastikan PASS (baseline, bukan TDD gagal-dulu untuk bug JS runtime)**

Run: `php artisan test --filter="createUrlBase yang benar" --compact`
Expected: PASS.

- [ ] **Step 3: Verifikasi bug — cek isi `jadwal-pelajaran-filter.js` saat ini**

Run: `grep -n "createUrlBase" resources/js/jadwal-pelajaran-filter.js`
Expected SEBELUM fix: hanya 1 baris hasil (`const url = new URL(this.createUrlBase, ...)` di method `tambahSlotUrl()`), TIDAK ADA baris inisialisasi `createUrlBase:` di objek state — ini pembuktian nyata bug (`this.createUrlBase` dipakai tapi tidak pernah di-set).

- [ ] **Step 4: Fix — tambah 1 baris di objek state Alpine**

Cari blok persis di awal file (baris 3-10):
```js
export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        storeUrlBase: config.storeUrlBase ?? '',
```
Ganti menjadi:
```js
export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        createUrlBase: config.createUrlBase ?? '',
        storeUrlBase: config.storeUrlBase ?? '',
```

- [ ] **Step 5: Verifikasi ulang — pastikan `createUrlBase` sekarang muncul 2x (inisialisasi + pemakaian)**

Run: `grep -n "createUrlBase" resources/js/jadwal-pelajaran-filter.js`
Expected: 2 baris hasil sekarang (baris inisialisasi baru + baris pemakaian lama di `tambahSlotUrl()`).

- [ ] **Step 6: Build asset frontend & jalankan test**

Run: `npm run build`
Expected: build sukses tanpa error.

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS (test lama + test baru Step 1).

- [ ] **Step 7: Commit**

```bash
git add resources/js/jadwal-pelajaran-filter.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): perbaiki bug createUrlBase undefined pada tombol Tambah Slot Jadwal"
```

---

## Task 2: Perbaiki 4 Icon Rusak

**Files:**
- Modify: `resources/views/components/icon.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `@case('data_table')`, `@case('list')`, `@case('school')` yang SUDAH ADA di `icon.blade.php` (ditambahkan siklus perbaikan Pola Jam sebelumnya di sesi ini) — TIDAK perlu ditambah lagi.
- Produces: `@case('event_busy')` baru — TIDAK dikonsumsi task lain di plan ini.

- [ ] **Step 1: Tulis test yang gagal — tidak ada icon rusak tersisa di file-file ini**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('tidak ada nama icon rusak (grid_on/format_list_bulleted/class/event_busy) di halaman jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertDontSee('name="grid_on"', false);
    $response->assertDontSee('name="format_list_bulleted"', false);
    $response->assertDontSee('name="class"', false);
    $response->assertDontSee('name="event_busy"', false);
});
```

- [ ] **Step 2: Jalankan test, catat hasil (pola `assertDontSee` ini kemungkinan besar PASS walau icon masih rusak — Blade sudah meng-compile `name` jadi parameter internal, bukan atribut HTML akhir; pembuktian utama ada di Step 6)**

Run: `php artisan test --filter="tidak ada nama icon rusak" --compact`

- [ ] **Step 3: Tambah `@case('event_busy')` baru di `resources/views/components/icon.blade.php`**

Sisipkan blok berikut DI MANA SAJA di antara `@case` yang sudah ada (sebelum baris `@default`):
```blade
    @case('event_busy')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/><path d="m9.5 13.5 5 5m0-5-5 5"/></svg>
        @break

```

- [ ] **Step 4: Ganti 3 nama icon di `_daftar.blade.php`**

Cari dan ganti 3 baris berikut (HANYA atribut `name`, tidak ada perubahan lain di baris itu):
1. Baris berisi `<x-icon name="grid_on" class="h-4 w-4 text-brand-500" />` (toggle "Matriks Roster") → ganti `name="grid_on"` jadi `name="data_table"`.
2. Baris berisi `<x-icon name="format_list_bulleted" class="h-4 w-4 text-gray-500" />` (toggle "Tampilan Daftar") → ganti `name="format_list_bulleted"` jadi `name="list"`.
3. Baris berisi `<x-icon name="event_busy" class="mx-auto h-10 w-10 text-gray-300 mb-2" />` (empty state) → nama TIDAK diganti (sudah benar sebagai nama), cukup pastikan `@case('event_busy')` dari Step 3 sudah ada.

- [ ] **Step 5: Ganti 1 nama icon di `_modal-form.blade.php`**

Cari baris berisi `<x-icon name="class" class="h-3.5 w-3.5 text-gray-400" />` (badge konteks kelas di header modal) → ganti `name="class"` jadi `name="school"`.

- [ ] **Step 6: Verifikasi manual — semua nama icon di SELURUH file modul ini sekarang valid**

Run: `grep -ohE 'x-icon name="[a-z_]+"' resources/views/portals/lembaga/akademik/jadwal-pelajaran/*.blade.php | grep -oE 'name="[a-z_]+"' | sort -u`
Run: `grep -oE "@case\('[a-z_]+'\)" resources/views/components/icon.blade.php | sort -u`
Expected: setiap nama dari hasil grep PERTAMA ada di hasil grep KEDUA. Kalau ada yang tidak ketemu, ulangi Step 3-5.

- [ ] **Step 7: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/icon.blade.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): perbaiki 4 icon rusak (data_table/list/school + event_busy baru)"
```

---

## Task 3: AJAX Hapus (Tanpa Reload) + Kunci Slot Non-Pelajaran

**Files:**
- Modify: `resources/js/jadwal-pelajaran-filter.js` (state SETELAH Task 1)
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` (state SETELAH Task 2)
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_matrix-roster.blade.php` (state SETELAH Task 2)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `JadwalPelajaranController::destroy()` (TIDAK diubah — SUDAH mendukung `RedirectResponse|JsonResponse`, dikonfirmasi baris 444-461).
- Produces: method `hapusJadwal(url, label)` baru pada state Alpine — dipakai di `_daftar.blade.php` dan `_matrix-roster.blade.php`.

- [ ] **Step 1: Baca ulang `jadwal-pelajaran-filter.js`, `_daftar.blade.php`, `_matrix-roster.blade.php` TERKINI**

Cari method `submitDuplicate()` di `jadwal-pelajaran-filter.js` (sekitar baris 144-174) dan `initTahunAjaranSelect()` setelahnya — `hapusJadwal()` disisipkan DI ANTARA keduanya.

- [ ] **Step 2: Tulis test yang gagal — tombol Hapus TIDAK lagi berupa `<form>`, memanggil `hapusJadwal()` sebagai gantinya**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('tombol hapus jadwal memanggil hapusJadwal() lewat AJAX, bukan submit form biasa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamPelajaran = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'hari' => Hari::Senin->value, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'jam_pelajaran_id' => $jamPelajaran->id, 'guru_id' => $guru->id,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertSee('hapusJadwal(', false);
    $response->assertDontSee('@submit.prevent="confirmDialog(\'Hapus Jadwal?\'', false);
});

it('slot non-pelajaran di matriks roster tidak bisa diklik untuk diisi jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'hari' => Hari::Senin->value, 'urutan' => 1, 'label' => 'Istirahat', 'is_pelajaran' => false]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertSee('Istirahat');
    $response->assertDontSee('openCreateModal({ jam_ids:', false);
});
```
(Test kedua butuh `$kelas->pola_jam_id` terhubung ke `$polaJam` yang sama supaya `_matrix-roster.blade.php` membaca slot ini — cek dulu kolom `pola_jam_id` di factory `Kelas`; kalau `Kelas::factory()` TIDAK otomatis mengisi `pola_jam_id`, tambahkan eksplisit: `Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id, 'pola_jam_id' => $polaJam->id])`.)

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="hapusJadwal\(\) lewat AJAX|slot non-pelajaran di matriks roster" --compact`
Expected: FAIL.

- [ ] **Step 4: Tambah method `hapusJadwal()` di `jadwal-pelajaran-filter.js`**

Sisipkan SETELAH method `submitDuplicate()` (baris 144-174 versi saat ini), SEBELUM `initTahunAjaranSelect()`:
```js
async hapusJadwal(url, label) {
    const konfirmasi = await confirmDialog('Hapus Jadwal?', `Apakah Anda yakin ingin menghapus jadwal ${label}?`, { confirmLabel: 'Ya, Hapus' });
    if (!konfirmasi) return;

    try {
        window.dispatchEvent(new CustomEvent('ajax-start'));
        const formData = new FormData();
        formData.append('_method', 'DELETE');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok) {
            Alpine.store('toast').push('success', data.message || 'Jadwal berhasil dihapus.');
            await this.muatUlangDaftar();
        } else {
            Alpine.store('toast').push('error', data.message || 'Gagal menghapus jadwal.');
        }
    } catch (e) {
        Alpine.store('toast').push('error', 'Terjadi kesalahan jaringan saat menghapus jadwal.');
    } finally {
        window.dispatchEvent(new CustomEvent('ajax-end'));
    }
},
```

- [ ] **Step 5: Ganti tombol Hapus di `_daftar.blade.php`**

Cari blok:
```blade
<form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
</form>
```
Ganti menjadi:
```blade
<button type="button" @click="hapusJadwal('{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}', @js(($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama))" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
```

- [ ] **Step 6: Ganti tombol Hapus di `_matrix-roster.blade.php` DAN kunci slot non-pelajaran**

Cari blok penuh (dari `@else` setelah kartu terisi sampai `@endif` penutup — baris 111-130 versi saat ini):
```blade
                                        @else
                                            {{-- Empty Slot Dropzone --}}
                                            @can('jadwal-pelajaran.kelola')
                                                <div @click="openCreateModal({ jam_ids: [{{ $slot->id }}] })" class="group flex flex-col items-center justify-center text-center rounded-2xl border-2 border-dashed border-gray-200 hover:border-brand-400 bg-gray-50/40 hover:bg-brand-50/30 p-4 transition-all duration-200 cursor-pointer h-full min-h-[140px]">
                                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/60 bg-white/80 px-2.5 py-1 text-xs font-mono font-bold text-gray-500 group-hover:text-brand-600 group-hover:border-brand-200 mb-2">
                                                        <x-icon name="schedule" class="h-3.5 w-3.5 opacity-60" />
                                                        <span>{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                    </span>
                                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-white shadow-xs border border-gray-200 group-hover:border-brand-300 group-hover:bg-brand-50 text-gray-400 group-hover:text-brand-600 transition-all mb-1 group-hover:scale-105">
                                                        <x-icon name="add" class="h-4 w-4" />
                                                    </span>
                                                    <span class="text-xs font-bold text-gray-400 group-hover:text-brand-700 transition-colors">+ Isi Jadwal</span>
                                                </div>
                                            @else
                                                <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/20 p-4 h-full min-h-[140px] text-gray-400">
                                                    <span class="text-xs font-mono font-semibold text-gray-500">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                    <span class="text-xs font-medium text-gray-400 mt-1">Kosong</span>
                                                </div>
                                            @endcan
                                        @endif
```
Ganti menjadi:
```blade
                                        @else
                                            @if (! $slot->is_pelajaran)
                                                {{-- Slot non-pelajaran (Istirahat/Upacara dari Pola Jam) -- tidak bisa diisi mata pelajaran --}}
                                                <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-amber-200 bg-amber-50/40 p-4 h-full min-h-[140px] text-amber-600">
                                                    <span class="text-xs font-mono font-semibold">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                    <span class="text-xs font-bold mt-1">{{ $slot->label }}</span>
                                                </div>
                                            @else
                                                {{-- Empty Slot Dropzone --}}
                                                @can('jadwal-pelajaran.kelola')
                                                    <div @click="openCreateModal({ jam_ids: [{{ $slot->id }}] })" class="group flex flex-col items-center justify-center text-center rounded-2xl border-2 border-dashed border-gray-200 hover:border-brand-400 bg-gray-50/40 hover:bg-brand-50/30 p-4 transition-all duration-200 cursor-pointer h-full min-h-[140px]">
                                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/60 bg-white/80 px-2.5 py-1 text-xs font-mono font-bold text-gray-500 group-hover:text-brand-600 group-hover:border-brand-200 mb-2">
                                                            <x-icon name="schedule" class="h-3.5 w-3.5 opacity-60" />
                                                            <span>{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                        </span>
                                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-white shadow-xs border border-gray-200 group-hover:border-brand-300 group-hover:bg-brand-50 text-gray-400 group-hover:text-brand-600 transition-all mb-1 group-hover:scale-105">
                                                            <x-icon name="add" class="h-4 w-4" />
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-400 group-hover:text-brand-700 transition-colors">+ Isi Jadwal</span>
                                                    </div>
                                                @else
                                                    <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/20 p-4 h-full min-h-[140px] text-gray-400">
                                                        <span class="text-xs font-mono font-semibold text-gray-500">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                        <span class="text-xs font-medium text-gray-400 mt-1">Kosong</span>
                                                    </div>
                                                @endcan
                                            @endif
                                        @endif
```

Cari juga blok tombol Hapus di kartu TERISI (baris 100-107 versi saat ini):
```blade
                                                        <form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })" class="inline-flex">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold text-error-500 hover:text-error-700 transition">
                                                                <x-icon name="delete" class="h-3.5 w-3.5" />
                                                                <span>Hapus</span>
                                                            </button>
                                                        </form>
```
Ganti menjadi:
```blade
                                                        <button type="button" @click="hapusJadwal('{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}', @js(($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama))" class="inline-flex items-center gap-1 text-[11px] font-bold text-error-500 hover:text-error-700 transition">
                                                            <x-icon name="delete" class="h-3.5 w-3.5" />
                                                            <span>Hapus</span>
                                                        </button>
```

- [ ] **Step 7: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="hapusJadwal\(\) lewat AJAX|slot non-pelajaran di matriks roster" --compact`
Expected: PASS.

- [ ] **Step 8: Jalankan regresi file penuh (termasuk test bentrok waktu, tenant guard — pastikan destroy() manapun yang dites via non-AJAX form submit lama tetap lulus)**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php tests/Feature/Admin/JamPelajaranCrudTest.php --compact`
Expected: semua PASS.

- [ ] **Step 9: Build asset & format & commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add resources/js/jadwal-pelajaran-filter.js resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/_matrix-roster.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): hapus jadwal tanpa reload halaman, kunci slot non-pelajaran di matriks"
```

---

## Task 4: Perbaiki Redundansi Teks "Jadwal Pelajaran Kelas Kelas 1-A"

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` (state SETELAH Task 3)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada.

- [ ] **Step 1: Baca ulang `_daftar.blade.php` TERKINI**

Cari baris `<h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas {{ $kelas->nama ?? '' }}</h2>`.

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('judul daftar jadwal tidak mengulang kata Kelas (nama kelas sudah diawali kata Kelas)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id, 'nama' => 'Kelas 1-A']);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertDontSee('Jadwal Pelajaran Kelas Kelas 1-A');
    $response->assertSee('Jadwal Pelajaran — Kelas 1-A');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="judul daftar jadwal tidak mengulang kata Kelas" --compact`
Expected: FAIL.

- [ ] **Step 4: Fix**

Ganti:
```blade
<h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas {{ $kelas->nama ?? '' }}</h2>
```
Menjadi:
```blade
<h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran — {{ $kelas->nama ?? '' }}</h2>
```

- [ ] **Step 5: Jalankan test, pastikan PASS, lalu commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): hilangkan redundansi kata Kelas pada judul daftar jadwal"
```

---

## Task 5: Filter — Badge Scope, Breadcrumb, Semester jadi TomSelect, Tombol Reset

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php` (state SETELAH Task 1)
- Modify: `resources/js/jadwal-pelajaran-filter.js` (state SETELAH Task 3)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`/`$activeLembaga` (SUDAH dikirim `JadwalPelajaranController::scopeHeaderData()`, TIDAK perlu diubah).
- Produces: `this.tahunAjaranTomSelect`, `this.semesterTomSelect` (instance TomSelect tersimpan — SEBELUMNYA `initTahunAjaranSelect()` tidak menyimpan instance-nya ke `this`, hanya lokal), method `initSemesterSelect()`, `resetFilter()` baru.

**⚠️ INI TASK PALING BERISIKO REGRESI DI SELURUH PLAN** — method `gantiTahunAjaran()` yang SUDAH ADA memanipulasi `<select>` semester langsung lewat `innerHTML`/`appendChild`. Begitu elemen itu dikelola TomSelect, manipulasi DOM manual TIDAK akan terlihat oleh TomSelect (TomSelect membungkus elemen asli, tidak "mengamati" perubahan DOM di luar API-nya sendiri). **`gantiTahunAjaran()` WAJIB disesuaikan di task ini juga**, bukan cuma menambah TomSelect baru — kode LENGKAP versi barunya ada di Step 5, JANGAN cuma tempel TomSelect tanpa mengubah method ini, filter Semester akan terlihat KOSONG walau opsi sebenarnya sudah ter-fetch.

- [ ] **Step 1: Baca ulang `index.blade.php` dan `jadwal-pelajaran-filter.js` TERKINI**

Di `index.blade.php`, cari blok Header & Breadcrumb (baris 11-20 versi saat ini) dan blok filter Semester (baris 71-79). Di `jadwal-pelajaran-filter.js`, cari `initTahunAjaranSelect()`, dan method `gantiTahunAjaran()` LENGKAP (sekitar baris 311-348 versi saat ini, SETELAH `hapusJadwal()` dari Task 3 disisipkan, nomor barisnya akan sedikit bergeser — cari berdasarkan isi, bukan nomor baris persis).

- [ ] **Step 2: Tulis test yang gagal — badge scope, breadcrumb Akademik, tombol Reset, filter Semester TomSelect**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('halaman index jadwal pelajaran menampilkan badge scope lembaga untuk aktor yayasan', function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_scope_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Cabang Utama']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.jadwal-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('SD Pintera Cabang Utama');
});

it('breadcrumb index jadwal pelajaran memakai Akademik, bukan Beranda', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('Akademik');
});

it('filter menampilkan tombol Reset Filter dan Semester dikelola TomSelect', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('resetFilter()', false);
    $response->assertSee('initSemesterSelect', false);
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="badge scope lembaga|breadcrumb index jadwal pelajaran|Reset Filter dan Semester" --compact`
Expected: FAIL.

- [ ] **Step 4: Ganti Header & Breadcrumb di `index.blade.php`**

Cari blok:
```blade
{{-- Header & Breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
        <p class="text-xs text-gray-500 mt-0.5">Kelola penomoran slot belajar, mata pelajaran, dan pengampu untuk tiap kelas.</p>
    </div>
    <p class="text-sm text-gray-500">
        Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
    </p>
</div>
```
Ganti menjadi:
```blade
{{-- Header & Breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
            @if ($isYayasan ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Kelola penomoran slot belajar, mata pelajaran, dan pengampu untuk tiap kelas.</p>
    </div>
    <p class="text-sm text-gray-500">
        Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
    </p>
</div>
```

- [ ] **Step 5: Ganti `gantiTahunAjaran()` LENGKAP dan tambah `initSemesterSelect()`, `resetFilter()` di `jadwal-pelajaran-filter.js`**

Cari `initTahunAjaranSelect()`:
```js
initTahunAjaranSelect(el) {
    new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari tahun ajaran...',
        onChange: (value) => {
            this.tahunAjaranId = value;
            this.gantiTahunAjaran(value);
        },
    });
},
```
Ganti menjadi (simpan instance ke `this`, tambah `initSemesterSelect()` dan `resetFilter()` tepat setelahnya):
```js
initTahunAjaranSelect(el) {
    this.tahunAjaranTomSelect = new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari tahun ajaran...',
        onChange: (value) => {
            this.tahunAjaranId = value;
            this.gantiTahunAjaran(value);
        },
    });
},

initSemesterSelect(el) {
    this.semesterTomSelect = new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari semester...',
        onChange: (value) => {
            this.semesterId = value;
            this.muatUlangDaftar();
        },
    });
},

resetFilter() {
    this.tahunAjaranTomSelect?.clear(true);
    this.tahunAjaranId = '';
    this.gantiTahunAjaran('');
},
```

Cari method `gantiTahunAjaran()` LENGKAP:
```js
async gantiTahunAjaran(tahunAjaranId) {
    this.kelasId = '';
    this.semesterId = '';
    this.kelasTomSelect?.clear(true);
    this.kelasTomSelect?.clearOptions();
    if (this.$refs.semesterSelect) {
        this.$refs.semesterSelect.innerHTML = '<option value="">— Pilih Semester —</option>';
    }

    if (tahunAjaranId) {
        try {
            const url = new URL(this.opsiUrl, window.location.origin);
            url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await response.json();

            if (!response.ok) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
            } else {
                json.kelasList.forEach((kelas) => {
                    this.kelasTomSelect.addOption({ value: String(kelas.id), text: kelas.nama });
                });
                this.kelasTomSelect.refreshOptions(false);

                json.semesterList.forEach((semester) => {
                    const option = document.createElement('option');
                    option.value = semester.id;
                    option.textContent = semester.nama + (semester.status_aktif ? ' (Aktif)' : '');
                    this.$refs.semesterSelect.appendChild(option);
                });
            }
        } catch (error) {
            Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
        }
    }

    await this.muatUlangDaftar();
},
```
Ganti SELURUHNYA menjadi (versi kompatibel TomSelect untuk semester):
```js
async gantiTahunAjaran(tahunAjaranId) {
    this.kelasId = '';
    this.semesterId = '';
    this.kelasTomSelect?.clear(true);
    this.kelasTomSelect?.clearOptions();
    this.semesterTomSelect?.clear(true);
    this.semesterTomSelect?.clearOptions();

    if (tahunAjaranId) {
        try {
            const url = new URL(this.opsiUrl, window.location.origin);
            url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await response.json();

            if (!response.ok) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
            } else {
                json.kelasList.forEach((kelas) => {
                    this.kelasTomSelect.addOption({ value: String(kelas.id), text: kelas.nama });
                });
                this.kelasTomSelect.refreshOptions(false);

                json.semesterList.forEach((semester) => {
                    this.semesterTomSelect.addOption({
                        value: String(semester.id),
                        text: semester.nama + (semester.status_aktif ? ' (Aktif)' : ''),
                    });
                });
                this.semesterTomSelect.refreshOptions(false);
            }
        } catch (error) {
            Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester.');
        }
    }

    await this.muatUlangDaftar();
},
```

Tambah state awal `tahunAjaranTomSelect: null, semesterTomSelect: null,` di objek `return { ... }` (sejajar dengan `kelasTomSelect: null,` yang sudah ada).

- [ ] **Step 6: Ganti blok filter di `index.blade.php` — Semester jadi TomSelect + tombol Reset Filter**

Cari blok:
```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <x-input-label value="Tahun Ajaran" />
        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Tahun Ajaran —</option>
            @foreach ($tahunAjaranList as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Semester" />
        <select x-ref="semesterSelect" x-model="semesterId" @change="muatUlangDaftar()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Semester —</option>
            @foreach ($semesterList as $semester)
                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}{{ $semester->status_aktif ? ' (Aktif)' : '' }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Kelas" />
        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Kelas —</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
            @endforeach
        </select>
    </div>
</div>
```
Ganti menjadi:
```blade
<div class="flex items-center justify-between border-b border-gray-100 pb-2">
    <div></div>
    <template x-if="tahunAjaranId || kelasId || semesterId">
        <button type="button" @click="resetFilter()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition">
            <x-icon name="close" class="h-3.5 w-3.5" />
            Reset Filter
        </button>
    </template>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <x-input-label value="Tahun Ajaran" />
        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Tahun Ajaran —</option>
            @foreach ($tahunAjaranList as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Semester" />
        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Semester —</option>
            @foreach ($semesterList as $semester)
                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}{{ $semester->status_aktif ? ' (Aktif)' : '' }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Kelas" />
        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Kelas —</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
            @endforeach
        </select>
    </div>
</div>
```

- [ ] **Step 7: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="badge scope lembaga|breadcrumb index jadwal pelajaran|Reset Filter dan Semester" --compact`
Expected: PASS.

- [ ] **Step 8: Verifikasi manual di browser — WAJIB, bukan opsional, karena ini titik paling berisiko regresi**

Buka halaman `admin/jadwal-pelajaran` di browser (`npm run build` dulu supaya asset ter-update). Pilih Tahun Ajaran → pastikan dropdown Semester TERISI opsi (bukan kosong) dan bisa dipilih. Pilih Semester → pastikan daftar/matriks jadwal ter-update via AJAX. Klik "Reset Filter" → pastikan ketiga filter kembali kosong dan tampilan kembali ke state awal ("Lengkapi Filter Terlebih Dahulu"). Kalau dropdown Semester TERLIHAT KOSONG padahal seharusnya ada opsi — itu tanda Step 5 (penyesuaian `gantiTahunAjaran()`) tidak diterapkan dengan benar, STOP dan perbaiki sebelum lanjut.

- [ ] **Step 9: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

- [ ] **Step 10: Build & format & commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php resources/js/jadwal-pelajaran-filter.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): badge scope lembaga, breadcrumb Akademik, filter Semester TomSelect + Reset Filter"
```

---

## Task 6: KPI Cards Ringkas

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` (state SETELAH Task 4)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$jadwalList` (SUDAH dikirim controller, TIDAK berubah).
- Produces: tidak ada.

- [ ] **Step 1: Baca ulang `_daftar.blade.php` TERKINI**

Cari blok header card (baris 3-12 versi saat ini, dari `<div class="flex flex-col sm:flex-row ...">` sampai `</div>` penutup blok header, SEBELUM blok view-toggle `<div class="inline-flex rounded-xl bg-gray-100 p-1 ...">`).

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('menampilkan KPI Mata Pelajaran Aktif dan Guru Pengampu Terlibat', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertSee('Mata Pelajaran Aktif');
    $response->assertSee('Guru Pengampu Terlibat');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Mata Pelajaran Aktif dan Guru Pengampu" --compact`
Expected: FAIL.

- [ ] **Step 4: Sisipkan KPI cards**

Cari titik TEPAT SETELAH `<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-gray-200 shadow-xs"> ... </div>` (blok header card lengkap, berisi judul + toggle view), sisipkan SEBELUM blok `{{-- Tampilan Matriks Mingguan --}}`:
```blade
@php
    $totalMapel = $jadwalList->pluck('mata_pelajaran_id')->filter()->unique()->count();
    $totalGuru = $jadwalList->pluck('guru_id')->unique()->count();
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Mata Pelajaran Aktif</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalMapel }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Guru Pengampu Terlibat</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalGuru }}</p>
    </div>
</div>

```

- [ ] **Step 5: Jalankan test, pastikan PASS, lalu commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): tambah KPI Mata Pelajaran Aktif dan Guru Pengampu Terlibat"
```

---

## Task 7: Tooltip Tambahan

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php` (state SETELAH Task 5)
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: komponen `<x-tooltip>` (SUDAH ADA, dipakai persis pola dari siklus Pola Jam/Kurikulum Assignment sebelumnya di sesi ini — trigger default non-`as-button` untuk elemen yang isinya BUKAN tombol tunggal, sesuai kontrak komponen).
- Produces: tidak ada.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari blok tombol "Salin dari Kelas Lain" (`<template x-if="kelasId && semesterId"> ... <button type="button" @click="openDuplicateModal()"> ... </button>`).

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('menampilkan tooltip pada tombol Salin dari Kelas Lain dan badge anti-bentrok di modal duplikasi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertOk();
    $response->assertSee('Salin susunan mata pelajaran, guru, dan ruangan dari kelas lain');
    $response->assertSee('Slot yang sudah terisi di kelas tujuan');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tooltip pada tombol Salin dari Kelas Lain" --compact`
Expected: FAIL.

- [ ] **Step 4: Bungkus tombol "Salin dari Kelas Lain" dengan `<x-tooltip>`**

Cari blok:
```blade
<button
    type="button"
    @click="openDuplicateModal()"
    class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
>
    <x-icon name="content_copy" class="h-4 w-4 text-gray-500" />
    <span>Salin dari Kelas Lain</span>
</button>
```
Ganti menjadi:
```blade
<x-tooltip text="Salin susunan mata pelajaran, guru, dan ruangan dari kelas lain yang jadwalnya sudah diatur.">
    <button
        type="button"
        @click="openDuplicateModal()"
        class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
    >
        <x-icon name="content_copy" class="h-4 w-4 text-gray-500" />
        <span>Salin dari Kelas Lain</span>
    </button>
</x-tooltip>
```

- [ ] **Step 5: Bungkus badge "Mekanisme Anti-Bentrok Proaktif" di `_modal-duplicate.blade.php`**

Cari blok:
```blade
<div class="rounded-xl bg-brand-50/70 p-3.5 border border-brand-100 text-xs space-y-1 text-brand-900">
    <div class="flex items-center gap-1.5 font-semibold text-brand-700">
        <x-icon name="info" class="h-4 w-4 shrink-0 text-brand-500" />
        <span>Mekanisme Anti-Bentrok Proaktif</span>
    </div>
    <p class="text-brand-800/80 leading-relaxed">
        Sistem akan secara otomatis melepaskan slot yang bertentangan (jika slot sudah diisi di kelas tujuan, atau guru sudah mengajar di kelas lain pada jam tersebut).
    </p>
</div>
```
Ganti menjadi:
```blade
<x-tooltip text="Slot yang sudah terisi di kelas tujuan, atau guru yang sudah mengajar di jam yang sama di kelas lain, otomatis dilewati tanpa menimpa data yang ada.">
    <div class="rounded-xl bg-brand-50/70 p-3.5 border border-brand-100 text-xs space-y-1 text-brand-900 cursor-help">
        <div class="flex items-center gap-1.5 font-semibold text-brand-700">
            <x-icon name="info" class="h-4 w-4 shrink-0 text-brand-500" />
            <span>Mekanisme Anti-Bentrok Proaktif</span>
        </div>
        <p class="text-brand-800/80 leading-relaxed">
            Sistem akan secara otomatis melepaskan slot yang bertentangan (jika slot sudah diisi di kelas tujuan, atau guru sudah mengajar di kelas lain pada jam tersebut).
        </p>
    </div>
</x-tooltip>
```

- [ ] **Step 6: Jalankan test, pastikan PASS, lalu commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): tambah tooltip pada tombol Salin dari Kelas Lain dan badge anti-bentrok"
```

---

## Task 8: Breadcrumb `create.blade.php` & `edit.blade.php`

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/create.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/edit.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada.

**Task ini AMAN dikerjakan PARALEL dengan Task 1-7** kalau memakai subagent-driven-development — file objek utamanya (`create.blade.php`, `edit.blade.php`) sama sekali berbeda dari file yang disentuh task lain.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('breadcrumb halaman create dan edit jadwal pelajaran memakai Akademik, bukan Beranda', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamPelajaran = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'hari' => Hari::Senin->value, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $create = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', [
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));
    $create->assertOk();
    $create->assertSee('Akademik');

    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'jam_pelajaran_id' => $jamPelajaran->id, 'guru_id' => $guru->id,
    ]);
    $edit = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.edit', $jadwal));
    $edit->assertOk();
    $edit->assertSee('Akademik');
});
```
(Sesuaikan parameter route `create` kalau ternyata controller mengharuskan format berbeda — cek `JadwalPelajaranController::create()` sebelum menjalankan test kalau ada error 404/redirect tak terduga.)

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="breadcrumb halaman create dan edit" --compact`
Expected: FAIL.

- [ ] **Step 3: Fix `create.blade.php`**

Cari blok:
```blade
<p class="text-sm text-gray-500">
    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
</p>
```
Ganti kata "Beranda" jadi "Akademik" (SATU kata saja):
```blade
<p class="text-sm text-gray-500">
    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
</p>
```

- [ ] **Step 4: Fix `edit.blade.php`**

`edit.blade.php` SUDAH dibaca penuh saat plan ini disusun (dikonfirmasi strukturnya identik `create.blade.php`, hanya beda label terakhir "Edit" vs "Tambah" dan memakai `$jadwalPelajaran->semester_id` bukan `$semesterId`) — kode di bawah ini kutipan LANGSUNG dari file, bukan asumsi. Cari blok breadcrumb:
```blade
<p class="text-sm text-gray-500">
    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
</p>
```
Ganti kata "Beranda" jadi "Akademik":
```blade
<p class="text-sm text-gray-500">
    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
</p>
```
**Catatan**: kode di atas sudah diverifikasi cocok persis dengan isi file saat plan ditulis. Kalau ternyata file sudah berubah lagi sejak itu (misal ada task lain yang menyentuhnya di luar plan ini) — baca ulang dulu sebelum menerapkan, jangan dipaksa kalau ternyata sudah tidak cocok.

- [ ] **Step 5: Jalankan test, pastikan PASS, lalu commit**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: semua PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/create.blade.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/edit.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): breadcrumb create/edit konsisten pakai Akademik"
```

---

## Task 9: Regression Sweep Penutup

**Files:** tidak ada file baru — task ini murni verifikasi.

**WAJIB dikerjakan PALING AKHIR, setelah Task 1-8 (termasuk Task 8) semuanya selesai.**

- [ ] **Step 1: Jalankan semua test scoped modul ini**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php tests/Feature/Admin/JadwalPelajaranSiswaControllerTest.php tests/Feature/Admin/JamPelajaranCrudTest.php tests/Feature/Akademik/JadwalSarprasCollisionTest.php --compact`
Expected: semua PASS.

- [ ] **Step 2: Format seluruh perubahan PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` — kalau ada yang di-fix otomatis, ulangi Step 1.

- [ ] **Step 3: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error.

- [ ] **Step 4: Verifikasi manual — konsistensi nama icon final di SELURUH file modul**

Run: `grep -ohE 'x-icon name="[a-z_]+"' resources/views/portals/lembaga/akademik/jadwal-pelajaran/*.blade.php | grep -oE 'name="[a-z_]+"' | sort -u`
Run: `grep -oE "@case\('[a-z_]+'\)" resources/views/components/icon.blade.php | sort -u`
Expected: SETIAP nama dari hasil pertama ada di hasil kedua.

- [ ] **Step 5: Verifikasi manual di browser (Task 5's Step 8 sudah dilakukan, ulangi cek singkat end-to-end)**

Buka halaman `admin/jadwal-pelajaran`: pilih Tahun Ajaran → Semester → Kelas, pastikan daftar/matriks muncul. Klik "+ Tambah Slot Jadwal" — pastikan URL yang terbuka BUKAN `/undefined?...` (bukti Task 1 berhasil). Coba Hapus salah satu jadwal — pastikan TIDAK ada reload halaman penuh (scroll position tetap, toast muncul).

- [ ] **Step 6: Verifikasi route tidak berubah**

Run: `php artisan route:list --name=jadwal-pelajaran`
Expected: semua route masih terdaftar persis seperti sebelumnya — plan ini TIDAK PERNAH mengubah controller/routing.

- [ ] **Step 7: Tanyakan ke user apakah mau full suite**

Jangan jalankan `php artisan test` (full suite) tanpa izin eksplisit — tanyakan ke user dulu, HANYA jalankan sendirian (tidak paralel dengan proses test lain) kalau disetujui.

- [ ] **Step 8: Commit penutup (kalau Step 2 menghasilkan perubahan format)**

```bash
git add -A
git commit -m "chore(jadwal-pelajaran): regression sweep penutup"
```
(kalau tidak ada perubahan file di step ini, skip commit — tidak boleh commit kosong.)

---

## Self-Review (5 Putaran)

**Putaran 1 — Cakupan spec vs task**: Semua §2.1-§2.9 dari spec punya task yang eksplisit mengimplementasikannya (Task 1↔§2.1, Task 2↔§2.3, Task 3↔§2.2+§2.7, Task 4↔§2.4, Task 5↔§2.5, Task 6↔§2.6, Task 7↔§2.8, Task 8↔§2.9). Global Constraints spec §4 semua masuk section "Global Constraints" plan ini kata-per-kata.

**Putaran 2 — Urutan & konflik file**: Ditulis catatan wajib eksplisit di awal plan bahwa `jadwal-pelajaran-filter.js` (Task 1,3,5), `index.blade.php` (Task 5,7), `_daftar.blade.php` (Task 2,3,4,6), `_matrix-roster.blade.php` (Task 2,3) semua WAJIB sekuensial. Task 8 (`create`/`edit.blade.php`) ditandai eksplisit AMAN paralel. Setiap task yang mengedit file yang sudah disentuh task sebelumnya diberi Step 1 "baca ulang TERKINI" secara eksplisit.

**Putaran 3 — Akurasi kode "Current" vs state antar-task**: Kode "cari blok ini" di Task 3 (AJAX hapus) sudah mengasumsikan icon SUDAH benar dari Task 2 (dicek: blok `_matrix-roster.blade.php` yang dikutip Task 3 Step 6 TIDAK menyertakan atribut icon apa pun yang berubah dari Task 2, jadi tidak ada konflik kutipan). Kode Task 5 (filter) mengasumsikan `hapusJadwal()` dari Task 3 SUDAH ada di file JS sebelum method `gantiTahunAjaran()` diubah — dicek posisi penyisipan (`hapusJadwal()` disisipkan SETELAH `submitDuplicate()`, SEBELUM `initTahunAjaranSelect()`; Task 5 mengubah `initTahunAjaranSelect()` dan `gantiTahunAjaran()` yang posisinya SETELAH titik sisip Task 3) — TIDAK ADA overlap baris yang bikin instruksi "cari blok ini" jadi salah.

**Putaran 4 — Keamanan/regresi**: Task 3 (AJAX hapus) TIDAK mengubah otorisasi (`$this->authorize('jadwal-pelajaran.kelola')` di controller tetap dipanggil, endpoint yang dituju SAMA PERSIS `route('admin.jadwal-pelajaran.destroy', $jadwal)`) — hanya cara request dikirim yang berubah (fetch vs form submit), dan CSRF token WAJIB disertakan manual (ditulis eksplisit di Step 4 Task 3, plus Global Constraints). Task 5 (filter TomSelect) diberi peringatan KHUSUS soal risiko regresi `gantiTahunAjaran()` di 3 tempat: judul task, deskripsi "Interfaces", dan Step 8 verifikasi manual WAJIB di browser (bukan opsional) — ini satu-satunya task di plan ini yang eksplisit meminta verifikasi visual manual sebelum lanjut, karena risiko silent-fail (dropdown terlihat kosong tanpa error JS yang jelas) tidak bisa sepenuhnya ditangkap lewat `assertSee` di Pest.

**Putaran 5 — Placeholder scan & konsistensi nama**: Scan ulang semua task untuk red-flag ("TODO", "seperti biasa") — tidak ditemukan. Konsistensi nama state Alpine baru dicek: `tahunAjaranTomSelect`, `semesterTomSelect` (Task 5, ditambahkan sejajar `kelasTomSelect` yang sudah ada, tidak bentrok), `hapusJadwal` (Task 3, dipakai identik di `_daftar.blade.php` dan `_matrix-roster.blade.php`, signature `(url, label)` konsisten di kedua pemakaian). Task 8: draf awal plan (mengikuti spec §2.9 yang memang menandai `edit.blade.php` sebagai "belum diverifikasi") sempat menyalin instruksi "implementer WAJIB baca dulu" — SETELAH file ini benar-benar dibaca penuh selama penyusunan plan, ternyata strukturnya cocok persis dengan `create.blade.php`, jadi Task 8 diperbarui memuat kode LANGSUNG dari file (bukan asumsi/instruksi verifikasi lagi), dengan catatan ringan untuk baca ulang HANYA kalau file berubah lagi sebelum task ini dieksekusi. Ini koreksi nyata pasca-draf, bukan placeholder — bukti self-review putaran ini menangkap sesuatu, bukan cuma formalitas.
