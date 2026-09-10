<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_submission_is_not_available(): void
    {
        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'someone@worktrack.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
