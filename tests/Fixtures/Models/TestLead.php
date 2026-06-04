<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestLead extends Model
{
    use SoftDeletes;

    protected $table = 'test_leads';

    protected $fillable = [
        'name',
        'email',
        'source',
        'status',
    ];
}
