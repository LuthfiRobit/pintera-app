---
paths:
  - 'database/migrations/**'
---

# Migrations

## Foreign keys: foreignId()->constrained()
Foreign key columns use $table->foreignId('x')->constrained(), not foreignIdFor() or manual ->foreign()->references()->on().

## Enums are stored as native DB ENUM columns
Enum-like columns (status, tipe, kategori, etc.) are declared as $table->enum(...) in the database, not string() + a PHP enum cast on the model. Follow this for new enum columns.

## Migration down(): match reversibility to the change type
Structural migrations (create/alter table) always get a clean down() (dropIfExists / dropColumn). Data-affecting migrations (backfill, data migration) get a full reverse when the original data can be reconstructed. When the change is genuinely lossy, down() may be an intentional no-op with an explanatory comment, or reset the column to its default (e.g. null) — state in a comment which case applies.
