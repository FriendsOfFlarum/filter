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

use Flarum\Api\Resource\PostResource;
use Flarum\Foundation\AbstractServiceProvider;
use FoF\Filter\Api\Resource\AutoMergePostResource;

class AutoMergeServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // API resources are resolved from the container by `flarum.api.resource_handler`,
        // so swapping the binding is enough for our subclass to be used everywhere.
        $this->container->bind(PostResource::class, AutoMergePostResource::class);
    }
}
