<?php

use App\Domains\Keuangan\Enums\KategoriTagihan;

it('reports isPpdb true only for pendaftaran and daftar_ulang', function () {
    expect(KategoriTagihan::Pendaftaran->isPpdb())->toBeTrue();
    expect(KategoriTagihan::DaftarUlang->isPpdb())->toBeTrue();
    expect(KategoriTagihan::Spp->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Tahunan->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Kegiatan->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Custom->isPpdb())->toBeFalse();
    expect(KategoriTagihan::Lainnya->isPpdb())->toBeFalse();
});

it('has a label for every case', function () {
    expect(KategoriTagihan::Pendaftaran->label())->toBe('Pendaftaran');
    expect(KategoriTagihan::DaftarUlang->label())->toBe('Daftar Ulang');
    expect(KategoriTagihan::Spp->label())->toBe('SPP');
    expect(KategoriTagihan::Tahunan->label())->toBe('Tahunan');
    expect(KategoriTagihan::Kegiatan->label())->toBe('Kegiatan');
    expect(KategoriTagihan::Custom->label())->toBe('Custom');
    expect(KategoriTagihan::Lainnya->label())->toBe('Lainnya');
});
