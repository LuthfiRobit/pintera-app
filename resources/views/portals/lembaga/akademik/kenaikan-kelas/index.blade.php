@php use App\Domains\Akademik\Enums\BentukPendidikan; @endphp
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kenaikan Kelas</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kenaikan Kelas</b>
            </p>
        </div>

        {{-- Source & Target Tahun Ajaran Picker --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran Sumber (kelas lama)" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran Tujuan (kelas baru)" />
                    <select name="tahun_ajaran_tujuan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
            </form>
        </div>

        @if ($kelasLamaList->isNotEmpty() && $tahunAjaranTujuanId === null)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-700">
                Pilih juga <b>Tahun Ajaran Tujuan</b> di atas untuk menampilkan pilihan kelas &amp; semester tujuan.
            </div>
        @endif

        @if ($kelasLamaList->isNotEmpty() && $tahunAjaranTujuanId !== null)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-100 bg-white px-6 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Pemetaan Kenaikan Kelas</p>
                    <p class="mt-0.5 text-xs text-gray-500">Tentukan tindakan untuk setiap kelas lama: naikkan ke kelas tujuan, atau luluskan.</p>
                </div>

                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
                                    <th class="px-6 py-3.5">Kelas Lama</th>
                                    <th class="px-4 py-3.5 text-center">Jml Siswa</th>
                                    <th class="px-4 py-3.5">Tindakan</th>
                                    <th class="px-4 py-3.5">Kelas Tujuan</th>
                                    <th class="px-4 py-3.5">Salin Jadwal ke Semester</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($kelasLamaList as $kelasLama)
                                    <tr class="transition hover:bg-gray-50/60">
                                        <td class="px-6 py-4 font-bold text-gray-900">{{ $kelasLama->nama }}</td>
                                        <td class="px-4 py-4 text-center text-gray-500">{{ $kelasLama->siswa_count }}</td>
                                        @php
                                            $isTingkatAkhir = $kelasLama->lembaga
                                                ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->isTingkatAkhir($kelasLama->tingkat)
                                                : false;
                                        @endphp
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="naik" @selected(! $isTingkatAkhir)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir)>Lulus</option>
                                            </select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">—</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-4">
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" name="mapping[{{ $kelasLama->id }}][salin_jadwal]" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                                <select name="mapping[{{ $kelasLama->id }}][semester_tujuan_id]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                    <option value="">—</option>
                                                    @foreach ($semesterList as $semester)
                                                        <option value="{{ $semester->id }}">{{ $semester->nama }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end border-t border-gray-100 bg-gray-50/50 px-6 py-4">
                        <x-primary-button type="submit">Proses Kenaikan Kelas</x-primary-button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
