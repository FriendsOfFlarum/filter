<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Event;

use Flarum\Post\Post;

/**
 * A post tripped the filter, and has been held for approval or deleted.
 *
 * Dispatched once the post is saved, so a new post has its ID.
 */
class PostWasFiltered
{
    public function __construct(
        public Post $post,
        public bool $deleted,
        // The filtered string was in the discussion's title, not the post.
        public bool $inTitle,
        // The author was emailed about it.
        public bool $emailed
    ) {
    }
}
