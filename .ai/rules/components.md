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

## Align &lt;x-select&gt; styling to the Komponen Penilaian index look, then adopt it everywhere
Backlog: `<x-select>` already exists but 63+ files bypass it with hand-copied Tailwind classes (including all selects/TomSelect fields on the Komponen Penilaian pages) — `$attributes->merge()` already forwards x-ref/x-init/etc, so there's no technical reason not to use it. User explicitly prefers the visual style used on the Komponen Penilaian index page (`rounded-lg border-gray-200 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500` — thin single-width focus ring, no hover border change) over `<x-select>`'s current heavier look (`focus:ring-4 ring-brand-500/20`, `hover:border-gray-300`). When doing this consolidation, update `<x-select>`'s own classes to match the preferred look first, then migrate the 63+ files to use it — don't push the preferred page toward the component's current style.
