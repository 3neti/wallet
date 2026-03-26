<?php

namespace LBHurtado\Wallet\Events;

use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class DisbursementEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->transaction->payable->id),
        ];
    }

    public function broadcastWith(): array
    {
        $uuid = $this->transaction->uuid;
        $amount = Money::ofMinor($this->transaction->amount, 'PHP')->getAmount()->toFloat();

        return [
            'uuid' => $uuid,
            'amount' => $amount,
        ];
    }
}
