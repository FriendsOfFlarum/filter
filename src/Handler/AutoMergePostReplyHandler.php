<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Handler;

use Flarum\Discussion\DiscussionRepository;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Post\Command\PostReply;
use Flarum\Post\Command\PostReplyHandler;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use Flarum\Post\PostRepository;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class AutoMergePostReplyHandler
{
    use DispatchEventsTrait;

    protected PostReplyHandler $original;
    protected SettingsRepositoryInterface $settings;
    protected PostRepository $posts;
    protected DiscussionRepository $discussions;

    public function __construct(
        PostReplyHandler $original,
        SettingsRepositoryInterface $settings,
        PostRepository $posts,
        DiscussionRepository $discussions,
        Dispatcher $events
    ) {
        $this->original = $original;
        $this->settings = $settings;
        $this->posts = $posts;
        $this->discussions = $discussions;
        $this->events = $events;
    }

    /**
     * @throws PermissionDeniedException
     */
    public function handle(PostReply $command): CommentPost
    {
        if (
            !$this->settings->get('fof-filter.autoMergePosts')
            || $command->isFirstPost
        ) {
            return $this->original->handle($command);
        }

        $actor = $command->actor;
        $discussion = $this->discussions->findOrFail(
            $command->discussionId,
            $actor
        );

        $lastPost = $this->posts->query()
            ->where('discussion_id', '=', $discussion->id)
            ->whereNull('hidden_at')
            ->orderBy('number', 'desc')
            ->first();

        $cooldown = (int) $this->settings->get('fof-filter.cooldown');
        if (
            !$lastPost instanceof CommentPost
            || $lastPost->user_id !== $actor->id
            || $lastPost->auto_mod
            || ($cooldown > 0 && $lastPost->created_at->lessThanOrEqualTo(Carbon::now()->subMinutes($cooldown)))
        ) {
            return $this->original->handle($command);
        }

        $actor->assertCan('reply', $discussion);

        $newContent = Arr::get($command->data, 'attributes.content', '');
        $mergedContent = $lastPost->content."\n\n".$newContent;

        $lastPost->revise($mergedContent, $actor);

        $this->events->dispatch(
            new Saving($lastPost, $actor, [
                'attributes' => ['content' => $mergedContent],
            ])
        );

        $lastPost->save();
        $this->dispatchEventsFor($lastPost, $actor);

        return $lastPost;
    }
}
