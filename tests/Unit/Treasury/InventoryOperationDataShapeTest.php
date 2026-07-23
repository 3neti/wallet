<?php

use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

it('keeps inventory operation DTO shapes explicit and package owned', function () {
    $effectiveAt = '2026-07-23T12:00:00+08:00';

    $recognition = new TreasuryInventoryRecognitionData(
        operationReference: 'recognition:001',
        inventoryReference: 'inventory:cash:001',
        settlementResourceReference: 'resource:netbank:001',
        amountMinor: 25000,
        currency: 'PHP',
        status: 'planned',
        idempotencyKey: 'recognition-key:001',
        effectiveAt: $effectiveAt,
        externalReference: 'provider-transaction:001',
        metadata: ['provider' => 'netbank'],
    );

    $reclassification = new TreasuryInventoryReclassificationData(
        operationReference: 'reclassification:001',
        sourceInventoryReference: 'inventory:paynamics:001',
        destinationInventoryReference: 'inventory:cash:001',
        amountMinor: 24500,
        currency: 'PHP',
        status: 'planned',
        idempotencyKey: 'reclassification-key:001',
        effectiveAt: $effectiveAt,
        externalReference: 'provider-settlement:001',
    );

    $adjustment = new TreasuryInventoryAdjustmentData(
        operationReference: 'adjustment:001',
        inventoryReference: 'inventory:paynamics:001',
        deltaAmountMinor: -500,
        currency: 'PHP',
        status: 'planned',
        idempotencyKey: 'adjustment-key:001',
        effectiveAt: $effectiveAt,
        externalReference: 'reconciliation:001',
        metadata: ['reason' => 'provider-fee'],
    );

    $reversal = new TreasuryOperationReversalData(
        operationReference: 'reversal:001',
        reversesOperationReference: 'recognition:001',
        amountMinor: 25000,
        currency: 'PHP',
        status: 'planned',
        idempotencyKey: 'reversal-key:001',
        effectiveAt: $effectiveAt,
        externalReference: 'provider-reversal:001',
    );

    expect($recognition->toArray())->toBe([
        'operationReference' => 'recognition:001',
        'inventoryReference' => 'inventory:cash:001',
        'settlementResourceReference' => 'resource:netbank:001',
        'amountMinor' => 25000,
        'currency' => 'PHP',
        'status' => 'planned',
        'idempotencyKey' => 'recognition-key:001',
        'effectiveAt' => $effectiveAt,
        'externalReference' => 'provider-transaction:001',
        'metadata' => ['provider' => 'netbank'],
    ])->and($reclassification->sourceInventoryReference)->toBe('inventory:paynamics:001')
        ->and($reclassification->destinationInventoryReference)->toBe('inventory:cash:001')
        ->and($adjustment->deltaAmountMinor)->toBe(-500)
        ->and($reversal->reversesOperationReference)->toBe('recognition:001');
});

it('keeps optional inventory operation evidence nullable', function () {
    $recognition = new TreasuryInventoryRecognitionData(
        'recognition:002',
        'inventory:cash:002',
        'resource:netbank:002',
        1000,
        'PHP',
        'planned',
        'recognition-key:002',
    );

    expect($recognition->effectiveAt)->toBeNull()
        ->and($recognition->externalReference)->toBeNull()
        ->and($recognition->metadata)->toBe([]);
});
