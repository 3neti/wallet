<?php

namespace LBHurtado\Wallet\Jobs;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LBHurtado\Wallet\Events\BalanceUpdated;

class BroadcastBalanceUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $walletId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $wallet = Wallet::find($this->walletId);

        if (! $wallet) {
            Log::warning('BroadcastBalanceUpdated job: wallet not found', [
                'wallet_id' => $this->walletId,
            ]);

            return;
        }

        // Refresh balance to ensure we have latest data
        $wallet->refreshBalance();

        // Dispatch the broadcast event
        BalanceUpdated::dispatch($wallet, new \DateTimeImmutable);

        Log::debug('BroadcastBalanceUpdated job: event dispatched', [
            'wallet_id' => $wallet->getKey(),
            'balance' => $wallet->balanceFloat,
            'holder_id' => $wallet->holder->id ?? null,
        ]);
    }
}
