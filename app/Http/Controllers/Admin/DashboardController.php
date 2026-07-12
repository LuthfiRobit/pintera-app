<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lembaga;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole('guru')) {
            return view('admin.dashboard.guru');
        }

        if ($user->widestScopeLevel() === 'yayasan') {
            return view('admin.dashboard.yayasan', ['lembagaList' => Lembaga::all()]);
        }

        return view('admin.dashboard.lembaga');
    }
}
