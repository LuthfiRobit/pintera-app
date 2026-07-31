# Welcome Index Portal Cards — Design Specification

**Date:** 2026-08-01  
**Author:** Antigravity (Lead Code Architect & UI/UX Specialist)  
**Status:** Approved by User  
**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, SVG Vector Icons (Phosphor/Heroicons inspired)

---

## 1. Overview & Purpose

Transform the default Laravel welcome page at `resources/views/welcome.blade.php` into an executive, premium-grade **Pintera Integrated Portal Landing Page**. The page guides school ecosystem stakeholders (Yayasan, Admin/TU, Teachers, and Students) to their appropriate functional domain while maintaining a universal centralized authentication entrance (`route('login')`).

---

## 2. Visual Design System & Aesthetics (Approach 1: Modern Glassmorphism)

- **Design Framework:** Follows `ui-styling` and `ui-ux-pro-max` guidelines for modern mobile and web interfaces.
- **Background:** Subtle radial ambient glow / mesh gradient over clean off-white (`#FDFDFC`) in light mode and deep near-black (`#0a0a0a`) in dark mode.
- **Surface & Cards:** Glassmorphic surfaces using `backdrop-blur-md`, subtle inner white opacity border (`border border-white/10 dark:border-white/5`), and elevated drop shadows.
- **Interactions & Motion:**
  - Card hover micro-animations (`hover:-translate-y-1 hover:shadow-2xl transition-all duration-300`).
  - Clear pressed visual states (`active:translate-y-0 active:scale-[0.99]`).
  - No layout-shifting bounds or jitter during transitions.
- **Typography:** Instrument Sans / modern clean geometric sans-serif font family with strict hierarchical font sizes and readable line heights.

---

## 3. Responsive Grid Architecture (Mobile-First)

The main 4-card interactive selection grid follows standard mobile-first Tailwind breakpoints:
- **Mobile (`< 768px`):** 1 vertical column (`grid grid-cols-1 gap-6`)
- **Tablet (`768px - 1280px`):** 2 columns × 2 rows (`md:grid-cols-2 gap-6`)
- **Widescreen Desktop (`≥ 1280px`):** 4 side-by-side columns (`xl:grid-cols-4 gap-6`)

---

## 4. Portal Card Data Mapping & Content

Every card includes a dedicated SVG vector icon, a tailored color theme (badge, icon tint, hover border glow), clear functional copy, and an interactive call-to-action button linking directly to the centralized login endpoint (`route('login')`).

| Portal | Vector Icon Theme | Accent Color Tokens | Headline & Role Description | CTA Button Label | Target URL |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Portal Yayasan** | 🏛️ Classical Columns / Structure | `emerald` (`text-emerald-500`, `bg-emerald-500/10`, `border-emerald-500/20`, `group-hover:border-emerald-500/50`) | **Yayasan & Eksekutif**<br>Monitoring kinerja, evaluasi KPI, & ringkasan finansial lembaga. | **Masuk Yayasan &rarr;** | `route('login')` |
| **Portal Admin** | ⚙️ Shield / Gear Controls | `blue` (`text-blue-500`, `bg-blue-500/10`, `border-blue-500/20`, `group-hover:border-blue-500/50`) | **Admin & Tata Usaha**<br>Manajemen data sekolah, kepegawaian, tagihan & konfigurasi SPMB. | **Masuk Admin &rarr;** | `route('login')` |
| **Portal Guru** | 📚 Academic Book / Educator | `amber` (`text-amber-500`, `bg-amber-500/10`, `border-amber-500/20`, `group-hover:border-amber-500/50`) | **Guru & Tenaga Didik**<br>Pengisian presensi biometrik, penyusunan RPP, & pencatatan nilai. | **Masuk Guru &rarr;** | `route('login')` |
| **Portal Siswa** | 🎓 Graduation Cap / Learner | `purple` (`text-purple-500`, `bg-purple-500/10`, `border-purple-500/20`, `group-hover:border-purple-500/50`) | **Siswa & Wali Murid**<br>Akses kalender akademik, lihat tagihan sekolah, & e-rapor. | **Masuk Siswa &rarr;** | `route('login')` |

---

## 5. Accessibility & WCAG Compliance

- **Color Contrast:** All body text meets or exceeds WCAG AA (≥ 4.5:1) in both Light and Dark themes. Accent badges use shaded text (`text-emerald-700 dark:text-emerald-400` where needed) to ensure readability against tinted backgrounds.
- **Touch Target Dimensions:** All card call-to-action touch areas and nav links exceed 44×44pt.
- **Semantic Structure:** Utilizes standard HTML5 semantic landmarks (`<header>`, `<main>`, `<section>`, `<footer>`).
- **Screen Reader Support:** Interactive links include clean `aria-label` attributes describing the portal target.

---

## 6. Verification & Testing Plan

1. **Automated Feature Test:**
   - Verify that accessing the root path `/` renders the new `welcome` view with HTTP status `200 OK`.
   - Assert that the response text contains all 4 portal headlines ("Portal Yayasan", "Portal Admin", "Portal Guru", "Portal Siswa") and links to `route('login')`.
2. **Visual Verification:**
   - Verify rendering across desktop, tablet, and mobile simulated widths without horizontal overflow.
