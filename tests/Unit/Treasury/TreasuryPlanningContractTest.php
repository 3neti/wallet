<?php

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Transfer;
use LBHurtado\Wallet\Tests\Models\User;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryDrawData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryRepaymentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasurySliceData;

it('keeps the aggregate planning contract stable and package-owned', function () {
    $contract = new ReflectionClass(TreasuryPlanningContract::class);
    $expectedMethods = [
        'planInventory' => TreasuryInventoryData::class,
        'planAllocation' => TreasuryAllocationData::class,
        'planSlice' => TreasurySliceData::class,
        'planDraw' => TreasuryDrawData::class,
        'planRelease' => TreasuryReleaseData::class,
        'planRepayment' => TreasuryRepaymentData::class,
        'planReversal' => TreasuryReversalData::class,
    ];

    expect($contract->isInterface())->toBeTrue()
        ->and(array_map(
            fn (ReflectionMethod $method) => $method->getName(),
            $contract->getMethods()
        ))->toBe(array_keys($expectedMethods));

    foreach ($expectedMethods as $methodName => $dataClass) {
        $method = $contract->getMethod($methodName);
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(1)
            ->and($parameters[0]->getType()?->getName())->toBe($dataClass)
            ->and($method->getReturnType()?->getName())->toBe($dataClass);
    }
});

it('does not import Bavix or external commercial domain classes', function () {
    $directory = new RecursiveDirectoryIterator(__DIR__.'/../../../src/Treasury');
    $files = new RecursiveIteratorIterator($directory);
    $forbiddenImportFragments = [
        'Bavix\\Wallet',
        'LBHurtado\\Voucher',
        'XChange',
        'Facility',
        'Lien',
        'SettlementEnvelope',
        'ExecutionEngine',
    ];

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

        foreach ($matches[1] as $import) {
            foreach ($forbiddenImportFragments as $fragment) {
                expect($import)->not->toContain($fragment);
            }
        }
    }
});

it('allows a test-local planning implementation without mutating wallet state', function () {
    $planner = new class implements TreasuryPlanningContract
    {
        public function planInventory(TreasuryInventoryData $inventory): TreasuryInventoryData
        {
            return $inventory;
        }

        public function planAllocation(TreasuryAllocationData $allocation): TreasuryAllocationData
        {
            return $allocation;
        }

        public function planSlice(TreasurySliceData $slice): TreasurySliceData
        {
            return $slice;
        }

        public function planDraw(TreasuryDrawData $draw): TreasuryDrawData
        {
            return $draw;
        }

        public function planRelease(TreasuryReleaseData $release): TreasuryReleaseData
        {
            return $release;
        }

        public function planRepayment(TreasuryRepaymentData $repayment): TreasuryRepaymentData
        {
            return $repayment;
        }

        public function planReversal(TreasuryReversalData $reversal): TreasuryReversalData
        {
            return $reversal;
        }
    };

    $holder = User::factory()->create();
    $holder->deposit(10000);
    $wallet = $holder->wallet;
    $wallet->refreshBalance();
    $balanceBefore = $wallet->balanceInt;
    $transactionsBefore = Transaction::query()->count();
    $transfersBefore = Transfer::query()->count();

    $inventory = new TreasuryInventoryData('inventory:001', 'cash', 'PHP', 10000, 'planned', 'inventory:001');
    $allocation = new TreasuryAllocationData('allocation:001', 'inventory:001', 8000, 'PHP', 'planned', 'allocation:001');
    $slice = new TreasurySliceData('slice:001', 'allocation:001', 4000, 'PHP', 'planned', 'slice:001');
    $draw = new TreasuryDrawData('draw:001', 'allocation:001', 2000, 'PHP', 'planned', 'draw:001', 'slice:001');
    $release = new TreasuryReleaseData('release:001', 'allocation:001', 2000, 'PHP', 'planned', 'release:001', 'slice:001');
    $repayment = new TreasuryRepaymentData('repayment:001', 'allocation:001', 1000, 'PHP', 'planned', 'repayment:001', 'slice:001', 'draw:001');
    $reversal = new TreasuryReversalData('reversal:001', 'draw:001', 'allocation:001', 2000, 'PHP', 'planned', 'reversal:001', 'slice:001');

    expect($planner->planInventory($inventory))->toBe($inventory)
        ->and($planner->planAllocation($allocation))->toBe($allocation)
        ->and($planner->planSlice($slice))->toBe($slice)
        ->and($planner->planDraw($draw))->toBe($draw)
        ->and($planner->planRelease($release))->toBe($release)
        ->and($planner->planRepayment($repayment))->toBe($repayment)
        ->and($planner->planReversal($reversal))->toBe($reversal);

    $wallet->refreshBalance();

    expect($wallet->balanceInt)->toBe($balanceBefore)
        ->and(Transaction::query()->count())->toBe($transactionsBefore)
        ->and(Transfer::query()->count())->toBe($transfersBefore);
});
