<?php

namespace LBHurtado\Wallet\Actions;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Lorisleiva\Actions\Concerns\AsAction;

class TopupWalletAction
{
    use AsAction;

    public function handle(Wallet $user, float $amount): \Bavix\Wallet\Models\Transfer
    {
        $system = app(SystemUserResolverService::class)->resolve();

        return $system->transferFloat($user, $amount);
    }
}
