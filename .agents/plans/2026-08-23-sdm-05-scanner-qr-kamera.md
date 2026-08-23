# Scanner QR Kamera untuk Kehadiran SDM — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan mode pemindaian QR berbasis kamera browser ke halaman `admin/kehadiran-sdm/scan.blade.php`, berdampingan dengan input token manual yang sudah ada, dengan fallback otomatis ke manual kalau kamera gagal diakses.

**Architecture:** Sebuah Alpine.js data component baru (`resources/js/qr-camera-scanner.js`) membungkus library `html5-qrcode`, dipasang sebagai nested `x-data` scope di dalam scope form yang sudah ada di `scan.blade.php`. Component ini memanggil callback (`onScanSuccess`, `onCameraError`) yang didefinisikan sebagai closure di scope root halaman — closure itu langsung memanipulasi state root (`token`, `mode`, `message`) dan memanggil `submitScan()` yang sudah ada, TANPA mengubah kontrak endpoint backend sama sekali.

**Tech Stack:** Laravel 12 (Blade), Alpine.js 3.x, Vite, library npm baru `html5-qrcode`.

## Global Constraints

- **TIDAK ADA perubahan file PHP apapun** (controller/Action/route/model/permission) — hard constraint dari spec. `AttendanceQrScanController`, `ScanQrAttendanceAction`, route `admin.kehadiran-sdm.scan.store`/`admin.kehadiran-sdm.scan.index` HARUS tetap identik.
- Kontrak endpoint POST `admin.kehadiran-sdm.scan.store` (TIDAK BOLEH berubah): body JSON `{ token: string, arah: 'masuk'|'pulang', attendance_point_id: int|null }`; respons sukses `200 { message: string }`; respons gagal `422 { message: string }`.
- State Alpine root yang sudah ada di `scan.blade.php` dan WAJIB dipertahankan namanya persis: `arah`, `attendancePointId`, `token`, `loading`, `message`, `messageType`, `scanHistory`, method `submitScan()`.
- Library kamera: `html5-qrcode` (npm), bukan `jsQR` atau `BarcodeDetector` native.
- Mode default saat halaman dibuka: **Kamera**. Fallback otomatis ke Manual kalau kamera gagal.
- `facingMode: 'environment'`, TIDAK ADA dropdown pemilih kamera.
- Setelah QR terbaca: **auto-submit langsung** (bukan isi field lalu tunggu tap tombol).
- Cegah submit ganda: pause kamera + overlay "Memproses..." selama ±2.5 detik setelah tiap scan berhasil, baru resume.
- Field Arah (Masuk/Pulang) dan Titik Absen tetap selalu terlihat di kedua mode.
- Baseline kode yang dikutip plan ini: commit `a7e30dd` di branch `sdm-v1`.

---

## Task 1: Library kamera + komponen Alpine `qrCameraScanner`

**Files:**
- Modify: `package.json` (tambah dependency `html5-qrcode`)
- Create: `resources/js/qr-camera-scanner.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Produces: `qrCameraScanner(config)` — fungsi factory Alpine data component. `config` menerima:
  - `config.elementId: string` (opsional, default `'qr-camera-reader'`) — id elemen DOM tempat preview kamera dirender oleh library.
  - `config.onScanSuccess: (decodedText: string) => void` (opsional) — dipanggil sekali per scan berhasil, dengan string token hasil decode mentah.
  - `config.onCameraError: (message: string) => void` (opsional) — dipanggil kalau kamera gagal start, dengan pesan error dalam Bahasa Indonesia yang siap ditampilkan ke user.
  - Method publik yang di-expose di object hasil: `startCamera()` (async), `stopCamera()` (async). Property reaktif: `cameraActive` (boolean), `processing` (boolean).
- Task ini TIDAK menyentuh Blade sama sekali — verifikasi murni lewat build sukses (tidak ada cara otomatis menguji perilaku kamera browser di task ini, itu ditest manual di Task 3).

- [ ] **Step 1: Install dependency `html5-qrcode`**

Jalankan:
```bash
npm install html5-qrcode
```

Verifikasi: buka `package.json`, pastikan section `"dependencies"` sekarang berisi baris `"html5-qrcode": "^x.y.z"` (versi persis tergantung rilis terbaru saat install — itu wajar, jangan dipaksa versi tertentu). Pastikan juga `package-lock.json` ikut berubah (banyak baris baru terkait `html5-qrcode`).

- [ ] **Step 2: Buat `resources/js/qr-camera-scanner.js`**

Buat file baru dengan isi PERSIS berikut:

```javascript
import { Html5Qrcode } from 'html5-qrcode';

export function qrCameraScanner(config) {
    config = config || {};

    return {
        elementId: config.elementId || 'qr-camera-reader',
        onScanSuccess: config.onScanSuccess || function () {},
        onCameraError: config.onCameraError || function () {},
        html5Qrcode: null,
        cameraActive: false,
        processing: false,

        async startCamera() {
            if (this.cameraActive || this.html5Qrcode) {
                return;
            }
            this.html5Qrcode = new Html5Qrcode(this.elementId);
            try {
                await this.html5Qrcode.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => this.handleScanSuccess(decodedText),
                    () => {
                        // Dipanggil per-frame saat tidak ada QR terbaca — noise normal, sengaja diabaikan.
                    },
                );
                this.cameraActive = true;
            } catch (error) {
                this.cameraActive = false;
                this.html5Qrcode = null;
                this.onCameraError(this.describeError(error));
            }
        },

        async stopCamera() {
            if (!this.html5Qrcode) {
                return;
            }
            if (this.cameraActive) {
                try {
                    await this.html5Qrcode.stop();
                } catch (error) {
                    // Stream mungkin sudah berhenti duluan (mis. tab pindah) — aman diabaikan.
                }
            }
            this.html5Qrcode = null;
            this.cameraActive = false;
            this.processing = false;
        },

        handleScanSuccess(decodedText) {
            if (this.processing) {
                return;
            }
            this.processing = true;
            if (this.html5Qrcode) {
                this.html5Qrcode.pause(true);
            }
            this.onScanSuccess(decodedText);
            setTimeout(() => {
                this.processing = false;
                if (this.html5Qrcode && this.cameraActive) {
                    this.html5Qrcode.resume();
                }
            }, 2500);
        },

        describeError(error) {
            const name = error && error.name ? error.name : '';
            if (name === 'NotAllowedError') {
                return 'Kamera tidak dapat diakses: izin ditolak oleh browser.';
            }
            if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                return 'Tidak ada kamera yang terdeteksi pada perangkat ini.';
            }
            if (typeof window !== 'undefined' && window.isSecureContext === false) {
                return 'Kamera hanya bisa diakses lewat koneksi HTTPS atau localhost.';
            }
            return 'Kamera tidak dapat diaktifkan. Silakan gunakan Input Manual.';
        },
    };
}
```

- [ ] **Step 3: Daftarkan komponen di `resources/js/app.js`**

Buka `resources/js/app.js`. Tambahkan baris import baru tepat di bawah baris `import { tomSelectPegawai } from './tom-select-pegawai';` (sekitar baris 45):

```javascript
import { qrCameraScanner } from './qr-camera-scanner';
```

Lalu cari baris registrasi `Alpine.data('tomSelectPegawai', tomSelectPegawai);` (sekitar baris 97) dan tambahkan baris baru tepat di bawahnya:

```javascript
Alpine.data('qrCameraScanner', qrCameraScanner);
```

**Sekalian perbaiki 1 baris duplikat yang sudah ada** (tidak terkait fitur ini tapi persis di area yang sedang disentuh): cari 2 baris identik berturutan `Alpine.data('triaseForm', triaseForm);` (baris 98 dan 99 di versi baseline) — **hapus salah satu baris duplikatnya**, sisakan hanya SATU baris `Alpine.data('triaseForm', triaseForm);`.

Setelah Step 3, urutan baris registrasi di sekitar situ harus jadi:
```javascript
Alpine.data('tomSelectPegawai', tomSelectPegawai);
Alpine.data('qrCameraScanner', qrCameraScanner);
Alpine.data('triaseForm', triaseForm);
Alpine.data('virtualAccountFilter', virtualAccountFilter);
```

- [ ] **Step 4: Build untuk verifikasi tidak ada error syntax/import**

Jalankan:
```bash
npm run build
```

Expected: build selesai tanpa error (`vite build` sukses, exit code 0). Kalau ada error `Cannot find module 'html5-qrcode'` — ulangi Step 1, pastikan `npm install html5-qrcode` benar-benar selesai tanpa error sebelum lanjut.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json resources/js/qr-camera-scanner.js resources/js/app.js
git commit -m "feat(sdm): tambah komponen Alpine qrCameraScanner (html5-qrcode)"
```

---

## Task 2: Integrasi UI toggle Kamera/Manual di `scan.blade.php`

**Files:**
- Modify: `resources/views/admin/kehadiran-sdm/scan.blade.php` (ganti seluruh isi file)
- Test: `tests/Feature/Admin/AttendanceQrScanViewTest.php` (baru)

**Interfaces:**
- Consumes: `qrCameraScanner(config)` dari Task 1 (harus sudah ter-daftar sebagai `Alpine.data('qrCameraScanner', ...)`).
- Route yang dipakai (TIDAK berubah dari sebelumnya): `admin.kehadiran-sdm.scan.index` (GET, render halaman), `admin.kehadiran-sdm.scan.store` (POST, submit scan — dipanggil dari `submitScan()` yang sudah ada, tidak disentuh task ini).

- [ ] **Step 1: Tulis test yang gagal dulu**

Buat file baru `tests/Feature/Admin/AttendanceQrScanViewTest.php`:

```php
<?php
// tests/Feature/Admin/AttendanceQrScanViewTest.php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the scan page with both camera and manual mode toggles', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.catat']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.scan.index'));

    $response->assertOk()
        ->assertSee('Scan Kamera')
        ->assertSee('Input Manual')
        ->assertSee('qr-camera-reader', false)
        ->assertSee('qrCameraScanner', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test tests/Feature/Admin/AttendanceQrScanViewTest.php
```

Expected: **FAIL** — halaman saat ini belum punya teks "Scan Kamera"/"Input Manual" atau elemen `qr-camera-reader`/`qrCameraScanner`.

- [ ] **Step 3: Ganti seluruh isi `resources/views/admin/kehadiran-sdm/scan.blade.php`**

Timpa SELURUH isi file (bukan sekadar tambah baris) dengan konten PERSIS berikut:

```blade
{{-- resources/views/admin/kehadiran-sdm/scan.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-0" x-data="{
        mode: 'kamera',
        arah: 'masuk',
        attendancePointId: '',
        token: '',
        loading: false,
        message: null,
        messageType: 'success',
        scanHistory: [],
        async submitScan() {
            if (!this.token.trim()) return;
            this.loading = true;
            this.message = null;
            const submittedToken = this.token;
            try {
                const response = await fetch('{{ route('admin.kehadiran-sdm.scan.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    },
                    body: JSON.stringify({ 
                        token: submittedToken, 
                        arah: this.arah,
                        attendance_point_id: this.attendancePointId || null
                    }),
                });
                const data = await response.json();
                this.message = data.message;
                this.messageType = response.ok ? 'success' : 'error';
                
                if (response.ok) {
                    this.scanHistory.unshift({
                        id: Date.now(),
                        message: data.message,
                        arah: this.arah,
                        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                        success: true
                    });
                    if (this.scanHistory.length > 5) this.scanHistory.pop();
                }
            } catch (e) {
                this.message = 'Gagal menghubungi server. Periksa koneksi jaringan Anda.';
                this.messageType = 'error';
            } finally {
                this.token = '';
                this.loading = false;
                this.$nextTick(() => {
                    if (this.$refs.tokenInput) this.$refs.tokenInput.focus();
                });
            }
        }
    }">
        
        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pemindai QR Presensi SDM</h1>
                <p class="text-xs text-gray-500 mt-0.5">Gunakan kamera untuk memindai QR pegawai, scanner fisik, atau ketik token manual.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> SDM <span class="mx-1 text-gray-300">&rsaquo;</span> Kehadiran <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Scan QR</b>
                </p>
                <a href="{{ route('admin.kehadiran-sdm.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Kehadiran SDM</span>
                </a>
            </div>
        </div>

        {{-- Grid 2 Kolom --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            
            {{-- Form Scanner --}}
            <div class="lg:col-span-7 space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">

                    {{-- Mode Toggle: Kamera vs Manual --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Mode Pemindaian</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="mode = 'kamera'"
                                :class="mode === 'kamera' ? 'border-brand-300 bg-brand-50/60 text-brand-800 ring-2 ring-brand-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'"
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Scan Kamera</span>
                            </button>
                            <button
                                type="button"
                                @click="mode = 'manual'; $nextTick(() => { if ($refs.tokenInput) $refs.tokenInput.focus() })"
                                :class="mode === 'manual' ? 'border-brand-300 bg-brand-50/60 text-brand-800 ring-2 ring-brand-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'"
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Input Manual</span>
                            </button>
                        </div>
                    </div>

                    {{-- Arah Presensi Radio Pills --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Arah Presensi <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label 
                                :class="arah === 'masuk' ? 'border-emerald-300 bg-emerald-50/60 text-emerald-800 ring-2 ring-emerald-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <input type="radio" x-model="arah" value="masuk" class="text-emerald-600 focus:ring-emerald-500">
                                <span>MASUK</span>
                            </label>
                            <label 
                                :class="arah === 'pulang' ? 'border-amber-300 bg-amber-50/60 text-amber-800 ring-2 ring-amber-500/20' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'" 
                                class="flex items-center justify-center gap-2 rounded-xl border p-3 text-xs font-bold transition cursor-pointer"
                            >
                                <input type="radio" x-model="arah" value="pulang" class="text-amber-600 focus:ring-amber-500">
                                <span>PULANG</span>
                            </label>
                        </div>
                    </div>

                    {{-- Titik Absen (opsional) --}}
                    @if (isset($titikAbsen) && $titikAbsen->isNotEmpty())
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Lokasi / Titik Absen (opsional)</label>
                            <select x-model="attendancePointId" class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Gunakan Titik Absen Default —</option>
                                @foreach ($titikAbsen as $titik)
                                    <option value="{{ $titik->id }}">{{ $titik->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Mode Kamera --}}
                    <div
                        x-show="mode === 'kamera'"
                        x-data="qrCameraScanner({
                            elementId: 'qr-camera-reader',
                            onScanSuccess: (decodedText) => { token = decodedText; submitScan(); },
                            onCameraError: (msg) => {
                                mode = 'manual';
                                message = msg;
                                messageType = 'error';
                                $nextTick(() => { if ($refs.tokenInput) $refs.tokenInput.focus() });
                            }
                        })"
                        x-effect="mode === 'kamera' ? startCamera() : stopCamera()"
                    >
                        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-black">
                            <div id="qr-camera-reader" class="aspect-square w-full"></div>
                            <div
                                x-show="processing"
                                x-cloak
                                class="absolute inset-0 flex items-center justify-center bg-black/60 text-xs font-bold text-white"
                            >
                                Memproses...
                            </div>
                        </div>
                        <p class="mt-2 text-center text-[11px] text-gray-400">Arahkan kamera ke QR Code pegawai. Pemindaian dan pencatatan berjalan otomatis.</p>
                    </div>

                    {{-- Mode Manual --}}
                    <div x-show="mode === 'manual'">
                        <form @submit.prevent="submitScan()" class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Token QR Pegawai <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <input 
                                        x-ref="tokenInput" 
                                        x-model="token" 
                                        type="text" 
                                        placeholder="Scan atau ketik token unik..." 
                                        class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 pr-10 font-mono text-sm text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m0 14v1m8-8h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <button 
                                type="submit" 
                                x-bind:disabled="loading || !token.trim()" 
                                class="w-full rounded-xl bg-brand-600 px-4 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50 active:scale-[0.98]"
                            >
                                <span x-text="loading ? 'Memproses Presensi...' : 'Catat Presensi SDM'"></span>
                            </button>
                        </form>
                    </div>

                    {{-- Feedback Message Card --}}
                    <template x-if="message">
                        <div 
                            class="rounded-xl border p-4 text-xs font-semibold flex items-center gap-3 transition"
                            :class="messageType === 'success' ? 'border-emerald-200 bg-emerald-50/80 text-emerald-800' : 'border-rose-200 bg-rose-50/80 text-rose-800'"
                        >
                            <template x-if="messageType === 'success'">
                                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <template x-if="messageType === 'error'">
                                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <span x-text="message"></span>
                        </div>
                    </template>

                </div>
            </div>

            {{-- History Log Sesi ini --}}
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h2 class="font-display text-xs font-bold text-gray-900 uppercase tracking-wider">Riwayat Pemindaian (Sesi Ini)</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600" x-text="scanHistory.length + ' scan'"></span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <template x-if="scanHistory.length === 0">
                            <div class="py-8 text-center text-gray-400">
                                <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="mt-2 text-xs font-semibold text-gray-600">Belum ada pemindaian di sesi ini.</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Hasil pemindaian akan tercatat di sini.</p>
                            </div>
                        </template>

                        <template x-for="item in scanHistory" :key="item.id">
                            <div class="py-2.5 flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs font-bold text-gray-900" x-text="item.message"></p>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold mt-1" :class="item.arah === 'masuk' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" x-text="item.arah.toUpperCase()"></span>
                                </div>
                                <span class="font-mono text-[10px] text-gray-400 shrink-0 mt-0.5" x-text="item.time"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
```

**Catatan penting soal apa yang berubah dari file lama vs baru:**
- Ditambahkan: property `mode: 'kamera'` di root `x-data`.
- Ditambahkan: blok toggle "Mode Pemindaian" (2 tombol).
- Ditambahkan: blok "Mode Kamera" (`x-data="qrCameraScanner(...)"` nested + `x-effect` start/stop otomatis).
- Diubah: blok form token lama dibungkus `<div x-show="mode === 'manual'">` dan atribut `autofocus` pada `<input x-ref="tokenInput">` DIHAPUS (karena sekarang mode default adalah kamera, bukan manual — autofocus otomatis ke input yang sedang hidden itu tidak berguna).
- Tidak berubah sama sekali: method `submitScan()`, state `arah`/`attendancePointId`/`loading`/`message`/`messageType`/`scanHistory`, blok Arah Presensi, blok Titik Absen, Feedback Message Card, kolom History Log.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```bash
php artisan test tests/Feature/Admin/AttendanceQrScanViewTest.php
```

Expected: **PASS** (1 passed).

- [ ] **Step 5: Jalankan ulang test existing yang menyentuh endpoint scan (regresi backend)**

```bash
php artisan test tests/Feature/Admin/AttendanceQrScanControllerTest.php
```

Expected: **PASS** (2 passed) — test ini menguji endpoint POST yang TIDAK disentuh task ini, jadi harus tetap hijau tanpa perubahan apapun.

- [ ] **Step 6: Build ulang untuk pastikan Blade + JS baru kompatibel**

```bash
npm run build
```

Expected: build sukses tanpa error.

- [ ] **Step 7: Commit**

```bash
git add resources/views/admin/kehadiran-sdm/scan.blade.php tests/Feature/Admin/AttendanceQrScanViewTest.php
git commit -m "feat(sdm): integrasikan toggle scan kamera/manual di halaman scan QR SDM"
```

---

## Task 3: Verifikasi manual di browser sungguhan + handoff log

**Files:**
- Create: `.agents/logs/2026-08-23-sdm-05-scanner-qr-kamera.md`

**Interfaces:**
- Consumes: hasil Task 1 & 2 (build sudah jadi, halaman sudah live di `admin.kehadiran-sdm.scan.index`).

Task ini TIDAK BISA diuji otomatis (tidak ada kamera di lingkungan CI/test PHP) — WAJIB dilakukan verifikasi manual langsung di browser sungguhan sebelum dianggap selesai. Jalankan `npm run dev` (atau `npm run build` lalu buka lewat server Laravel biasa — `php artisan serve` / Laragon) dan login sebagai user dengan permission `kehadiran-sdm.catat`, buka halaman Scan QR SDM.

- [ ] **Step 1: Uji di device desktop (laptop/PC dengan 1 webcam)**

Buka halaman scan di browser desktop (Chrome/Edge). Centang semua ini SATU PER SATU dan catat hasilnya (PASS/FAIL) — jangan tandai selesai kalau ada yang belum benar-benar dicoba:
- [ ] Saat halaman pertama kali dibuka, browser meminta izin akses kamera secara otomatis (tanpa perlu tap tombol apapun dulu).
- [ ] Setelah izin diberikan, preview video dari webcam tampil di kotak "Mode Kamera".
- [ ] Buka halaman `sdm.qr-saya` (QR Kehadiran Saya) untuk salah satu akun pegawai di device/tab lain (atau screenshot QR-nya lalu tampilkan di layar HP kedua), arahkan webcam ke gambar QR itu.
- [ ] QR terbaca, overlay "Memproses..." muncul sesaat, lalu card feedback hijau "Kehadiran berhasil dicatat: [nama pegawai]" tampil, dan baris baru muncul di "Riwayat Pemindaian (Sesi Ini)" di kolom kanan.
- [ ] Setelah ±2-3 detik, overlay "Memproses..." hilang dan kamera siap scan lagi (coba scan QR yang sama sekali lagi setelah overlay hilang — harus berhasil tercatat lagi sebagai entri baru, BUKAN diblokir/diabaikan).
- [ ] Tap tombol "Input Manual" — preview kamera hilang dari layar, dan LAMPU INDIKATOR KAMERA FISIK di device benar-benar MATI (bukan cuma disembunyikan secara visual). Field token manual tampil dan otomatis mendapat fokus.
- [ ] Tap tombol "Scan Kamera" lagi — kamera menyala ulang (lampu indikator nyala lagi), preview video tampil lagi, siap scan.

- [ ] **Step 2: Uji di device HP (Android atau iOS, browser apapun yang tersedia)**

- [ ] Ulangi seluruh skenario Step 1 di HP. Perhatikan khusus: kamera yang aktif default HARUS kamera BELAKANG (bukan kamera depan/selfie) tanpa perlu setting apapun dari admin.
- [ ] Scan QR sungguhan dari layar HP LAIN (device kedua) atau dari print-out QR fisik — pastikan berhasil terbaca dan tercatat.

- [ ] **Step 3: Simulasikan skenario kamera gagal (fallback ke manual)**

Di browser desktop, tolak permintaan izin kamera (klik "Block"/"Deny" saat prompt izin muncul), lalu reload halaman:
- [ ] Halaman otomatis pindah ke mode "Input Manual" (toggle "Input Manual" yang aktif/ter-highlight).
- [ ] Card feedback error (merah) muncul dengan pesan "Kamera tidak dapat diakses: izin ditolak oleh browser."
- [ ] Field token manual tetap berfungsi normal (bisa ketik token, submit, dan tercatat seperti biasa) — jalur manual TIDAK RUSAK oleh perubahan ini.

- [ ] **Step 4: Tulis handoff log**

Buat file `.agents/logs/2026-08-23-sdm-05-scanner-qr-kamera.md` berisi (dalam Bahasa Indonesia): ringkasan tiap task (1-3) yang sudah dikerjakan beserta commit hash masing-masing, hasil test otomatis (`AttendanceQrScanViewTest`, `AttendanceQrScanControllerTest`) dengan angka pasti (jumlah passed), dan HASIL VERIFIKASI MANUAL Step 1-3 di atas SATU PER SATU dengan status PASS/FAIL yang jujur beserta device/browser apa yang dipakai untuk tiap uji coba (jangan tulis "sudah ditest" tanpa detail device — itu tidak bisa dipercaya sebagai bukti). Kalau ada langkah yang FAIL atau tidak bisa dicoba (mis. tidak ada device HP tersedia saat itu), tulis apa adanya, JANGAN mengarang hasil PASS.

- [ ] **Step 5: Commit handoff log**

```bash
git add .agents/logs/2026-08-23-sdm-05-scanner-qr-kamera.md
git commit -m "docs(sdm): handoff log scanner QR kamera kehadiran SDM"
```
