<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Adapters\Bavix\BavixInventoryReadModelService;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryWalletBalanceData;
use LBHurtado\Wallet\Treasury\ReadModels\WalletBalanceInventoryReadModelService;

it('binds the package-neutral inventory read-model service as a singleton', function () {
    $first = app(TreasuryInventoryReadModelContract::class);
    $second = app(TreasuryInventoryReadModelContract::class);

    expect($first)->toBeInstanceOf(WalletBalanceInventoryReadModelService::class)
        ->and($second)->toBe($first)
        ->and(app(BavixInventoryReadModelService::class))->toBeInstanceOf(BavixInventoryReadModelService::class);
});

it('keeps the inventory read-model contract stable and package-owned', function () {
    $contract = new ReflectionClass(TreasuryInventoryReadModelContract::class);
    $methods = $contract->getMethods();
    $read = $contract->getMethod('read');

    expect($contract->isInterface())->toBeTrue()
        ->and($methods)->toHaveCount(1)
        ->and($read->getParameters())->toHaveCount(1)
        ->and($read->getParameters()[0]->getType()?->getName())->toBe(TreasuryWalletBalanceData::class)
        ->and($read->getReturnType()?->getName())->toBe(TreasuryInventoryReadModelData::class);
});

it('returns a stable wallet-baseline shape with explicit absent Treasury facts', function () {
    $walletBalance = new TreasuryWalletBalanceData(
        walletReference: 'wallet:001',
        currency: 'PHP',
        walletBalanceMinor: 12500,
        metadata: [
            'source' => 'test',
            'treasury_facts' => 'caller-override',
        ],
    );
    $readModel = app(TreasuryInventoryReadModelContract::class)->read($walletBalance);

    expect($walletBalance->toArray())->toBe([
        'walletReference' => 'wallet:001',
        'currency' => 'PHP',
        'walletBalanceMinor' => 12500,
        'metadata' => [
            'source' => 'test',
            'treasury_facts' => 'caller-override',
        ],
    ])->and($readModel)->toBeInstanceOf(TreasuryInventoryReadModelData::class)
        ->and($readModel->toArray())->toBe([
            'walletReference' => 'wallet:001',
            'currency' => 'PHP',
            'walletBalanceMinor' => 12500,
            'eligibleInventoryMinor' => 12500,
            'allocatedAmountMinor' => 0,
            'drawnAmountMinor' => 0,
            'releasedAmountMinor' => 0,
            'outstandingAmountMinor' => 0,
            'usableAmountMinor' => 12500,
            'hasTreasuryFacts' => false,
            'inventoryReference' => null,
            'allocationReference' => null,
            'metadata' => [
                'source' => 'test',
                'treasury_facts' => 'absent',
                'treasury_read_model' => 'wallet-baseline',
                'treasury_read_model_status' => 'read-only',
            ],
        ]);
});

it('distinguishes accounting balance from eligible and usable inventory', function () {
    $readModel = app(TreasuryInventoryReadModelContract::class)->read(
        new TreasuryWalletBalanceData('wallet:credit', 'PHP', -500)
    );

    expect($readModel->walletBalanceMinor)->toBe(-500)
        ->and($readModel->eligibleInventoryMinor)->toBe(0)
        ->and($readModel->usableAmountMinor)->toBe(0)
        ->and($readModel->allocatedAmountMinor)->toBe(0)
        ->and($readModel->drawnAmountMinor)->toBe(0)
        ->and($readModel->releasedAmountMinor)->toBe(0)
        ->and($readModel->outstandingAmountMinor)->toBe(0)
        ->and($readModel->hasTreasuryFacts)->toBeFalse();
});

it('reads current Bavix wallet state without creating or changing financial records', function () {
    $holder = User::factory()->create();
    $holder->deposit(12345);
    $wallet = $holder->wallet;

    $balanceBefore = $wallet->balanceInt;
    $walletsBefore = Wallet::query()->count();
    $transactionsBefore = Transaction::query()->count();
    $transfersBefore = Transfer::query()->count();

    $readModel = app(BavixInventoryReadModelService::class)->read($wallet);

    expect($readModel->walletReference)->toBe($wallet->uuid)
        ->and($readModel->currency)->toBe($wallet->currency)
        ->and($readModel->walletBalanceMinor)->toBe(12345)
        ->and($readModel->eligibleInventoryMinor)->toBe(12345)
        ->and($readModel->usableAmountMinor)->toBe(12345)
        ->and($readModel->allocatedAmountMinor)->toBe(0)
        ->and($readModel->drawnAmountMinor)->toBe(0)
        ->and($readModel->releasedAmountMinor)->toBe(0)
        ->and($readModel->outstandingAmountMinor)->toBe(0)
        ->and($readModel->hasTreasuryFacts)->toBeFalse()
        ->and($readModel->inventoryReference)->toBeNull()
        ->and($readModel->allocationReference)->toBeNull()
        ->and($wallet->fresh()->balanceInt)->toBe($balanceBefore)
        ->and(Wallet::query()->count())->toBe($walletsBefore)
        ->and(Transaction::query()->count())->toBe($transactionsBefore)
        ->and(Transfer::query()->count())->toBe($transfersBefore);
});
