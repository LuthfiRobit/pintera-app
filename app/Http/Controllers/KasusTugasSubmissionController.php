<?php
// app/Http/Controllers/KasusTugasSubmissionController.php

namespace App\Http\Controllers;

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Scopes\TenantScope;
use App\Notifications\SubmissionRevisiNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasusTugasSubmissionController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas): RedirectResponse
    {
        $this->authorize('kasus.view');
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
        if ($kasusTugas->frekuensi === 'harian') {
            $rules['tanggal'] = [
                'required', 'date',
                'after_or_equal:'.$kasusTugas->mulai_pada->toDateString(),
                'before_or_equal:'.$kasusTugas->batas_selesai_pada->toDateString(),
            ];
        }
        $data = $request->validate($rules);

        if ($kasusTugas->frekuensi === 'harian') {
            $terkunci = KasusTugasSubmission::where('tugas_id', $kasusTugas->id)
                ->whereDate('tanggal', $data['tanggal'])
                ->whereIn('status_review', ['menunggu_review', 'diterima'])
                ->exists();
            abort_if($terkunci, 422, 'Tanggal ini sudah memiliki bukti pengerjaan yang menunggu atau sudah diterima.');
        }

        $lampiranPath = ($mediaDisetujui && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-tugas-lampiran', 'local')
            : null;

        KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswa->id : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $user->orangTua->id,
            'teks' => $data['teks'] ?? null,
            'lampiran' => $lampiranPath,
            'tanggal' => $data['tanggal'] ?? null,
        ]);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Bukti pengerjaan berhasil dikirim.');
    }

    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validate([
            'status_review' => ['required', 'in:diterima,revisi_diminta'],
            'catatan_revisi' => ['required_if:status_review,revisi_diminta', 'nullable', 'string'],
        ]);

        $kasusTugasSubmission->update([
            'status_review' => $data['status_review'],
            'catatan_revisi' => $data['catatan_revisi'] ?? null,
        ]);

        if ($data['status_review'] === 'revisi_diminta') {
            if ($kasusTugas->frekuensi !== 'harian') {
                $kasusTugas->update(['status' => 'revisi']);
            }

            $notifiable = $kasusTugasSubmission->siswa_id !== null
                ? $kasusTugasSubmission->siswa()->withoutGlobalScope(TenantScope::class)->first()
                    ?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasusTugasSubmission->orangTua;
            $notifiable?->notify(new SubmissionRevisiNotification($kasusTugasSubmission));
        }

        return redirect()->route('kasus.show', $kasus)->with('status', 'Review submission berhasil disimpan.');
    }

    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission): StreamedResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        abort_if($kasusTugasSubmission->lampiran === null, 404);

        $user = auth()->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        $isSubmitter = ($kasusTugasSubmission->siswa_id !== null && $kasusTugasSubmission->siswa_id === $user->siswa?->id)
            || ($kasusTugasSubmission->orang_tua_id !== null && $kasusTugasSubmission->orang_tua_id === $user->orangTua?->id);
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);

        abort_if(! $isSubmitter && ! $isKonselor && ! $isKontakUtama && ! $isTriaseAdmin, 404);

        return Storage::disk('local')->download($kasusTugasSubmission->lampiran);
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
