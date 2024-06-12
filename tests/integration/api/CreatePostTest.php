<?php

namespace FoF\Filter\Tests\integration\api;

use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Illuminate\Support\Arr;

class CreatePostTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'settings' => [
                ['key' => 'fof-filter.words', 'value' => 'wibble'.PHP_EOL.'wobble'.PHP_EOL],
                ['key' => 'fof-filter.censors', 'value' => '["\/(w|w\\.|w\\-|\u03c9|\u03c8|\u03a8)(i|i\\.|i\\-|!|\\||\\]\\[|]|1|\u222b|\u00cc|\u00cd|\u00ce|\u00cf|\u00ec|\u00ed|\u00ee|\u00ef)(b|b\\.|b\\-|8|\\|3|\u00df|\u0392|\u03b2)(b|b\\.|b\\-|8|\\|3|\u00df|\u0392|\u03b2)(l|1\\.|l\\-|!|\\||\\]\\[|]|\u00a3|\u222b|\u00cc|\u00cd|\u00ce|\u00cf)(e|e\\.|e\\-|3|\u20ac|\u00c8|\u00e8|\u00c9|\u00e9|\u00ca|\u00ea|\u2211)\/i","\/(w|w\\.|w\\-|\u03c9|\u03c8|\u03a8)(o|o\\.|o\\-|0|\u039f|\u03bf|\u03a6|\u00a4|\u00b0|\u00f8)(b|b\\.|b\\-|8|\\|3|\u00df|\u0392|\u03b2)(b|b\\.|b\\-|8|\\|3|\u00df|\u0392|\u03b2)(l|1\\.|l\\-|!|\\||\\]\\[|]|\u00a3|\u222b|\u00cc|\u00cd|\u00ce|\u00cf)(e|e\\.|e\\-|3|\u20ac|\u00c8|\u00e8|\u00c9|\u00e9|\u00ca|\u00ea|\u2211)\/i"]']
            ]
        ]);

        parent::setUp();

    }

    /**
     * @test
     */
    public function create_discussion_without_any_bad_words()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'title' => 'test - too-obscure',
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                    ]
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

    /**
     * @test
     */
    public function create_discussion_with_bad_words()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'title' => 'test - wibble',
                            'content' => 'predetermined content for automated testing - wibble',
                        ],
                    ]
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        /** @var Discussion $discussion */
        $discussion = Discussion::firstOrFail();
        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('test - wibble', $discussion->title);
        $this->assertEquals('test - wibble', Arr::get($data, 'data.attributes.title'));

        $post = $discussion->firstPost;

        $this->assertNotNull($post);
        $this->assertEquals('predetermined content for automated testing - wibble', $post->content);

        $this->assertFalse($post->is_approved);
        $this->assertFalse($discussion->is_approved);
    }
}
