<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Submission\ReviewSubmissionAction;
use App\Domains\Kasus\Actions\Submission\SubmitBuktiTugasAction;
use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\DataTransferObjects\SubmitBuktiTugasData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasusTugasSubmissionController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas, SubmitBuktiTugasAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if(! in_array($kasusTugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true), 403);

        $user = $request->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $siswa->id;
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();

        abort_unless($isSiswaTerkait || $isKontakUtama, 403);

        $mediaDisetujui = KasusConsent::where('kasus_id', $kasus->id)
            ->where('jenis', 'pengumpulan_media')->where('status', 'disetujui')->exists();
        $hasLampiran = $mediaDisetujui && $request->hasFile('lampiran');

        $rules = ['teks' => [$hasLampiran ? 'nullable' : 'required', 'string']];
        if ($mediaDisetujui) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'];
        }
        $data = $request->validate($rules);

        $lampiranPath = ($mediaDisetujui && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-tugas-lampiran', 'local')
            : null;

        $action->execute(
            $kasusTugas,
            new SubmitBuktiTugasData(teks: $data['teks'] ?? null, lampiranPath: $lampiranPath),
            isSiswaTerkait: $isSiswaTerkait,
            siswaId: $siswa->id,
            orangTuaId: $isSiswaTerkait ? null : $user->orangTua->id,
        );

        return redirect()->route('kasus.show', $kasus)->with('status', 'Bukti pengerjaan berhasil dikirim.');
    }

    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);

        $data = $request->validate([
            'status_review' => ['required', 'in:diterima,revisi_diminta'],
            'catatan_revisi' => ['required_if:status_review,revisi_diminta', 'nullable', 'string'],
        ]);

        $action->execute($kasusTugas, $kasusTugasSubmission, new ReviewSubmissionData(
            statusReview: $data['status_review'],
            catatanRevisi: $data['catatan_revisi'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Review submission berhasil disimpan.');
    }

    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, KasusPolicy $policy): StreamedResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        abort_if($kasusTugasSubmission->lampiran === null, 404);

        $user = auth()->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        abort_if(! $policy->downloadLampiran($user, $kasus, $kasusTugasSubmission, $siswa), 404);

        return Storage::disk('local')->download($kasusTugasSubmission->lampiran);
    }
}
