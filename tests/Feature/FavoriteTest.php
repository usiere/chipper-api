<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_favorite_a_post()
    {
        $post = Post::factory()->create();

        $this->postJson(route('favorites.store', ['post' => $post]))
            ->assertStatus(401);
    }

    public function test_a_user_can_favorite_a_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_remove_a_post_from_his_favorites()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_not_remove_a_non_favorited_item()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNotFound();
    }

    public function test_a_user_can_favorite_another_user()
    {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author]))
            ->assertOk()
            ->assertJson(['message' => 'User favorited successfully']);

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_unfavorite_a_user()
    {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author]))
            ->assertOk();

        $this->assertDatabaseHas('favorites', [
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('users.unfavorite', ['user' => $author]))
            ->assertOk()
            ->assertJson(['message' => 'User removed from favorites']);

        $this->assertDatabaseMissing('favorites', [
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_cannot_favorite_themselves()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $user]))
            ->assertStatus(422)
            ->assertJson(['message' => 'You cannot favorite yourself']);

        $this->assertDatabaseMissing('favorites', [
            'favoritable_id' => $user->id,
            'favoritable_type' => User::class,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_guest_cannot_favorite_a_user()
    {
        $author = User::factory()->create();

        $this->postJson(route('users.favorite', ['user' => $author]))
            ->assertStatus(401);
    }

    public function test_a_user_cannot_favorite_same_user_twice()
    {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author]))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author]))
            ->assertStatus(422)
            ->assertJson(['message' => 'Already favorited']);
    }

    public function test_a_user_cannot_unfavorite_non_favorited_user()
    {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('users.unfavorite', ['user' => $author]))
            ->assertStatus(404)
            ->assertJson(['message' => 'User was not in favorites']);
    }
}
