<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    // MustVerifyEmailTrait is the actual working code (sendEmailVerificationNotification,
    // hasVerifiedEmail, etc.) — the `implements MustVerifyEmail` above is just the
    // interface/contract. You need both; the interface alone has no real behavior.
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'first_name',
        'middle_initial',
        'last_name',
        'email',
        'password',
        'user_type',
        'contact_number',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function parentProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ParentAccount::class, 'user_id');
    }

    /**
     * Where this person's own photo is shown, or null when they have none. The version in the address changes with the photo (so a
     * browser never keeps an old picture). Only the person it belongs to can open it; see UserPhotoController.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_version ? route('photos.user', ['user' => $this->id, 'v' => $this->photo_version]) : null;
    }
}
