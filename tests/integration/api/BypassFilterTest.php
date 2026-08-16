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

use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * `discussion.bypassFoFFilter` lets trusted users post words that would
 * otherwise be filtered.
 *
 * Core's DiscussionPolicy prefixes the ability with `discussion.` when the
 * check is made against a Discussion, which is how the bare
 * `can('bypassFoFFilter', $discussion)` in CheckPost resolves the stored
 * `discussion.bypassFoFFilter` permission.
 */
class BypassFilterTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');

        $this->prepareDatabase([
            User::class => [
                // User 1 is the admin created by the test installer.
                // 2: an ordinary member, no special permissions.
                $this->normalUser(),
                // 3: an ordinary member who has been granted the bypass.
                ['id' => 3, 'username' => 'trusted', 'email' => 'trusted@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 100, 'name_singular' => 'Trusted', 'name_plural' => 'Trusted', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 100],
            ],
            'group_permission' => [
                ['group_id' => 100, 'permission' => 'discussion.bypassFoFFilter'],
                // Otherwise the second post in a test trips the 10s throttle.
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
        ]);

        parent::setUp();
    }

    protected function startDiscussion(int $authenticatedAs, string $content)
    {
        return $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $authenticatedAs,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test discussion',
                            'content' => $content,
                        ],
                    ],
                ],
            ])
        );
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function actors(): array
    {
        return [
            // [user id, may bypass the filter]
            'member without the permission' => [2, false],
            'member with the permission'    => [3, true],
            // Admins pass every permission check via Gate::allows().
            'admin'                         => [1, true],
        ];
    }

    #[Test]
    #[DataProvider('actors')]
    public function bad_words_are_only_filtered_for_actors_without_the_bypass(int $userId, bool $mayBypass)
    {
        $response = $this->startDiscussion($userId, 'a post containing wibble');

        $this->assertEquals(201, $response->getStatusCode());

        $discussion = Discussion::firstOrFail();
        $post = $discussion->firstPost;

        if ($mayBypass) {
            $this->assertTrue($post->is_approved, 'Post should not have been held for approval.');
            $this->assertTrue($discussion->is_approved);
            $this->assertFalse((bool) $post->auto_mod);
            $this->assertCount(0, $post->flags, 'Post should not have been flagged.');
        } else {
            $this->assertFalse($post->is_approved, 'Post should have been held for approval.');
            $this->assertFalse($discussion->is_approved);
            $this->assertTrue((bool) $post->auto_mod);
            $this->assertCount(1, $post->flags, 'Post should have been flagged.');
        }
    }

    #[Test]
    public function bypassing_user_does_not_trigger_the_flagged_email()
    {
        $this->setting('fof-filter.emailWhenFlagged', true);

        $this->startDiscussion(3, 'a post containing wibble');

        $post = Discussion::firstOrFail()->firstPost;

        $this->assertFalse((bool) $post->emailed, 'No email should be sent when the filter is bypassed.');
    }

    #[Test]
    public function bypassing_user_is_not_auto_deleted_when_auto_delete_is_enabled()
    {
        $this->setting('fof-filter.autoDeletePosts', true);

        $this->startDiscussion(3, 'a post containing wibble');

        $discussion = Discussion::first();

        $this->assertNotNull($discussion, 'Discussion should not have been deleted.');
        $this->assertTrue($discussion->firstPost->is_approved);
    }

    #[Test]
    public function permission_is_scoped_to_the_filter_and_not_granted_by_unrelated_ones()
    {
        // A member holding some other discussion permission must still be
        // filtered — the check must not degrade into "has any permission".
        $this->database()->table('group_permission')->insert([
            'group_id'   => Group::MEMBER_ID,
            'permission' => 'discussion.rename',
        ]);

        $this->startDiscussion(2, 'a post containing wibble');

        $post = Discussion::firstOrFail()->firstPost;

        $this->assertFalse($post->is_approved);
        $this->assertTrue((bool) $post->auto_mod);
    }
}
