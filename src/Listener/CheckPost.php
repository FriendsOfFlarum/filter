<?php

/*
 * This file is part of fof/filter.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Filter\Listener;

use Carbon\Carbon;
use Flarum\Flags\Event\Created;
use Flarum\Flags\Flag;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\Filter\CensorGenerator;
use Illuminate\Contracts\Cache\Store as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckPost
{
    public function __construct(protected SettingsRepositoryInterface $settings, protected TranslatorInterface $translator, protected Mailer $mailer, protected Dispatcher $events, protected Cache $cache, protected ViewFactory $views)
    {
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;

        if ($post->auto_mod || $this->mayBypass($event->actor, $post)) {
            return;
        }

        if ($this->checkContent($post->content)) {
            if ((bool) $this->settings->get('fof-filter.autoDeletePosts')) {
                $this->deletePost($post);
            } else {
                $this->flagPost($post);

                if ((bool) $this->settings->get('fof-filter.emailWhenFlagged') && $post->emailed == 0) {
                    $this->sendEmail($post);
                }
            }
        }
    }

    /**
     * Whether this post is exempt from filtering.
     *
     * The author is checked as well as the actor: someone else saving the post
     * — a moderator editing it, say — must not cause content the author was
     * allowed to write to be filtered on the strength of their own
     * permissions.
     */
    protected function mayBypass(User $actor, Post $post): bool
    {
        if ($actor->can('bypassFoFFilter', $post->discussion)) {
            return true;
        }

        $author = $post->user;

        return $author !== null
            && $author->id !== $actor->id
            && $author->can('bypassFoFFilter', $post->discussion);
    }

    public function checkContent(?string $postContent): bool
    {
        // Event posts store structured content rather than a string, and
        // imported posts may have NULL content. Neither can be filtered.
        if ($postContent === null || $postContent === '') {
            return false;
        }

        $censors = $this->getCensors();

        $isExplicit = false;

        preg_replace_callback(
            $censors,
            static function ($matches) use (&$isExplicit) {
                if ($matches) {
                    $isExplicit = true;
                }

                return $matches[0];
            },
            str_replace(' ', '', $postContent)
        );

        return $isExplicit;
    }

    protected function getCensors(): array
    {
        $cached = $this->cache->get('fof-filter.censors');
        $censors = $cached === null ? null : json_decode($cached, true);

        // Ensure $censors is a non-empty array
        if (!is_array($censors) || empty($censors)) {
            // Censors have not been initialized correctly, generate them
            $censors = CensorGenerator::generateCensors($this->settings->get('fof-filter.words', ''));
            $this->cache->forever('fof-filter.censors', json_encode($censors));
        }

        return $censors;
    }

    public function deletePost(Post $post): void
    {
        /** @phpstan-ignore-next-line */
        $post->is_approved = false;
        $post->auto_mod = true;
        $post->afterSave(function ($post) {
            if ($post->number === 1) {
                $post->discussion->delete();
            }
        });
    }

    public function flagPost(Post $post): void
    {
        /** @phpstan-ignore-next-line */
        $post->is_approved = false;
        $post->auto_mod = true;
        $post->afterSave(function ($post) {
            if ($post->number == 1) {
                $post->discussion->is_approved = false;
                $post->discussion->save();
            }

            $flag = new Flag();
            $flag->post_id = $post->id;
            $flag->type = 'autoMod';
            $flag->reason_detail = $this->translator->trans('fof-filter.forum.flag_message');
            $flag->created_at = Carbon::now();
            $flag->save();

            $this->events->dispatch(new Created($flag, new Guest()));
        });
    }

    public function sendEmail(Post $post): void
    {
        // Admin hasn't saved an email template to the database
        $subject = trim((string) $this->settings->get('fof-filter.flaggedSubject'))
            ?: $this->translator->trans('fof-filter.admin.email.default_subject');
        $text = trim((string) $this->settings->get('fof-filter.flaggedEmail'))
            ?: $this->translator->trans('fof-filter.admin.email.default_text');

        $user = $post->user;
        $userEmail = $user->email;
        $username = $user->display_name;

        $safeUsername = htmlentities(strip_tags($user->username), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace %USERNAME% placeholder directly with the safe username
        $formattedText = str_replace('%USERNAME%', $safeUsername, $text);

        $forumTitle = $this->settings->get('forum_title');

        // Pass an explicit title so the heading can't inherit a stale `title`
        // left on the shared (singleton) view factory by an earlier email.
        // See flarum/framework#4767.
        $title = $subject;

        // Core's layouts read these off the view factory rather than the data
        // array, so they have to be shared as well as passed.
        $this->views->share(compact('forumTitle', 'userEmail', 'username', 'title'));

        // Both views are required: Flarum\Mail\Mailer drops whichever one the
        // admin's `mail_format` setting excludes, and sends both by default.
        $this->mailer->send(
            [
                'text' => 'fof-filter::plain',
                'html' => 'fof-filter::html',
            ],
            compact('forumTitle', 'userEmail', 'username', 'title') + [
                'text' => $formattedText,
                // The body is admin-authored and may be plain text, so line
                // breaks have to become markup for the HTML part. nl2br leaves
                // any HTML they did write untouched.
                'html' => nl2br($formattedText),
            ],
            function (Message $message) use ($subject, $userEmail, $username) {
                $message->to($userEmail, $username);
                $message->subject($subject);
            }
        );

        $post->emailed = true;
    }
}
