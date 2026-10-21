<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter;

use Flarum\Audit\Extend\Audit;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Extend;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Post\Post;
use Flarum\Settings\Event\Saving as SettingSaving;
use FoF\Filter\Event\PostWasAutoMerged;
use FoF\Filter\Event\PostWasFiltered;
use FoF\Filter\Listener\AddCensorChecks;
use FoF\Filter\Listener\CheckDiscussionTitle;
use FoF\Filter\Listener\CheckPost;
use FoF\Filter\Provider\AutoMergeServiceProvider;

// The matched string and the post's content are left out: the log keeps
// payloads, and the post itself is linked.
$filtered = fn (PostWasFiltered $event) => [
    'post_id'       => $event->post->id,
    'discussion_id' => $event->post->discussion_id,
    'user_id'       => $event->post->user_id,
    'source'        => $event->inTitle ? 'title' : 'content',
    'emailed'       => $event->emailed,
];

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\View())
        ->namespace('fof-filter', __DIR__.'/views'),

    (new Extend\Model(Post::class))
        ->cast('auto_mod', 'bool')
        ->cast('emailed', 'bool'),

    (new Extend\Event())
        ->listen(SettingSaving::class, AddCensorChecks::class)
        ->listen(PostSaving::class, CheckPost::class)
        ->listen(DiscussionSaving::class, CheckDiscussionTitle::class),

    (new Extend\ServiceProvider())
        ->register(AutoMergeServiceProvider::class),

    (new Extend\Settings())
        ->default('fof-filter.autoDeletePosts', false)
        ->default('fof-filter.autoMergePosts', false)
        ->default('fof-filter.cooldown', 15)
        ->default('fof-filter.emailWhenFlagged', false),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-audit', fn () => [
            (new Audit())
                ->listen(PostWasFiltered::class, 'fof-filter.post_flagged', fn (PostWasFiltered $event) => $event->deleted ? null : $filtered($event))
                ->listen(PostWasFiltered::class, 'fof-filter.post_deleted', fn (PostWasFiltered $event) => $event->deleted ? $filtered($event) : null)
                ->listen(PostWasAutoMerged::class, 'fof-filter.post_auto_merged', fn (PostWasAutoMerged $event) => [
                    'post_id'       => $event->post->id,
                    'discussion_id' => $event->post->discussion_id,
                ]),
        ]),
];
