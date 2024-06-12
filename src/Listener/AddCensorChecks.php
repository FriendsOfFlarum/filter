<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Listener;

use Flarum\Settings\Event\Saving;
use FoF\Filter\CensorGenerator;
use Illuminate\Contracts\Cache\Store as Cache;

class AddCensorChecks
{
    /**
     * @var Cache
     */
    protected $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function handle(Saving $event)
    {
        if (isset($event->settings['fof-filter.words'])) {
            $this->cache->forever('fof-filter.censors', json_encode(
                CensorGenerator::generateCensors($event->settings['fof-filter.words'])
            ));
        }
    }
}
