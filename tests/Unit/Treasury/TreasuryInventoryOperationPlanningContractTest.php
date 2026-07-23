<?php

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationPlanningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

it('exposes an additive package-owned inventory operation planning contract', function () {
    $contract = new ReflectionClass(TreasuryInventoryOperationPlanningContract::class);
    $expectedMethods = [
        'planRecognition' => TreasuryInventoryRecognitionData::class,
        'planReclassification' => TreasuryInventoryReclassificationData::class,
        'planAdjustment' => TreasuryInventoryAdjustmentData::class,
        'planReversal' => TreasuryOperationReversalData::class,
    ];

    expect($contract->isInterface())->toBeTrue()
        ->and(array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ))->toBe(array_keys($expectedMethods));

    foreach ($expectedMethods as $methodName => $dataClass) {
        $method = $contract->getMethod($methodName);
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(1)
            ->and($parameters[0]->getType()?->getName())->toBe($dataClass)
            ->and($method->getReturnType()?->getName())->toBe($dataClass);
    }
});

it('does not expand the established aggregate planning contract', function () {
    $contract = new ReflectionClass(TreasuryPlanningContract::class);

    expect(array_map(
        fn (ReflectionMethod $method): string => $method->getName(),
        $contract->getMethods(),
    ))->toBe([
        'planInventory',
        'planAllocation',
        'planSlice',
        'planDraw',
        'planRelease',
        'planRepayment',
        'planReversal',
    ]);
});
