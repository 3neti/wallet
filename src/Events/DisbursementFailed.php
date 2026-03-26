<?php

namespace LBHurtado\Wallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Voucher\Models\Voucher;

class DisbursementFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public \Throwable $exception,
        public ?string $mobile = null
    ) {}
}
