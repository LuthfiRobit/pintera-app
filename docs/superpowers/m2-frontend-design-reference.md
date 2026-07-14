# M2 — SPMB Portal Publik: Frontend Design Reference

Companion to `docs/superpowers/frontend-design-reference.md` (the admin panel's reference). Read both before writing any M2 Blade view. **Same token system as admin** — the public portal must look like it belongs to the same product, not a separate "consumer" skin.

## Source

Structural/UX patterns adapted from an existing Stitch project in this account ("Portal Pendaftaran Siswa Baru," `projects/8851196390795009250`) — its own color palette (Professional Blue #1E40AF) is **not** used; only its component ideas are. Re-themed entirely onto the admin panel's actual current tokens below.

## Token System — reuse exactly, do not invent new colors

From the live `tailwind.config.js` (not the stale values in the admin reference doc — verify against the actual file before building):
- `ink` `#0F2547` — primary text, headers, primary buttons
- `paper` `#F7F9FC` — page background
- `slate` `#5B6478` — secondary text, borders, muted labels
- `brass` `#C9A227` — institutional accent, used sparingly (active step, signature moment)
- `signal-red` `#C81E3A` — errors, rejected/invalid states only
- `signal-green` `#1E8F63` — success, verified/completed states
- `signal-amber` `#C9820F` — pending/in-progress states (new use here — not yet used elsewhere, appropriate for "Menunggu Verifikasi")

Fonts: `font-display` = Plus Jakarta Sans (headers only), `font-sans` = Inter (body/forms/default), `font-mono` = IBM Plex Mono (kode pendaftaran, NIK/NISN display, timestamps).

## Structural Patterns (from Stitch reference, re-themed)

**Wizard stepper** — horizontal on desktop, vertical/collapsed on mobile. Each step: a numbered circle badge. Completed = `bg-signal-green` with checkmark; active = `bg-brass` (the signature moment — same role brass plays for "active tahun ajaran" in admin); upcoming = `bg-slate/20 text-slate`. A thin connecting line between circles, same treatment. Show `"Tahap {n}: {label} — {percent}%"` text below the stepper on mobile where the visual stepper alone is too cramped.

**Document upload card** — dashed border (`border-2 border-dashed border-slate/30`), centered icon + label, format/size hint below in `text-xs text-slate` (e.g. "PDF/JPG/PNG, maks 2MB"). On successful upload: solid `bg-brass/5 border-brass/30` tint, filename shown, "Hapus" link in `text-signal-red`. Never occupies more visual weight than the form fields around it — a quiet, functional card, not a decorative dropzone.

**Status badge** — pill-shaped (`rounded-full`), semantic by state: `bg-signal-amber/10 text-signal-amber` for Menunggu Verifikasi (the only state M2 ever shows), reserved `bg-signal-green/10 text-signal-green` for Diterima and `bg-signal-red/10 text-signal-red` for Ditolak (M3's states — build the badge to support all three now, even though M2 only ever renders amber).

**Summary/review card** — used on the wizard's final review step and the status-check result page. Plain white card (`bg-white border border-ink/10 rounded-2xl`, matching admin's `x-panel` component exactly — reuse that component), label/value pairs in a simple two-column grid, `font-mono` for NIK/kode pendaftaran values.

**OTP input** — six separate single-digit boxes (`w-12 h-14 text-center font-mono text-xl border-ink/15 rounded-xl`, auto-advance focus on input), not one plain text field — this is the one place a slightly more "product" feel earns its keep, since it's a genuinely distinct interaction the rest of the admin panel never needs.

## Layout Shape

No admin sidebar/topbar chrome — this is a public, unauthenticated surface. Each page: a centered card (max-width ~640px for form steps, ~480px for the OTP/kode-akses screens), light `paper` background, a small lembaga identity header (nama lembaga + logo placeholder, pulled from the `Lembaga` record matching the URL slug) instead of the admin's fixed sidebar. No bottom mobile nav bar (that pattern in the Stitch reference belongs to a persistent logged-in dashboard, which M2 explicitly does not have — no login, no draft, no dashboard to return to).

## Copy Register

Same voice as the admin reference doc: active voice, plain verbs, sentence case, name actions by what the wali murid controls ("Lanjut ke Dokumen," not "Submit," "Kirim Kode" not "Send OTP"). Written for a parent/guardian filling this out for the first time, possibly nervous about a formal process — instructional microcopy above fields (e.g. "Gunakan huruf kapital di setiap awal kata sesuai akta") is worth keeping from the Stitch reference; it measurably reduces re-submission friction for this kind of form.

## Signature Moment

One deliberately bold element for this surface: the stepper's active-step `brass` circle, exactly mirroring the admin panel's "active tahun ajaran"/lembaga-switcher brass treatment — the visual thread that ties the public portal back to the same institution as the admin panel a parent may later see referenced in an email or printed bukti pendaftaran.
