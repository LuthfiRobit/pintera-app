# Spec: Deep Scan & Architectural Assessment (Pintera App)

## Tujuan
Melakukan audit dan asesmen arsitektural mendalam pada codebase `pintera-app` (direktori `app/`, `routes/`, `resources/`, `config/`, dan `database/migrations/`) untuk menjawab:
1. Status kelayakan koeksistensi arsitektur domain (`app/Domains/`) berdampingan dengan kode legacy.
2. Gap analysis keamanan multi-tenancy dan kesiapan Spatie Permissions multi-team.
3. Matriks prioritas perombakan dan arsitektur folder bridge penghubung antara legacy dan domain baru.

## Scope
- **In-Scope**:
  - Scanning dan audit statis struktur direktori, namespacing PSR-4, routing, controller, service, middleware, model Eloquent, traits, dan skema database.
  - Identifikasi potensi bug fatal, celah query IDOR, dan kebocoran data multi-tenant.
  - Analisis kompatibilitas Spatie Permissions terhadap konteks multi-team/scope.
  - Pembuatan laporan audit lengkap dan matriks prioritas di `docs/deep_scan_architectural_assessment.md`.
- **Out-of-Scope**:
  - Modifikasi atau pemindahan file sumber kode aplikasi (sesuai instruksi eksplisit user: *hanya lakukan analisis & laporan audit*).

## Acceptance Criteria
1. Laporan audit tersimpan lengkap di `docs/deep_scan_architectural_assessment.md`.
2. Analisis koeksistensi menjawab kelayakan `app/Domains/` tanpa merusak fitur lama.
3. Analisis celah multi-tenant menyajikan bukti kode nyata dan model-model yang belum terproteksi.
4. Analisis Spatie Permission menjelaskan status `'teams' => false` dan dampaknya.
5. Matriks prioritas memetakan bagian yang wajib segera diperbaiki (P0) vs yang dapat ditunda (P1/P2), serta arsitektur folder bridge `app/Domains/Shared/`.
