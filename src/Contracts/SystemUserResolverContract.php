<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Contracts;

use Bavix\Wallet\Interfaces\Wallet;

interface SystemUserResolverContract
{
    public function resolve(): Wallet;
}
