<?php

namespace LBHurtado\Wallet\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class DepositConfirmed extends DisbursementEvent implements ShouldBroadcast
{
    public function broadcastAs(): string
    {
        return 'deposit.confirmed';
    }
}
