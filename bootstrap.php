<?php namespace issyrocks12\filter;

use Flarum\Foundation\Application;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;

return function (Dispatcher $events, Factory $views, Application $app) {
    $events->subscribe(Listener\FilterPosts::class);
    $events->subscribe(Listener\AddClientAssets::class);
    $events->subscribe(Listener\AddApiAttributes::class);
  
    $app->register(Providers\StorageServiceProvider::class);
  
    $views->addNameSpace('issyrocks12-filter', __DIR__.'/views');
};
