<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class AdminUser extends TestUser implements AuthenticatableContract, FilamentUser
{
    use Authenticatable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
