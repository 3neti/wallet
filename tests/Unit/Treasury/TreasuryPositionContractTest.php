<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;

it('exposes provider-neutral Treasury Position contracts', function () {
    $provisioning = new ReflectionClass(TreasuryPositionProvisioningContract::class);
    $readModel = new ReflectionClass(TreasuryPositionReadModelContract::class);

    expect($provisioning->getMethod('provision')->getParameters()[0]->getType()?->getName())
        ->toBe(Model::class)
        ->and($provisioning->getMethod('provision')->getParameters()[1]->getType()?->getName())
        ->toBe(TreasuryPositionDefinitionData::class)
        ->and($provisioning->getMethod('provision')->getReturnType()?->getName())
        ->toBe(TreasuryPositionData::class)
        ->and($readModel->getMethod('find')->getReturnType()?->allowsNull())
        ->toBeTrue();
});

it('keeps Bavix types outside the public Treasury Position boundary', function () {
    $paths = [
        __DIR__.'/../../../src/Treasury/Contracts',
        __DIR__.'/../../../src/Treasury/Data',
        __DIR__.'/../../../src/Treasury/Enums',
    ];
    $source = '';

    foreach ($paths as $path) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getPathname());
                $source .= is_string($contents) ? $contents : '';
            }
        }
    }

    expect($source)->not->toContain('Bavix\\Wallet');
});
