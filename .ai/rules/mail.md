---
paths:
  - 'app/Mail/**'
---

# Mail

## Mailables use build()+view(), sent synchronously
Write Mailables with a `build(): self` method returning `->view()` — never markdown `Content`/`Envelope`. Use constructor property promotion for data. Send synchronously via `Mail::to()->send()` — do not add `ShouldQueue`.
