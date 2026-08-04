<?php

// app/Http/Controllers/KasusController.php

namespace App\Http\Controllers;

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Notifications\KasusDiajukanNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KasusController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kasus.view');

        $user = $request->user();

        if ($user->hasRole('siswa')) {
            $kasusList = Kasus::with('siswa')->where('siswa_id', $user->siswa?->id)->latest()->get();
        } elseif ($user->hasRole('orang_tua')) {
            // Orang tua accounts have no lembaga_id of their own, so the default TenantScope
            // on Kasus (a real, non-null lembaga_id) would fail-closed to zero rows for them.
            // Bypass it here; the where() on diajukan_oleh_orang_tua_id already scopes the
            // result to this orang_tua's own submissions.
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where('diajukan_oleh_orang_tua_id', $user->orangTua?->id)->latest()->get();
        } elseif ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
            $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where('konselor_karyawan_id', $karyawanId)->latest()->get();
        } elseif ($user->hasRole('guru')) {
            $kasusList = Kasus::with('siswa')
                ->where(fn ($q) => $q->where('diajukan_oleh_guru_id', $user->guru?->id)
                    ->orWhere('konselor_guru_id', $user->guru?->id))
                ->latest()->get();
        } else {
            $kasusList = Kasus::with('siswa')->latest()->get();
        }

        return view('kasus.index', ['kasusList' => $kasusList]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kasus.ajukan');

        $user = $request->user();

        $siswaList = $user->hasRole('orang_tua')
            ? ($user->orangTua?->siswa()->withoutGlobalScope(TenantScope::class)->orderBy('nama_lengkap')->get() ?? collect())
            : Siswa::orderBy('nama_lengkap')->get();

        return view('kasus.create', ['siswaList' => $siswaList]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('kasus.ajukan');

        $user = $request->user();
        $isGuru = $user->hasRole('guru');

        $rules = [
            'siswa_id' => ['required', 'exists:siswa,id'],
            'kategori_masalah' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
        ];
        if ($isGuru) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
        }
        $data = $request->validate($rules);

        if ($isGuru) {
            abort_if($user->guru === null, 403);
            $siswa = Siswa::findOrFail($data['siswa_id']);
        } else {
            abort_if($user->orangTua === null, 403);
            $siswa = $user->orangTua->siswa()
                ->withoutGlobalScope(TenantScope::class)
                ->where('siswa.id', $data['siswa_id'])
                ->firstOrFail();
        }

        $lampiranPath = ($isGuru && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-lampiran', 'public')
            : null;

        $kasus = DB::transaction(function () use ($data, $siswa, $isGuru, $user, $lampiranPath) {
            return Kasus::create([
                'siswa_id' => $siswa->id,
                'lembaga_id' => $siswa->lembaga_id,
                'diajukan_oleh_guru_id' => $isGuru ? $user->guru->id : null,
                'diajukan_oleh_orang_tua_id' => $isGuru ? null : $user->orangTua->id,
                'kategori_masalah' => $data['kategori_masalah'],
                'deskripsi' => $data['deskripsi'],
                'lampiran' => $lampiranPath,
                'status' => StatusKasus::Diajukan,
            ]);
        });

        // The Kasus->siswa relation would re-apply Siswa's TenantScope when lazy-loaded,
        // which (for an orang_tua submitter with no lembaga_id) filters the real siswa row
        // out entirely. Cache the already-authorized, scope-bypassed $siswa on the relation
        // so notifyPihakLain() (and the redirect target) see the correct record.
        $kasus->setRelation('siswa', $siswa);

        $this->notifyPihakLain($kasus, $isGuru);

        return redirect()->route('kasus.index')->with('status', 'Kasus berhasil diajukan.');
    }

    public function show(Kasus $kasus): View
    {
        $this->authorize('kasus.view');

        $user = auth()->user();

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $isSubmitter = ($kasus->diajukan_oleh_guru_id !== null && $kasus->diajukan_oleh_guru_id === $user->guru?->id)
            || ($kasus->diajukan_oleh_orang_tua_id !== null && $kasus->diajukan_oleh_orang_tua_id === $user->orangTua?->id);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);
        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $kasus->siswa_id;

        abort_if(! $isSubmitter && ! $isKontakUtama && ! $isTriaseAdmin && ! $isKonselor && ! $isSiswaTerkait, 404);

        // Guru and Karyawan both use BelongsToTenant. For an orang_tua actor (null lembaga_id),
        // TenantScope would fail-closed to zero rows for these konselor relations, silently
        // hiding the assigned konselor's identity from the informed-consent screen.
        $kasus->load([
            'consents',
            'konselorGuru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'konselorKaryawan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        return view('kasus.show', [
            'kasus' => $kasus,
            'isKontakUtama' => $isKontakUtama,
            'isKonselor' => $isKonselor,
            'isSiswaTerkait' => $isSiswaTerkait,
        ]);
    }

    private function notifyPihakLain(Kasus $kasus, bool $isGuru): void
    {
        if ($isGuru) {
            $kontakUtama = $kasus->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            $kontakUtama?->notify(new KasusDiajukanNotification($kasus));

            return;
        }

        $kelas = $kasus->siswa->kelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas = $kelas?->waliKelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas?->notify(new KasusDiajukanNotification($kasus));
    }
}
