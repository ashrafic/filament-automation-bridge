<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestProfile extends Model
{
    protected $table = 'test_profiles';

    protected $fillable = [
        'bio',
        'avatar_url',
        'phone',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}
