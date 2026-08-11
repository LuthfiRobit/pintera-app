<?php
// app/Http/Controllers/Admin/JenisTagihanMonitoringController.php

namespace App\Http\Controllers\Admin;

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;

class JenisTagihanMonitoringController extends BaseController
{
    use AuthorizesRequests;

    public function index(JenisTagihan $jenisTagihan)
    {
        $this->authorize('jenis-tagihan.view');

        return response('Dashboard monitoring skeleton', 200);
    }

    public function batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan)
    {
        $this->authorize('jenis-tagihan.edit');

        return response('OK', 200);
    }
}
