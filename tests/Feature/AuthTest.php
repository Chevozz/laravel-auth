<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_revokes_only_current_token_and_errors_are_json(): void
    {
        User::factory()->create(['email' => 'login@example.test', 'password' => 'password123']);
        $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $first = $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'password123'])->assertOk()->json('data.token');
        $second = $this->postJson('/api/login', ['email' => 'login@example.test', 'password' => 'password123'])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->get('/api/me')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/me')->assertOk();
    }

    public function test_registration_validation_and_rate_limit(): void
    {
        $this->post('/api/register', ['name' => 'Name', 'email' => 'bad', 'password' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        User::factory()->create(['email' => 'used@example.test']);
        $this->postJson('/api/register', ['name' => 'Name', 'email' => 'used@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/login', ['email' => 'none@example.test', 'password' => 'wrong']);
        }
        $response->assertStatus(429);
    }

    public function test_registration_returns_safe_user_and_real_bearer_token(): void
    {
        $response = $this->postJson('/api/register', ['name' => 'Jeda', 'email' => 'jeda@example.test', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $response->assertCreated()->assertJsonPath('data.user.email', 'jeda@example.test')->assertJsonMissingPath('data.user.password');
        $this->assertTrue(Hash::check('password123', User::first()->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/me')->assertOk()->assertJsonPath('data.email', 'jeda@example.test');
    }
}
