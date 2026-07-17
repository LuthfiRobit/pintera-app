# Redesign UI Foundation (token + komponen bersama) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti sistem desain lama (`ink`/`paper`/`slate`/`brass`, Plus Jakarta Sans, Material Symbols) dengan token & komponen baru bergaya TailAdmin (indigo untuk admin panel, navy untuk portal publik, font Outfit, ikon SVG inline) di lapisan fondasi — Tailwind config, komponen Blade bersama (`x-*`), dan dua shell layout (admin + portal) — tanpa mengubah satu pun halaman konten (Lembaga, Guru, Tagihan, dst.) yang menyusul di plan terpisah.

**Architecture:** Tambah token warna baru (`brand-*`, `portal-*`, `gray-*`, `success-*`, `error-*`, `warning-*`) berdampingan dengan token lama di `tailwind.config.js` — token lama TIDAK dihapus karena puluhan halaman yang belum di-migrasi masih memakainya. Komponen Blade bersama (`x-primary-button`, `x-badge`, dst.) ditulis ulang memakai token baru tapi **prop API-nya dipertahankan sama persis** (nama tone, prop, slot) supaya caller yang sudah ada tidak perlu diubah. Ikon Material Symbols (font ligature) diganti komponen `<x-icon name="..." />` yang me-render SVG inline, dengan nama `name` yang sama persis dengan nama Material Symbol lama (`apartment`, `school`, dst.) — jadi array/props pemanggil ikon tidak perlu diubah, hanya baris render-nya.

**Tech Stack:** Laravel 12 Blade components, Tailwind CSS 3, Alpine.js (sudah terpasang, tidak ada dependency baru).

## Di Luar Scope Plan Ini

`resources/views/layouts/guest.blade.php` (shell auth admin saat ini: kartu terpusat di atas gradient gelap) **sengaja tidak disentuh** di plan ini. Mockup autentikasi yang disetujui (split-screen: form kiri, panel merek kanan) mengubah struktur halaman, bukan cuma warna — mengerjakannya sekarang berarti menulis ulang `guest.blade.php` dua kali (sekali reskin token, sekali lagi restrukturisasi ke split-screen). Ditunda ke plan halaman auth tersendiri yang langsung menulis struktur final. Enam halaman auth (admin: sign in + reset password; portal: sign in, sign up, verifikasi OTP, reset password) dan `resources/views/auth/*.blade.php`, `resources/views/portal/auth/*.blade.php` semuanya masuk plan tersebut, bukan plan ini.

## Global Constraints

- Token warna diambil dari `demo.tailadmin.com/style.css` (lihat `docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md` §3) — jangan improvisasi hex baru di luar yang tercantum di situ.
- Token lama (`ink`, `paper`, `slate`, `brass`, `signal-red`, `signal-green`, `signal-amber`, `spmb-primary`, `spmb-accent`, `spmb-bg`, `spmb-tint`) TIDAK BOLEH dihapus dari `tailwind.config.js` di plan ini — masih dipakai puluhan file yang belum di-migrasi.
- Prop API komponen Blade yang sudah ada (`@props([...])`, nama slot) tidak boleh berubah kecuali disebutkan eksplisit di task — banyak caller di seluruh app bergantung padanya.
- Ikon baru pakai gaya stroke (Feather/Lucide): `viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"` kecuali disebutkan lain.
- Setiap task diakhiri commit terpisah.

---

## Task 1: Tailwind design tokens + font Outfit

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/views/layouts/app.blade.php:12` (font link)
- Modify: `resources/views/layouts/portal.blade.php:14` (font link)

**Interfaces:**
- Produces: Tailwind color classes `brand-50/100/300/500/600`, `portal-50/500/600`, `gray-50..900`, `success-50/500/600/700`, `error-50/500/600/700`, `warning-50/500/600/700`. `font-sans`/`font-display` now resolve to Outfit. `shadow-card`/`shadow-elevated` redefined. Semua task berikutnya bergantung pada nama-nama class ini persis seperti ditulis di sini.

- [ ] **Step 1: Tambah token warna baru & redefinisi shadow di `tailwind.config.js`**

Ganti isi `theme.extend` (baris 12-36) jadi:

```js
    theme: {
        extend: {
            colors: {
                // token lama — jangan dihapus, masih dipakai halaman yang belum di-migrasi
                ink: '#0F2547',
                paper: '#F7F9FC',
                slate: '#5B6478',
                brass: '#C9A227',
                'signal-red': '#C81E3A',
                'signal-green': '#1E8F63',
                'signal-amber': '#C9820F',
                'spmb-primary': '#1E3A8A',
                'spmb-accent': '#2563EB',
                'spmb-bg': '#F1F2FA',
                'spmb-tint': '#EFF4FF',

                // token baru — redesign TailAdmin (lihat docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md)
                brand: {
                    50: '#ECF3FF',
                    100: '#DDE9FF',
                    300: '#9CB9FF',
                    500: '#465FFF',
                    600: '#3641F5',
                },
                portal: {
                    50: '#E7EEF5',
                    500: '#1E3A5F',
                    600: '#16324F',
                },
                gray: {
                    50: '#F9FAFB',
                    100: '#F2F4F7',
                    200: '#E4E7EC',
                    300: '#D0D5DD',
                    400: '#98A2B3',
                    500: '#667085',
                    600: '#475467',
                    700: '#344054',
                    800: '#1D2939',
                    900: '#101828',
                },
                success: {
                    50: '#ECFDF3',
                    500: '#12B76A',
                    600: '#039855',
                    700: '#027A48',
                },
                error: {
                    50: '#FEF3F2',
                    500: '#F04438',
                    600: '#D92D20',
                    700: '#B42318',
                },
                warning: {
                    50: '#FFFAEB',
                    500: '#F79009',
                    600: '#DC6803',
                    700: '#B54708',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0px 1px 3px 0px rgba(16, 24, 40, 0.10)',
                elevated: '0 12px 28px rgba(16, 24, 40, 0.16)',
            },
        },
    },
```

- [ ] **Step 2: Ganti font link di `resources/views/layouts/app.blade.php`**

Baris 12 saat ini:
```html
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|inter:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
```
Ganti jadi:
```html
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
```
(Baris 13, link Material Symbols, JANGAN dihapus dulu — masih dipakai sidebar/topbar/stat-tile sampai Task 5.)

- [ ] **Step 3: Ganti font link di `resources/views/layouts/portal.blade.php`**

Baris 14 saat ini:
```html
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|inter:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
```
Ganti jadi:
```html
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
```

- [ ] **Step 4: Verifikasi build Tailwind tidak error**

Run: `npm run build`
Expected: build sukses (exit code 0), tidak ada warning "unknown utility class" untuk `brand-*`/`portal-*`/`gray-*`/`success-*`/`error-*`/`warning-*` di file yang sudah ada (karena belum ada file lain yang memakainya di task ini).

- [ ] **Step 5: Jalankan test suite penuh sebagai baseline**

Run: `php artisan test`
Expected: semua test PASS (perubahan di task ini murni token, tidak mengubah HTML/teks yang di-assert test manapun).

- [ ] **Step 6: Commit**

```bash
git add tailwind.config.js resources/views/layouts/app.blade.php resources/views/layouts/portal.blade.php
git commit -m "feat: add TailAdmin-derived design tokens (brand/portal/gray/success/error/warning) and swap to Outfit font"
```

---

## Task 2: Komponen ikon SVG (`x-icon`)

**Files:**
- Create: `resources/views/components/icon.blade.php`

**Interfaces:**
- Produces: `<x-icon name="{one of: menu, expand_more, dashboard, apartment, school, calendar_month, waves, signpost, quiz, fact_check, payments, receipt_long, group, groups, shield_person, check_circle, cancel, hourglass_empty, pending_actions}" class="h-5 w-5 ..." />`. Nama `name` sengaja sama persis dengan nama Material Symbol lama yang digantikannya (dipetakan di komentar tiap `@case`) — dipakai Task 3, 5, 6.

- [ ] **Step 1: Buat `resources/views/components/icon.blade.php`**

```blade
{{-- Ikon SVG inline, pengganti font Material Symbols. `name` = nama Material Symbol lama yang digantikan. --}}
@props(['name'])

@switch($name)
    @case('menu')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" {{ $attributes }}><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        @break

    @case('expand_more')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M6 9l6 6 6-6"/></svg>
        @break

    @case('dashboard')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        @break

    @case('apartment')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/></svg>
        @break

    @case('school')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M22 9 12 5 2 9l10 4 10-4Z"/><path d="M6 11v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>
        @break

    @case('calendar_month')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/></svg>
        @break

    @case('waves')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M2 12c1.5-2 3.5-2 5 0s3.5 2 5 0 3.5-2 5 0 3.5 2 5 0"/><path d="M2 18c1.5-2 3.5-2 5 0s3.5 2 5 0 3.5-2 5 0 3.5 2 5 0"/></svg>
        @break

    @case('signpost')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M12 3v4"/><path d="M4 7h9l2 2-2 2H4V7Z"/><path d="M12 12v9"/></svg>
        @break

    @case('quiz')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9a3 3 0 0 1 5.5-1.7c.6.8.5 2-.3 2.7l-1.7 1.5c-.4.3-.5.6-.5 1.2"/><path d="M12 16h.01"/></svg>
        @break

    @case('fact_check')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="6" y="3" width="12" height="4" rx="1"/><path d="M6 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1"/><path d="M9 13l2 2 4-4"/></svg>
        @break

    @case('payments')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="2" y="6" width="14" height="10" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M20 8v9a2 2 0 0 1-2 2H6"/></svg>
        @break

    @case('receipt_long')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M6 2h9l3 3v17l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5V2Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
        @break

    @case('group')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M17 8.5a3 3 0 1 1 0 5.8M21.5 20c0-2.8-1.9-4.9-4.5-5.6"/></svg>
        @break

    @case('groups')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="7" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M1 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M11 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
        @break

    @case('shield_person')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M12 2 4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5l-8-3Z"/><circle cx="12" cy="10" r="2.3"/><path d="M8.5 16c.7-2 2-3 3.5-3s2.8 1 3.5 3"/></svg>
        @break

    @case('check_circle')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.3 2.3L16 10"/></svg>
        @break

    @case('cancel')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
        @break

    @case('hourglass_empty')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M7 3h10M7 21h10"/><path d="M7 3c0 4.5 4 5.5 5 6-1 .5-5 1.5-5 6v6M17 3c0 4.5-4 5.5-5 6 1 .5 5 1.5 5 6v6"/></svg>
        @break

    @case('pending_actions')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="4" y="4" width="12" height="17" rx="2"/><path d="M7 8h6M7 12h4"/><circle cx="17.5" cy="16.5" r="4.5"/><path d="M17.5 14.5v2l1.3 1.3"/></svg>
        @break
@endswitch
```

- [ ] **Step 2: Verifikasi Blade compile tanpa error**

Run: `php artisan view:cache && php artisan view:clear`
Expected: kedua command sukses (exit code 0), tidak ada `Illuminate\View\ViewException` soal syntax `icon.blade.php`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/icon.blade.php
git commit -m "feat: add x-icon component with inline SVG icons replacing Material Symbols"
```

---

## Task 3: Restyle komponen aksi & tampilan (button, badge, input, stat-tile)

**Files:**
- Modify: `resources/views/components/primary-button.blade.php`
- Modify: `resources/views/components/secondary-button.blade.php`
- Modify: `resources/views/components/danger-button.blade.php`
- Modify: `resources/views/components/link-button.blade.php`
- Modify: `resources/views/components/badge.blade.php`
- Modify: `resources/views/components/input-label.blade.php`
- Modify: `resources/views/components/input-error.blade.php`
- Modify: `resources/views/components/text-input.blade.php`
- Modify: `resources/views/components/stat-tile.blade.php`

**Interfaces:**
- Consumes: `<x-icon name="..." class="..." />` dari Task 2.
- Produces: prop API SEMUA komponen ini tidak berubah (lihat tiap step) — dipakai ~25+ file caller yang sudah ada, jangan sampai ada yang perlu diubah.

- [ ] **Step 1: `primary-button.blade.php`**

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
```

- [ ] **Step 2: `secondary-button.blade.php`**

```blade
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
```

- [ ] **Step 3: `danger-button.blade.php`**

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-error-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-error-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
```

- [ ] **Step 4: `link-button.blade.php`**

```blade
@props(['variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-sm hover:bg-brand-600',
        'ghost' => 'border border-gray-200 text-gray-700 hover:bg-gray-50',
    ];
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition active:scale-[0.98] ' . ($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</a>
```

- [ ] **Step 5: `badge.blade.php`** (tone API tidak berubah: `brass`/`green`/`red`/`amber`/`blue`/`slate` — hanya warna di baliknya yang diganti token baru)

```blade
@props(['tone' => 'slate'])

@php
    $tones = [
        'brass' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-success-50 text-success-700',
        'red' => 'bg-error-50 text-error-700',
        'amber' => 'bg-warning-50 text-warning-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'slate' => 'bg-gray-100 text-gray-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
```

- [ ] **Step 6: `input-label.blade.php`**

```blade
@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
```

- [ ] **Step 7: `input-error.blade.php`**

```blade
@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm text-error-600']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
```

- [ ] **Step 8: `text-input.blade.php`**

```blade
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-50 disabled:text-gray-400']) }}>
```

- [ ] **Step 9: `stat-tile.blade.php`** (props tidak berubah: `label`, `value`, `hint`, `icon` — `icon` masih menerima nama Material Symbol lama, sekarang dirender lewat `<x-icon>`)

```blade
@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:shadow-elevated']) }}>
    <div class="flex items-center justify-between">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                <x-icon :name="$icon" class="h-[18px] w-[18px]" />
            </span>
        @endif
    </div>
    <p class="mt-2 font-display text-3xl font-bold text-gray-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
```

- [ ] **Step 10: Verifikasi Blade compile tanpa error**

Run: `php artisan view:cache && php artisan view:clear`
Expected: sukses, exit code 0.

- [ ] **Step 11: Jalankan test yang menyentuh komponen ini**

Run: `php artisan test --filter=DashboardTest`
Run: `php artisan test --filter=LembagaCrudTest`
Run: `php artisan test --filter=UserManagementTest`
Expected: semua PASS — komponen ini dipakai di halaman-halaman itu, test mengecek teks/route bukan class CSS jadi tidak boleh gagal.

- [ ] **Step 12: Jalankan test suite penuh**

Run: `php artisan test`
Expected: semua PASS.

- [ ] **Step 13: Commit**

```bash
git add resources/views/components/primary-button.blade.php resources/views/components/secondary-button.blade.php resources/views/components/danger-button.blade.php resources/views/components/link-button.blade.php resources/views/components/badge.blade.php resources/views/components/input-label.blade.php resources/views/components/input-error.blade.php resources/views/components/text-input.blade.php resources/views/components/stat-tile.blade.php
git commit -m "feat: restyle button/badge/input/stat-tile components to new design tokens"
```

---

## Task 4: Restyle komponen struktural (panel, dropdown, modal, toast)

**Files:**
- Modify: `resources/views/components/panel.blade.php`
- Modify: `resources/views/components/dropdown.blade.php`
- Modify: `resources/views/components/dropdown-link.blade.php`
- Modify: `resources/views/components/modal.blade.php`
- Modify: `resources/views/components/toast.blade.php`

**Interfaces:**
- Produces: prop API tidak berubah (`align`/`width`/`contentClasses` di dropdown, `name`/`show`/`maxWidth` di modal, slot-based di panel/toast).

- [ ] **Step 1: `panel.blade.php`**

```blade
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card']) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 2: `dropdown.blade.php`**

```blade
@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-2 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-2xl shadow-elevated {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-2xl border border-gray-200 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
```

- [ ] **Step 3: `dropdown-link.blade.php`**

```blade
<a {{ $attributes->merge(['class' => 'flex items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none']) }}>{{ $slot }}</a>
```

- [ ] **Step 4: `modal.blade.php`** — satu-satunya baris yang memuat token lama adalah overlay; sisanya (`x-data`, event listener, panel `shadow-elevated`) tidak diubah karena `shadow-elevated` otomatis memakai nilai baru dari Task 1 tanpa perlu diedit

Ganti baris `<div class="absolute inset-0 bg-ink/60"></div>` jadi:
```blade
        <div class="absolute inset-0 bg-gray-900/60"></div>
```

- [ ] **Step 5: `toast.blade.php`**

```blade
<div
    x-data
    class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2 sm:right-6 sm:top-6"
>
    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-white p-4 shadow-elevated"
            :class="toast.type === 'success' ? 'border-success-500/20' : 'border-error-500/20'"
        >
            <span
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                :class="toast.type === 'success' ? 'bg-success-50 text-success-700' : 'bg-error-50 text-error-700'"
                x-text="toast.type === 'success' ? '✓' : '✕'"
            ></span>
            <p class="flex-1 text-sm text-gray-900" x-text="toast.message"></p>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="$store.toast.remove(toast.id)">
                <span class="text-sm">✕</span>
            </button>
        </div>
    </template>
</div>
```

- [ ] **Step 6: Verifikasi Blade compile tanpa error**

Run: `php artisan view:cache && php artisan view:clear`
Expected: sukses, exit code 0.

- [ ] **Step 7: Jalankan test suite penuh**

Run: `php artisan test`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/panel.blade.php resources/views/components/dropdown.blade.php resources/views/components/dropdown-link.blade.php resources/views/components/modal.blade.php resources/views/components/toast.blade.php
git commit -m "feat: restyle panel/dropdown/modal/toast components to new design tokens"
```

---

## Task 5: Restyle admin shell (app.blade.php + sidebar + topbar)

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Modify: `resources/views/layouts/topbar.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `<x-icon>` (Task 2), `brand-*`/`gray-*` tokens (Task 1).
- Produces: shell admin final — semua halaman admin (`@extends`/`<x-app-layout>` lewat `app.blade.php`) otomatis ikut tampilan baru tanpa diubah satu-satu.

- [ ] **Step 1: `app.blade.php`** — hapus link Material Symbols (baris 13), ganti `bg-paper`→`bg-gray-50`, `text-ink`→`text-gray-900`, `border-ink/10`→`border-gray-200`, `bg-white/60`→`bg-white/70`

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased text-gray-900">
        <div x-data="{ sidebarOpen: false }" class="min-h-full bg-gray-50 lg:flex">
            <x-toast />

            @include('layouts.sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.topbar')

                @isset($header)
                    <header class="border-b border-gray-200 bg-white/70">
                        <div class="px-4 py-6 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 2: `sidebar.blade.php`** — `$navGroups` array TIDAK berubah, hanya bagian render (warna putih, active state indigo, ikon SVG)

```blade
@php
    $navGroups = [
        [
            'label' => 'I. Ringkasan',
            'items' => [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'II. Data Induk',
            'items' => array_filter([
                Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'apartment'] : null,
                Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'school'] : null,
                Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar_month'] : null,
            ]),
        ],
        [
            'label' => 'III. SPMB',
            'items' => array_filter([
                Auth::user()->can('gelombang-ppdb.view') ? ['route' => 'admin.gelombang-ppdb.index', 'pattern' => 'admin.gelombang-ppdb.*', 'label' => 'Gelombang PPDB', 'icon' => 'waves'] : null,
                Auth::user()->can('jalur-ppdb.view') ? ['route' => 'admin.jalur-ppdb.index', 'pattern' => 'admin.jalur-ppdb.*', 'label' => 'Jalur PPDB', 'icon' => 'signpost'] : null,
                Auth::user()->can('jenis-tes.view') ? ['route' => 'admin.jenis-tes.index', 'pattern' => 'admin.jenis-tes.*', 'label' => 'Jenis Tes', 'icon' => 'quiz'] : null,
                Auth::user()->can('spmb-pendaftaran.view') ? ['route' => 'admin.spmb-pendaftaran.index', 'pattern' => 'admin.spmb-pendaftaran.*', 'label' => 'Verifikasi & Keputusan', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'IV. Keuangan',
            'items' => array_filter([
                Auth::user()->can('jenis-tagihan.view') ? ['route' => 'admin.jenis-tagihan.index', 'pattern' => 'admin.jenis-tagihan.*', 'label' => 'Jenis Tagihan', 'icon' => 'payments'] : null,
                Auth::user()->can('tagihan.view') && Route::has('admin.tagihan.index') ? ['route' => 'admin.tagihan.index', 'pattern' => 'admin.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt_long'] : null,
                Auth::user()->can('pembayaran.view') ? ['route' => 'admin.pembayaran.index', 'pattern' => 'admin.pembayaran.*', 'label' => 'Verifikasi Pembayaran', 'icon' => 'fact_check'] : null,
            ]),
        ],
        [
            'label' => 'V. Akses & Peran',
            'items' => array_filter([
                Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'group'] : null,
                Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'shield_person'] : null,
            ]),
        ],
    ];
@endphp

<!-- Mobile scrim -->
<div
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-gray-200 px-6">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 font-display text-lg font-bold text-white">
            {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
        </span>
        <div class="leading-tight">
            <p class="font-display text-base font-bold text-gray-900">{{ config('app.name', 'Pintera') }}</p>
            <p class="text-[11px] uppercase tracking-[0.14em] text-gray-400">Sistem Administrasi</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-4 py-6">
        @foreach ($navGroups as $group)
            @if (count($group['items']))
                <div class="mb-7">
                    <p class="mb-2 px-2 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                        {{ $group['label'] }}
                    </p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = request()->routeIs($item['pattern']); @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                                        {{ $active ? 'bg-brand-50 font-semibold text-brand-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                                >
                                    <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand-500' : 'text-gray-400 group-hover:text-gray-500' }}" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-gray-200 px-6 py-4">
        <p class="text-[11px] leading-relaxed text-gray-400">
            &copy; {{ now()->year }} {{ config('app.name') }}. Sistem administrasi internal.
        </p>
    </div>
</aside>
```

- [ ] **Step 3: `topbar.blade.php`**

```blade
@php
    $isYayasan = Auth::user()->widestScopeLevel() === 'yayasan';
    $activeLembagaId = session('active_lembaga_id');
    $lembagaOptions = $isYayasan ? once(fn () => \App\Models\Lembaga::query()->select('id', 'nama')->orderBy('nama')->get()) : collect();
    $activeLembaga = $activeLembagaId ? $lembagaOptions->firstWhere('id', $activeLembagaId) : null;
    $sealLabel = $activeLembaga ? Str::of($activeLembaga->nama)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : 'YY';
@endphp

<header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-gray-200 bg-white/70 px-4 backdrop-blur-md sm:px-6 lg:px-10">
    <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 lg:hidden" aria-label="Buka menu">
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    <div class="min-w-0 flex-1"></div>

    <div class="flex items-center gap-3 sm:gap-5">
        @if ($isYayasan)
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    class="flex items-center gap-2.5 rounded-full border border-brand-100 bg-brand-50 py-1 pl-1 pr-3 transition hover:bg-brand-100"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-brand-300 font-display text-xs font-bold uppercase text-brand-600">
                        {{ $sealLabel }}
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[10px] uppercase tracking-[0.12em] text-gray-400">Meninjau</span>
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-gray-900">{{ $activeLembaga->nama ?? 'Semua Lembaga' }}</span>
                    </span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>

                <div
                    x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 z-30 mt-2 w-64 rounded-2xl border border-gray-200 bg-white py-2 shadow-elevated"
                    style="display: none;"
                >
                    <p class="px-4 pb-2 pt-1 font-display text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400">Registrasi Lembaga</p>
                    <a
                        href="{{ request()->fullUrlWithQuery(['switch_lembaga' => 'all']) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ ! $activeLembaga ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                    >
                        Semua Lembaga
                        @if (! $activeLembaga)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                    </a>
                    <div class="my-1 border-t border-gray-200"></div>
                    @foreach ($lembagaOptions as $option)
                        <a
                            href="{{ request()->fullUrlWithQuery(['switch_lembaga' => $option->id]) }}"
                            class="flex items-center justify-between px-4 py-2 text-sm {{ $activeLembagaId === $option->id ? 'font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $option->nama }}
                            @if ($activeLembagaId === $option->id)<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm text-gray-900 transition hover:bg-gray-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 font-display text-xs font-semibold text-white">
                        {{ Str::of(Auth::user()->name)->substr(0, 1) }}
                    </span>
                    <span class="hidden max-w-[8rem] truncate font-medium sm:block">{{ Auth::user()->name }}</span>
                    <x-icon name="expand_more" class="h-[18px] w-[18px] text-gray-500" />
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">{{ __('Profil') }}</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Keluar') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
```

- [ ] **Step 4: `resources/css/app.css`** — hapus rule `.material-symbols-outlined` (sudah tidak dipakai file manapun di admin shell setelah step di atas)

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] {
    display: none !important;
}
```

- [ ] **Step 5: Hapus link Material Symbols dari `app.blade.php`**

Hapus baris ini (baris 13 sebelum Step 1 di atas, sudah tidak ada di kode pengganti Step 1 — pastikan memang sudah tidak ada):
```html
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300..600,0..1&display=swap" rel="stylesheet" />
```

- [ ] **Step 6: Verifikasi Blade compile & build**

Run: `php artisan view:cache && php artisan view:clear`
Run: `npm run build`
Expected: keduanya sukses, exit code 0.

- [ ] **Step 7: Jalankan test yang menyentuh sidebar/topbar**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS — test ini secara spesifik mengecek teks "Dashboard Guru", "SD Pintera Switcher", dan query string `switch_lembaga=` yang di-generate topbar, jadi kalau lolos berarti markup baru masih benar secara fungsional.

Run: `php artisan test`
Expected: semua PASS.

- [ ] **Step 8: Verifikasi visual manual**

Run: `composer dev` (menjalankan server + vite sekaligus, sesuai script yang sudah ada di `composer.json`)
Buka browser ke `http://localhost:8000/login`, login dengan `superadmin@sistem.test` / `password` (akun dari `EssentialUserSeeder`).
Expected: sidebar putih dengan item aktif berwarna indigo, ikon SVG tampil (bukan teks nama ikon mentah), dropdown profil pojok kanan atas terbuka dengan gaya baru.

- [ ] **Step 9: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/layouts/sidebar.blade.php resources/views/layouts/topbar.blade.php resources/css/app.css
git commit -m "feat: restyle admin shell (sidebar/topbar) to indigo TailAdmin look, remove Material Symbols"
```

---

## Task 6: Restyle portal shell (layouts/portal.blade.php, navy)

**Files:**
- Modify: `resources/views/layouts/portal.blade.php`

**Interfaces:**
- Consumes: `<x-icon>` (Task 2), `portal-*`/`gray-*` tokens (Task 1).

- [ ] **Step 1: Tulis ulang `portal.blade.php`**

```blade
{{-- resources/views/layouts/portal.blade.php --}}
@props(['title' => 'Portal Pendaftar'])

<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-gray-50 font-sans text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"
            style="display: none;"
        ></div>

        <div class="flex min-h-full">
            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-portal-500 transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 font-display text-lg font-bold text-white">
                        {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
                    </span>
                    <p class="font-display text-base font-bold text-white">Portal Pendaftar</p>
                </div>

                <nav class="flex-1 overflow-y-auto px-4 py-6">
                    <a
                        href="{{ route('portal.dashboard') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('portal.dashboard') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <x-icon name="dashboard" class="h-5 w-5" />
                        Dashboard
                    </a>
                    <a
                        href="{{ route('portal.tagihan.index') }}"
                        class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('portal.tagihan.*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <x-icon name="receipt_long" class="h-5 w-5" />
                        Tagihan & Pembayaran
                    </a>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-gray-200 bg-white/70 px-4 backdrop-blur-md sm:px-6">
                    <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 lg:hidden" aria-label="Buka menu">
                        <x-icon name="menu" class="h-5 w-5" />
                    </button>
                    <div class="min-w-0 flex-1"></div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-900">{{ auth('portal')->user()->nama }}</span>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-gray-500 hover:text-gray-900">Keluar</button>
                        </form>
                    </div>
                </header>

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>

                <footer class="px-4 py-6 text-center text-xs text-gray-500 sm:px-6">
                    &copy; {{ now()->year }} {{ config('app.name') }}
                </footer>
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 2: Verifikasi Blade compile tanpa error**

Run: `php artisan view:cache && php artisan view:clear`
Expected: sukses, exit code 0.

- [ ] **Step 3: Jalankan test portal**

Run: `php artisan test --filter=Portal`
Expected: semua PASS (mencakup `Portal\DashboardTest`, `Portal\LoginTest`, `Portal\PortalAuthPagesRenderTest`, dll — semua mengecek teks/route, bukan class CSS).

Run: `php artisan test`
Expected: semua PASS.

- [ ] **Step 4: Verifikasi visual manual**

Dengan `composer dev` masih jalan dari Task 5, buka `http://localhost:8000/portal/login`, login dengan `pendaftar.smp@example.test` / `password` (akun dari `AkunPendaftarSeeder`).
Expected: sidebar navy (bukan lagi biru `spmb-primary` lama), ikon Dashboard & Tagihan tampil sebagai SVG (sebelumnya ikon di layout ini rusak karena `portal.blade.php` tidak pernah memuat font Material Symbols — jadi ini sekaligus perbaikan bug lama).

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/portal.blade.php
git commit -m "feat: restyle portal shell to navy design tokens, fix missing Material Symbols font in portal layout"
```

---

## Task 7: Verifikasi akhir fondasi

**Files:** (tidak ada file baru — task ini murni verifikasi menyeluruh)

- [ ] **Step 1: Jalankan test suite penuh**

Run: `php artisan test`
Expected: semua test PASS, 0 failure.

- [ ] **Step 2: Build production assets**

Run: `npm run build`
Expected: build sukses tanpa warning "content" Tailwind yang hilang.

- [ ] **Step 3: Verifikasi visual — 3 halaman representatif**

Dengan `composer dev` jalan:
1. `/dashboard` (login `superadmin@sistem.test` / `password`) — sidebar putih indigo, stat card pakai ikon SVG baru, dropdown profil & lembaga-switcher bergaya popover baru.
2. `/admin/lembaga` (halaman index yang BELUM di-restyle kontennya) — pastikan halaman tetap tampil (tidak pecah), meski isinya (tabel, tombol) masih pakai token lama sampai plan lanjutan mengerjakannya.
3. `/portal/dashboard` (login `pendaftar.smp@example.test` / `password`) — sidebar navy, ikon SVG tampil.

Expected: ketiganya render tanpa error 500, tanpa elemen tak bergaya (unstyled), tanpa teks nama ikon mentah (`dashboard`, `apartment`, dst. muncul sebagai teks).

- [ ] **Step 4: Catat status di spec**

Update `docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md` baris `**Status:**` jadi:
```
**Status:** Fondasi (token + komponen bersama) selesai diimplementasi — lihat docs/superpowers/plans/2026-07-17-redesign-ui-foundation.md. Rollout per halaman menyusul di plan terpisah.
```

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md
git commit -m "docs: mark UI redesign foundation as implemented"
```
