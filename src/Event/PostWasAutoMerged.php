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

use Flarum\Post\CommentPost;
use Flarum\User\User;

/**
 * A reply was merged into its author's previous post instead of being
 * created as a post of its own.
 */
class PostWasAutoMerged
{
    public function __construct(
        // The post the reply was merged into.
        public CommentPost $post,
        public User $actor
    ) {
    }
}
