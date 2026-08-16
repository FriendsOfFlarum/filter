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

use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;

/**
 * Applies the word filter to discussion titles.
 *
 * The post listener only ever sees a post's content, so a filtered word placed
 * in the title alone used to go straight through.
 */
class CheckDiscussionTitle
{
    public function __construct(protected CheckPost $checkPost)
    {
    }

    public function handle(Saving $event): void
    {
        $discussion = $event->discussion;

        if (!$discussion->isDirty('title')) {
            return;
        }

        if ($event->actor->can('bypassFoFFilter', $discussion)) {
            return;
        }

        if (!$this->checkPost->checkContent($discussion->title)) {
            return;
        }

        $this->moderateFirstPostAfterSave($discussion);
    }

    /**
     * The first post carries the approval state and the flag that moderators
     * act on, so a filtered title is moderated through it.
     *
     * When a discussion is started the post does not exist yet at the point
     * the discussion is first saved: core creates it afterwards and saves the
     * discussion a second time to attach it. So if there is no post to act on
     * yet, wait for that next save.
     */
    protected function moderateFirstPostAfterSave(Discussion $discussion): void
    {
        $discussion->afterSave(function (Discussion $discussion) {
            $post = $discussion->firstPost ?? $discussion->posts()->where('number', 1)->first();

            if ($post === null) {
                $this->moderateFirstPostAfterSave($discussion);

                return;
            }

            if ($post->auto_mod) {
                return;
            }

            $this->checkPost->moderate($post);
            $post->save();
        });
    }
}
