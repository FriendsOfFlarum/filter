<?php namespace issyrocks12\filter;

use Illuminate\Contracts\Events\Dispatcher;

return function (Dispatcher $events) {
    $events->subscribe(Listener\FilterPosts::class);
    $events->subscribe(Listener\AddClientAssets::class);
};
