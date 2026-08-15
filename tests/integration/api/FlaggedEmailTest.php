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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use FoF\Filter\Tests\integration\FilterTestCase;
use Illuminate\Mail\Events\MessageSent;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;

class FlaggedEmailTest extends FilterTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-filter');

        $this->addWordsToFilter('wibble');
        $this->setting('fof-filter.emailWhenFlagged', true);
        $this->setting('mail_driver', 'log');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
        ]);

        parent::setUp();
    }

    /**
     * Post something that trips the filter, returning the email that was sent.
     */
    protected function postAndCaptureEmail(): Email
    {
        /** @var Email|null $sent */
        $sent = null;

        // MessageSent rather than MessageSending: the latter is dispatched
        // through `until()`, which only reaches listeners registered before
        // the mailer captured its dispatcher.
        $this->app()->getContainer()->make('events')->listen(
            MessageSent::class,
            function (MessageSent $event) use (&$sent) {
                $sent = $event->message;
            }
        );

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

        $this->assertNotNull($sent, 'No email was sent for the flagged post.');

        return $sent;
    }

    #[Test]
    public function flagged_email_is_sent_with_both_html_and_plain_text_parts()
    {
        $email = $this->postAndCaptureEmail();

        $html = $email->getHtmlBody();
        $plain = $email->getTextBody();

        // `mail_format` defaults to multipart, so both parts must be present.
        $this->assertNotEmpty($html, 'Email has no HTML part.');
        $this->assertNotEmpty($plain, 'Email has no plain text part.');

        // The HTML part must be core's informational layout, not a bare
        // <html> wrapper: it carries the forum header, styling and footer.
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('id="home-link"', $html);

        // The plain part must be plain — no markup leaking through.
        $this->assertStringNotContainsString('<html', $plain);
        $this->assertStringNotContainsString('<!DOCTYPE', $plain);
    }

    #[Test]
    public function flagged_email_contains_the_configured_body_and_resolves_the_username_placeholder()
    {
        $this->setting('fof-filter.flaggedEmail', 'Hi %USERNAME%, your post was filtered.');

        $email = $this->postAndCaptureEmail();

        foreach ([$email->getHtmlBody(), $email->getTextBody()] as $body) {
            // %USERNAME% must be substituted, not passed through verbatim.
            $this->assertStringNotContainsString('%USERNAME%', $body);
            $this->assertStringContainsString('Hi normal, your post was filtered.', $body);
        }
    }

    #[Test]
    public function line_breaks_in_a_plain_text_body_survive_into_the_html_part()
    {
        $this->setting('fof-filter.flaggedEmail', "First line.\n\nSecond line.");

        $email = $this->postAndCaptureEmail();

        // Without this the two lines run together in HTML mail clients.
        $this->assertStringContainsString('<br', $email->getHtmlBody());

        // The plain part keeps real newlines rather than markup.
        $plain = $email->getTextBody();
        $this->assertStringNotContainsString('<br', $plain);
        $this->assertStringContainsString('First line.', $plain);
        $this->assertStringContainsString('Second line.', $plain);
    }

    #[Test]
    public function the_post_is_marked_as_emailed_so_a_second_send_is_suppressed()
    {
        $this->postAndCaptureEmail();

        $post = \Flarum\Discussion\Discussion::firstOrFail()->firstPost;

        // `emailed` is what stops the author being mailed again every time the
        // flagged post is saved.
        $this->assertTrue((bool) $post->emailed);

        // Re-saving the flagged post must not send a second email.
        $sentAgain = false;
        $this->app()->getContainer()->make('events')->listen(
            MessageSent::class,
            function () use (&$sentAgain) {
                $sentAgain = true;
            }
        );

        $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => 'another wibble reply'],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
        );

        $post->refresh();
        $this->assertTrue((bool) $post->emailed, 'The emailed flag must persist.');
    }

    #[Test]
    public function flagged_email_uses_the_configured_subject_as_its_heading()
    {
        $this->setting('fof-filter.flaggedSubject', 'Your post was filtered');

        $email = $this->postAndCaptureEmail();

        $this->assertEquals('Your post was filtered', $email->getSubject());

        // An explicit title is passed so the heading can't inherit a stale one
        // from an earlier email on the shared view factory.
        $this->assertStringContainsString('Your post was filtered', $email->getHtmlBody());
    }
}
