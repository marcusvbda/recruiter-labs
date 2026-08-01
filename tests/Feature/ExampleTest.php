<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_the_admin_panel(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect('/admin');
    }
}
