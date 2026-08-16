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
 * A filtered word in a discussion title must be caught, not just one in the
 * post body.
 *
 * Reported in #27 and #68.
 */
class FilterTitleTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'trusted', 'email' => 't@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 100, 'name_singular' => 'Trusted', 'name_plural' => 'Trusted', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 100],
            ],
            'group_permission' => [
                ['group_id' => 100, 'permission' => 'discussion.bypassFoFFilter'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'a clean existing title', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>clean</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
            ],
        ]);

        parent::setUp();
    }

    protected function startDiscussion(int $authenticatedAs, string $title, string $content)
    {
        return $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $authenticatedAs,
                'json'            => [
                    'data' => [
                        'attributes' => compact('title', 'content'),
                    ],
                ],
            ])
        );
    }

    #[Test]
    public function a_bad_word_in_the_title_is_filtered()
    {
        $this->startDiscussion(2, 'a title containing wibble', 'a perfectly clean body');

        $discussion = Discussion::query()->where('title', 'a title containing wibble')->firstOrFail();
        $post = $discussion->firstPost;

        $this->assertFalse((bool) $discussion->is_approved, 'The discussion should be held for approval.');
        $this->assertFalse((bool) $post->is_approved, 'The first post should be held for approval.');
        $this->assertTrue((bool) $post->auto_mod);
        $this->assertCount(1, $post->flags, 'The post should have been flagged.');
    }

    #[Test]
    public function a_clean_title_and_body_is_left_alone()
    {
        $this->startDiscussion(2, 'a perfectly ordinary title', 'a perfectly clean body');

        $discussion = Discussion::query()->where('title', 'a perfectly ordinary title')->firstOrFail();

        $this->assertTrue((bool) $discussion->is_approved);
        $this->assertTrue((bool) $discussion->firstPost->is_approved);
        $this->assertCount(0, $discussion->firstPost->flags);
    }

    #[Test]
    public function a_bad_word_in_the_body_is_still_filtered()
    {
        $this->startDiscussion(2, 'a perfectly ordinary title', 'this body has wibble in it');

        $discussion = Discussion::query()->where('title', 'a perfectly ordinary title')->firstOrFail();

        $this->assertFalse((bool) $discussion->is_approved);
        $this->assertFalse((bool) $discussion->firstPost->is_approved);
    }

    #[Test]
    public function renaming_a_discussion_to_a_bad_title_is_filtered()
    {
        $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => ['title' => 'renamed to include wibble'],
                    ],
                ],
            ])
        );

        $discussion = Discussion::query()->findOrFail(1);

        $this->assertFalse((bool) $discussion->is_approved, 'A renamed discussion should be held for approval.');
    }

    #[Test]
    public function a_user_with_the_bypass_permission_may_use_a_filtered_title()
    {
        $this->startDiscussion(3, 'a title containing wibble', 'a perfectly clean body');

        $discussion = Discussion::query()->where('title', 'a title containing wibble')->firstOrFail();

        $this->assertTrue((bool) $discussion->is_approved);
        $this->assertTrue((bool) $discussion->firstPost->is_approved);
        $this->assertCount(0, $discussion->firstPost->flags);
    }

    #[Test]
    public function a_filtered_title_is_auto_deleted_when_enabled()
    {
        $this->setting('fof-filter.autoDeletePosts', true);

        $this->startDiscussion(2, 'another title with wibble', 'a perfectly clean body');

        $this->assertNull(
            Discussion::query()->where('title', 'another title with wibble')->first(),
            'The discussion should have been deleted.'
        );
        $this->assertEquals(0, Flag::query()->count(), 'No flag should be raised when auto-deleting.');
    }
}
