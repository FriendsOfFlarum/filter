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
 * With `autoDeletePosts` enabled, a filtered post is held unapproved and
 * flagged for moderation is skipped — the discussion is removed outright when
 * the offending post is the first one.
 */
class AutoDeleteTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');
        $this->setting('fof-filter.autoDeletePosts', true);

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Existing', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                [
                    'id'            => 1,
                    'discussion_id' => 1,
                    'user_id'       => 2,
                    'type'          => 'comment',
                    'content'       => '<t><p>clean opening post</p></t>',
                    'number'        => 1,
                    'created_at'    => Carbon::now(),
                    'is_approved'   => 1,
                ],
            ],
        ]);

        parent::setUp();
    }

    #[Test]
    public function first_post_containing_a_bad_word_deletes_the_whole_discussion()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'a new discussion',
                            'content' => 'opening post with wibble in it',
                        ],
                    ],
                ],
            ])
        );

        // The discussion is deleted inside the same request, so there is
        // nothing left to serialize into a 201 response.
        $this->assertEquals(404, $response->getStatusCode());

        // Only the pre-seeded discussion should remain.
        $this->assertNull(
            Discussion::query()->where('title', 'a new discussion')->first(),
            'The offending discussion should have been deleted.'
        );

        $this->assertEquals(1, Discussion::query()->count());
    }

    #[Test]
    public function auto_delete_does_not_flag_the_post_for_moderation()
    {
        $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => 'a reply containing wibble'],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
        );

        // Deleting and flagging are mutually exclusive branches: with
        // auto-delete on, nothing should be queued for moderator review.
        $this->assertEquals(0, Flag::query()->count(), 'No flag should be raised when auto-deleting.');
    }

    #[Test]
    public function a_filtered_reply_is_held_unapproved_but_leaves_the_discussion_intact()
    {
        $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => 'a reply containing wibble'],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
        );

        // The discussion must survive: only a first post takes it down.
        $discussion = Discussion::query()->find(1);
        $this->assertNotNull($discussion, 'A filtered reply must not delete the discussion.');

        $reply = Post::query()->where('discussion_id', 1)->where('number', '>', 1)->first();

        $this->assertNotNull($reply);
        $this->assertFalse((bool) $reply->is_approved);
        $this->assertTrue((bool) $reply->auto_mod);
    }

    #[Test]
    public function clean_posts_are_untouched_when_auto_delete_is_enabled()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'perfectly fine',
                            'content' => 'nothing objectionable here',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $discussion = Discussion::query()->where('title', 'perfectly fine')->first();

        $this->assertNotNull($discussion, 'A clean discussion must not be deleted.');
        $this->assertTrue((bool) $discussion->is_approved);
        $this->assertTrue((bool) $discussion->firstPost->is_approved);
    }
}
