<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LembagaController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('lembaga.view');

        $query = $request->user()->widestScopeLevel() === 'yayasan'
            ? Lembaga::query()
            : Lembaga::where('id', $request->user()->lembaga_id);

        $query->when($request->filled('cari'), function ($q) use ($request) {
            $cari = $request->query('cari');
            $q->where(function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")->orWhere('npsn', 'like', "%{$cari}%");
            });
        })
            ->when($request->filled('bentuk'), fn ($q) => $q->where('bentuk_pendidikan', $request->query('bentuk')))
            ->when($request->filled('status'), fn ($q) => $q->where('status_sekolah', $request->query('status')));

        $lembaga = $query->orderBy('nama')->paginate(10)->withQueryString();

        return view('admin.lembaga.index', ['lembaga' => $lembaga]);
    }

    public function create(Request $request): View
    {
        $this->authorize('lembaga.create');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        return view('admin.lembaga.create', ['yayasanList' => Yayasan::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('lembaga.create');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $data = $this->validated($request);
        $data = array_merge($data, $this->booleans($request, isCreate: true));

        Lembaga::create($data);

        return redirect()->route('admin.lembaga.index')->with('status', 'Lembaga berhasil dibuat.');
    }

    public function edit(Request $request, Lembaga $lembaga): View
    {
        $this->authorize('lembaga.edit');
        $this->authorizeOwnLembaga($request, $lembaga);

        return view('admin.lembaga.edit', ['lembaga' => $lembaga]);
    }

    public function update(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $this->authorizeOwnLembaga($request, $lembaga);

        $data = $this->validated($request, $lembaga);
        $data = array_merge($data, $this->booleans($request, isCreate: false));

        $lembaga->update($data);

        return redirect()->route('admin.lembaga.index')->with('status', 'Lembaga berhasil diperbarui.');
    }

    private function authorizeOwnLembaga(Request $request, Lembaga $lembaga): void
    {
        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        $isOwnLembaga = $lembaga->id === $request->user()->lembaga_id;

        abort_unless($isYayasanScope || $isOwnLembaga, 403);
    }

    /**
     * HTML checkboxes omit their key entirely when unchecked, so these three
     * booleans are read directly off the request rather than through
     * validate() — otherwise unchecking one on an edit form would silently
     * leave the previous value in place instead of turning it off.
     */
    private function booleans(Request $request, bool $isCreate): array
    {
        return [
            'mbs' => $request->boolean('mbs'),
            'memungut_iuran' => $request->boolean('memungut_iuran'),
            // On create, a request that never sent status_aktif at all (e.g. a raw API
            // call that predates this form) should still land active, matching the
            // migration's own default(true) — only an edit's unchecked box means "off".
            'status_aktif' => $isCreate && ! $request->has('status_aktif') ? true : $request->boolean('status_aktif'),
        ];
    }

    private function validated(Request $request, ?Lembaga $lembaga = null): array
    {
        $data = $request->validate([
            'yayasan_id' => ['required', 'exists:yayasan,id'],
            'npsn' => ['required', 'string', 'max:20', Rule::unique('lembaga', 'npsn')->ignore($lembaga?->id)],
            'nss' => ['nullable', 'string', 'max:255'],
            'kode_lembaga' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('lembaga', 'kode_lembaga')->ignore($lembaga?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'bentuk_pendidikan' => ['required', 'in:KB,TPA,SPS,TK,SD,SMP,SMA,SMK,SLB'],
            'status_sekolah' => ['required', 'in:negeri,swasta'],
            'status_kepemilikan' => ['nullable', 'string', 'max:255'],
            'naungan' => ['required', 'in:kemendikdasmen,kemenag'],
            'akreditasi' => ['nullable', 'in:A,B,C,belum'],
            'sk_pendirian_nomor' => ['nullable', 'string', 'max:255'],
            'sk_pendirian_tanggal' => ['nullable', 'date'],
            'sk_izin_operasional_nomor' => ['nullable', 'string', 'max:255'],
            'sk_izin_operasional_tanggal' => ['nullable', 'date'],
            'sk_akreditasi_nomor' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_akreditasi' => ['nullable', 'date'],
            'nama_kepala_sekolah' => ['nullable', 'string', 'max:255'],
            'nama_bendahara_bosp' => ['nullable', 'string', 'max:255'],
            'alamat_jalan' => ['nullable', 'string'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'nama_dusun' => ['nullable', 'string', 'max:255'],
            'desa_kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'lintang' => ['nullable', 'numeric', 'between:-90,90'],
            'bujur' => ['nullable', 'numeric', 'between:-180,180'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'fax' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'nama_bank' => ['nullable', 'string', 'max:255'],
            'cabang_kcp_unit' => ['nullable', 'string', 'max:255'],
            'rekening_atas_nama' => ['nullable', 'string', 'max:255'],
            'nomor_rekening' => ['nullable', 'string'],
            'nama_wajib_pajak' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string'],
            'nominal_iuran' => ['nullable', 'numeric', 'min:0'],
            'periode_iuran' => ['nullable', 'in:bulanan,tahunan'],
        ]);

        $data['kode_lembaga'] = Str::upper($data['kode_lembaga']);

        return $data;
    }
}
