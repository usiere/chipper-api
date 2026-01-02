<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Notifications\NewPostFromFavoriteUser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
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

    public function test_favorites_index_returns_structured_response_with_posts_and_users()
    {
        $user = User::factory()->create();
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        $author1 = User::factory()->create();
        $author2 = User::factory()->create();

        // Favorite some posts and users
        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post1]));
        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post2]));
        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author1]));
        $this->actingAs($user)
            ->postJson(route('users.favorite', ['user' => $author2]));

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'posts' => [
                        '*' => [
                            'id',
                            'title',
                            'body',
                            'user' => [
                                'id',
                                'name',
                                'email'
                            ]
                        ]
                    ],
                    'users' => [
                        '*' => [
                            'id',
                            'name',
                            'email'
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');

        // Check that we have the correct number of items
        $this->assertCount(2, $data['posts']);
        $this->assertCount(2, $data['users']);

        // Check that posts include user information
        $this->assertArrayHasKey('user', $data['posts'][0]);
        $this->assertArrayHasKey('user', $data['posts'][1]);
    }

    public function test_favorites_index_returns_empty_arrays_when_no_favorites()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk()
            ->assertJson([
                'data' => [
                    'posts' => [],
                    'users' => []
                ]
            ]);
    }

    public function test_followers_are_notified_when_favorited_user_creates_post()
    {
        Notification::fake();

        // Create users
        $author = User::factory()->create();
        $follower = User::factory()->create();

        // Follower favorites author
        $follower->favorites()->create([
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates post
        $this->actingAs($author)->postJson('/api/posts', [
            'title' => 'Test Post',
            'body' => 'Test body content',
        ]);

        // Assert notification was sent
        Notification::assertSentTo($follower, NewPostFromFavoriteUser::class);
    }

    public function test_non_followers_are_not_notified_when_user_creates_post()
    {
        Notification::fake();

        $author = User::factory()->create();
        $nonFollower = User::factory()->create();

        $this->actingAs($author)->postJson('/api/posts', [
            'title' => 'Test Post',
            'body' => 'Test body content',
        ]);

        Notification::assertNotSentTo($nonFollower, NewPostFromFavoriteUser::class);
    }

    public function test_multiple_followers_are_notified_when_favorited_user_creates_post()
    {
        Notification::fake();

        $author = User::factory()->create();
        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();

        // Both users favorite the author
        $follower1->favorites()->create([
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);
        $follower2->favorites()->create([
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates post
        $this->actingAs($author)->postJson('/api/posts', [
            'title' => 'Test Post',
            'body' => 'Test body content',
        ]);

        // Both followers should be notified
        Notification::assertSentTo($follower1, NewPostFromFavoriteUser::class);
        Notification::assertSentTo($follower2, NewPostFromFavoriteUser::class);
    }
}
