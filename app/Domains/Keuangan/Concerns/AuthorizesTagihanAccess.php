<?php

// app/Domains/Keuangan/Concerns/AuthorizesTagihanAccess.php

namespace App\Domains\Keuangan\Concerns;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Support\Facades\Auth;

trait AuthorizesTagihanAccess
{
    private function authorizeTagihanAccess(Tagihan $tagihan): void
    {
        $orangTua = Auth::user()->orangTua;
        $ownsChild = $orangTua !== null
            && $tagihan->tagihable_type === Siswa::class
            && $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($tagihan->tagihable_id)->exists();

        abort_unless($ownsChild, 403);
    }
}
