<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        /**
         * @var \Flarum\Settings\SettingsRepositoryInterface
         */
        $settings = resolve('flarum.settings');

        if (!empty($words = $settings->get('fof-filter.words', null))) {
            $words = str_replace(', ', "\n", $words);
            $settings->set('fof-filter.words', $words);
        }
    },
    'down' => function (Builder $schema) {
        /**
         * @var \Flarum\Settings\SettingsRepositoryInterface
         */
        $settings = resolve('flarum.settings');

        if (!empty($words = $settings->get('fof-filter.words', null))) {
            $words = str_replace(["\n", PHP_EOL], ', ', $words);
            $settings->set('fof-filter.words', $words);
        }
    },
];
