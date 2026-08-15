<?php

namespace NishangSystems\Passkeys\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Cose\Algorithm\Manager;

class Passkey extends Model
{
    protected static ?SerializerInterface $webAuthnSerializer = null;

    protected static ?AttestationStatementSupportManager $attestationStatementSupportManager = null;

    protected $fillable = [
        'name',
        'credential_id',
        'data',
        'owner_type',
        'owner_id',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    public function getCredential(): CredentialRecord
    {
        return $this->webAuthnSerializer()->deserialize(
            $this->data,
            CredentialRecord::class,
            'json'
        );
    }

    public static function webAuthnSerializer(): SerializerInterface
    {
        if (self::$webAuthnSerializer === null) {
            self::$webAuthnSerializer = (new WebauthnSerializerFactory(
                self::attestationStatementSupportManager()
            ))->create();
        }

        return self::$webAuthnSerializer;
    }

    public static function attestationStatementSupportManager(): AttestationStatementSupportManager
    {
        if (self::$attestationStatementSupportManager === null) {
            $manager = new AttestationStatementSupportManager();
            $manager->add(new NoneAttestationStatementSupport());
            $attestations = config('passkeys.attestations', []);
            if (in_array('packed', $attestations, true)) {
                $manager->add(new PackedAttestationStatementSupport(new Manager()));
            }
            if (in_array('fido', $attestations, true)) {
                $manager->add(new FidoU2FAttestationStatementSupport());
            }
            self::$attestationStatementSupportManager = $manager;
        }

        return self::$attestationStatementSupportManager;
    }
}
