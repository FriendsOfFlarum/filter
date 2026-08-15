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
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use PHPUnit\Framework\Attributes\Test;

class AutoMergePostTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->setting('fof-filter.cooldown', 15);

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'other', 'email' => 'other@machine.local', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Auto merge', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
            ],
            Post::class => [
                [
                    'id'            => 1,
                    'discussion_id' => 1,
                    'user_id'       => 2,
                    'type'          => 'comment',
                    'content'       => '<t><p>first post</p></t>',
                    'number'        => 1,
                    'created_at'    => Carbon::now(),
                ],
            ],
        ]);

        parent::setUp();
    }

    protected function reply(int $authenticatedAs, string $content)
    {
        return $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $authenticatedAs,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => $content],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
                // Auto-merge deliberately targets replies posted seconds apart,
                // which is exactly what the post throttler blocks.
                ->withAttribute('bypassThrottling', true)
        );
    }

    #[Test]
    public function reply_within_cooldown_is_merged_into_previous_post()
    {
        $this->setting('fof-filter.autoMergePosts', true);

        $response = $this->reply(2, 'second post');

        $this->assertEquals(201, $response->getStatusCode());

        // No new post row was created; the existing one absorbed the content.
        $this->assertEquals(1, Post::query()->count());

        $post = Post::query()->findOrFail(1);

        // `content` unparses back to the raw markup the user typed.
        $this->assertEquals("first post\n\nsecond post", $post->content);

        // The stored value must be re-parsed into a single well-formed
        // document rather than the two originals concatenated.
        $this->assertNotFalse(simplexml_load_string($post->parsed_content));
    }

    #[Test]
    public function reply_from_a_different_user_is_not_merged()
    {
        $this->setting('fof-filter.autoMergePosts', true);

        $response = $this->reply(3, 'from someone else');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2, Post::query()->count());
    }

    #[Test]
    public function reply_after_cooldown_expires_is_not_merged()
    {
        $this->setting('fof-filter.autoMergePosts', true);

        $this->app();

        Post::query()->where('id', 1)->update([
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $response = $this->reply(2, 'much later');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2, Post::query()->count());
    }

    #[Test]
    public function reply_is_not_merged_when_setting_is_disabled()
    {
        $this->setting('fof-filter.autoMergePosts', false);

        $response = $this->reply(2, 'second post');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(2, Post::query()->count());
    }
}
