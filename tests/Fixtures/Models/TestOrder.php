<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestOrder extends Model
{
    use SoftDeletes;

    protected $table = 'test_orders';

    protected $fillable = [
        'total',
        'status',
        'paid_at',
        'user_id',
    ];

    protected $casts = [
        'total' => 'float',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TestOrderItem::class, 'order_id');
    }
}
