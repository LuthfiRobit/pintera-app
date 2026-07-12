<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Activitylog\Models\Activity;

it('logs role changes without leaking anything sensitive (roles have no sensitive columns)', function () {
    $role = Role::create(['name' => 'logged-role', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    $role->update(['scope_level' => 'lembaga', 'name' => 'logged-role-renamed']);

    $activity = Activity::where('subject_type', Role::class)->where('subject_id', $role->id)->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->toArray())->toHaveKey('attributes');
});

it('logs yayasan changes without leaking npwp_yayasan', function () {
    $yayasan = Yayasan::create(['nama' => 'Yayasan Uji', 'npwp_yayasan' => '01.111.111.1-111.000']);

    $activity = Activity::where('subject_type', Yayasan::class)->where('subject_id', $yayasan->id)->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->toArray()['attributes'])->not->toHaveKey('npwp_yayasan');
});

it('logs lembaga changes without leaking nomor_rekening or npwp', function () {
    $yayasan = Yayasan::factory()->create();

    $lembaga = Lembaga::create([
        'yayasan_id' => $yayasan->id,
        'npsn' => '11112222',
        'nama' => 'SD Uji Log',
        'bentuk_pendidikan' => 'SD',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
        'nomor_rekening' => '999888777',
        'npwp' => '03.456.789.0-123.000',
    ]);

    $activity = Activity::where('subject_type', Lembaga::class)->where('subject_id', $lembaga->id)->latest()->first();

    expect($activity)->not->toBeNull();
    $attributes = $activity->properties->toArray()['attributes'];
    expect($attributes)->not->toHaveKey('nomor_rekening');
    expect($attributes)->not->toHaveKey('npwp');
});

it('logs guru changes without leaking nik or nik_hash', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $guru = Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890222',
        'nama' => 'Guru Log Uji',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $activity = Activity::where('subject_type', Guru::class)->where('subject_id', $guru->id)->latest()->first();

    expect($activity)->not->toBeNull();
    $attributes = $activity->properties->toArray()['attributes'];
    expect($attributes)->not->toHaveKey('nik');
    expect($attributes)->not->toHaveKey('nik_hash');
    expect($attributes)->toHaveKey('nama');
});
