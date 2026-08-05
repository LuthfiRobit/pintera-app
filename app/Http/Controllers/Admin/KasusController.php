<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\Scopes\TenantScope;
use App\Notifications\KonselorDipilihNotification;
use App\Services\KonselorAllocationResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
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

        return view('admin.kasus.index', [
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

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $kandidat = $resolver->kandidatUntuk($siswa);

        return view('admin.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
    }

    public function assignKonselor(Request $request, Kasus $kasus, KonselorAllocationResolver $resolver): RedirectResponse
    {
        $this->authorize('kasus.triase');
        $this->authorizeLembaga($kasus);

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

        DB::transaction(function () use ($data, $kasus) {
            $kasus->update([
                'tingkat_urgensi' => $data['tingkat_urgensi'],
                'status' => StatusKasus::MenungguConsent,
                'konselor_guru_id' => $data['konselor_tipe'] === 'guru' ? $data['konselor_id'] : null,
                'konselor_karyawan_id' => $data['konselor_tipe'] === 'karyawan' ? $data['konselor_id'] : null,
            ]);

            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']);
            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);
        });

        $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new KonselorDipilihNotification($kasus));

        return redirect()->route('admin.kasus.index')->with('status', 'Konselor berhasil ditugaskan, menunggu persetujuan orang tua.');
    }

    private function authorizeLembaga(Kasus $kasus): void
    {
        $user = auth()->user();
        abort_if($user->widestScopeLevel() !== 'yayasan' && $kasus->lembaga_id !== $user->lembaga_id, 404);
    }
}
