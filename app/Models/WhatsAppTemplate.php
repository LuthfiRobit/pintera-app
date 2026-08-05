<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_template';

    protected $fillable = ['kode', 'isi_template', 'deskripsi'];

    public static function renderKode(string $kode, array $placeholders): ?string
    {
        $template = static::where('kode', $kode)->first();

        if ($template === null) {
            return null;
        }

        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
        }

        return strtr($template->isi_template, $replacements);
    }
}
