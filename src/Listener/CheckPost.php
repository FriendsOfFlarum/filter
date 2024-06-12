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
use FoF\Filter\CensorGenerator;
use Illuminate\Contracts\Cache\Store as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckPost
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @var Cache
     */
    protected $cache;

    public function __construct(SettingsRepositoryInterface $settings, TranslatorInterface $translator, Mailer $mailer, Dispatcher $bus, Cache $cache)
    {
        $this->settings = $settings;
        $this->translator = $translator;
        $this->mailer = $mailer;
        $this->bus = $bus;
        $this->cache = $cache;
    }

    public function handle(Saving $event)
    {
        $post = $event->post;

        if ($post->auto_mod || $event->actor->can('bypassFoFFilter', $post->discussion)) {
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

    public function checkContent($postContent): bool
    {
        $censors = $this->getCensors();

        $isExplicit = false;

        preg_replace_callback(
            $censors,
            function ($matches) use (&$isExplicit) {
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
        $censors = json_decode($this->cache->get('fof-filter.censors'), true);

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

            $this->bus->dispatch(new Created($flag, new Guest()));
        });
    }

    public function sendEmail($post): void
    {
        // Admin hasn't saved an email template to the database
        if (empty($this->settings->get('fof-filter.flaggedSubject'))) {
            $this->settings->set(
                'fof-filter.flaggedSubject',
                $this->translator->trans('fof-filter.admin.email.default_subject')
            );
        }

        if (empty($this->settings->get('fof-filter.flaggedEmail'))) {
            $this->settings->set(
                'fof-filter.flaggedEmail',
                $this->translator->trans('fof-filter.admin.email.default_text')
            );
        }

        $email = $post->user->email;
        $linebreaks = ["\n", "\r\n"];
        $subject = $this->settings->get('fof-filter.flaggedSubject');
        $text = str_replace($linebreaks, $post->user->username, $this->settings->get('fof-filter.flaggedEmail'));
        $this->mailer->send(
            'fof-filter::default',
            ['text' => $text],
            function (Message $message) use ($subject, $email) {
                $message->to($email);
                $message->subject($subject);
            }
        );
        $post->emailed = true;
    }
}
