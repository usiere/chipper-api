<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_imports_users_from_json_url()
    {
        Http::fake([
            'https://example.com/users' => Http::response([
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ], 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://example.com/users',
            '--limit' => 10,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_it_respects_the_limit_parameter()
    {
        Http::fake([
            'https://example.com/users' => Http::response([
                ['name' => 'User 1', 'email' => 'user1@example.com'],
                ['name' => 'User 2', 'email' => 'user2@example.com'],
                ['name' => 'User 3', 'email' => 'user3@example.com'],
            ], 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://example.com/users',
            '--limit' => 2,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'user1@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'user2@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'user3@example.com']);
    }

    public function test_it_handles_invalid_url()
    {
        Http::fake([
            'https://invalid.com/users' => Http::response(null, 404),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://invalid.com/users',
            '--limit' => 5,
        ])->assertFailed();
    }

    public function test_it_skips_duplicate_emails()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        Http::fake([
            'https://example.com/users' => Http::response([
                ['name' => 'Existing User', 'email' => 'existing@example.com'],
                ['name' => 'New User', 'email' => 'new@example.com'],
            ], 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://example.com/users',
            '--limit' => 10,
        ])->assertSuccessful();

        $this->assertEquals(1, User::where('email', 'existing@example.com')->count());
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_it_requires_limit_parameter()
    {
        $this->artisan('import:users', [
            'url' => 'https://example.com/users',
        ])->assertFailed();
    }
}