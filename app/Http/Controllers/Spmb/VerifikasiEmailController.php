<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\JalurPpdb;
use App\Services\OtpService;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class VerifikasiEmailController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);

        return view('spmb.verifikasi-email', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, OtpService $otpService): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $otpService->kirim($data['email']);

        session(['spmb_email_pending.'.$lembaga->id.'.'.$jalur->id => $data['email']]);

        return redirect()->route('spmb.verifikasi-otp', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }

    public function edit(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        return view('spmb.verifikasi-otp', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function update(
        Request $request,
        string $lembagaSlug,
        JalurPpdb $jalur,
        OtpService $otpService,
        PendaftaranWizardSession $wizardSession
    ): RedirectResponse {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $data = $request->validate([
            'kode_otp' => ['required', 'string'],
        ]);

        $email = session('spmb_email_pending.'.$lembaga->id.'.'.$jalur->id);

        if (! $email || ! $otpService->verifikasi($email, $data['kode_otp'])) {
            return back()->withErrors(['kode_otp' => 'Kode salah, kedaluwarsa, atau sudah dipakai.']);
        }

        $wizardSession->put($lembaga, $jalur, ['email_pendaftaran' => $email]);

        return redirect()->route('portal.wizard.data-diri');
    }
}
