<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_public_api_identity_keeps_its_existing_response_contract(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'NUVIKOZ API')
            ->assertJsonPath('data.version', 'v1');
    }
}
