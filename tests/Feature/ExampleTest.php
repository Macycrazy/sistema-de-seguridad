<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Los permisos viven en la base desde la parte 3, y el gate que abre el inicio los consulta.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->entrandoComo();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
