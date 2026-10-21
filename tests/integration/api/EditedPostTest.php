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
 * An existing post is filtered when its content changes, and only then.
 *
 * Having been filtered once must not exempt a post from later edits, and
 * saving a post without touching its content (hiding or approving it) must
 * not filter it.
 */
class EditedPostTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');
        $this->setting('allow_post_editing', '-1');

        $this->prepareDatabase([
            User::class => [
                // 2: an ordinary member, the author of every post.
                $this->normalUser(),
                // 4: may hide and approve posts, but may NOT bypass the filter.
                ['id' => 4, 'username' => 'mod', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 101, 'name_singular' => 'Mods', 'name_plural' => 'Mods', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 4, 'group_id' => 101],
            ],
            'group_permission' => [
                ['group_id' => 101, 'permission' => 'discussion.hidePosts'],
                ['group_id' => 101, 'permission' => 'discussion.approvePosts'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'a discussion', 'user_id' => 2, 'first_post_id' => 1, 'last_post_number' => 4, 'comment_count' => 4, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>clean opening post</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
                // Filtered once, then approved by a moderator.
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>clean after approval</p></t>', 'number' => 2, 'created_at' => Carbon::now(), 'is_approved' => 1, 'auto_mod' => 1],
                // Written before "wibble" was added to the list.
                ['id' => 3, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>an old post with wibble</p></t>', 'number' => 3, 'created_at' => Carbon::now(), 'is_approved' => 1],
                // Filtered and still waiting for a moderator.
                ['id' => 4, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>a held post with wibble</p></t>', 'number' => 4, 'created_at' => Carbon::now(), 'is_approved' => 0, 'auto_mod' => 1],
            ],
            Flag::class => [
                ['id' => 1, 'post_id' => 4, 'type' => 'autoMod', 'created_at' => Carbon::now()],
            ],
        ]);

        parent::setUp();
    }

    protected function patchPost(int $postId, int $authenticatedAs, array $attributes)
    {
        return $this->send(
            $this->request('PATCH', '/api/posts/'.$postId, [
                'authenticatedAs' => $authenticatedAs,
                'json'            => [
                    'data' => [
                        'attributes' => $attributes,
                    ],
                ],
            ])
        );
    }

    protected function autoModFlags(int $postId): int
    {
        return Flag::query()->where('post_id', $postId)->where('type', 'autoMod')->count();
    }

    #[Test]
    public function a_previously_filtered_post_is_filtered_again_when_edited()
    {
        $response = $this->patchPost(2, 2, ['content' => 'edited to include wibble']);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertFalse((bool) Post::query()->findOrFail(2)->is_approved, 'The edit should be held for approval.');
        $this->assertEquals(1, $this->autoModFlags(2));
    }

    #[Test]
    public function hiding_an_unchanged_post_does_not_filter_it()
    {
        $response = $this->patchPost(3, 4, ['isHidden' => true]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertTrue((bool) Post::query()->findOrFail(3)->is_approved, 'Hiding a post must not unapprove it.');
        $this->assertEquals(0, $this->autoModFlags(3));
    }

    #[Test]
    public function approving_a_held_post_keeps_it_approved()
    {
        $response = $this->patchPost(4, 4, ['isApproved' => true]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertTrue((bool) Post::query()->findOrFail(4)->is_approved, 'Approving a post must not filter it straight back.');
    }

    #[Test]
    public function editing_a_held_post_again_does_not_add_a_second_flag()
    {
        $response = $this->patchPost(4, 2, ['content' => 'still has wibble in it']);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertFalse((bool) Post::query()->findOrFail(4)->is_approved);
        $this->assertEquals(1, $this->autoModFlags(4));
    }
}
