<?php

namespace Tests\Unit;

use App\Models\WebauthnCredential;
use Tests\TestCase;

class WebauthnCredentialTest extends TestCase
{
    public function test_it_generates_credential_id_hash_with_sha512(): void
    {
        $credentialId = 'credential-id-value';

        $this->assertSame(
            hash('sha512', $credentialId),
            WebauthnCredential::makeCredentialIdHash($credentialId)
        );

        $this->assertSame(128, strlen(WebauthnCredential::makeCredentialIdHash($credentialId)));
    }

    public function test_credential_id_is_encrypted_on_the_model(): void
    {
        $credential = new WebauthnCredential([
            'credential_id' => 'real-credential-id',
        ]);

        $this->assertNotSame('real-credential-id', $credential->getAttributes()['credential_id']);
        $this->assertSame('real-credential-id', $credential->credential_id);
    }

    public function test_public_key_is_stored_without_hashing(): void
    {
        $credential = new WebauthnCredential([
            'public_key' => 'public-key-value',
        ]);

        $this->assertSame('public-key-value', $credential->getAttributes()['public_key']);
    }

    public function test_sensitive_webauthn_fields_are_hidden(): void
    {
        $credential = new WebauthnCredential([
            'credential_id' => 'real-credential-id',
            'credential_id_hash' => WebauthnCredential::makeCredentialIdHash('real-credential-id'),
            'public_key' => 'public-key-value',
        ]);

        $payload = $credential->toArray();

        $this->assertArrayNotHasKey('credential_id', $payload);
        $this->assertArrayNotHasKey('credential_id_hash', $payload);
        $this->assertArrayNotHasKey('public_key', $payload);
    }
}
