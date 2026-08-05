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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
            // Cheap fast-path pre-check, before the file upload runs, so a locked date is
            // rejected without ever writing an orphaned file to disk. The authoritative,
            // race-safe check happens again inside the transaction below.
            $terkunciCepat = KasusTugasSubmission::where('tugas_id', $kasusTugas->id)
                ->whereDate('tanggal', $data['tanggal'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->first();

            if ($terkunciCepat && in_array($terkunciCepat->status_review, ['menunggu_review', 'diterima'], true)) {
                throw ValidationException::withMessages([
                    'tanggal' => 'Tanggal ini sudah memiliki bukti pengerjaan yang menunggu atau sudah diterima.',
                ]);
            }
        }

        $lampiranPath = ($mediaDisetujui && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-tugas-lampiran', 'local')
            : null;

        $payload = [
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswa->id : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $user->orangTua->id,
            'teks' => $data['teks'] ?? null,
            'lampiran' => $lampiranPath,
            'tanggal' => $data['tanggal'] ?? null,
        ];

        if ($kasusTugas->frekuensi === 'harian') {
            // Re-check (and lock) the lock condition inside the transaction so a second
            // concurrent request for the same date can't slip past this check before the
            // first request's insert commits. The lock semantics here must match the
            // view's: only the LATEST submission for this tugas_id + tanggal determines
            // whether the date is currently locked — not "any submission that ever
            // existed for that date". `->orderByDesc('id')` breaks a same-second
            // `created_at` tie identically to the view's `sortByDesc('created_at')`
            // (PHP's stable sort keeps insertion/id order on ties), so both sides always
            // agree on which submission is "latest".
            //
            // Retried up to 3 times: under MySQL's default REPEATABLE READ isolation,
            // `lockForUpdate()` against a WHERE clause matching zero rows (the very first
            // submission for a given tugas) takes only a gap lock, and InnoDB gap locks do
            // NOT conflict with each other — two concurrent "first submission" requests can
            // both acquire it, then deadlock on the subsequent INSERT's insert-intention
            // lock. Laravel's transaction retry catches that deadlock (SQLSTATE 40001) and
            // re-runs the closure, which then sees the other request's committed row and
            // throws the correct ValidationException instead of surfacing a 500. If the
            // connection's isolation level is ever changed to READ COMMITTED, gap locks
            // disappear entirely and this whole re-check must be revisited.
            DB::transaction(function () use ($kasusTugas, $data, $payload): void {
                $submisiTerbaru = KasusTugasSubmission::where('tugas_id', $kasusTugas->id)
                    ->whereDate('tanggal', $data['tanggal'])
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $terkunci = $submisiTerbaru
                    && in_array($submisiTerbaru->status_review, ['menunggu_review', 'diterima'], true);

                if ($terkunci) {
                    throw ValidationException::withMessages([
                        'tanggal' => 'Tanggal ini sudah memiliki bukti pengerjaan yang menunggu atau sudah diterima.',
                    ]);
                }

                KasusTugasSubmission::create($payload);
            }, 3);
        } else {
            KasusTugasSubmission::create($payload);
        }

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
