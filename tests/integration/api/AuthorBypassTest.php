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
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A post written by someone allowed to bypass the filter must stay exempt when
 * somebody else saves it, rather than being filtered on the strength of the
 * editor's permissions.
 *
 * Reported in #68.
 */
class AuthorBypassTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');

        $this->prepareDatabase([
            User::class => [
                // 2: an ordinary member, no bypass.
                $this->normalUser(),
                // 3: may bypass the filter.
                ['id' => 3, 'username' => 'trusted', 'email' => 't@machine.local', 'is_email_confirmed' => 1],
                // 4: may edit anyone's posts, but may NOT bypass the filter.
                ['id' => 4, 'username' => 'mod', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 100, 'name_singular' => 'Trusted', 'name_plural' => 'Trusted', 'is_hidden' => 0],
                ['id' => 101, 'name_singular' => 'Mods', 'name_plural' => 'Mods', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 100],
                ['user_id' => 4, 'group_id' => 101],
            ],
            'group_permission' => [
                ['group_id' => 100, 'permission' => 'discussion.bypassFoFFilter'],
                ['group_id' => 101, 'permission' => 'discussion.editPosts'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'a discussion', 'user_id' => 3, 'first_post_id' => 1, 'comment_count' => 2, 'is_approved' => 1],
            ],
            Post::class => [
                // Authored by the bypassing user, containing a filtered word.
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>contains wibble</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
                // Authored by an ordinary member, also containing one.
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>contains wibble</p></t>', 'number' => 2, 'created_at' => Carbon::now(), 'is_approved' => 1],
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

    #[Test]
    public function a_moderator_edit_does_not_filter_a_bypassing_authors_post()
    {
        $this->editPost(1, 4, 'still contains wibble after a moderator edit');

        $post = Post::query()->findOrFail(1);

        $this->assertTrue((bool) $post->is_approved, "The author may bypass the filter, so their post should not be held.");
        $this->assertFalse((bool) $post->auto_mod);
        $this->assertCount(0, $post->flags);
    }

    #[Test]
    public function a_moderator_edit_still_filters_an_ordinary_members_post()
    {
        $this->editPost(2, 4, 'still contains wibble after a moderator edit');

        $post = Post::query()->findOrFail(2);

        $this->assertFalse((bool) $post->is_approved, 'The author may not bypass, so their post should be held.');
        $this->assertTrue((bool) $post->auto_mod);
    }

    #[Test]
    public function the_author_editing_their_own_post_still_bypasses()
    {
        $this->editPost(1, 3, 'edited by the author, still contains wibble');

        $post = Post::query()->findOrFail(1);

        $this->assertTrue((bool) $post->is_approved);
        $this->assertFalse((bool) $post->auto_mod);
    }

    #[Test]
    public function an_ordinary_member_editing_their_own_post_is_still_filtered()
    {
        $this->editPost(2, 2, 'edited by the author, still contains wibble');

        $post = Post::query()->findOrFail(2);

        $this->assertFalse((bool) $post->is_approved);
        $this->assertTrue((bool) $post->auto_mod);
    }
}
