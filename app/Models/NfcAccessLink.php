<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'expires_at', 'revoked_at', 'scope'])]
class NfcAccessLink extends Model
{
    public const SCOPES = ['view', 'edit', 'c', 'u', 'd', 'cu', 'cd', 'ud', 'cud'];

    public function permissions(): array
    {
        return match ($this->scope ?? 'edit') {
            'edit', 'cud' => ['create' => true, 'update' => true, 'delete' => true],
            'c', 'u', 'd', 'cu', 'cd', 'ud' => [
                'create' => str_contains($this->scope, 'c'),
                'update' => str_contains($this->scope, 'u'),
                'delete' => str_contains($this->scope, 'd'),
            ],
            default => ['create' => false, 'update' => false, 'delete' => false],
        };
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
