<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Resource\PostResource;
use Flarum\Bus\Dispatcher as BusDispatcher;
use Flarum\Foundation\ErrorHandling\LogReporter;
use Flarum\Locale\TranslatorInterface;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\Post\PostRepository;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Merges a reply into the author's immediately preceding post when it is made
 * within the configured cooldown window.
 *
 * In Flarum 1.x this was done by decorating `PostReplyHandler` through the
 * command bus. The bus was removed in 2.0, so post creation is intercepted
 * here in the API resource layer instead.
 */
class AutoMergePostResource extends PostResource
{
    public function __construct(
        PostRepository $posts,
        TranslatorInterface $translator,
        LogReporter $log,
        BusDispatcher $bus,
        protected SettingsRepositoryInterface $settings,
        protected EventDispatcher $events
    ) {
        parent::__construct($posts, $translator, $log, $bus);
    }

    /**
     * @param Post $model
     * @param Context $context
     */
    public function create(object $model, \Tobyz\JsonApiServer\Context $context): object
    {
        $previous = $this->postToMergeInto($model, $context);

        if ($previous === null) {
            return parent::create($model, $context);
        }

        // `content` was already set onto the new (unsaved) post by the resource's
        // field setter, so we read it back off rather than from the request body.
        $mergedContent = $previous->content."\n\n".$model->content;
        $actor = $context->getActor();

        $previous->revise($mergedContent, $actor);

        $this->events->dispatch(
            new Saving($previous, $actor, [
                'attributes' => ['content' => $mergedContent],
            ])
        );

        $previous->save();

        return $previous;
    }

    /**
     * The actor's last post in this discussion, if this reply should be merged
     * into it. Returns null when the reply should be created as a new post.
     *
     * @param Post $model the new, unsaved post
     */
    protected function postToMergeInto(Post $model, Context $context): ?CommentPost
    {
        if (! $model instanceof CommentPost) {
            return null;
        }

        if (! $this->settings->get('fof-filter.autoMergePosts')) {
            return null;
        }

        // The first post of a discussion has nothing before it to merge into.
        if ($context->internal('isFirstPost')) {
            return null;
        }

        // A reply carrying a poll is left as its own post to avoid detaching the poll.
        if (Arr::has($context->body(), 'data.attributes.poll')) {
            return null;
        }

        $actor = $context->getActor();

        $lastPost = $this->posts->query()
            ->where('discussion_id', '=', $model->discussion_id)
            ->whereNull('hidden_at')
            ->orderBy('number', 'desc')
            ->first();

        if (
            ! $lastPost instanceof CommentPost
            || $lastPost->user_id !== $actor->id
            // Never merge into a post the filter has flagged or auto-moderated.
            || $lastPost->auto_mod
        ) {
            return null;
        }

        $cooldown = (int) $this->settings->get('fof-filter.cooldown');

        // A cooldown of 0 means "always merge, regardless of age".
        if ($cooldown > 0 && $lastPost->created_at->lessThanOrEqualTo(Carbon::now()->subMinutes($cooldown))) {
            return null;
        }

        return $lastPost;
    }
}
