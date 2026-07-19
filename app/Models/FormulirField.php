<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormulirField extends Model
{
    use BelongsToTenant;

    protected $table = 'formulir_field';

    protected $fillable = ['jalur_ppdb_id', 'lembaga_id', 'label', 'field_type', 'options', 'is_required', 'urutan'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormulirField $field) {
            if (empty($field->lembaga_id)) {
                $field->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($field->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }

    public function jawabanFormulir(): HasMany
    {
        return $this->hasMany(JawabanFormulirPendaftaran::class);
    }
}
