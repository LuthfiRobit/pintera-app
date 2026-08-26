---
paths:
  - 'app/Domains/*/Models/**'
---

# Domains Models

## Mass assignment via $fillable
Models use protected $fillable allow-lists, not $guarded block-lists.

## No model Observers or lifecycle-hook closures
Side effects on model create/update/delete go in Actions/Services explicitly, not in Observer classes, #[ObservedBy], or static::booted()/saving()/creating() closures on the model.

## Auto-increment primary keys only
Models use default auto-increment integer primary keys. Do not add HasUuids/HasUlids, including for public/API-facing or webhook-ingested models.
