<?php

namespace App\Models;

use RuntimeException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            if ($role->exists && $role->is_protected && $role->isDirty('scope_level')) {
                throw new RuntimeException('Scope level role yang dilindungi tidak dapat diubah.');
            }
        });

        static::deleting(function (Role $role) {
            if ($role->is_protected) {
                throw new RuntimeException('Role yang dilindungi tidak dapat dihapus.');
            }
        });
    }
}
