<?php

use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryDrawData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryRepaymentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceData;

it('keeps planning DTO shapes stable', function () {
    $cases = [
        [
            new TreasuryInventoryData(
                inventoryReference: 'inventory:cash:001',
                resourceType: 'cash',
                currency: 'PHP',
                capacityMinor: 100000,
                status: 'planned',
                idempotencyKey: 'inventory-plan:001',
                externalReference: 'resource:001',
                metadata: ['source' => 'planning'],
            ),
            [
                'inventoryReference' => 'inventory:cash:001',
                'resourceType' => 'cash',
                'currency' => 'PHP',
                'capacityMinor' => 100000,
                'status' => 'planned',
                'idempotencyKey' => 'inventory-plan:001',
                'externalReference' => 'resource:001',
                'metadata' => ['source' => 'planning'],
            ],
        ],
        [
            new TreasuryAllocationData(
                allocationReference: 'allocation:001',
                inventoryReference: 'inventory:cash:001',
                amountMinor: 50000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'allocation-plan:001',
                externalReference: 'external-context:001',
                metadata: ['purpose' => 'settlement'],
            ),
            [
                'allocationReference' => 'allocation:001',
                'inventoryReference' => 'inventory:cash:001',
                'amountMinor' => 50000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'allocation-plan:001',
                'externalReference' => 'external-context:001',
                'metadata' => ['purpose' => 'settlement'],
            ],
        ],
        [
            new TreasurySliceData(
                sliceReference: 'slice:001',
                allocationReference: 'allocation:001',
                amountMinor: 20000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'slice-plan:001',
                externalReference: 'external-context:001:part:001',
                metadata: ['sequence' => 1],
            ),
            [
                'sliceReference' => 'slice:001',
                'allocationReference' => 'allocation:001',
                'amountMinor' => 20000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'slice-plan:001',
                'externalReference' => 'external-context:001:part:001',
                'metadata' => ['sequence' => 1],
            ],
        ],
        [
            new TreasuryDrawData(
                operationReference: 'draw:001',
                allocationReference: 'allocation:001',
                amountMinor: 12000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'draw-plan:001',
                sliceReference: 'slice:001',
                metadata: ['reason' => 'planned-settlement'],
            ),
            [
                'operationReference' => 'draw:001',
                'allocationReference' => 'allocation:001',
                'amountMinor' => 12000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'draw-plan:001',
                'sliceReference' => 'slice:001',
                'metadata' => ['reason' => 'planned-settlement'],
            ],
        ],
        [
            new TreasuryReleaseData(
                operationReference: 'release:001',
                allocationReference: 'allocation:001',
                amountMinor: 8000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'release-plan:001',
                sliceReference: 'slice:001',
                metadata: ['reason' => 'planned-release'],
            ),
            [
                'operationReference' => 'release:001',
                'allocationReference' => 'allocation:001',
                'amountMinor' => 8000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'release-plan:001',
                'sliceReference' => 'slice:001',
                'metadata' => ['reason' => 'planned-release'],
            ],
        ],
        [
            new TreasuryRepaymentData(
                operationReference: 'repayment:001',
                allocationReference: 'allocation:001',
                amountMinor: 5000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'repayment-plan:001',
                sliceReference: 'slice:001',
                drawReference: 'draw:001',
                metadata: ['reason' => 'planned-repayment'],
            ),
            [
                'operationReference' => 'repayment:001',
                'allocationReference' => 'allocation:001',
                'amountMinor' => 5000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'repayment-plan:001',
                'sliceReference' => 'slice:001',
                'drawReference' => 'draw:001',
                'metadata' => ['reason' => 'planned-repayment'],
            ],
        ],
        [
            new TreasuryReversalData(
                operationReference: 'reversal:001',
                reversesOperationReference: 'draw:001',
                allocationReference: 'allocation:001',
                amountMinor: 12000,
                currency: 'PHP',
                status: 'planned',
                idempotencyKey: 'reversal-plan:001',
                sliceReference: 'slice:001',
                metadata: ['reason' => 'planned-compensation'],
            ),
            [
                'operationReference' => 'reversal:001',
                'reversesOperationReference' => 'draw:001',
                'allocationReference' => 'allocation:001',
                'amountMinor' => 12000,
                'currency' => 'PHP',
                'status' => 'planned',
                'idempotencyKey' => 'reversal-plan:001',
                'sliceReference' => 'slice:001',
                'metadata' => ['reason' => 'planned-compensation'],
            ],
        ],
    ];

    foreach ($cases as [$data, $expected]) {
        expect($data->toArray())->toBe($expected);
    }
});

it('keeps optional planning references and metadata nullable or empty', function () {
    $inventory = new TreasuryInventoryData(
        inventoryReference: 'inventory:cash:002',
        resourceType: 'cash',
        currency: 'PHP',
        capacityMinor: 1000,
        status: 'planned',
        idempotencyKey: 'inventory-plan:002',
    );

    $draw = new TreasuryDrawData(
        operationReference: 'draw:002',
        allocationReference: 'allocation:002',
        amountMinor: 1000,
        currency: 'PHP',
        status: 'planned',
        idempotencyKey: 'draw-plan:002',
    );

    expect($inventory->externalReference)->toBeNull()
        ->and($inventory->metadata)->toBe([])
        ->and($draw->sliceReference)->toBeNull()
        ->and($draw->metadata)->toBe([]);
});
