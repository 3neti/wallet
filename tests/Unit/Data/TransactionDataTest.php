<?php

use LBHurtado\Wallet\Data\TransactionData;
use LBHurtado\Wallet\Tests\Models\User;

it('has transaction data', function () {
    $user = User::factory()->create();
    $transaction = $user->depositFloat(100);
    $data = TransactionData::fromModel($transaction);
    expect($data)->toBeInstanceOf(TransactionData::class);
});
