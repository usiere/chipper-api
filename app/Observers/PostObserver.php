<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostFromFavoriteUser;
use Illuminate\Support\Facades\Notification;

class PostObserver
{
    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        // Get users who have favorited the post author
        $followers = User::whereHas('favorites', function ($query) use ($post) {
            $query->where('favoritable_id', $post->user_id)
                  ->where('favoritable_type', User::class);
        })->get();

        // Notify each follower
        Notification::send($followers, new NewPostFromFavoriteUser($post));
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "force deleted" event.
     */
    public function forceDeleted(Post $post): void
    {
        //
    }
}
