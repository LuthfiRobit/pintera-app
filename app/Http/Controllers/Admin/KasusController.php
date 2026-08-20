<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Kasus\Actions\Manajemen\AssignKonselorAction;
use App\Domains\Kasus\Actions\Manajemen\DestroyKasusAction;
use App\Domains\Kasus\Actions\Manajemen\RestoreKasusAction;
use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Services\KonselorAllocationResolver;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KasusController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kasus.view');

        $kasusList = Kasus::with('siswa')->where('status', StatusKasus::Diajukan)->latest()->get();
        $totalSemua = Kasus::count();
        $totalProses = Kasus::whereIn('status', [StatusKasus::MenungguConsent, StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi])->count();

        return view('portals.lembaga.kasus.index', [
            'kasusList' => $kasusList,
            'totalMenunggu' => $kasusList->count(),
            'totalProses' => $totalProses,
            'totalSemua' => $totalSemua,
        ]);
    }

    public function triase(Kasus $kasus, KonselorAllocationResolver $resolver): View
    {
        $this->authorize('kasus.triase');
        $this->authorizeLembaga($kasus);
        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $kandidat = $resolver->kandidatUntuk($siswa);

        return view('portals.lembaga.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
    }

    public function assignKonselor(Request $request, Kasus $kasus, KonselorAllocationResolver $resolver, AssignKonselorAction $action): RedirectResponse
    {
        $this->authorize('kasus.triase');
        $this->authorizeLembaga($kasus);
        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $data = $request->validate([
            'tingkat_urgensi' => ['required', 'in:rendah,sedang,tinggi'],
            'konselor_tipe' => ['required', 'in:guru,karyawan'],
            'konselor_id' => ['required', 'integer'],
        ]);

        $kandidat = $resolver->kandidatUntuk($siswa);
        $kandidatIds = $kandidat
            ->filter(fn ($k) => $k->tipe === $data['konselor_tipe'])
            ->map(fn ($k) => $k->model->id)
            ->all();

        abort_unless(in_array((int) $data['konselor_id'], $kandidatIds, true), 422, 'Konselor yang dipilih tidak valid.');

        $action->execute($kasus, $siswa, new AssignKonselorData(
            tingkatUrgensi: $data['tingkat_urgensi'],
            konselorTipe: $data['konselor_tipe'],
            konselorId: (int) $data['konselor_id'],
        ));

        return redirect()->route('admin.kasus.index')->with('status', 'Konselor berhasil ditugaskan, menunggu persetujuan orang tua.');
    }

    public function destroy(Kasus $kasus, DestroyKasusAction $action): RedirectResponse
    {
        $this->authorize('kasus.hapus');
        $this->authorizeLembaga($kasus);

        abort_unless($kasus->status === StatusKasus::Selesai, 422, 'Hanya kasus berstatus Selesai yang dapat dihapus.');

        $action->execute($kasus);

        return redirect()->route('admin.kasus.index')->with('status', 'Kasus berhasil dihapus.');
    }

    public function restore(Kasus $kasus, RestoreKasusAction $action): RedirectResponse
    {
        $this->authorize('kasus.pulihkan');
        $this->authorizeLembaga($kasus);
        abort_unless($kasus->trashed(), 404);

        $action->execute($kasus);

        return redirect()->route('admin.kasus.terhapus')->with('status', 'Kasus berhasil dipulihkan.');
    }

    private function authorizeLembaga(Kasus $kasus): void
    {
        $user = auth()->user();
        abort_if($user->widestScopeLevel() !== 'yayasan' && $kasus->lembaga_id !== $user->lembaga_id, 404);
    }
}
