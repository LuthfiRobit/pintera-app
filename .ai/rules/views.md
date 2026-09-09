---
paths:
  - 'resources/views/**'
---

# Views

## Blade composition: layout / reusable widget / page partial
Use a class component (<x-app-layout>) only for page layouts. Use anonymous components in resources/views/components/** (with @props) for reusable UI widgets (buttons, inputs, modals, badges). Use @include for a partial specific to one page that isn't reused elsewhere.

## Disable scope-gated actions, don't hide them
When an action button is gated by tenant scope (e.g. yayasan actor must switch to 1 lembaga before "Tambah X"), render it disabled with `<x-tooltip>` explaining why — never hide it outright. Hiding leaves the user unaware the action exists at all.

## Grouped/accordion lists default to collapsed
When rows are grouped under a collapsible parent (accordion card per subject/category), default every group to collapsed, not expanded. The collapsed header must carry enough summary info (name, count, status) that the user doesn't need to expand to judge state — that's what makes collapsing safe.

## Custom role="button" elements need keyboard + ARIA support
A `<div role="button" tabindex="0">` driven only by `@click` is keyboard-focusable but not keyboard-operable. Always pair it with `@keydown.enter.prevent` and `@keydown.space.prevent` calling the same handler. If it toggles visibility of another element, add `:aria-expanded` (reflecting the toggle state) and `aria-controls` (pointing at that element's id).
