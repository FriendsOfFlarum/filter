<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Post\Command\PostReplyHandler;
use FoF\Filter\Handler\AutoMergePostReplyHandler;

class AutoMergeServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->extend(PostReplyHandler::class, function (PostReplyHandler $handler, $container) {
            return $container->make(AutoMergePostReplyHandler::class, ['original' => $handler]);
        });
    }
}
