<?php

namespace LBHurtado\Wallet\Listeners;

use Bavix\Wallet\Internal\Events\BalanceUpdatedEventInterface;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use LBHurtado\Wallet\Events\BalanceUpdated;

/**
 * Listens to Bavix Wallet's internal BalanceUpdatedEvent
 * and dispatches our broadcastable BalanceUpdated event.
 *
 * Queued to handle balance updates asynchronously.
 * Broadcasting is also queued automatically via ShouldBroadcast.
 */
class DispatchBalanceUpdatedBroadcast implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(BalanceUpdatedEventInterface $event): void
    {
        // Fetch wallet by ID from the event
        $wallet = Wallet::find($event->getWalletId());

        if (! $wallet) {
            Log::warning('BalanceUpdatedEvent received but wallet not found', [
                'wallet_id' => $event->getWalletId(),
            ]);

            return;
        }

        // Dispatch our broadcastable event
        BalanceUpdated::dispatch($wallet, $event->getUpdatedAt());

        Log::debug('BalanceUpdated broadcast dispatched from Bavix event', [
            'wallet_id' => $wallet->getKey(),
            'balance' => $wallet->balanceFloat,
            'holder_id' => $wallet->holder->id ?? null,
        ]);
    }
}
