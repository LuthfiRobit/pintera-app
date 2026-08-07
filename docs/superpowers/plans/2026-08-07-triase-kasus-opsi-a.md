# Triase Kasus (Opsi A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan perombakan UI Opsi A (Interactive Focus Form) pada halaman Triase Kasus menggunakan standar desain Hero Card, Segmented Cards, dan Alpine.js interaktif.

**Architecture:** 
1. Mengekstrak logika *state* UI ke dalam modul ES `triase-form.js` untuk Alpine.js.
2. Mengganti *native select* dan *radio* HTML dengan komponen kartu interaktif yang dikendalikan secara reaktif (*reactive state*) melalui `x-data`.
3. Memastikan form tetap kompatibel dengan struktur request controller backend yang sudah ada (menghasilkan *input hidden* yang valid).

**Tech Stack:** Laravel Blade, Tailwind CSS, Alpine.js, Vite

## Global Constraints
- Tidak boleh mengubah *logic backend/controller* (controller `AdminKasusController@assignKonselor` harus tetap menerima payload yang sama).
- Standar *Premium Museum Quality UX* harus dipenuhi (animasi mulus, *ring* untuk *active state*, dan warna *badge* yang kaya).

---

### Task 1: Modul Alpine.js Dasar

**Files:**
- Create: `resources/js/triase-form.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Produces: Komponen Alpine `triaseForm` yang memiliki *state* `urgensi` dan `konselor`.

- [ ] **Step 1: Buat file Javascript untuk triase-form**
```javascript
// resources/js/triase-form.js
export function triaseForm(config = {}) {
    return {
        urgensi: config.urgensiAwal || 'sedang',
        konselorTipe: config.konselorTipeAwal || '',
        konselorId: config.konselorIdAwal || '',
        
        setUrgensi(val) {
            this.urgensi = val;
        },
        
        setKonselor(tipe, id) {
            this.konselorTipe = tipe;
            this.konselorId = id;
        }
    }
}
```

- [ ] **Step 2: Daftarkan ke app.js**
Tambahkan `import { triaseForm } from './triase-form';` dan `Alpine.data('triaseForm', triaseForm);` ke dalam `resources/js/app.js`.

- [ ] **Step 3: Verifikasi Build**
Jalankan perintah `npm run build` untuk memastikan modul baru terkompilasi.
Run: `npm run build`
Expected: Berhasil tanpa error.

- [ ] **Step 4: Commit**
```bash
git add resources/js/triase-form.js resources/js/app.js
git commit -m "feat(kasus): buat modul alpine triaseForm"
```

---

### Task 2: Hero Overview & Segmented Cards (Urgensi)

**Files:**
- Modify: `resources/views/admin/kasus/triase.blade.php`

**Interfaces:**
- Consumes: Komponen Alpine `triaseForm`.

- [ ] **Step 1: Inisialisasi x-data dan perbarui Hero Card**
Bungkus elemen form di `triase.blade.php` dengan `x-data="triaseForm({ urgensiAwal: '{{ old('tingkat_urgensi', 'sedang') }}', konselorTipeAwal: '{{ old('konselor_tipe', '') }}', konselorIdAwal: '{{ old('konselor_id', '') }}' })"`. Percantik Hero overview (Kategori & Deskripsi) dengan latar belakang gradien halus atau abu-abu kontras.

- [ ] **Step 2: Implementasi Segmented Cards untuk Urgensi**
Ganti tag `<select name="tingkat_urgensi">` menjadi UI grid berisi 3 blok klik-able (button type="button"):
- Rendah (Hijau)
- Sedang (Kuning)
- Tinggi (Merah)
Gunakan `x-bind:class` untuk mengubah warna *border/ring* ketika diklik (`x-on:click="setUrgensi('...')"`). Tambahkan input tersembunyi `<input type="hidden" name="tingkat_urgensi" x-model="urgensi">`.

- [ ] **Step 3: Jalankan Tes Backend**
Karena UI berubah secara struktural, pastikan endpoint backend tetap valid menerima input dari form yang baru.
Run: `php artisan test --filter Kasus`
Expected: PASS.

- [ ] **Step 4: Commit**
```bash
git add resources/views/admin/kasus/triase.blade.php
git commit -m "feat(kasus): ubah select urgensi menjadi segmented cards interaktif"
```

---

### Task 3: Dynamic Radio Cards (Pemilihan Konselor)

**Files:**
- Modify: `resources/views/admin/kasus/triase.blade.php`

**Interfaces:**
- Consumes: `konselorTipe` dan `konselorId` dari `triaseForm`.

- [ ] **Step 1: Implementasi Dynamic Radio Cards**
Pada bagian pilihan konselor, ubah atribut CSS dari `<label>` menggunakan `x-bind:class` agar mengevaluasi apakah konselor sedang aktif: `konselorTipe === 'guru' && konselorId == '...'`. 
Saat label diklik, Alpine akan memicu `setKonselor(tipe, id)`. Hapus input `type="radio"` native dan ganti dengan *hidden inputs* reaktif Alpine, atau biarkan native `radio` namun disembunyikan menggunakan utilitas `sr-only`. 
Pastikan *state error* untuk kapasitas berlebih (`isFull`) ditangani secara visual (latar merah pudar).

- [ ] **Step 2: Pastikan Validasi Error Tampil**
Jika ada validasi form error (seperti `konselor_id` kosong), pastikan pesan *error component* (`<x-input-error>`) merender di posisi bawah dengan presisi.

- [ ] **Step 3: Verifikasi Tes**
Pastikan test tetap berjalan sukses (form assignment bisa disubmit tanpa memecahkan validation rules lama).
Run: `php artisan test --filter Kasus`
Expected: PASS.

- [ ] **Step 4: Commit**
```bash
git add resources/views/admin/kasus/triase.blade.php
git commit -m "feat(kasus): percantik kartu alokasi konselor dengan Alpine state"
```
