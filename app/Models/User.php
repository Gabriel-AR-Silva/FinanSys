<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function pockets(): HasMany
    {
        return $this->hasMany(Pocket::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }

    public function financialEvaluations(): HasMany
    {
        return $this->hasMany(FinancialEvaluation::class);
    }

    public function internalAlerts(): HasMany
    {
        return $this->hasMany(InternalAlert::class);
    }

    public function patrimonialAssets(): HasMany
    {
        return $this->hasMany(PatrimonialAsset::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify((new QueuedResetPassword($token))->afterCommit());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
