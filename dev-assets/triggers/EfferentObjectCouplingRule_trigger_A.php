<?php

declare(strict_types=1);

// This file should trigger the EfferentObjectCouplingRule
// It references more than 20 different classes

class HighCouplingClass
{
    public function __construct(
        private \Illuminate\Http\Request $request,
        private \Illuminate\Database\Eloquent\Model $model,
        private \Illuminate\Support\Collection $collection,
        private \Illuminate\Validation\Validator $validator,
        private \Illuminate\Auth\AuthManager $auth,
        private \Illuminate\Cache\CacheManager $cache,
        private \Illuminate\Session\SessionManager $session,
        private \Illuminate\Mail\Mailer $mailer,
        private \Illuminate\Queue\QueueManager $queue,
        private \Illuminate\Filesystem\Filesystem $filesystem,
        private \Illuminate\Translation\Translator $translator,
        private \Illuminate\Encryption\Encrypter $encrypter,
        private \Illuminate\Hashing\Hasher $hasher,
        private \Illuminate\Pagination\Paginator $paginator,
        private \Illuminate\Routing\Router $router,
        private \Illuminate\View\View $view,
        private \Illuminate\Broadcasting\Broadcaster $broadcaster,
        private \Illuminate\Notifications\Notification $notification,
        private \Illuminate\Events\Dispatcher $dispatcher,
        private \Illuminate\Log\Logger $logger,
        private \Illuminate\Config\Repository $config
    ) {}

    public function process(): void
    {
        // Additional class references to exceed the limit
        $response = new \Illuminate\Http\Response();
        $factory = new \Illuminate\View\Factory();
        $blade = new \Illuminate\View\Compilers\BladeCompiler();
    }
}