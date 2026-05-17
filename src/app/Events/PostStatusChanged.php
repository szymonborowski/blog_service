<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Foundation\Events\Dispatchable;

class PostStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Post   $post,
        public readonly string $newStatus,
    ) {}
}
