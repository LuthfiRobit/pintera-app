# Master Data SDM Enhancements & UI/UX Refinements (Design Specification)

**Date:** 2026-08-02  
**Domain:** Master Data SDM (Guru & Siswa)  
**Status:** Pending User Approval  
**Architecture Methodology:** Superpowers (`ui-styling`, `ui-ux-pro-max`, `brainstorming`)

---

## 1. Executive Summary & Goal Description
This design document defines the architectural upgrade and UI/UX transformation for **Pintera App's Master Data SDM (Sumber Daya Manusia)** modules: **Guru** and **Siswa**. 
The objective is to eliminate traditional redundant page navigations, enrich relational data management, provide comprehensive visibility into automated login account generation, and deliver a museum-quality desktop-like web interface.

---

## 2. Module 1: Guru (Inline View-to-Edit & Tabbed Relational Profile)

### 2.1 Problem Statement
Currently, `GuruController` exposes basic CRUD operations with simple form layouts. The database already contains rich relationship tables (`riwayat_pendidikan_guru`, `sertifikasi_guru`, and `guru_jabatan_tambahan`), but the administrator interface completely lacks UI and routing endpoints to view or manage these relations. Furthermore, conventional editing forces administrators into form-only interfaces that create visual clutter when simply browsing staff profiles.

### 2.2 UI/UX Architecture: Toggle-in-Place & Tabs UI
We will convert `resources/views/admin/guru/edit.blade.php` into a unified **Interactive Staff Profile & Management Portal** powered by Tailwind CSS and Alpine.js.

#### A. Navigation & Information Architecture (4 Tabs)
The page layout will feature an identity hero banner (displaying Teacher Name, NIP, Status Badge, and Primary Teaching Role) above four interactive shadcn-inspired navigation tabs:
1. **Tab 1: Profil Utama (Biodata & Kepegawaian)**
   - Houses NIK, NIP, NUPTK, personal bio, employment status, TMT dates, and login account linkage.
2. **Tab 2: Riwayat Pendidikan**
   - Displays academic degrees (Jenjang, Gelar, Jurusan, Universitas, Tahun Lulus) in a clean timeline layout.
3. **Tab 3: Sertifikasi Guru**
   - Displays teaching certifications and registration numbers in modular grid cards.
4. **Tab 4: Jabatan Tambahan**
   - Displays administrative assignments (e.g., Wakil Kepala Sekolah, Pembina Ekskul/OSIS, Wali Kelas) linked to active academic years.

#### B. Toggle-In-Place (Mode Lihat ↔ Mode Ubah)
To satisfy `/ui-ux-pro-max` guidelines on visual clarity and cognitive reduction:
- **Default State (Mode Lihat):** All form attributes are rendered as high-contrast typographic text, badges, and clean metadata grids (zero input box borders or clutter).
- **Action Trigger:** An explicit button **"✏️ Ubah Profil"** toggles Alpine.js state (`x-data="{ editMode: {{ $errors->any() ? 'true' : 'false' }} }"`).
- **Active State (Mode Ubah):** Static text elements smoothly transition into interactive input fields (`<input>`, `<select>`) within 150-300ms micro-transition timings. If validation fails on server submission, the view loads immediately in `editMode: true` with error banners highlighted.

#### C. Interactive Relational Management (Modals)
Within Tabs 2, 3, and 4:
- In View Mode, administrators see structured cards or timelines of existing historical records.
- Clicking **"➕ Tambah Riwayat / Sertifikasi / Jabatan"** triggers a localized modal dialog (no full-page navigation or redirect loops).
- Edit and Delete actions operate directly within these cards using modular form submissions to new relational endpoints.

### 2.3 Backend Controller & Routing Extension
To serve relational actions without bloating `GuruController`, we will establish clean auxiliary controllers under `App\Http\Controllers\Admin\Guru\`:
- `RiwayatPendidikanController@store|update|destroy`
- `SertifikasiController@store|update|destroy`
- `JabatanTambahanController@store|update|destroy`

All routes will enforce strict tenant scoping (`lembaga_id` ownership verification via Eloquent relationships) and check standard RBAC permissions (`guru.edit`).

---

## 3. Module 2: Siswa (Import Template & Account Generator Mastery)

### 3.1 Problem Statement
While `SiswaImportController` silently invokes `AkunSiswaGenerator` during Excel/CSV imports, administrators receive zero visual feedback or instruction regarding username/password generation. Additionally, historical student records or students imported without account generation have no automated bulk account creation mechanism, forcing manual interventions.

### 3.2 UI/UX Architecture: Account Generator Mastery

#### A. Excel Import Template Enrichment
- **`SiswaImportTemplateExport` Updates:** We will update the downloadable Excel template to include informative header notes or supplementary instructional rows clarifying that **every valid row automatically generates both a Student Profile and a linked User Login Account**.
- We will standardize column headings and formatting guidance (e.g., date formats, mandatory vs. optional fields, and classroom exact name matching).

#### B. Transparent Import Preview Table
- In `import-preview.blade.php`, we will transform the basic preview list into a structured analytical table featuring an explicit column: **`Prediksi Akun Login`**.
- During `preview()`, the controller will invoke `AkunSiswaGenerator::usernameUntuk($lembaga, $row['nis'])` in read-only prediction mode to display the exact target username (e.g., `smpitprm.2026001`) and alert administrators to the default password rule (NIS).

#### C. Mass Account Generator & Individual Actions in Student Catalog (`index.blade.php`)
- **Mass Generator Button ("⚡ Generate Akun Massal"):** Displayed dynamically at the top of the student catalog table whenever there exist active students without a login account (`whereNull('user_id')`). Clicking it opens a confirmation dialog showing the exact count of affected students and executes batch generation atomically inside a database transaction.
- **Individual Action ("Buat Akun"):** For any student row in the catalog where `user_id` is null, an explicit primary mini-button **"Buat Akun Login"** replaces the standard "Reset Password" action, enabling targeted one-click creation.

### 3.3 Backend Controller Extensions
We will add two dedicated endpoints to `SiswaController`:
- `generateAkunMassal(Request $request): RedirectResponse`: Finds all active students in the acting user's tenant where `user_id IS NULL`, iterates through them inside a `DB::transaction`, calls `AkunSiswaGenerator::buat()`, and assigns the resulting accounts.
- `generateAkun(Siswa $siswa): RedirectResponse`: Creates and assigns a login account to an individual student who currently lacks one, returning a descriptive status notification.

---

## 4. Design Standards & Compliance (`ui-styling` & `ui-ux-pro-max`)
1. **Iconography:** Strict use of SVG vector icons (Phosphor/Heroicons equivalents via Blade formatting). Zero structural Unicode emojis in production code.
2. **Contrast & Theming:** All static profile text, card background layers, and modal scrims adhere to WCAG 4.5:1 minimum contrast ratios across light and dark modes.
3. **Spacing Rhythm:** 8dp consistent spacing scale (`space-y-4`, `p-6`, `gap-3`, `mt-2`) across profile cards and modal dialogs.
4. **Touch Targets:** Minimum 44x44px interactive bounds for all tab triggers, action buttons, and modal closure icons.

---

## 5. Verification Strategy & Automated Testing
Before completing implementation, we must verify zero regressions and complete runtime functional accuracy using automated test suites:

### 5.1 Automated Feature Tests to Create / Expand
- **`Tests\Feature\Admin\GuruRelationalProfileTest` (NEW):**
  - Asserts rendering of the 4 tabs and view-to-edit toggle markup in `admin.guru.edit`.
  - Asserts successful CRUD actions on `RiwayatPendidikan`, `Sertifikasi`, and `JabatanTambahan` endpoints.
  - Asserts strict tenant isolation (a user from Lembaga A cannot attach or modify certifications for a Guru in Lembaga B).
- **`Tests\Feature\Admin\SiswaAccountGenerationTest` (NEW):**
  - Asserts `generateAkunMassal` correctly identifies students without `user_id`, generates authentic user records with `AkunSiswaGenerator`, and ignores existing account holders.
  - Asserts individual `generateAkun` successfully creates an account for an unassigned student and aborts if an account already exists.
- **`Tests\Feature\Admin\SiswaImportTest` (UPDATE/EXPAND):**
  - Asserts `preview()` returns username prediction strings in the view data.
  - Asserts downloaded template Excel contains accurate instruction headers and columns.

### 5.2 Full Test Suite Gate
- Command: `php artisan test`
- Criterion: 100% Pass Rate across all existing tests plus newly implemented feature specs.
