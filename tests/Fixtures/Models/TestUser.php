<?php

namespace Ashrafic\FilamentAutomationBridge\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestUser extends Model
{
    use SoftDeletes;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'is_visible',
        'score',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_visible' => 'boolean',
        'score' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(TestOrder::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TestProfile::class, 'user_id');
    }
}
