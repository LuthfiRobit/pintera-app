<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Pengajuan\AjukanKasusAction;
use App\Domains\Kasus\Actions\Pengajuan\ListKasusUntukUserAction;
use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KasusController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request, ListKasusUntukUserAction $action): View
    {
        $this->authorize('kasus.view');

        $kasusList = $action->execute($request->user());

        $totalKasus = $kasusList->count();
        $totalBerjalan = $kasusList->where('status', StatusKasus::Berjalan)->count();
        $totalEskalasi = $kasusList->where('status', StatusKasus::Eskalasi)->count();
        $totalButuhTindakan = $kasusList->filter(fn ($k) => in_array($k->status, [
            StatusKasus::Diajukan,
            StatusKasus::MenungguConsent,
            StatusKasus::Eskalasi,
        ], true))->count();

        return view('portals.kasus.index', [
            'kasusList' => $kasusList,
            'totalKasus' => $totalKasus,
            'totalBerjalan' => $totalBerjalan,
            'totalEskalasi' => $totalEskalasi,
            'totalButuhTindakan' => $totalButuhTindakan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kasus.ajukan');

        $user = $request->user();

        $siswaList = $user->hasRole('orang_tua')
            ? ($user->orangTua?->siswa()->withoutGlobalScope(TenantScope::class)->orderBy('nama_lengkap')->get() ?? collect())
            : Siswa::orderBy('nama_lengkap')->get();

        return view('portals.kasus.create', ['siswaList' => $siswaList]);
    }

    public function store(Request $request, AjukanKasusAction $action): RedirectResponse
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

        $action->execute(
            $siswa,
            new AjukanKasusData(
                siswaId: $siswa->id,
                kategoriMasalah: $data['kategori_masalah'],
                deskripsi: $data['deskripsi'],
                lampiranPath: $lampiranPath,
            ),
            isGuru: $isGuru,
            guruId: $isGuru ? $user->guru->id : null,
            orangTuaId: $isGuru ? null : $user->orangTua->id,
        );

        return redirect()->route('kasus.index')->with('status', 'Kasus berhasil diajukan.');
    }

    public function show(Kasus $kasus, KasusPolicy $policy): View
    {
        $this->authorize('kasus.view');

        $user = auth()->user();

        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        abort_if(! $policy->view($user, $kasus, $siswa), 404);

        activity('akses_klinis')
            ->causedBy($user)
            ->performedOn($kasus)
            ->log('Membuka detail kasus');

        // Guru and Karyawan both use BelongsToTenant. For an orang_tua actor (null lembaga_id),
        // TenantScope would fail-closed to zero rows for these konselor relations, silently
        // hiding the assigned konselor's identity from the informed-consent screen.
        $kasus->load([
            'consents',
            'konselorGuru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'konselorKaryawan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;

        return view('portals.kasus.show', [
            'kasus' => $kasus,
            'isKontakUtama' => $user->orangTua !== null
                && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists(),
            'isKonselor' => ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
                || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId),
            'isSiswaTerkait' => $user->siswa !== null && $user->siswa->id === $kasus->siswa_id,
            'isTriaseAdmin' => $user->can('kasus.triase')
                && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id),
        ]);
    }
}
