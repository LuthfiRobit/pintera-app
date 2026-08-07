# Triase Kasus UI/UX Design Spec

## 1. Konsep Utama
Arsitektur Opsi A (Interactive Focus Form) mempertahankan urutan baca linear (top-to-bottom) satu kolom, namun memaksimalkan setiap titik interaksi (input) dengan umpan balik visual (*visual feedback*) seketika melalui integrasi **Alpine.js**.

## 2. Struktur Komponen

### A. Case Overview (Hero Card)
- **Visual:** Kartu tunggal dengan efek `shadow-card` dan transisi `hover:shadow-elevated`.
- **Elemen:**
  - Kategori masalah dan Badge Status (dengan fungsi `badgeTone()`).
  - Latar belakang blok dengan rona abu-abu halus (`bg-gray-50`) untuk membedakan teks deskripsi dari form interaktif di bawahnya.
  - Tipografi difokuskan pada keterbacaan (menggunakan `font-medium` dan `leading-relaxed`).

### B. Input Tingkat Urgensi (Segmented Cards)
- **Fungsi:** Menggantikan dropdown standar `<select>` dengan tiga kartu interaktif.
- **Teknis:** Menggunakan Alpine.js state `urgensi` (default 'sedang').
- **Varian Kartu:**
  - 🟢 **Rendah:** Latar `bg-emerald-50`, garis batas `border-emerald-200` saat aktif.
  - 🟡 **Sedang:** Latar `bg-amber-50`, garis batas `border-amber-200` saat aktif.
  - 🔴 **Tinggi:** Latar `bg-error-50`, garis batas `border-error-200` saat aktif.
- **Interaksi:** Saat diklik, state Alpine ter-update, merender kartu yang diklik menjadi *bold* dan bercahaya (memiliki `ring-2`), sementara opsi lain meredup (*grayscale* atau *border* biasa). Input data yang dikirim ke backend (`<input type="hidden">` atau radio tersembunyi).

### C. Alokasi Konselor (Dynamic Radio Cards)
- **Fungsi:** Menampilkan daftar kandidat Guru BK dan Konselor Yayasan.
- **Teknis:** Menggunakan Alpine.js state `konselorId`.
- **Interaksi & Validasi:**
  - Kartu konselor yang dipilih akan mendapat *highlight* `border-brand-500`, `bg-brand-50/10`, dan efek `ring-2 ring-brand-500/20`.
  - Konselor yang kapasitasnya penuh (*Overloaded*) tetap dapat dipilih namun kartu akan berlatar `bg-error-50/30` sebagai peringatan *hard-visual*.
  - Pemilihan tipe konselor (`guru` atau `karyawan`) dan `id`-nya akan ditangani secara reaktif melalui Alpine untuk *hidden inputs*, meminimalisir manipulasi DOM manual (`document.getElementById`).

## 3. Data Flow & Security
- Form mengirim POST request ke rute `admin.kasus.assign-konselor`.
- Seluruh properti wajib (`tingkat_urgensi`, `konselor_tipe`, `konselor_id`) di-*bind* menggunakan atribut valid (bisa via radio buttons standar yang secara visual disembunyikan `sr-only` atau melalui input hidden dengan Alpine).

## 4. Rencana Implementasi
Pekerjaan akan difokuskan pada satu file utama:
- `resources/views/admin/kasus/triase.blade.php`: Merombak tag HTML konvensional menjadi komponen Alpine.js dan merekonstruksi CSS utilitas Tailwind.
