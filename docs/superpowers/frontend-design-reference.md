# Frontend Design Reference

Source: https://github.com/anthropics/skills/blob/main/skills/frontend-design/SKILL.md — read before any task that writes or edits a Blade view, so the admin panels don't default to a generic look.

## Core Philosophy
Ground design decisions in the subject matter itself. The aesthetic should emerge from the product's world (a yayasan/school administration system: enrollment, tuition, staff records) rather than generic dashboard templates.

## Design Principles
- **Hero/opening statement**: open each screen with the most characteristic thing in its world, not a generic "large number + small label" stat tile unless that's genuinely the right fit.
- **Typography as personality**: pair a display and body typeface deliberately; establish a clear type scale with intentional weights/spacing.
- **Structure encodes meaning**: numbering, labels, dividers must convey real information (e.g., semester 1/2 ordering, tahun ajaran chronology) — not decoration.
- **Motion strategy**: use animation only where it serves the task (e.g., a status change confirmation), not scattered effects.
- **Complexity matches vision**: for an administrative tool, precision in spacing/type/alignment matters more than maximalism.

## Color & Palette
Use a 4-6 named hex value system for this project. Avoid the three AI-default palettes unless specifically fitting:
- Warm cream (#F4F1EA) + high-contrast serif + terracotta accent
- Near-black + acid-green or vermilion accent
- Newspaper broadsheet style with hairlines and zero border-radius

## Typography
- Display face: characterful, used with restraint (e.g., section headers, dashboard titles)
- Body face: complementary, used for tables/forms
- Utility face (optional): for captions, table metadata, timestamps

## Spacing & Layout
- Sketch an ASCII wireframe before building a nontrivial screen (dashboard, role builder).
- Avoid CSS specificity conflicts (don't stack `.section` class rules with element-based margin/padding selectors that cancel each other).

## Copy & Content
- Write from the acting user's perspective (Admin Administrasi, Kepala Sekolah, etc.), not generic "user."
- Active voice, plain verbs, sentence case, no filler.
- Name actions by what the user recognizes/controls: "Aktifkan Tahun Ajaran," not "Submit."
- Keep the same action name throughout a flow (e.g., "Aktifkan" → "Diaktifkan," not "Activate" → "Enabled").
- Error messages explain what happened without apologizing; empty states invite the next action.

## Process for Each Nontrivial Screen
1. **Brainstorm**: compact token system (color, type, layout, one signature element).
2. **Critique against the brief**: would this same choice work for an unrelated product? If yes, it's generic — revise.
3. **Build**: only after confirming the direction is distinctive, implement precisely.

## Restraint
"Spend your boldness in one place" — pick one signature element per screen (e.g., the lembaga switcher, the role scope badge) and keep the rest disciplined. Baseline quality bar regardless of style: responsive down to mobile, visible keyboard focus, respects reduced-motion.

## Project Token System (binding for every M0 Blade view)

Defined once here so every task's screens read as one system instead of each
task inventing its own palette. Deliberately **not** cream+serif+terracotta,
not near-black+acid accent, not zero-radius newspaper style.

**Colors** (add to `tailwind.config.js` under `theme.extend.colors`):
- `ink` — `#1E2A38` (deep slate-navy — primary text, headers, primary buttons)
- `paper` — `#F7F7F5` (cool off-white page background, not warm cream)
- `slate` — `#5B6B7A` (secondary text, borders, muted labels)
- `brass` — `#B08D4C` (institutional accent — active/aktif states, the one
  signature color; used sparingly: status badges, the lembaga-switcher
  highlight, active-semester marker)
- `signal-red` — `#B3261E` (errors, destructive actions only)
- `signal-green` — `#2E6B4F` (success/lunas/aktif confirmations)

**Typography** (add via `next/font`-style `<link>` or self-hosted, then
register in `tailwind.config.js` under `theme.extend.fontFamily`):
- Display face (`font-display`): "Fraunces" (serif with character, used only
  for page/section titles — `<h1>`/`<h2>`, dashboard headers) — restrained,
  not used for body copy or table data
- Body face (`font-sans`, default): "Inter" — forms, tables, nav, buttons
- Numeric/utility face: `font-mono` (Tailwind's default mono stack) for
  NPSN/NIK/kode Dapodik values and timestamps, so identifiers are visually
  distinct from prose

**Signature element:** the lembaga switcher (yayasan-scope dashboard) and
active-semester/tahun-ajaran badges are this system's one deliberately bold
moment — `brass` background, `font-display` for the lembaga name. Every
other control (buttons, inputs, tables) stays quiet: `ink`/`paper`/`slate`
only, no gradients, no drop shadows beyond Tailwind's default `shadow`.

**Concrete replacement rule for every task below:** wherever the plan's
Blade code shows `bg-indigo-600`/`text-gray-800`/`bg-green-100`-style
default Tailwind palette classes, replace with `bg-ink`, `text-ink`,
`bg-brass`, `text-slate`, `bg-signal-green/10 text-signal-green`, etc., and
apply `font-display` to page `<h2>` headers. Keep every route, field name,
form field, and behavior exactly as specified — only the visual classes and
font assignment change.
