<?php

namespace LBHurtado\Wallet\Providers;

use Bavix\Wallet\Internal\Events\BalanceUpdatedEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use LBHurtado\Wallet\Listeners\DispatchBalanceUpdatedBroadcast;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        BalanceUpdatedEvent::class => [
            DispatchBalanceUpdatedBroadcast::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
