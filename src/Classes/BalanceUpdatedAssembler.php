<?php

namespace LBHurtado\Wallet\Classes;

use Bavix\Wallet\Internal\Assembler\BalanceUpdatedEventAssemblerInterface;
use Bavix\Wallet\Internal\Events\BalanceUpdatedEventInterface;
use Bavix\Wallet\Models\Wallet;
use DateTimeImmutable;
use LBHurtado\Wallet\Events\BalanceUpdated;

class BalanceUpdatedAssembler implements BalanceUpdatedEventAssemblerInterface
{
    public function create(Wallet $wallet): BalanceUpdatedEventInterface
    {
        return new BalanceUpdated($wallet, new DateTimeImmutable);
    }
}
