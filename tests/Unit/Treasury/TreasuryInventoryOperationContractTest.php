<?php

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

it('exposes a package-owned durable Inventory operation contract', function () {
    $contract = new ReflectionClass(TreasuryInventoryOperationContract::class);
    $expectedMethods = [
        'registerInventory' => TreasuryInventoryData::class,
        'recognize' => TreasuryInventoryRecognitionData::class,
        'reclassify' => TreasuryInventoryReclassificationData::class,
        'adjust' => TreasuryInventoryAdjustmentData::class,
        'reverse' => TreasuryOperationReversalData::class,
    ];

    expect($contract->isInterface())->toBeTrue()
        ->and(array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ))->toBe(array_keys($expectedMethods));

    foreach ($expectedMethods as $methodName => $dataClass) {
        $method = $contract->getMethod($methodName);

        expect($method->getParameters())->toHaveCount(1)
            ->and($method->getParameters()[0]->getType()?->getName())->toBe($dataClass)
            ->and($method->getReturnType()?->getName())->toBe($dataClass);
    }
});

it('keeps provider and commercial domains outside the durable Treasury boundary', function () {
    $directory = new RecursiveDirectoryIterator(__DIR__.'/../../../src/Treasury');
    $files = new RecursiveIteratorIterator($directory);

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        expect($source)->not->toContain('EmiNetbank')
            ->not->toContain('EmiPaynamics')
            ->not->toContain('LBHurtado\\XChange');
    }
});
