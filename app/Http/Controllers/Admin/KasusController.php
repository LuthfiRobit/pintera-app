<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\KasusConsent;
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

        return view('admin.kasus.index', ['kasusList' => $kasusList]);
    }

    public function triase(Kasus $kasus, KonselorAllocationResolver $resolver): View
    {
        $this->authorize('kasus.triase');

        $kandidat = $resolver->kandidatUntuk($kasus->siswa);

        return view('admin.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
    }

    public function assignKonselor(Request $request, Kasus $kasus): RedirectResponse
    {
        $this->authorize('kasus.triase');

        $data = $request->validate([
            'tingkat_urgensi' => ['required', 'in:rendah,sedang,tinggi'],
            'konselor_tipe' => ['required', 'in:guru,karyawan'],
            'konselor_id' => ['required', 'integer'],
        ]);

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

        $kontakUtama = $kasus->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new KonselorDipilihNotification($kasus));

        return redirect()->route('admin.kasus.index')->with('status', 'Konselor berhasil ditugaskan, menunggu persetujuan orang tua.');
    }
}
