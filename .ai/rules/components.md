---
paths:
  - 'resources/views/components/**'
---

# Components

## Extract the scope badge into a real component
Backlog: the isYayasan/activeLembaga scope badge (brand-colored lembaga name, or purple "Semua Lembaga") is duplicated verbatim across 15+ Blade files (Karyawan, RPP, Komponen Penilaian, Tahun Ajaran, Guru, etc). Next time you touch one of these, extract to `<x-scope-badge :is-yayasan="$isYayasan" :active-lembaga="$activeLembaga" />` instead of copy-pasting the markup again.

## Extract the KPI stat card into a real component
Backlog: the "icon in colored box + big number + label" KPI stat card is duplicated across 6+ files (Guru Asesmen, Guru Jurnal KBM, Komponen Penilaian, Rapor, Kasus akses-log, Kasus terhapus). Next time you touch one, extract to `<x-stat-card icon="..." color="..." value="..." label="..." />` instead of copy-pasting the markup again.

## Extract the status-colored progress bar into a real component
Backlog: the percentage progress bar with status-driven color (green/amber/red) is duplicated across 3+ files (Guru Asesmen, Jadwal Pelajaran, Komponen Penilaian). Next time you touch one, extract to `<x-progress-bar :value="$percent" />` with the color derived internally instead of copy-pasting the conditional classes again.
