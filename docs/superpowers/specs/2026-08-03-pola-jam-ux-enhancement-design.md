# Pola Jam Pro-Max UX Enhancements Design Specification
Date: 2026-08-03
Status: Approved & Validated (Superpowers Brainstorming)

## 1. Overview
This feature introduces high-leverage UX enhancements to the **Pola Jam & Jam Pelajaran** academic scheduling module within Pintera App:
1. **1-Click Duplication (Clone)**: Rapidly clone time schedule structures across academic years without repetitive manual data entry.
2. **Preventive Conflict Transparency Badge**: Instantly warn administrators during class assignment if a class is already linked to another schedule pattern.
3. **Weekly Timetable Matrix Toggle View**: Provide an elegant, grid-based weekly timetable matrix previewing Monday through Saturday side-by-side alongside the standard daily cards view.

---

## 2. Technical Architecture & Data Flow

### 2.1 Backend Duplication Endpoint
- **Route**: `POST /admin/pola-jam/{polaJam}/duplicate` mapped to `PolaJamController@duplicate` under `admin.php` routing protected by tenant scope.
- **Authorization**: Validates user has `pola-jam.create` authorization.
- **Database Transaction**:
  - Encapsulated within `DB::transaction()` to ensure atomicity.
  - Replicates the target `PolaJam` with name `"{$polaJam->nama} (Salinan)"` and existing `lembaga_id`.
  - Iterates over all child `JamPelajaran` records associated with the original pattern and creates matching copies for the new pattern ID.
  - Explicitly **does not copy** `Kelas` bindings (classes remain bound exclusively to their original schedule).
  - Redirects to index view with a status toast reporting successful cloning and total slots copied.

### 2.2 Eager-Loaded Relational Querying
- In `PolaJamController@index`, update class selection list query:
  ```php
  'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get()
  ```
- This ensures zero N+1 database querying when rendering current pattern link names in the Smart Assign modal.

---

## 3. UI/UX & Component Design

### 3.1 Card Actions & Clone Button (`index.blade.php`)
- In the top action bar of each Pola Jam card, append a dedicated duplicate button adjacent to *Edit Nama* and *Hapus*:
  - Styled with brand tinted outline (`border-brand-200 bg-brand-50/30 text-brand-700`).
  - Utilizes standard form POST submission to trigger duplication instantly.

### 3.2 Preventive Conflict Detection Badge (`_modal-assign-kelas.blade.php`)
- In the class assignment checkbox loop, dynamically display an inline amber warning badge if the class belongs to a different schedule pattern:
  ```html
  <span x-show="formAssign.polaId !== {{ $kelasOpsi->pola_jam_id ?? 'null' }}" class="mt-0.5 block text-[11px] text-amber-600 font-semibold flex items-center gap-1">
      <x-icon name="warning" class="h-3 w-3 text-amber-500 shrink-0" />
      <span>Tertaut di: {{ $kelasOpsi->polaJam->nama ?? '' }}</span>
  </span>
  ```
- Checkboxes remain interactive (*overwrite allowed*), allowing administrators to explicitly migrate classes between schedule patterns while fully informed of prior bindings.

### 3.3 Interactive Timetable Matrix View (`index.blade.php`)
- **State Switcher**: Wrap the time slots container in local Alpine reactivity: `x-data="{ viewMode: 'list' }"`.
- **Segmented Control**: Display a dual-tab chip controller (*Kartu Harian* vs *Matriks Mingguan*) in the section header.
- **Matrix Grid Construction (Blade Computed)**:
  - Collect unique period sequence numbers (`urutan`) across all days in the current pattern:
    `@php $allUrutan = $pola->jamPelajaran->pluck('urutan')->unique()->sort(); @endphp`
  - Render a responsive HTML matrix table with 7 columns: `Jam Ke- / Waktu`, `Senin`, `Selasa`, `Rabu`, `Kamis`, `Jum'at`, and `Sabtu`.
  - Iterate rows by period `$urutan`, querying matching day slots in memory using `$pola->jamPelajaran->where('hari.value', $hari)->where('urutan', $u)->first()`.
  - Cells display styled badge chips representing lesson labels (green/brand for regular lesson periods, amber/orange bold for break/ceremony non-lesson intervals) along with quick edit trigger buttons (`openEditSlot()`).
  - Empty slots display a minimalist placeholder dash (`-`).

---

## 4. Automated Testing Strategy (TDD)

### 4.1 Feature Asserts in `PolaJamCrudTest.php`
1. **Duplication Test (`it duplicates a pola jam along with all its jam pelajaran slots without copying kelas bindings`)**:
   - Create parent pattern with two lesson slots and one linked class.
   - Perform POST to duplicate route as authorized academic administrator.
   - Assert database count for patterns increased by 1 with suffix `(Salinan)`.
   - Assert cloned pattern has identical lesson slot count and attributes but zero linked classes.
2. **Tenant Isolation Test (`it rejects duplicating another lembaga's pola jam with 404`)**:
   - Attempt duplicating pattern owned by Lembaga B while authenticated under Lembaga A.
   - Assert HTTP 404 Not Found response and zero duplicate records created.
3. **Eager Loading Conflict Badge Test (`it displays eager loaded conflict schedule names without extra queries`)**:
   - Assert index endpoint renders existing assigned pattern names cleanly within class modal HTML output.

---

## 5. Self-Review Validation
- **Placeholders & TBDs**: None. All logic, routes, variables, and UI behaviors are explicitly fully described.
- **Internal Consistency**: Verified that `duplicate` strictly preserves single class-pattern relationships without triggering database uniqueness violations or multi-pattern conflicts.
- **Scope & Complexity**: Focused exclusively within the Pola Jam academic scheduling module; ideal for a cohesive implementation cycle.
