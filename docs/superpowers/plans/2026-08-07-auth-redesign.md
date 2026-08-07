# Auth Pages UI Redesign Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Merombak seluruh halaman autentikasi (Opsi A: Sleek & Refined) ke standar "Premium Museum Quality UX", dengan lokalisasi bahasa, perbaikan border/glow input, dan penambahan fitur *show/hide password*.

## Global Constraints
- Layout dasar Auth tidak berubah, hanya gaya komponen dan teks.
- Komponen *text-input* di-update untuk semua aplikasi, pastikan perubahannya *non-breaking*.

---

### Task 1: Refine `text-input` Component

**Files:**
- Modify: `resources/views/components/text-input.blade.php`

- `[ ]` **Step 1:** Ubah `border-gray-200` dan efek fokus menjadi lebih premium (`focus:ring-4 focus:ring-brand-500/20 focus:border-brand-500 hover:border-gray-300`).
- `[ ]` **Step 2:** Pastikan padding cukup (`py-2.5 px-3.5`). (Note: Tailwind forms might apply its own padding, but explicitly setting it is better).
- `[ ]` **Step 3:** Commit: `git commit -m "style: penyempurnaan border dan glow komponen text-input"`

---

### Task 2: Redesign Login View & Password Toggle

**Files:**
- Modify: `resources/views/auth/login.blade.php`

- `[ ]` **Step 1:** Terjemahkan teks statis ("Remember me" -> "Ingat Saya", "Forgot your password?" -> "Lupa Password?", "Log in" -> "Masuk").
- `[ ]` **Step 2:** Bungkus input `password` dengan `div x-data="{ show: false }"`.
- `[ ]` **Step 3:** Tambahkan tombol mata (menggunakan `<x-icon name="visibility" />` dari Material Symbols) untuk toggle `type="password"` ke `type="text"`.
- `[ ]` **Step 4:** Tambahkan spacing dan styling form yang lebih modern.
- `[ ]` **Step 5:** Commit: `git commit -m "feat(auth): redesign halaman login dan fitur lihat password"`

---

### Task 3: Redesign Forgot Password & Reset Password

**Files:**
- Modify: `resources/views/auth/forgot-password.blade.php`
- Modify: `resources/views/auth/reset-password.blade.php`

- `[ ]` **Step 1:** `forgot-password.blade.php`: Terjemahkan teks deskripsi dan tombol ("Email Password Reset Link" -> "Kirim Link Reset Password").
- `[ ]` **Step 2:** `reset-password.blade.php`: Terjemahkan teks ("Reset Password").
- `[ ]` **Step 3:** Terapkan fitur *toggle show password* di form `reset-password.blade.php` untuk input `password` dan `password_confirmation`.
- `[ ]` **Step 4:** Commit: `git commit -m "feat(auth): redesign dan lokalisasi halaman lupa dan reset password"`

---

### Task 4: Redesign Confirm, Verify, & Force Password

**Files:**
- Modify: `resources/views/auth/confirm-password.blade.php`
- Modify: `resources/views/auth/verify-email.blade.php`
- Modify: `resources/views/auth/force-password.blade.php`

- `[ ]` **Step 1:** Terapkan fitur *toggle show password* untuk halaman yang memerlukan input password (Confirm, Force).
- `[ ]` **Step 2:** Terjemahkan teks ("Confirm Password", "Resend Verification Email", "Log Out", dsb).
- `[ ]` **Step 3:** Commit: `git commit -m "feat(auth): redesign dan lokalisasi halaman konfirmasi dan verifikasi"`
