<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens';

    /**
     * Get the user that owns this token (One-to-One inverse).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'tokenable_id')
            ->where('tokenable_type', User::class);
    }
}

