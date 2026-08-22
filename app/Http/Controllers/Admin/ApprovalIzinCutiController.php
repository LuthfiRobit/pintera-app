<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApprovalIzinCutiController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $daftar = PengajuanIzinCuti::with(['pegawai', 'approvalRequest.currentStep'])
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [ApprovalStatus::Pending, ApprovalStatus::InReview]))
            ->latest('tanggal_mulai')
            ->get();

        return view('admin.kehadiran-sdm.izin-cuti.index', ['daftar' => $daftar]);
    }

    public function show(PengajuanIzinCuti $izinCuti): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $izinCuti->load(['pegawai', 'approvalRequest.currentStep', 'approvalRequest.logs.user']);

        return view('admin.kehadiran-sdm.izin-cuti.show', ['izinCuti' => $izinCuti]);
    }

    public function decision(Request $request, PengajuanIzinCuti $izinCuti, ProsesApprovalIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $data = $request->validate([
            'action' => ['required', 'in:APPROVE,REJECT'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($izinCuti, $request->user(), ApprovalAction::from($data['action']), $data['notes'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.kehadiran-sdm.izin-cuti.index')->with('status', 'Keputusan berhasil diproses.');
    }
}
