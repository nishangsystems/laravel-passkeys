<?php

namespace NishangSystems\Passkeys\Contracts;

interface PasskeyUser
{
    /**
     * Determine whether the user may authenticate with a passkey.
     */
    public function canAuthenticateWithPasskey(): bool;
}
