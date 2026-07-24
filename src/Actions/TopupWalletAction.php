<?php

namespace LBHurtado\Wallet\Actions;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transfer;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use Lorisleiva\Actions\Concerns\AsAction;

class TopupWalletAction
{
    use AsAction;

    public function __construct(
        private readonly SystemUserResolverContract $systemUserResolver,
    ) {}

    public function handle(Wallet $user, float $amount): Transfer
    {
        $system = $this->systemUserResolver->resolve();

        return $system->transferFloat($user, $amount);
    }
}
