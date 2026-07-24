<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialChargeData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;

it('charges client funds into commercial clearing and posts one exact waterfall', function () {
    $positions = commercialWaterfallPositions();
    $runtime = app(TreasuryPositionOperationContract::class);

    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'commercial-test:recognition',
        destinationPositionReference: $positions['treasury_clearing']->position_reference,
        amountMinor: 25_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-test:recognition:key',
        externalReference: 'provider-observation:commercial-test',
    ));
    $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'commercial-test:fund-client',
        sourcePositionReference: $positions['treasury_clearing']->position_reference,
        destinationPositionReference: $positions['client_funds']->position_reference,
        amountMinor: 25_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-test:fund-client:key',
        externalReference: 'commercial-test:recognition',
    ));

    $charge = new TreasuryPositionCommercialChargeData(
        operationReference: 'commercial-test:charge',
        sourcePositionReference: $positions['client_funds']->position_reference,
        destinationPositionReference: $positions['commercial_clearing']->position_reference,
        amountMinor: 25_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-test:charge:key',
        externalReference: 'commercial-sale:TEST-001',
        metadata: ['quote_reference' => 'commercial-quote:TEST-001'],
    );
    $firstCharge = $runtime->charge($charge);
    $replayedCharge = $runtime->charge($charge);

    $allocationAmounts = [
        'provider_cost' => 10_00,
        'product_revenue' => 8_00,
        'partner_commission' => 2_00,
        'commercial_revenue' => 5_00,
    ];

    foreach ($allocationAmounts as $key => $amountMinor) {
        $allocation = new TreasuryPositionAllocationData(
            operationReference: "commercial-test:allocation:{$key}",
            sourcePositionReference: $positions['commercial_clearing']->position_reference,
            destinationPositionReference: $positions[$key]->position_reference,
            amountMinor: $amountMinor,
            currency: 'PHP',
            idempotencyKey: "commercial-test:allocation:{$key}:key",
            externalReference: 'commercial-sale:TEST-001',
            metadata: ['allocation_rule_reference' => $key],
        );

        $first = $runtime->allocate($allocation);
        $replay = $runtime->allocate($allocation);

        expect($replay->toArray())->toBe($first->toArray());
    }

    expect($replayedCharge->toArray())->toBe($firstCharge->toArray())
        ->and(commercialPositionBalance($positions['client_funds']))->toBe(0)
        ->and(commercialPositionBalance($positions['commercial_clearing']))->toBe(0)
        ->and(commercialPositionBalance($positions['provider_cost']))->toBe(10_00)
        ->and(commercialPositionBalance($positions['product_revenue']))->toBe(8_00)
        ->and(commercialPositionBalance($positions['partner_commission']))->toBe(2_00)
        ->and(commercialPositionBalance($positions['commercial_revenue']))->toBe(5_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(7);
});

it('reverses a commercial allocation once with an append-only compensating movement', function () {
    $positions = commercialWaterfallPositions();
    $runtime = app(TreasuryPositionOperationContract::class);

    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'commercial-reversal-test:recognition',
        destinationPositionReference: $positions['treasury_clearing']->position_reference,
        amountMinor: 2_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-reversal-test:recognition:key',
        externalReference: 'provider-observation:commercial-reversal-test',
    ));
    $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'commercial-reversal-test:fund-client',
        sourcePositionReference: $positions['treasury_clearing']->position_reference,
        destinationPositionReference: $positions['client_funds']->position_reference,
        amountMinor: 2_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-reversal-test:fund-client:key',
        externalReference: 'commercial-reversal-test:recognition',
    ));
    $runtime->charge(new TreasuryPositionCommercialChargeData(
        operationReference: 'commercial-reversal-test:charge',
        sourcePositionReference: $positions['client_funds']->position_reference,
        destinationPositionReference: $positions['commercial_clearing']->position_reference,
        amountMinor: 2_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-reversal-test:charge:key',
        externalReference: 'commercial-sale:TEST-REVERSAL',
    ));
    $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'commercial-reversal-test:partner-allocation',
        sourcePositionReference: $positions['commercial_clearing']->position_reference,
        destinationPositionReference: $positions['partner_commission']->position_reference,
        amountMinor: 2_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-reversal-test:partner-allocation:key',
        externalReference: 'commercial-sale:TEST-REVERSAL',
    ));

    $reversal = new TreasuryPositionCommercialReversalData(
        operationReference: 'commercial-reversal-test:partner-reversal',
        reversesOperationReference: 'commercial-reversal-test:partner-allocation',
        sourcePositionReference: $positions['partner_commission']->position_reference,
        destinationPositionReference: $positions['commercial_clearing']->position_reference,
        amountMinor: 2_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-reversal-test:partner-reversal:key',
        externalReference: 'commercial-reversal:TEST-001',
    );

    $first = $runtime->reverseCommercialMovement($reversal);
    $replay = $runtime->reverseCommercialMovement($reversal);

    expect($replay->toArray())->toBe($first->toArray())
        ->and(commercialPositionBalance($positions['partner_commission']))->toBe(0)
        ->and(commercialPositionBalance($positions['commercial_clearing']))->toBe(2_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(5)
        ->and(fn () => $runtime->reverseCommercialMovement(new TreasuryPositionCommercialReversalData(
            operationReference: 'commercial-reversal-test:second-reversal',
            reversesOperationReference: 'commercial-reversal-test:partner-allocation',
            sourcePositionReference: $positions['partner_commission']->position_reference,
            destinationPositionReference: $positions['commercial_clearing']->position_reference,
            amountMinor: 2_00,
            currency: 'PHP',
            idempotencyKey: 'commercial-reversal-test:second-reversal:key',
            externalReference: 'commercial-reversal:TEST-002',
        )))->toThrow(TreasuryOperationConflict::class, 'already been reversed');
});

it('rejects commercial allocations that target client funds or cross settlement resources', function () {
    $positions = commercialWaterfallPositions();
    $runtime = app(TreasuryPositionOperationContract::class);

    $runtime->recognize(new TreasuryPositionRecognitionData(
        operationReference: 'commercial-invalid-test:recognition',
        destinationPositionReference: $positions['treasury_clearing']->position_reference,
        amountMinor: 1_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-invalid-test:recognition:key',
        externalReference: 'provider-observation:commercial-invalid-test',
    ));
    $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'commercial-invalid-test:fund-client',
        sourcePositionReference: $positions['treasury_clearing']->position_reference,
        destinationPositionReference: $positions['client_funds']->position_reference,
        amountMinor: 1_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-invalid-test:fund-client:key',
        externalReference: 'commercial-invalid-test:recognition',
    ));
    $runtime->charge(new TreasuryPositionCommercialChargeData(
        operationReference: 'commercial-invalid-test:charge',
        sourcePositionReference: $positions['client_funds']->position_reference,
        destinationPositionReference: $positions['commercial_clearing']->position_reference,
        amountMinor: 1_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-invalid-test:charge:key',
        externalReference: 'commercial-sale:TEST-INVALID',
    ));

    expect(fn () => $runtime->allocate(new TreasuryPositionAllocationData(
        operationReference: 'commercial-invalid-test:allocation',
        sourcePositionReference: $positions['commercial_clearing']->position_reference,
        destinationPositionReference: $positions['client_funds']->position_reference,
        amountMinor: 1_00,
        currency: 'PHP',
        idempotencyKey: 'commercial-invalid-test:allocation:key',
        externalReference: 'commercial-sale:TEST-INVALID',
    )))->toThrow(TreasuryInvariantViolation::class, 'not eligible');
});

/**
 * @return array<string, TreasuryPosition>
 */
function commercialWaterfallPositions(): array
{
    $system = User::factory()->create();
    $client = User::factory()->create();
    $partner = User::factory()->create();
    $runtime = app(TreasuryPositionProvisioningContract::class);
    $definitions = [
        'treasury_clearing' => [$system, TreasuryPositionPurpose::TreasuryClearing],
        'client_funds' => [$client, TreasuryPositionPurpose::ClientFunds],
        'commercial_clearing' => [$system, TreasuryPositionPurpose::CommercialClearing],
        'provider_cost' => [$system, TreasuryPositionPurpose::ProviderCostPayable],
        'product_revenue' => [$system, TreasuryPositionPurpose::ProductRevenue],
        'partner_commission' => [$partner, TreasuryPositionPurpose::PartnerCommissionPayable],
        'commercial_revenue' => [$system, TreasuryPositionPurpose::CommercialRevenue],
    ];
    $positions = [];

    foreach ($definitions as $key => [$principal, $purpose]) {
        $data = $runtime->provision(
            $principal,
            commercialPositionDefinition($principal, $purpose),
        );
        $positions[$key] = TreasuryPosition::query()
            ->where('position_reference', $data->positionReference)
            ->sole();
    }

    return $positions;
}

function commercialPositionDefinition(
    User $principal,
    TreasuryPositionPurpose $purpose,
): TreasuryPositionDefinitionData {
    $scope = hash('sha256', $principal->getKey().'|'.$purpose->value);

    return new TreasuryPositionDefinitionData(
        positionReference: 'position:commercial-test:'.substr($scope, 0, 32),
        principalReference: 'principal:user:'.$principal->getKey(),
        mandateReference: 'mandate:commercial-test:'.substr($scope, 0, 32),
        settlementResourceReference: 'resource:netbank:primary:php',
        settlementResourceType: 'provider_deposit_account',
        provider: 'netbank',
        connectionReference: 'primary',
        currency: 'PHP',
        decimalPlaces: 2,
        purpose: $purpose,
        custodyMode: TreasuryCustodyMode::ProviderProjection,
        legalProfile: 'treasury-settlement-ph-v1',
        legalProfileVersion: '2026-07-25.1',
        idempotencyKey: 'position-registration:commercial-test:'.substr($scope, 0, 32),
        reconciliationReference: 'reconciliation:netbank:primary',
    );
}

function commercialPositionBalance(TreasuryPosition $position): int
{
    return Wallet::query()
        ->findOrFail($position->internal_ledger_id)
        ->getBalanceIntAttribute();
}
