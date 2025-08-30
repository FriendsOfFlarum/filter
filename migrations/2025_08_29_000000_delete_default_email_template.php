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
    'up' => static function (Builder $schema) {
        $defaultSubject = 'Regarding Your Recent Post';
        $defaultText = <<<'EOT'
Thank you for posting! Unfortunately, your post was removed because it contained something we don't want to see on our forums.

If you believe this was an error, you are in luck, each removed post is reviewed by our moderators. If they find that this was wrongfully removed, they will restore it.

Thank you for your understanding.
EOT;

        // Helper method to normalize text for comparison
        $normalizeText = static function (?string $text): array {
            $lines = explode("\n", $text);
            $trimmedLines = array_map('trim', $lines);

            $filteredLines = array_filter($trimmedLines, static function ($line) {
                return !empty(trim($line));
            });

            // Convert to lowercase for case-insensitive comparison and re-index
            return array_values(array_map('strtolower', $filteredLines));
        };

        $isEqualNormalized = static function (string $default, string $setting) use ($normalizeText): bool {
            return $setting && $normalizeText($default) === $normalizeText($setting);
        };

        // If the setting exists and matches the old default, delete it
        // so it can take advantage of translations.

        $db = $schema->getConnection();

        $subjectSetting = $db->table('settings')
            ->where('key', 'fof-filter.flaggedSubject')
            ->first();

        $emailSetting = $db->table('settings')
            ->where('key', 'fof-filter.flaggedEmail')
            ->first();

        if ($subjectSetting && $isEqualNormalized($defaultSubject, $subjectSetting->value)) {
            $db->table('settings')
                ->where('key', 'fof-filter.flaggedSubject')
                ->delete();
        }

        if ($emailSetting && $isEqualNormalized($defaultText, $emailSetting->value)) {
            $db->table('settings')
                ->where('key', 'fof-filter.flaggedEmail')
                ->delete();
        }
    },
    'down' => static function ($schema) {
        // No need to reverse
    },
];
