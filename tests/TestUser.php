<?php

namespace NishangSystems\Passkeys\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NishangSystems\Passkeys\Traits\HasPasskeys;

class TestUser extends Authenticatable
{
    use HasPasskeys;

    protected $table = 'test_users';

    protected $fillable = ['name', 'email'];
}
