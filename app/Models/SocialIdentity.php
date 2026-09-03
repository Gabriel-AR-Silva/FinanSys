<?php

namespace App\Models;

use App\Enums\SocialProvider;
use Database\Factories\SocialIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'provider', 'provider_user_id', 'email', 'linked_at'])]
class SocialIdentity extends Model
{
    /** @use HasFactory<SocialIdentityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'linked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
