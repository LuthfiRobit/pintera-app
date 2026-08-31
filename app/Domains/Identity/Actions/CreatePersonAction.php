<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;
use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\Scopes\YayasanScope;

class CreatePersonAction
{
    /** @param array<string, mixed> $identityData */
    public function execute(array $identityData, ?int $lembagaId, ?int $actingYayasanId): Person
    {
        $yayasanId = $this->resolveYayasanId($lembagaId, $actingYayasanId);

        if (! empty($identityData['nik'])) {
            $nikHash = hash('sha256', $identityData['nik']);

            // Bypass Person::YayasanScope deliberately: that scope filters by the
            // acting authenticated user's own yayasan_id, which is not necessarily
            // $yayasanId (the yayasan we just transitively resolved from lembagaId
            // or actingYayasanId). Stacking both filters would AND them together,
            // so an actor whose own yayasan differs from the target yayasan would
            // never see the existing row and the duplicate-NIK check would silently
            // pass. We already have the exact, explicit yayasan_id to scope by, so
            // rely on that alone.
            $existing = Person::withoutGlobalScope(YayasanScope::class)
                ->where('yayasan_id', $yayasanId)
                ->where('nik_hash', $nikHash)
                ->first();

            if ($existing !== null) {
                throw new PersonAlreadyExistsException($existing);
            }
        }

        return Person::create([...$identityData, 'yayasan_id' => $yayasanId]);
    }

    private function resolveYayasanId(?int $lembagaId, ?int $actingYayasanId): int
    {
        if ($lembagaId !== null) {
            $lembagaYayasanId = Lembaga::findOrFail($lembagaId)->yayasan_id;

            abort_if(
                $actingYayasanId !== null && $actingYayasanId !== $lembagaYayasanId,
                422,
                'yayasan_id yang diberikan tidak cocok dengan yayasan pemilik lembaga.'
            );

            return $lembagaYayasanId;
        }

        abort_if($actingYayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

        return $actingYayasanId;
    }
}
