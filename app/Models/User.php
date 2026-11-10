<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'password_changed_at',
        'role',
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
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Platform superusers do not have an assigned vocational school
            if ($user->role === 'superuser') {
                return;
            }

            // Tenant accounts (admin_sekolah, admin, guru, siswa) must have explicit school_id
            if (empty($user->school_id)) {
                throw new \InvalidArgumentException('Akun pengguna institusi ('.($user->role ?? 'tenant').') wajib memiliki asosiasi sekolah (school_id).');
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function studentReports(): HasMany
    {
        return $this->hasMany(StudentReport::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentHistory::class);
    }

    public function isSuperuser(): bool
    {
        return $this->role === 'superuser';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'admin_sekolah'], true);
    }

    public function isSchoolAdmin(): bool
    {
        return in_array($this->role, ['admin', 'admin_sekolah'], true);
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    // Alias untuk kompatibilitas jika dibutuhkan
    public function isDosen(): bool
    {
        return $this->isGuru();
    }

    public function isMahasiswa(): bool
    {
        return $this->isSiswa();
    }

    public function isTeacher(): bool
    {
        return $this->isGuru();
    }

    public function isStudent(): bool
    {
        return $this->isSiswa();
    }
}
