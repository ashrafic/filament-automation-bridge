<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestOrderItem extends Model
{
    protected $table = 'test_order_items';

    protected $fillable = [
        'name',
        'price',
        'quantity',
        'order_id',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TestOrder::class, 'order_id');
    }
}
