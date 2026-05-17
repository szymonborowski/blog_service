<?php

namespace App\Observers;

use App\Events\PostStatusChanged;
use App\Models\Post;

class PostObserver
{
    public function updated(Post $post): void
    {
        if (!$post->wasChanged('status')) {
            return;
        }

        PostStatusChanged::dispatch($post, $post->status);
    }
}
