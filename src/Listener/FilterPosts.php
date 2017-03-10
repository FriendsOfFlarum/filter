<?php

namespace issyrocks12\filter\Listener;

use DirectoryIterator;
use Flarum\Event\PostWillBeSaved;
use Flarum\Flags\Flag;
use Flarum\Foundation\Application;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Event\ConfigureLocales;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Symfony\Component\Translation\TranslatorInterface;
use Illuminate\Contracts\Events\Dispatcher;

class FilterPosts
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
    /**
     * @var Application
     */
    protected $app;
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @param SettingsRepositoryInterface $settings
     * @param Application $app
     * @param TranslatorInterface $translator
     * @param Mailer $mailer
     */
    public function __construct(SettingsRepositoryInterface $settings, Mailer $mailer, Application $app, TranslatorInterface $translator)
    {
        $this->settings = $settings;
        $this->app = $app;
        $this->mailer = $mailer;
        $this->translator = $translator;
    }
    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(PostWillBeSaved::class, [$this, 'checkPost']);
        $events->listen(ConfigureLocales::class, [$this, 'configLocales']);
    }
    /**
     * @param PostWillBeSaved $event
     */
    public function checkPost(PostWillBeSaved $event)
    {
        $words = explode(', ', $this->settings->get('Words'));
        $post = $event->post;
        $content = $post->content;
        foreach ($words as $word)
        {
           if (stripos($content, $word) !== false || preg_match($word, $content)) 
           {
              $this->flagPost($post);
              if ($this->settings->get('emailWhenFlagged') == 1 && $post->emailed == 0)
              {
                $this->sendEmail($post);
              }
           break; } 
        } 
    }
    
   public function flagPost($post)
   {
      $post->is_approved = false;
      $post->afterSave(function ($post) {
      if ($post->number == 1)
      {
        $post->discussion->is_approved = false;
        $post->discussion->save();
      }
      $flag = new Flag;
      $flag->post_id = $post->id;
      $flag->type = $this->translator->trans('issyrocks12-filter.forum.flagger_name');
      $flag->reason = $this->translator->trans('issyrocks12-filter.forum.flag_message');
      $flag->time = time();
      $flag->save();
         });
   }
  
   public function sendEmail($post)
   {
        // Admin hasn't saved an email template to the database
        if ($this->settings->get('flaggedSubject') == '' && $this->settings->get('flaggedEmail') == '')
        {
          $this->settings->set('flaggedSubject', $this->translator->trans('issyrocks12-filter.admin.email.default_subject'));
          $this->settings->set('flaggedEmail', $this->translator->trans('issyrocks12-filter.admin.email.default_text'));
        }
        $email = $post->user->email;
        $linebreaks = array("\n", "\r\n");
        $subject = $this->settings->get('flaggedSubject');
        $text = str_replace($linebreaks, $post->user->username, $this->settings->get('flaggedEmail'));
        $this->mailer->send('issyrocks12-filter::default', ['text' => $text], function (Message $message) use ($subject, $email) {
        $message->to($email);
        $message->subject($subject);
          
         });
        $post->emailed = true;
   }
  
   public function configLocales(ConfigureLocales $event)
    {
        foreach (new DirectoryIterator(__DIR__ .'/../../locale') as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['yml', 'yaml'], false)) {
                $event->locales->addTranslations($file->getBasename('.' . $file->getExtension()), $file->getPathname());
            }
        }
    }
}
