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
use Flarum\Audit\AuditLog;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With flarum/audit enabled, what the filter does to a post is recorded.
 */
class AuditIntegrationTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'flarum-audit', 'fof-filter');

        $this->addWordsToFilter('wibble');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Existing', 'user_id' => 2, 'first_post_id' => 1, 'last_post_number' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>clean opening post</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
            ],
        ]);

        parent::setUp();
    }

    protected function reply(string $content)
    {
        return $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => $content],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
        );
    }

    protected function startDiscussion(string $title, string $content)
    {
        return $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => compact('title', 'content'),
                    ],
                ],
            ])
        );
    }

    protected function entries(string $action)
    {
        return AuditLog::query()->where('action', $action)->get();
    }

    #[Test]
    public function a_flagged_reply_is_recorded()
    {
        $this->reply('a reply containing wibble');

        $reply = Post::query()->where('discussion_id', 1)->where('number', 2)->firstOrFail();
        $entries = $this->entries('fof-filter.post_flagged');

        $this->assertCount(1, $entries);
        $this->assertEquals([
            'post_id'       => $reply->id,
            'discussion_id' => 1,
            'user_id'       => 2,
            'source'        => 'content',
            'emailed'       => false,
        ], $entries->first()->payload);
        $this->assertCount(0, $this->entries('fof-filter.post_deleted'));
    }

    #[Test]
    public function a_filtered_title_is_recorded_as_such()
    {
        $this->startDiscussion('a title containing wibble', 'a perfectly clean body');

        $entries = $this->entries('fof-filter.post_flagged');

        $this->assertCount(1, $entries);
        $this->assertEquals('title', $entries->first()->payload['source']);
    }

    /**
     * A new discussion that is auto-deleted leaves no entry: core saves a new
     * discussion and its first post in one transaction, and the request fails
     * once the discussion is gone, so everything it wrote is rolled back.
     */
    #[Test]
    public function an_auto_deleted_reply_is_recorded()
    {
        $this->setting('fof-filter.autoDeletePosts', true);

        $this->reply('a reply containing wibble');

        $entries = $this->entries('fof-filter.post_deleted');

        $this->assertCount(1, $entries);
        $this->assertEquals(2, $entries->first()->payload['user_id']);
        $this->assertCount(0, $this->entries('fof-filter.post_flagged'));
    }

    #[Test]
    public function a_clean_reply_is_not_recorded()
    {
        $this->reply('a perfectly clean reply');

        $this->assertCount(0, $this->entries('fof-filter.post_flagged'));
        $this->assertCount(0, $this->entries('fof-filter.post_deleted'));
    }

    #[Test]
    public function an_auto_merged_reply_is_recorded()
    {
        $this->setting('fof-filter.autoMergePosts', true);
        $this->setting('fof-filter.cooldown', 15);

        $this->reply('a clean reply merged into the opening post');

        $entries = $this->entries('fof-filter.post_auto_merged');

        $this->assertCount(1, $entries);
        $this->assertEquals(['post_id' => 1, 'discussion_id' => 1], $entries->first()->payload);
    }
}
