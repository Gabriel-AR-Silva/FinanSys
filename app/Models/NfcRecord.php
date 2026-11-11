<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'kind', 'record_key', 'payload'])]
class NfcRecord extends Model
{
    public const COMPANY = 'company';

    public const PRODUCTION = 'production';

    public const MANUFACTURER = 'manufacturer';

    public const INITIAL_PRODUCTION = 'initial_production';

    public const NOTICE = 'notice';

    public const HISTORY = 'history';

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
