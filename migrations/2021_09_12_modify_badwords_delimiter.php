<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        /**
         * @var \Flarum\Settings\SettingsRepositoryInterface
         */
        $settings = resolve('flarum.settings');

        if (!empty($words = $settings->get('fof-filter.words', null))) {
            $words = str_replace(', ', PHP_EOL, $words);
            $settings->set('fof-filter.words', $words);
        }
    },
    'down' => function (Builder $schema) {
        /**
         * @var \Flarum\Settings\SettingsRepositoryInterface
         */
        $settings = resolve('flarum.settings');

        if (!empty($words = $settings->get('fof-filter.words', null))) {
            $words = str_replace(PHP_EOL, ', ', $words);
            $settings->set('fof-filter.words', $words);
        }
    },
];
