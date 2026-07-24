<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Adapters\Bavix\BavixTreasuryPositionRuntime;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryPositionConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;

it('binds one durable provisioning and read runtime', function () {
    expect(app(TreasuryPositionProvisioningContract::class))
        ->toBeInstanceOf(BavixTreasuryPositionRuntime::class)
        ->toBe(app(TreasuryPositionProvisioningContract::class))
        ->and(app(TreasuryPositionReadModelContract::class))
        ->toBe(app(TreasuryPositionReadModelContract::class));
});

it('provisions distinct zero-balance positions for each configured provider connection', function () {
    $principal = User::factory()->create();
    $startingLedgerCount = $principal->wallets()->count();
    $runtime = app(TreasuryPositionProvisioningContract::class);

    $netbank = $runtime->provision(
        $principal,
        treasuryPositionDefinition(provider: 'netbank'),
    );
    $paynamics = $runtime->provision(
        $principal,
        treasuryPositionDefinition(
            provider: 'paynamics_constellation',
            positionReference: 'position:paynamics:primary:php:user:'.$principal->getKey(),
            resourceReference: 'resource:paynamics:primary:php',
            idempotencyKey: 'position-registration:paynamics:user:'.$principal->getKey(),
        ),
    );

    expect($netbank->purpose)->toBe(TreasuryPositionPurpose::ClientFunds)
        ->and($netbank->custodyMode)->toBe(TreasuryCustodyMode::PooledInternal)
        ->and($netbank->balanceMinor)->toBe(0)
        ->and($paynamics->balanceMinor)->toBe(0)
        ->and($principal->wallets()->count())->toBe($startingLedgerCount + 2)
        ->and(TreasuryPosition::query()->count())->toBe(2)
        ->and(Transaction::query()->count())->toBe(0);
});

it('replays an identical registration without creating another ledger', function () {
    $principal = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $definition = treasuryPositionDefinition();

    $first = $runtime->provision($principal, $definition);
    $ledgerCount = $principal->wallets()->count();
    $second = $runtime->provision($principal, $definition);

    expect($second->toArray())->toBe($first->toArray())
        ->and($principal->wallets()->count())->toBe($ledgerCount)
        ->and(TreasuryPosition::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0);
});

it('rejects an idempotency key reused for another definition', function () {
    $principal = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $runtime->provision($principal, treasuryPositionDefinition());

    expect(fn () => $runtime->provision(
        $principal,
        treasuryPositionDefinition(mandateReference: 'mandate:changed'),
    ))->toThrow(TreasuryPositionConflict::class, 'idempotency key');
});

it('allows only one position purpose per principal and provider connection', function () {
    $principal = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $runtime->provision($principal, treasuryPositionDefinition());

    expect(fn () => $runtime->provision(
        $principal,
        treasuryPositionDefinition(
            positionReference: 'position:netbank:primary:php:user:alternate',
            idempotencyKey: 'position-registration:netbank:user:alternate',
        ),
    ))->toThrow(TreasuryPositionConflict::class, 'conflicts with an existing principal');
});

it('reads position balances without exposing internal ledger identifiers', function () {
    $principal = User::factory()->create();
    app(TreasuryPositionProvisioningContract::class)->provision(
        $principal,
        treasuryPositionDefinition(),
    );

    $positions = app(TreasuryPositionReadModelContract::class)
        ->forPrincipal('principal:user:5');
    $position = app(TreasuryPositionReadModelContract::class)
        ->find('position:netbank:primary:php:user:5');

    expect($positions)->toHaveCount(1)
        ->and($position)->not->toBeNull()
        ->and($position?->settlementResourceReference)->toBe('resource:netbank:primary:php')
        ->and($position?->legalProfile)->toBe('treasury-settlement-ph-v1')
        ->and($position?->legalProfileVersion)->toBe('2026-07-24.1')
        ->and($position?->toArray())->not->toHaveKeys([
            'wallet',
            'wallet_id',
            'internalLedgerId',
            'internalLedgerUuid',
        ]);
});

it('reads a provider connection portfolio without exposing internal ledgers', function () {
    $firstPrincipal = User::factory()->create();
    $secondPrincipal = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $runtime->provision(
        $firstPrincipal,
        treasuryPositionDefinition(),
    );
    $runtime->provision(
        $secondPrincipal,
        treasuryPositionDefinition(
            positionReference: 'position:netbank:primary:php:user:6',
            idempotencyKey: 'position-registration:netbank:user:6',
            mandateReference: 'mandate:user:6:treasury',
            principalReference: 'principal:user:6',
        ),
    );
    $runtime->provision(
        $secondPrincipal,
        treasuryPositionDefinition(
            provider: 'paynamics_constellation',
            positionReference: 'position:paynamics:primary:php:user:6',
            resourceReference: 'resource:paynamics:primary:php',
            idempotencyKey: 'position-registration:paynamics:user:6',
            mandateReference: 'mandate:user:6:paynamics',
            principalReference: 'principal:user:6',
        ),
    );

    $positions = app(TreasuryPositionReadModelContract::class)
        ->forConnection('NETBANK', 'primary', 'php');

    expect($positions)->toHaveCount(2)
        ->and(collect($positions)->pluck('provider')->unique()->all())->toBe(['netbank'])
        ->and(collect($positions)->pluck('connectionReference')->unique()->all())->toBe(['primary'])
        ->and(collect($positions)->pluck('currency')->unique()->all())->toBe(['PHP'])
        ->and($positions[0]->toArray())->not->toHaveKeys([
            'wallet',
            'wallet_id',
            'internalLedgerId',
            'internalLedgerUuid',
        ]);
});

function treasuryPositionDefinition(
    string $provider = 'netbank',
    string $positionReference = 'position:netbank:primary:php:user:5',
    string $resourceReference = 'resource:netbank:primary:php',
    string $idempotencyKey = 'position-registration:netbank:user:5',
    string $mandateReference = 'mandate:user:5:treasury',
    string $principalReference = 'principal:user:5',
): TreasuryPositionDefinitionData {
    return new TreasuryPositionDefinitionData(
        positionReference: $positionReference,
        principalReference: $principalReference,
        mandateReference: $mandateReference,
        settlementResourceReference: $resourceReference,
        settlementResourceType: 'provider_deposit_account',
        provider: $provider,
        connectionReference: 'primary',
        currency: 'PHP',
        decimalPlaces: 2,
        purpose: TreasuryPositionPurpose::ClientFunds,
        custodyMode: TreasuryCustodyMode::PooledInternal,
        legalProfile: 'treasury-settlement-ph-v1',
        legalProfileVersion: '2026-07-24.1',
        idempotencyKey: $idempotencyKey,
        reconciliationReference: 'reconciliation:'.$provider.':primary',
        metadata: ['environment' => 'testing'],
    );
}
