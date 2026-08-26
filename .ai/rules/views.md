---
paths:
  - 'resources/views/**'
---

# Views

## Blade composition: layout / reusable widget / page partial
Use a class component (<x-app-layout>) only for page layouts. Use anonymous components in resources/views/components/** (with @props) for reusable UI widgets (buttons, inputs, modals, badges). Use @include for a partial specific to one page that isn't reused elsewhere.
