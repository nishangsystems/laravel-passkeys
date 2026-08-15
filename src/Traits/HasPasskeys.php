<?php

namespace NishangSystems\Passkeys\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use NishangSystems\Passkeys\Models\Passkey;

trait HasPasskeys
{
    public function passkeys(): MorphMany
    {
        return $this->morphMany(Passkey::class, 'owner');
    }
}
