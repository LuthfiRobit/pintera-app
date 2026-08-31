<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domains\Identity\Models\Person;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'lembaga_id',
        'yayasan_id',
        'is_active',
        'must_change_password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function guru(): HasOneThrough
    {
        return $this->hasOneThrough(
            Guru::class,
            Person::class,
            'user_id',
            'person_id',
            'id',
            'id'
        )->withoutGlobalScope(TenantScope::class);
    }

    public function orangTua(): HasOneThrough
    {
        return $this->hasOneThrough(
            OrangTua::class,
            Person::class,
            'user_id',
            'person_id',
            'id',
            'id'
        )->withoutGlobalScope(TenantScope::class);
    }

    public function siswa(): HasOneThrough
    {
        return $this->hasOneThrough(
            Siswa::class,
            Person::class,
            'user_id',
            'person_id',
            'id',
            'id'
        )->withoutGlobalScope(TenantScope::class);
    }

    public function karyawan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Karyawan::class,
            Person::class,
            'user_id',
            'person_id',
            'id',
            'id'
        )->withoutGlobalScope(TenantScope::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class)->where('module', 'finance');
    }

    public function widestScopeLevel(): string
    {
        $levels = $this->roles->pluck('scope_level');

        return match (true) {
            $levels->contains('platform') => 'platform',
            $levels->contains('yayasan') || $this->hasRole(['yayasan_super_admin', 'super_admin', 'bendahara_yayasan']) => 'yayasan',
            $levels->contains('lembaga') => 'lembaga',
            default => 'diri_sendiri',
        };
    }

    /**
     * Role fungsional user (mengecualikan pegawai_lembaga/pegawai_yayasan --
     * role scope-carrier yang bukan identitas pekerjaan, murni penentu
     * widestScopeLevel()). Dipakai untuk tampilan UI (daftar Pengguna, form
     * edit) supaya tidak menampilkan role teknis yang membingungkan.
     */
    public function functionalRoles(): Collection
    {
        return $this->roles->whereNotIn('name', ['pegawai_lembaga', 'pegawai_yayasan']);
    }

    /**
     * Satu sumber kebenaran untuk kelayakan Bottom Navigation Bar (mobile/tablet) --
     * dipakai bersama oleh bottom-nav.blade.php, app.blade.php (clearance <main>),
     * dan topbar.blade.php (logo vs hamburger) supaya ketiganya tidak bisa berbeda.
     */
    public function hasBottomNav(): bool
    {
        return $this->hasRole('guru') || $this->hasRole('siswa') || $this->orangTua !== null;
    }
}
