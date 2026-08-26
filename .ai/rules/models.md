---
paths:
  - 'app/Models/**'
---

# Models

## app/Models root is a frozen legacy zone
Do not add new model files to app/Models/ (root) — it holds pre-domain-refactor models only. A new model related to an existing domain (Akademik, Sdm, etc.) goes in app/Domains/{Domain}/Models/, even if that domain's older models still live in app/Models/.

## Mass assignment via $fillable
Models use protected $fillable allow-lists, not $guarded block-lists.

## No model Observers or lifecycle-hook closures
Side effects on model create/update/delete go in Actions/Services explicitly, not in Observer classes, #[ObservedBy], or static::booted()/saving()/creating() closures on the model.

## Auto-increment primary keys only
Models use default auto-increment integer primary keys. Do not add HasUuids/HasUlids, including for public/API-facing or webhook-ingested models.
