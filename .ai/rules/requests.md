---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Custom validation messages: inline messages(), no lang files
Custom validation messages are defined in a messages() method on the FormRequest itself. There are no lang/*/validation.php overrides — don't add one.
