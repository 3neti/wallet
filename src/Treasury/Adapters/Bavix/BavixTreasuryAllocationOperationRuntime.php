<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Adapters\Bavix;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReversalRequestData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryAllocationConflict;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;

final class BavixTreasuryAllocationOperationRuntime implements TreasuryAllocationOperationContract
{
    public function __construct(
        private readonly TreasuryMetadataSanitizerContract $metadataSanitizer,
    ) {}

    public function activate(TreasuryAllocationActivationData $activation): TreasuryAllocationOperationData
    {
        $this->assertRequest(
            $activation->operationReference,
            $activation->allocationReference,
            $activation->idempotencyKey,
            $activation->externalReference,
            $activation->currency,
        );

        if ($activation->initialAmountMinor <= 0) {
            throw new TreasuryInvariantViolation('Treasury Allocation initial amount must be positive.');
        }

        if ($activation->maximumAmountMinor < $activation->initialAmountMinor) {
            throw new TreasuryInvariantViolation('Treasury Allocation maximum must cover its initial amount.');
        }

        if (! $activation->replenishable
            && $activation->maximumAmountMinor !== $activation->initialAmountMinor) {
            throw new TreasuryInvariantViolation(
                'A non-replenishable Treasury Allocation maximum must equal its initial amount.',
            );
        }

        $requestHash = $this->requestHash(
            TreasuryAllocationOperationType::Activation,
            $activation->toArray(),
        );
        $existing = $this->existing(
            $activation->idempotencyKey,
            $requestHash,
            TreasuryAllocationOperationType::Activation,
        );

        if ($existing !== null) {
            return $this->operationData($existing);
        }

        try {
            return DB::transaction(function () use ($activation, $requestHash): TreasuryAllocationOperationData {
                $backing = TreasuryPositionOperation::query()
                    ->with(['sourcePosition', 'destinationPosition'])
                    ->where('operation_reference', $activation->backingReservationOperationReference)
                    ->lockForUpdate()
                    ->first();

                if ($backing === null
                    || $backing->operation_type !== TreasuryPositionOperationType::Reservation
                    || $backing->status !== 'committed'
                    || $backing->sourcePosition === null
                    || $backing->destinationPosition === null) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Allocation requires one committed Position Reservation.',
                    );
                }

                $positions = $this->lockedPositionsById([
                    $backing->source_position_id,
                    $backing->destination_position_id,
                ]);
                $source = $positions->get($backing->source_position_id);
                $reserve = $positions->get($backing->destination_position_id);

                if (! $source instanceof TreasuryPosition || ! $reserve instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation('Treasury Allocation backing Position was not found.');
                }

                $this->assertPosition($source, TreasuryPositionPurpose::ClientFunds, $activation->currency);
                $this->assertPosition($reserve, TreasuryPositionPurpose::PayCodeReserve, $activation->currency);
                $this->assertCompatiblePositions($source, $reserve);

                if ($backing->amount_minor !== $activation->initialAmountMinor
                    || $backing->currency !== $activation->currency) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Allocation must exactly match its principal Reservation.',
                    );
                }

                $existing = $this->existing(
                    $activation->idempotencyKey,
                    $requestHash,
                    TreasuryAllocationOperationType::Activation,
                    true,
                );

                if ($existing !== null) {
                    return $this->operationData($existing);
                }

                if (TreasuryAllocation::query()
                    ->where('backing_position_operation_id', $backing->getKey())
                    ->lockForUpdate()
                    ->exists()) {
                    throw new TreasuryAllocationConflict(
                        'Treasury Position Reservation is already claimed by another Allocation.',
                    );
                }

                if (TreasuryAllocation::query()
                    ->where('allocation_reference', $activation->allocationReference)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new TreasuryAllocationConflict(
                        'Treasury Allocation reference is already registered.',
                    );
                }

                $this->assertOperationReferenceAvailable($activation->operationReference, false);

                $metadata = $this->metadataSanitizer->forPersistence($activation->metadata);
                $allocation = TreasuryAllocation::query()->create([
                    'allocation_reference' => $activation->allocationReference,
                    'registration_idempotency_key' => $activation->idempotencyKey,
                    'registration_hash' => $requestHash,
                    'backing_position_operation_id' => $backing->getKey(),
                    'reserve_position_id' => $reserve->getKey(),
                    'release_position_id' => $source->getKey(),
                    'currency' => $activation->currency,
                    'decimal_places' => $reserve->decimal_places,
                    'initial_amount_minor' => $activation->initialAmountMinor,
                    'maximum_amount_minor' => $activation->maximumAmountMinor,
                    'balance_minor' => $activation->initialAmountMinor,
                    'replenishable' => $activation->replenishable,
                    'version' => 1,
                    'status' => 'active',
                    'external_reference' => $activation->externalReference,
                    'metadata' => $metadata,
                ]);

                return $this->operationData(TreasuryAllocationOperation::query()->create([
                    'operation_reference' => $activation->operationReference,
                    'idempotency_key' => $activation->idempotencyKey,
                    'request_hash' => $requestHash,
                    'allocation_id' => $allocation->getKey(),
                    'operation_type' => TreasuryAllocationOperationType::Activation,
                    'source_position_id' => $source->getKey(),
                    'destination_position_id' => $reserve->getKey(),
                    'amount_minor' => $activation->initialAmountMinor,
                    'currency' => $activation->currency,
                    'balance_before_minor' => 0,
                    'balance_after_minor' => $activation->initialAmountMinor,
                    'status' => 'committed',
                    'effective_at' => now(),
                    'external_reference' => $activation->externalReference,
                    'metadata' => $metadata,
                ]));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverExisting(
                $activation->idempotencyKey,
                $requestHash,
                TreasuryAllocationOperationType::Activation,
                $exception,
            );
        }
    }

    public function draw(TreasuryAllocationMovementData $draw): TreasuryAllocationOperationData
    {
        return $this->move(
            movement: $draw,
            type: TreasuryAllocationOperationType::Draw,
            positionType: TreasuryPositionOperationType::AllocationDraw,
        );
    }

    public function replenish(TreasuryAllocationMovementData $replenishment): TreasuryAllocationOperationData
    {
        return $this->move(
            movement: $replenishment,
            type: TreasuryAllocationOperationType::Replenishment,
            positionType: TreasuryPositionOperationType::AllocationReplenishment,
        );
    }

    public function release(TreasuryAllocationReleaseRequestData $release): TreasuryAllocationOperationData
    {
        $this->assertRequest(
            $release->operationReference,
            $release->allocationReference,
            $release->idempotencyKey,
            $release->externalReference,
            $release->currency,
        );
        $requestHash = $this->requestHash(
            TreasuryAllocationOperationType::Release,
            $release->toArray(),
        );
        $existing = $this->existing(
            $release->idempotencyKey,
            $requestHash,
            TreasuryAllocationOperationType::Release,
        );

        if ($existing !== null) {
            return $this->operationData($existing);
        }

        try {
            return DB::transaction(function () use ($release, $requestHash): TreasuryAllocationOperationData {
                $allocation = $this->lockedAllocation($release->allocationReference);
                $this->assertAllocationActive($allocation, $release->currency);
                $balanceBefore = $allocation->balance_minor;
                $positions = $this->lockedPositionsById([
                    $allocation->reserve_position_id,
                    $allocation->release_position_id,
                ]);
                $reserve = $positions->get($allocation->reserve_position_id);
                $destination = $positions->get($allocation->release_position_id);

                if (! $reserve instanceof TreasuryPosition || ! $destination instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation('Treasury Allocation Position was not found.');
                }

                $this->assertPosition($reserve, TreasuryPositionPurpose::PayCodeReserve, $release->currency);
                $this->assertPosition($destination, TreasuryPositionPurpose::ClientFunds, $release->currency);
                $this->assertCompatiblePositions($reserve, $destination);
                $metadata = $this->metadataSanitizer->forPersistence($release->metadata);
                $this->assertOperationReferenceAvailable($release->operationReference);
                $positionOperation = $balanceBefore > 0
                    ? $this->transfer(
                        operationReference: $release->operationReference,
                        idempotencyKey: $release->idempotencyKey,
                        requestHash: $requestHash,
                        type: TreasuryPositionOperationType::AllocationRelease,
                        source: $reserve,
                        destination: $destination,
                        amountMinor: $balanceBefore,
                        currency: $release->currency,
                        externalReference: $release->externalReference,
                        metadata: $metadata,
                    )
                    : null;

                $allocation->forceFill([
                    'balance_minor' => 0,
                    'version' => $allocation->version + 1,
                    'status' => 'released',
                ])->save();

                return $this->operationData($this->createOperation(
                    operationReference: $release->operationReference,
                    idempotencyKey: $release->idempotencyKey,
                    requestHash: $requestHash,
                    allocation: $allocation,
                    type: TreasuryAllocationOperationType::Release,
                    source: $reserve,
                    destination: $destination,
                    amountMinor: $balanceBefore,
                    currency: $release->currency,
                    balanceBefore: $balanceBefore,
                    balanceAfter: 0,
                    externalReference: $release->externalReference,
                    metadata: $metadata,
                    positionOperation: $positionOperation,
                ));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverExisting(
                $release->idempotencyKey,
                $requestHash,
                TreasuryAllocationOperationType::Release,
                $exception,
            );
        }
    }

    public function reverse(TreasuryAllocationReversalRequestData $reversal): TreasuryAllocationOperationData
    {
        $this->assertRequest(
            $reversal->operationReference,
            $reversal->allocationReference,
            $reversal->idempotencyKey,
            $reversal->externalReference,
            $reversal->currency,
        );
        $requestHash = $this->requestHash(
            TreasuryAllocationOperationType::Reversal,
            $reversal->toArray(),
        );
        $existing = $this->existing(
            $reversal->idempotencyKey,
            $requestHash,
            TreasuryAllocationOperationType::Reversal,
        );

        if ($existing !== null) {
            return $this->operationData($existing);
        }

        try {
            return DB::transaction(function () use ($reversal, $requestHash): TreasuryAllocationOperationData {
                $allocation = $this->lockedAllocation($reversal->allocationReference);

                if ($allocation->status === 'released' || $allocation->currency !== $reversal->currency) {
                    throw new TreasuryInvariantViolation('Treasury Allocation cannot accept this reversal.');
                }

                $reversed = TreasuryAllocationOperation::query()
                    ->where('allocation_id', $allocation->getKey())
                    ->where('operation_reference', $reversal->reversesOperationReference)
                    ->lockForUpdate()
                    ->first();

                if ($reversed === null
                    || ! in_array($reversed->operation_type, [
                        TreasuryAllocationOperationType::Draw,
                        TreasuryAllocationOperationType::Replenishment,
                    ], true)
                    || $reversed->status !== 'committed') {
                    throw new TreasuryInvariantViolation(
                        'Treasury Allocation reversal source is not eligible.',
                    );
                }

                if (TreasuryAllocationOperation::query()
                    ->where('reverses_operation_id', $reversed->getKey())
                    ->lockForUpdate()
                    ->exists()) {
                    throw new TreasuryAllocationConflict(
                        'Treasury Allocation operation has already been reversed.',
                    );
                }

                $positions = $this->lockedPositionsById(array_values(array_filter([
                    $reversed->source_position_id,
                    $reversed->destination_position_id,
                ])));
                $originalSource = $positions->get($reversed->source_position_id);
                $originalDestination = $positions->get($reversed->destination_position_id);

                if (! $originalSource instanceof TreasuryPosition
                    || ! $originalDestination instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation('Treasury Allocation reversal Position was not found.');
                }

                $source = $originalDestination;
                $destination = $originalSource;

                if ($reversed->operation_type === TreasuryAllocationOperationType::Draw) {
                    $this->assertPosition($source, TreasuryPositionPurpose::ClientFunds, $reversal->currency);
                    $this->assertPosition($destination, TreasuryPositionPurpose::PayCodeReserve, $reversal->currency);
                } else {
                    $this->assertPosition($source, TreasuryPositionPurpose::PayCodeReserve, $reversal->currency);
                    $this->assertPosition($destination, TreasuryPositionPurpose::ClientFunds, $reversal->currency);
                }

                $balanceBefore = $allocation->balance_minor;
                $balanceAfter = $reversed->operation_type === TreasuryAllocationOperationType::Draw
                    ? $balanceBefore + $reversed->amount_minor
                    : $balanceBefore - $reversed->amount_minor;

                if ($balanceAfter < 0 || $balanceAfter > $allocation->maximum_amount_minor) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Allocation reversal would violate its balance limits.',
                    );
                }

                $this->assertCompatiblePositions($source, $destination);
                $metadata = $this->metadataSanitizer->forPersistence($reversal->metadata);
                $this->assertOperationReferenceAvailable($reversal->operationReference);
                $positionOperation = $this->transfer(
                    operationReference: $reversal->operationReference,
                    idempotencyKey: $reversal->idempotencyKey,
                    requestHash: $requestHash,
                    type: TreasuryPositionOperationType::AllocationReversal,
                    source: $source,
                    destination: $destination,
                    amountMinor: $reversed->amount_minor,
                    currency: $reversal->currency,
                    externalReference: $reversal->externalReference,
                    metadata: [
                        ...$metadata,
                        'reverses_allocation_operation_reference' => $reversed->operation_reference,
                    ],
                );

                $allocation->forceFill([
                    'balance_minor' => $balanceAfter,
                    'version' => $allocation->version + 1,
                    'status' => $balanceAfter === 0 ? 'depleted' : 'active',
                ])->save();

                return $this->operationData($this->createOperation(
                    operationReference: $reversal->operationReference,
                    idempotencyKey: $reversal->idempotencyKey,
                    requestHash: $requestHash,
                    allocation: $allocation,
                    type: TreasuryAllocationOperationType::Reversal,
                    source: $source,
                    destination: $destination,
                    amountMinor: $reversed->amount_minor,
                    currency: $reversal->currency,
                    balanceBefore: $balanceBefore,
                    balanceAfter: $balanceAfter,
                    externalReference: $reversal->externalReference,
                    metadata: $metadata,
                    positionOperation: $positionOperation,
                    reversedOperation: $reversed,
                ));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverExisting(
                $reversal->idempotencyKey,
                $requestHash,
                TreasuryAllocationOperationType::Reversal,
                $exception,
            );
        }
    }

    private function move(
        TreasuryAllocationMovementData $movement,
        TreasuryAllocationOperationType $type,
        TreasuryPositionOperationType $positionType,
    ): TreasuryAllocationOperationData {
        $this->assertRequest(
            $movement->operationReference,
            $movement->allocationReference,
            $movement->idempotencyKey,
            $movement->externalReference,
            $movement->currency,
        );

        if ($movement->amountMinor <= 0) {
            throw new TreasuryInvariantViolation('Treasury Allocation movement amount must be positive.');
        }

        $requestHash = $this->requestHash($type, $movement->toArray());
        $existing = $this->existing($movement->idempotencyKey, $requestHash, $type);

        if ($existing !== null) {
            return $this->operationData($existing);
        }

        try {
            return DB::transaction(function () use (
                $movement,
                $type,
                $positionType,
                $requestHash,
            ): TreasuryAllocationOperationData {
                $allocation = $this->lockedAllocation($movement->allocationReference);
                $this->assertAllocationActive($allocation, $movement->currency);
                $counterparty = TreasuryPosition::query()
                    ->where('position_reference', $movement->counterpartyPositionReference)
                    ->first();

                if ($counterparty === null) {
                    throw new TreasuryInvariantViolation('Treasury Allocation counterparty Position was not found.');
                }

                $positions = $this->lockedPositionsById([
                    $allocation->reserve_position_id,
                    $counterparty->getKey(),
                ]);
                $reserve = $positions->get($allocation->reserve_position_id);
                $counterparty = $positions->get($counterparty->getKey());

                if (! $reserve instanceof TreasuryPosition || ! $counterparty instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation('Treasury Allocation Position was not found.');
                }

                $this->assertPosition($reserve, TreasuryPositionPurpose::PayCodeReserve, $movement->currency);
                $this->assertPosition($counterparty, TreasuryPositionPurpose::ClientFunds, $movement->currency);
                $this->assertCompatiblePositions($reserve, $counterparty);
                $balanceBefore = $allocation->balance_minor;

                if ($type === TreasuryAllocationOperationType::Draw) {
                    $source = $reserve;
                    $destination = $counterparty;
                    $balanceAfter = $balanceBefore - $movement->amountMinor;
                } else {
                    if (! $allocation->replenishable) {
                        throw new TreasuryInvariantViolation('Treasury Allocation is not replenishable.');
                    }

                    $source = $counterparty;
                    $destination = $reserve;
                    $balanceAfter = $balanceBefore + $movement->amountMinor;
                }

                if ($balanceAfter < 0 || $balanceAfter > $allocation->maximum_amount_minor) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Allocation movement would violate its balance limits.',
                    );
                }

                $metadata = $this->metadataSanitizer->forPersistence($movement->metadata);
                $this->assertOperationReferenceAvailable($movement->operationReference);
                $positionOperation = $this->transfer(
                    operationReference: $movement->operationReference,
                    idempotencyKey: $movement->idempotencyKey,
                    requestHash: $requestHash,
                    type: $positionType,
                    source: $source,
                    destination: $destination,
                    amountMinor: $movement->amountMinor,
                    currency: $movement->currency,
                    externalReference: $movement->externalReference,
                    metadata: $metadata,
                );

                $allocation->forceFill([
                    'balance_minor' => $balanceAfter,
                    'version' => $allocation->version + 1,
                    'status' => $balanceAfter === 0 ? 'depleted' : 'active',
                ])->save();

                return $this->operationData($this->createOperation(
                    operationReference: $movement->operationReference,
                    idempotencyKey: $movement->idempotencyKey,
                    requestHash: $requestHash,
                    allocation: $allocation,
                    type: $type,
                    source: $source,
                    destination: $destination,
                    amountMinor: $movement->amountMinor,
                    currency: $movement->currency,
                    balanceBefore: $balanceBefore,
                    balanceAfter: $balanceAfter,
                    externalReference: $movement->externalReference,
                    metadata: $metadata,
                    positionOperation: $positionOperation,
                ));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverExisting(
                $movement->idempotencyKey,
                $requestHash,
                $type,
                $exception,
            );
        }
    }

    private function transfer(
        string $operationReference,
        string $idempotencyKey,
        string $requestHash,
        TreasuryPositionOperationType $type,
        TreasuryPosition $source,
        TreasuryPosition $destination,
        int $amountMinor,
        string $currency,
        string $externalReference,
        array $metadata,
    ): TreasuryPositionOperation {
        $ledgers = $this->lockedLedgers([
            (int) $source->internal_ledger_id,
            (int) $destination->internal_ledger_id,
        ]);
        $sourceLedger = $ledgers->get((int) $source->internal_ledger_id);
        $destinationLedger = $ledgers->get((int) $destination->internal_ledger_id);

        if (! $sourceLedger instanceof Wallet || ! $destinationLedger instanceof Wallet) {
            throw new TreasuryInvariantViolation('Treasury Allocation ledger was not found.');
        }

        if ($sourceLedger->getBalanceIntAttribute() < $amountMinor) {
            throw new TreasuryInvariantViolation('Treasury Allocation source balance is insufficient.');
        }

        $transfer = $sourceLedger->transfer($destinationLedger, $amountMinor, [
            ...$metadata,
            'treasury_allocation_operation_reference' => $operationReference,
            'treasury_operation_type' => $type->value,
        ]);
        $transfer->loadMissing(['withdraw', 'deposit']);

        return TreasuryPositionOperation::query()->create([
            'operation_reference' => $operationReference,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'operation_type' => $type,
            'source_position_id' => $source->getKey(),
            'destination_position_id' => $destination->getKey(),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'external_reference' => $externalReference,
            'transfer_id' => $transfer->getKey(),
            'transfer_uuid' => $transfer->uuid,
            'source_transaction_id' => $transfer->withdraw->getKey(),
            'source_transaction_uuid' => $transfer->withdraw->uuid,
            'destination_transaction_id' => $transfer->deposit->getKey(),
            'destination_transaction_uuid' => $transfer->deposit->uuid,
            'status' => 'committed',
            'metadata' => $metadata,
        ]);
    }

    private function createOperation(
        string $operationReference,
        string $idempotencyKey,
        string $requestHash,
        TreasuryAllocation $allocation,
        TreasuryAllocationOperationType $type,
        ?TreasuryPosition $source,
        ?TreasuryPosition $destination,
        int $amountMinor,
        string $currency,
        int $balanceBefore,
        int $balanceAfter,
        string $externalReference,
        array $metadata,
        ?TreasuryPositionOperation $positionOperation = null,
        ?TreasuryAllocationOperation $reversedOperation = null,
    ): TreasuryAllocationOperation {
        return TreasuryAllocationOperation::query()->create([
            'operation_reference' => $operationReference,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'allocation_id' => $allocation->getKey(),
            'operation_type' => $type,
            'source_position_id' => $source?->getKey(),
            'destination_position_id' => $destination?->getKey(),
            'reverses_operation_id' => $reversedOperation?->getKey(),
            'position_operation_id' => $positionOperation?->getKey(),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'balance_before_minor' => $balanceBefore,
            'balance_after_minor' => $balanceAfter,
            'status' => 'committed',
            'effective_at' => now(),
            'external_reference' => $externalReference,
            'metadata' => $metadata,
        ]);
    }

    private function lockedAllocation(string $reference): TreasuryAllocation
    {
        $allocation = TreasuryAllocation::query()
            ->where('allocation_reference', $reference)
            ->lockForUpdate()
            ->first();

        if ($allocation === null) {
            throw new TreasuryInvariantViolation('Treasury Allocation was not found.');
        }

        return $allocation;
    }

    private function assertAllocationActive(TreasuryAllocation $allocation, string $currency): void
    {
        if (! in_array($allocation->status, ['active', 'depleted'], true)
            || $allocation->currency !== $currency) {
            throw new TreasuryInvariantViolation('Treasury Allocation is not active for this currency.');
        }
    }

    private function assertPosition(
        TreasuryPosition $position,
        TreasuryPositionPurpose $purpose,
        string $currency,
    ): void {
        if ($position->status !== 'active'
            || $position->purpose !== $purpose
            || $position->currency !== $currency) {
            throw new TreasuryInvariantViolation('Treasury Position is not eligible for this Allocation operation.');
        }
    }

    private function assertCompatiblePositions(
        TreasuryPosition $source,
        TreasuryPosition $destination,
    ): void {
        if ($source->settlement_resource_id !== $destination->settlement_resource_id
            || $source->provider !== $destination->provider
            || $source->connection_reference !== $destination->connection_reference
            || $source->currency !== $destination->currency
            || $source->decimal_places !== $destination->decimal_places) {
            throw new TreasuryInvariantViolation(
                'Treasury Allocation Positions must share one provider connection and Settlement Resource.',
            );
        }
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, TreasuryPosition>
     */
    private function lockedPositionsById(array $ids): Collection
    {
        return TreasuryPosition::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (TreasuryPosition $position): int => (int) $position->getKey());
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Wallet>
     */
    private function lockedLedgers(array $ids): Collection
    {
        return Wallet::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Wallet $wallet): int => (int) $wallet->getKey());
    }

    private function assertRequest(
        string $operationReference,
        string $allocationReference,
        string $idempotencyKey,
        string $externalReference,
        string $currency,
    ): void {
        foreach ([
            ['Operation reference', $operationReference],
            ['Allocation reference', $allocationReference],
            ['Idempotency key', $idempotencyKey],
            ['External reference', $externalReference],
        ] as [$name, $reference]) {
            if (trim($reference) === ''
                || mb_strlen($reference) > 191
                || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
                throw new TreasuryInvariantViolation("{$name} is invalid.");
            }
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new TreasuryInvariantViolation('Treasury Allocation currency is invalid.');
        }
    }

    private function assertOperationReferenceAvailable(
        string $reference,
        bool $checkPositionOperations = true,
    ): void {
        if (TreasuryAllocationOperation::query()
            ->where('operation_reference', $reference)
            ->lockForUpdate()
            ->exists()
            || ($checkPositionOperations && TreasuryPositionOperation::query()
                ->where('operation_reference', $reference)
                ->lockForUpdate()
                ->exists())) {
            throw new TreasuryAllocationConflict(
                'Treasury Allocation operation reference is already registered.',
            );
        }
    }

    private function existing(
        string $idempotencyKey,
        string $requestHash,
        TreasuryAllocationOperationType $type,
        bool $lock = false,
    ): ?TreasuryAllocationOperation {
        $query = TreasuryAllocationOperation::query()
            ->with(['allocation', 'sourcePosition', 'destinationPosition', 'reversedOperation'])
            ->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        $operation = $query->first();

        if ($operation === null) {
            return null;
        }

        if ($operation->operation_type !== $type
            || ! hash_equals($operation->request_hash, $requestHash)) {
            throw new TreasuryAllocationConflict(
                'Treasury Allocation idempotency key was reused with different input.',
            );
        }

        return $operation;
    }

    private function recoverExisting(
        string $idempotencyKey,
        string $requestHash,
        TreasuryAllocationOperationType $type,
        UniqueConstraintViolationException $exception,
    ): TreasuryAllocationOperationData {
        $existing = $this->existing($idempotencyKey, $requestHash, $type);

        if ($existing === null) {
            throw $exception;
        }

        return $this->operationData($existing);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(TreasuryAllocationOperationType $type, array $payload): string
    {
        try {
            return hash('sha256', json_encode([
                'type' => $type->value,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new TreasuryInvariantViolation(
                'Treasury Allocation metadata must be JSON encodable.',
                previous: $exception,
            );
        }
    }

    private function operationData(TreasuryAllocationOperation $operation): TreasuryAllocationOperationData
    {
        $operation->loadMissing([
            'allocation',
            'sourcePosition',
            'destinationPosition',
            'reversedOperation',
        ]);

        return new TreasuryAllocationOperationData(
            operationReference: $operation->operation_reference,
            allocationReference: $operation->allocation->allocation_reference,
            operationType: $operation->operation_type,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            balanceBeforeMinor: $operation->balance_before_minor,
            balanceAfterMinor: $operation->balance_after_minor,
            status: $operation->status,
            idempotencyKey: $operation->idempotency_key,
            externalReference: $operation->external_reference,
            sourcePositionReference: $operation->sourcePosition?->position_reference,
            destinationPositionReference: $operation->destinationPosition?->position_reference,
            reversesOperationReference: $operation->reversedOperation?->operation_reference,
            metadata: $operation->metadata ?? [],
        );
    }
}
