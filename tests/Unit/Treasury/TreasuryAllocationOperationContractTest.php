<?php

declare(strict_types=1);

use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReversalRequestData;

it('exposes one package-owned durable Allocation operation contract', function () {
    $contract = new ReflectionClass(TreasuryAllocationOperationContract::class);
    $expected = [
        'activate' => TreasuryAllocationActivationData::class,
        'draw' => TreasuryAllocationMovementData::class,
        'replenish' => TreasuryAllocationMovementData::class,
        'release' => TreasuryAllocationReleaseRequestData::class,
        'reverse' => TreasuryAllocationReversalRequestData::class,
    ];

    expect($contract->isInterface())->toBeTrue()
        ->and(array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ))->toBe(array_keys($expected));

    foreach ($expected as $methodName => $input) {
        $method = $contract->getMethod($methodName);

        expect($method->getParameters())->toHaveCount(1)
            ->and($method->getParameters()[0]->getType()?->getName())->toBe($input)
            ->and($method->getReturnType()?->getName())->toBe(TreasuryAllocationOperationData::class);
    }
});

it('keeps voucher and x-change types outside the durable Allocation boundary', function () {
    $paths = [
        __DIR__.'/../../../src/Treasury/Contracts',
        __DIR__.'/../../../src/Treasury/Data',
        __DIR__.'/../../../src/Treasury/Enums',
        __DIR__.'/../../../src/Treasury/Models',
        __DIR__.'/../../../src/Treasury/ReadModels',
        __DIR__.'/../../../src/Treasury/Adapters/Bavix/BavixTreasuryAllocationOperationRuntime.php',
    ];
    $source = '';

    foreach ($paths as $path) {
        if (is_file($path)) {
            $contents = file_get_contents($path);
            $source .= is_string($contents) ? $contents : '';

            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getPathname());
                $source .= is_string($contents) ? $contents : '';
            }
        }
    }

    expect($source)->not->toContain('LBHurtado\\Voucher')
        ->not->toContain('LBHurtado\\XChange');
});
