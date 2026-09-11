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
                    <x-input-label for="tahun_ajaran_id" value="Tahun Ajaran Sumber (kelas lama)" />
                    <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <x-input-label for="tahun_ajaran_tujuan_id" value="Tahun Ajaran Tujuan (kelas baru)" />
                    <select id="tahun_ajaran_tujuan_id" name="tahun_ajaran_tujuan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
            </form>
        </div>

        @if ($errorTahunAjaran)
            <div class="rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-700">
                {{ $errorTahunAjaran }}
            </div>
        @endif

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

                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}" x-data="kenaikanKelasForm()" @submit.prevent="konfirmasiDanKirim($event)">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase font-bold tracking-wider text-gray-600">
                                    <th class="px-5 py-3">Kelas Lama</th>
                                    <th class="px-4 py-3 text-center">Jml Siswa</th>
                                    <th class="px-4 py-3">Tindakan</th>
                                    <th class="px-4 py-3">Kelas Tujuan</th>
                                    <th class="px-4 py-3">
                                        Salin Jadwal ke Semester
                                        <span class="block text-[10px] font-normal normal-case text-gray-400 mt-0.5">Menyalin struktur jadwal pelajaran kelas lama ke kelas tujuan, di semester yang dipilih</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($kelasLamaList as $kelasLama)
                                    <tr data-kelas-lama="{{ $kelasLama->id }}"
                                        :data-warning="((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) ? '1' : '0'"
                                        :class="{ 'border-l-4 border-amber-400 bg-amber-50/30': ((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) }"
                                        class="transition hover:bg-gray-50/60"
                                        x-data="{
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumAsalLabel: {{ Js::from($kelasLama->kurikulum?->label()) }},
                                        kurikulumTujuan: null,
                                        kurikulumTujuanLabel: null,
                                        tingkatTujuan: null,
                                        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.kurikulumTujuanLabel = opt?.dataset.kurikulumLabel || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
                                        get selisihIndexTingkat() {
                                            if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
                                            const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
                                            const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
                                            if (indexAsal === -1 || indexTujuan === -1) return null;
                                            return indexTujuan - indexAsal;
                                        },
                                    }">
                                        <td class="px-5 py-3.5 font-bold text-gray-900">{{ $kelasLama->nama }}
                                            @if ($kelasLama->siswa_count === 0)
                                                 <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                                    Sudah diproses / kosong
                                                </span>
                                            @endif
                                            <span class="text-xs font-normal text-gray-400">(Tingkat {{ $kelasLama->tingkat ?? '-' }})</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center text-gray-500">{{ $kelasLama->siswa_count }}</td>
                                        @php
                                            $isTingkatAkhir = $kelasLama->lembaga
                                                ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->isTingkatAkhir($kelasLama->tingkat)
                                                : false;
                                        @endphp
                                        <td class="px-4 py-3.5">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="lewati" @selected($kelasLama->siswa_count === 0)>Lewati{{ $kelasLama->siswa_count === 0 ? ' (sudah kosong)' : '' }}</option>
                                                <option value="naik" @selected(! $isTingkatAkhir && $kelasLama->siswa_count > 0)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir && $kelasLama->siswa_count > 0)>Lulus</option>
                                            </select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" x-on:change="onKelasTujuanChange($event)" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">—</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-kurikulum-label="{{ $kelasBaru->kurikulum?->label() }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </select>
                                            <p x-show="tingkatTujuan !== null" class="mt-1 text-xs text-gray-400" x-text="'Tingkat tujuan: ' + tingkatTujuan"></p>
                                            <p x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsalLabel + ', kelas tujuan ' + kurikulumTujuanLabel"></p>
                                            <p x-show="selisihIndexTingkat === 0" class="mt-1 text-xs text-gray-400" x-text="'↔ Tinggal kelas: tingkat tidak berubah (' + tingkatAsal + ')'"></p>
                                            <p x-show="selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Tingkat tidak wajar: dari tingkat ' + tingkatAsal + ' ke ' + tingkatTujuan + ' — periksa kembali pilihan kelas tujuan'"></p>
                                        </td>
                                        <td class="px-4 py-3.5">
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
                        <x-primary-button type="submit" x-bind:disabled="submitting">Proses Kenaikan Kelas</x-primary-button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
