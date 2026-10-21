<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With `autoDeletePosts` enabled, only new content is deleted. Content that
 * already exists is held for a moderator instead: deleting a discussion's
 * first post takes the discussion with it, including everyone else's replies.
 */
class AutoDeleteExistingContentTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');
        $this->setting('fof-filter.autoDeletePosts', true);
        $this->setting('allow_renaming', '-1');
        $this->setting('allow_post_editing', '-1');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'replier', 'email' => 'replier@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Existing', 'user_id' => 2, 'first_post_id' => 1, 'last_post_number' => 2, 'comment_count' => 2, 'participant_count' => 2, 'is_approved' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>clean opening post</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>a clean reply from someone else</p></t>', 'number' => 2, 'created_at' => Carbon::now(), 'is_approved' => 1],
            ],
        ]);

        parent::setUp();
    }

    protected function editPost(int $postId, int $authenticatedAs, string $content)
    {
        return $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => $authenticatedAs,
                'json'            => [
                    'data' => [
                        'attributes' => ['content' => $content],
                    ],
                ],
            ])
        );
    }

    protected function assertDiscussionSurvives(): void
    {
        $this->assertNotNull(Discussion::query()->find(1), 'The existing discussion must not be deleted.');
        // A rename adds an event post, so check the seeded posts by ID rather
        // than counting.
        $this->assertEquals(2, Post::query()->whereIn('id', [1, 2])->count(), 'No post in the discussion may be deleted.');
    }

    protected function assertHeldForReview(int $postId): void
    {
        $post = Post::query()->findOrFail($postId);

        $this->assertFalse((bool) $post->is_approved, 'The post should be held for approval.');
        $this->assertEquals(1, Flag::query()->where('post_id', $postId)->where('type', 'autoMod')->count(), 'A moderator should be shown the post.');
    }

    #[Test]
    public function renaming_an_existing_discussion_to_a_filtered_title_does_not_delete_it()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => ['title' => 'renamed to include wibble'],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertDiscussionSurvives();
        $this->assertHeldForReview(1);
        $this->assertFalse((bool) Discussion::query()->findOrFail(1)->is_approved);
    }

    #[Test]
    public function editing_the_first_post_to_a_filtered_word_does_not_delete_the_discussion()
    {
        $response = $this->editPost(1, 2, 'an opening post edited to include wibble');

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertDiscussionSurvives();
        $this->assertHeldForReview(1);
    }

    #[Test]
    public function editing_a_reply_to_a_filtered_word_holds_it_for_review()
    {
        $response = $this->editPost(2, 3, 'a reply edited to include wibble');

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertDiscussionSurvives();
        $this->assertHeldForReview(2);
        $this->assertTrue((bool) Discussion::query()->findOrFail(1)->is_approved, 'Only a first post holds the discussion.');
    }
}
