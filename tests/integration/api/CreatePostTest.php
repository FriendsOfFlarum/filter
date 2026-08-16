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
use Flarum\Flags\Flag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CreatePostTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        //$this->addWordsToFilter('wibble'.PHP_EOL.'wobble');
        $this->manyWords();

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
        ]);

        parent::setUp();
    }

    #[Test]
    public function create_discussion_without_any_bad_words()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test - too-obscure',
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        /** @var Discussion $discussion */
        $discussion = Discussion::firstOrFail();
        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('test - too-obscure', $discussion->title);
        $this->assertEquals('test - too-obscure', Arr::get($data, 'data.attributes.title'));

        $post = $discussion->firstPost;

        $this->assertNotNull($post);
        $this->assertEquals('predetermined content for automated testing - too-obscure', $post->content);

        $this->assertTrue($post->is_approved);
        $this->assertTrue($discussion->is_approved);
    }

    public static function badWords()
    {
        return [
            ['wibble'],
            ['wobble'],
        ];
    }

    #[Test]
    #[DataProvider('badWords')]
    public function create_discussion_with_bad_words_requires_approval(string $badWord)
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => "test - $badWord",
                            'content' => "predetermined content for automated testing - $badWord",
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        /** @var Discussion $discussion */
        $discussion = Discussion::firstOrFail();
        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals("test - $badWord", $discussion->title);
        $this->assertEquals("test - $badWord", Arr::get($data, 'data.attributes.title'));

        $post = $discussion->firstPost;

        $this->assertNotNull($post);
        $this->assertEquals("predetermined content for automated testing - $badWord", $post->content);

        $this->assertFalse($post->is_approved);
        $this->assertFalse($discussion->is_approved);
    }

    #[Test]
    public function flagged_post_gets_an_auto_mod_flag()
    {
        $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test - wibble',
                            'content' => 'predetermined content for automated testing - wibble',
                        ],
                    ],
                ],
            ])
        );

        $post = Discussion::firstOrFail()->firstPost;

        /** @var Flag $flag */
        $flag = Flag::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertEquals('autoMod', $flag->type);
        $this->assertNotEmpty($flag->reason_detail);
        $this->assertNotNull($flag->created_at);

        // The flag is raised by the extension rather than a member, so it has
        // no flagging user. flags' own UI reads `post.user()` for the avatar,
        // so a null `user_id` renders fine.
        $this->assertNull($flag->user_id);

        // Moderators reach flagged posts through this relation.
        $this->assertTrue($post->flags->contains($flag));
    }
}
