<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPA shell should respond successfully without requiring a Vite build artifact.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
