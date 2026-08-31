<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\ConflictingUserAccountsException;
use App\Domains\Identity\Models\Person;
use Illuminate\Support\Facades\DB;

class MergePersonsAction
{
    private const ROLE_TABLES = ['guru', 'karyawan', 'orang_tua', 'siswa'];

    public function execute(Person $losing, Person $winning): void
    {
        abort_if(
            $losing->yayasan_id !== $winning->yayasan_id,
            422,
            'Tidak bisa merge Person lintas yayasan -- itu dua identitas yang memang independen by design.'
        );

        if ($losing->user_id !== null && $winning->user_id !== null) {
            throw new ConflictingUserAccountsException($losing, $winning);
        }

        DB::transaction(function () use ($losing, $winning) {
            foreach (self::ROLE_TABLES as $table) {
                DB::table($table)->where('person_id', $losing->id)->update(['person_id' => $winning->id]);
            }

            if ($losing->user_id !== null && $winning->user_id === null) {
                // persons.user_id carries a unique constraint: clear it on the losing
                // side (and persist merged_into_person_id in the same statement) before
                // assigning it to the winning side, or the winning update violates the
                // constraint while the losing row still holds the same user_id.
                $carriedUserId = $losing->user_id;
                $losing->update(['user_id' => null, 'merged_into_person_id' => $winning->id]);
                $winning->update(['user_id' => $carriedUserId]);
            } else {
                $losing->update(['merged_into_person_id' => $winning->id]);
            }

            $losing->delete();
        });
    }
}
