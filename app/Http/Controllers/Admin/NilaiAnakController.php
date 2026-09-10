<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NilaiAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function __construct(
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {}

    public function index(Request $request): View
    {
        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $semesterList = $anak && $anak->kelas
            ? Semester::withoutGlobalScope(TenantScope::class)->where('tahun_ajaran_id', $anak->kelas->tahun_ajaran_id)->orderByDesc('id')->get()
            : collect();
        $semesterId = $request->integer('semester_id') ?: $semesterList->first()?->id;

        $nilaiList = ($anak && $semesterId)
            ? NilaiSiswa::withoutGlobalScope(TenantScope::class)
                ->where('siswa_id', $anak->id)
                ->whereNotNull('nilai_angka')
                ->whereHas('asesmen', fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->where('semester_id', $semesterId)
                    ->whereIn('jenis', JenisAsesmen::masukRapor()))
                ->with([
                    'komponenPenilaian' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                    'asesmen' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                ])
                ->get()
            : collect();

        $pengajuanRapor = ($anak && $semesterId)
            ? PengajuanRapor::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $anak->kelas_id)
                ->where('semester_id', $semesterId)
                ->where('status', StatusPengajuanRapor::Disetujui)
                ->first()
            : null;

        // Rapor dengan status apapun (untuk info banner ketika belum Disetujui)
        $pengajuanRaporSemua = (! $pengajuanRapor && $anak && $semesterId)
            ? PengajuanRapor::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $anak->kelas_id)
                ->where('semester_id', $semesterId)
                ->latest()
                ->first()
            : null;

        return view('admin.orang-tua.nilai-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'nilaiList' => $nilaiList,
            'pengajuanRapor' => $pengajuanRapor,
            'pengajuanRaporSemua' => $pengajuanRaporSemua,
        ]);
    }

    public function unduhRapor(Request $request, Siswa|int|string $siswa): Response
    {
        $siswaId = $siswa instanceof Siswa ? $siswa->id : (int) $siswa;
        $anakList = $this->resolveAnakList($request->user());
        abort_unless($anakList->contains('id', $siswaId), 403);

        $siswa = $siswa instanceof Siswa ? $siswa : $anakList->firstWhere('id', $siswaId);

        $semester = Semester::withoutGlobalScope(TenantScope::class)->find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $pengajuanRapor = PengajuanRapor::withoutGlobalScope(TenantScope::class)
            ->where('kelas_id', $siswa->kelas_id)
            ->where('semester_id', $semester->id)
            ->where('status', StatusPengajuanRapor::Disetujui)
            ->first();
        abort_if($pengajuanRapor === null, 404, 'Rapor untuk semester ini belum tersedia.');

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($siswa->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }
}
