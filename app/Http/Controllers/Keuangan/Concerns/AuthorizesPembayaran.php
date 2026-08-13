<?php
// app/Http/Controllers/Keuangan/Concerns/AuthorizesPembayaran.php

namespace App\Http\Controllers\Keuangan\Concerns;

use App\Models\Pembayaran;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait AuthorizesPembayaran
{
    private function authorizePembayaran(Pembayaran $pembayaran): void
    {
        $orangTua = Auth::user()->orangTua;
        $ownsChild = $orangTua !== null
            && $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($pembayaran->siswa_id)->exists();

        abort_unless($ownsChild, 403);
    }
}
