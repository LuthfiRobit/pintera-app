<?php

namespace Database\Factories;

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TagihanItem> */
class TagihanItemFactory extends Factory
{
    protected $model = TagihanItem::class;

    public function definition(): array
    {
        return [
            'tagihan_id' => Tagihan::factory(),
            'jenis_tagihan_id' => JenisTagihan::factory(),
            'jumlah' => 150000,
        ];
    }
}
