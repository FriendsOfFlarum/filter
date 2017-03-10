<?php 

namespace issyrocks12\filter\Listener;

use issyrocks12\filter\Api\RegisterController;
use Flarum\Event\ConfigureApiRoutes;
use Illuminate\Events\Dispatcher;

class AddApiAttributes
{
    public function subscribe(Dispatcher $events)
    {
        $events->listen(ConfigureApiRoutes::class, [$this, 'configureApiRoutes']);
    }
    public function configureApiRoutes(ConfigureApiRoutes $event)
    {
        $event->post('/issyrocks12/filter/register', 'issyrocks12.filter.register', RegisterController::class);
      
    }
}