<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Adapters\Bavix;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionAllocationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;

final class BavixTreasuryPositionOperationRuntime implements TreasuryPositionOperationContract
{
    public function recognize(
        TreasuryPositionRecognitionData $recognition,
    ): TreasuryPositionRecognitionData {
        $this->assertRequest(
            $recognition->operationReference,
            $recognition->idempotencyKey,
            $recognition->amountMinor,
            $recognition->currency,
            $recognition->externalReference,
        );
        $requestHash = $this->requestHash(
            TreasuryPositionOperationType::Recognition,
            $recognition->toArray(),
        );
        $existing = $this->existing(
            $recognition->idempotencyKey,
            $requestHash,
            TreasuryPositionOperationType::Recognition,
        );

        if ($existing !== null) {
            return $this->recognitionData($existing);
        }

        try {
            return DB::transaction(function () use ($recognition, $requestHash): TreasuryPositionRecognitionData {
                $destination = $this->lockedPosition(
                    $recognition->destinationPositionReference,
                );
                $existing = $this->existing(
                    $recognition->idempotencyKey,
                    $requestHash,
                    TreasuryPositionOperationType::Recognition,
                    true,
                );

                if ($existing !== null) {
                    return $this->recognitionData($existing);
                }

                $this->assertPositionPurpose(
                    $destination,
                    [
                        TreasuryPositionPurpose::TreasuryClearing,
                        TreasuryPositionPurpose::LegacyUnattributed,
                    ],
                    $recognition->currency,
                );
                $this->assertOperationReferenceAvailable($recognition->operationReference);
                $ledger = $this->lockedLedger((int) $destination->internal_ledger_id);
                $transaction = $ledger->deposit($recognition->amountMinor, [
                    ...$recognition->metadata,
                    'treasury_position_operation_reference' => $recognition->operationReference,
                    'treasury_position_reference' => $destination->position_reference,
                    'treasury_operation_type' => TreasuryPositionOperationType::Recognition->value,
                ], true);

                return $this->recognitionData(TreasuryPositionOperation::query()->create([
                    'operation_reference' => $recognition->operationReference,
                    'idempotency_key' => $recognition->idempotencyKey,
                    'request_hash' => $requestHash,
                    'operation_type' => TreasuryPositionOperationType::Recognition,
                    'destination_position_id' => $destination->getKey(),
                    'amount_minor' => $recognition->amountMinor,
                    'currency' => $recognition->currency,
                    'external_reference' => $recognition->externalReference,
                    'destination_transaction_id' => $transaction->getKey(),
                    'destination_transaction_uuid' => $transaction->uuid,
                    'status' => 'committed',
                    'metadata' => $recognition->metadata,
                ]));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing(
                $recognition->idempotencyKey,
                $requestHash,
                TreasuryPositionOperationType::Recognition,
            );

            if ($existing === null) {
                throw $exception;
            }

            return $this->recognitionData($existing);
        }
    }

    public function allocate(
        TreasuryPositionAllocationData $allocation,
    ): TreasuryPositionAllocationData {
        $this->assertRequest(
            $allocation->operationReference,
            $allocation->idempotencyKey,
            $allocation->amountMinor,
            $allocation->currency,
            $allocation->externalReference,
        );

        if ($allocation->sourcePositionReference === $allocation->destinationPositionReference) {
            throw new TreasuryInvariantViolation(
                'Treasury allocation requires distinct source and destination Positions.',
            );
        }

        $requestHash = $this->requestHash(
            TreasuryPositionOperationType::Allocation,
            $allocation->toArray(),
        );
        $existing = $this->existing(
            $allocation->idempotencyKey,
            $requestHash,
            TreasuryPositionOperationType::Allocation,
        );

        if ($existing !== null) {
            return $this->allocationData($existing);
        }

        try {
            return DB::transaction(function () use ($allocation, $requestHash): TreasuryPositionAllocationData {
                $positions = $this->lockedPositions([
                    $allocation->sourcePositionReference,
                    $allocation->destinationPositionReference,
                ]);
                $source = $positions->get($allocation->sourcePositionReference);
                $destination = $positions->get($allocation->destinationPositionReference);

                if (! $source instanceof TreasuryPosition || ! $destination instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation(
                        'Treasury allocation Position was not found.',
                    );
                }

                $existing = $this->existing(
                    $allocation->idempotencyKey,
                    $requestHash,
                    TreasuryPositionOperationType::Allocation,
                    true,
                );

                if ($existing !== null) {
                    return $this->allocationData($existing);
                }

                $this->assertPositionPurpose(
                    $source,
                    [
                        TreasuryPositionPurpose::TreasuryClearing,
                        TreasuryPositionPurpose::LegacyUnattributed,
                    ],
                    $allocation->currency,
                );
                $this->assertPosition(
                    $destination,
                    TreasuryPositionPurpose::ClientFunds,
                    $allocation->currency,
                );
                $this->assertCompatiblePositions($source, $destination);
                $this->assertOperationReferenceAvailable($allocation->operationReference);
                $ledgers = $this->lockedLedgers([
                    (int) $source->internal_ledger_id,
                    (int) $destination->internal_ledger_id,
                ]);
                $sourceLedger = $ledgers->get((int) $source->internal_ledger_id);
                $destinationLedger = $ledgers->get((int) $destination->internal_ledger_id);

                if (! $sourceLedger instanceof Wallet || ! $destinationLedger instanceof Wallet) {
                    throw new TreasuryInvariantViolation(
                        'Treasury allocation ledger was not found.',
                    );
                }

                $transfer = $sourceLedger->transfer($destinationLedger, $allocation->amountMinor, [
                    ...$allocation->metadata,
                    'treasury_position_operation_reference' => $allocation->operationReference,
                    'treasury_source_position_reference' => $source->position_reference,
                    'treasury_destination_position_reference' => $destination->position_reference,
                    'treasury_operation_type' => TreasuryPositionOperationType::Allocation->value,
                ]);
                $transfer->loadMissing(['withdraw', 'deposit']);

                return $this->allocationData(TreasuryPositionOperation::query()->create([
                    'operation_reference' => $allocation->operationReference,
                    'idempotency_key' => $allocation->idempotencyKey,
                    'request_hash' => $requestHash,
                    'operation_type' => TreasuryPositionOperationType::Allocation,
                    'source_position_id' => $source->getKey(),
                    'destination_position_id' => $destination->getKey(),
                    'amount_minor' => $allocation->amountMinor,
                    'currency' => $allocation->currency,
                    'external_reference' => $allocation->externalReference,
                    'transfer_id' => $transfer->getKey(),
                    'transfer_uuid' => $transfer->uuid,
                    'source_transaction_id' => $transfer->withdraw->getKey(),
                    'source_transaction_uuid' => $transfer->withdraw->uuid,
                    'destination_transaction_id' => $transfer->deposit->getKey(),
                    'destination_transaction_uuid' => $transfer->deposit->uuid,
                    'status' => 'committed',
                    'metadata' => $allocation->metadata,
                ]));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing(
                $allocation->idempotencyKey,
                $requestHash,
                TreasuryPositionOperationType::Allocation,
            );

            if ($existing === null) {
                throw $exception;
            }

            return $this->allocationData($existing);
        }
    }

    private function assertRequest(
        string $operationReference,
        string $idempotencyKey,
        int $amountMinor,
        string $currency,
        string $externalReference,
    ): void {
        foreach ([
            ['Operation reference', $operationReference],
            ['Idempotency key', $idempotencyKey],
            ['External reference', $externalReference],
        ] as [$name, $reference]) {
            if (
                trim($reference) === ''
                || mb_strlen($reference) > 191
                || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1
            ) {
                throw new TreasuryInvariantViolation("{$name} is invalid.");
            }
        }

        if ($amountMinor <= 0) {
            throw new TreasuryInvariantViolation(
                'Treasury Position operation amount must be positive.',
            );
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new TreasuryInvariantViolation(
                'Treasury Position operation currency is invalid.',
            );
        }
    }

    private function assertPosition(
        TreasuryPosition $position,
        TreasuryPositionPurpose $purpose,
        string $currency,
    ): void {
        $this->assertPositionPurpose($position, [$purpose], $currency);
    }

    /**
     * @param  list<TreasuryPositionPurpose>  $purposes
     */
    private function assertPositionPurpose(
        TreasuryPosition $position,
        array $purposes,
        string $currency,
    ): void {
        if (
            $position->status !== 'active'
            || ! in_array($position->purpose, $purposes, true)
            || $position->currency !== $currency
        ) {
            throw new TreasuryInvariantViolation(
                'Treasury Position is not eligible for this operation.',
            );
        }
    }

    private function assertCompatiblePositions(
        TreasuryPosition $source,
        TreasuryPosition $destination,
    ): void {
        if (
            $source->settlement_resource_id !== $destination->settlement_resource_id
            || $source->provider !== $destination->provider
            || $source->connection_reference !== $destination->connection_reference
            || $source->currency !== $destination->currency
            || $source->decimal_places !== $destination->decimal_places
        ) {
            throw new TreasuryInvariantViolation(
                'Treasury allocation Positions must share one provider connection and Settlement Resource.',
            );
        }
    }

    private function lockedPosition(string $reference): TreasuryPosition
    {
        $position = TreasuryPosition::query()
            ->where('position_reference', $reference)
            ->lockForUpdate()
            ->first();

        if ($position === null) {
            throw new TreasuryInvariantViolation('Treasury Position was not found.');
        }

        return $position;
    }

    /**
     * @param  list<string>  $references
     * @return Collection<string, TreasuryPosition>
     */
    private function lockedPositions(array $references): Collection
    {
        return TreasuryPosition::query()
            ->whereIn('position_reference', $references)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('position_reference');
    }

    private function lockedLedger(int $id): Wallet
    {
        $ledger = Wallet::query()->whereKey($id)->lockForUpdate()->first();

        if ($ledger === null) {
            throw new TreasuryInvariantViolation('Treasury Position ledger was not found.');
        }

        return $ledger;
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

    private function assertOperationReferenceAvailable(string $reference): void
    {
        if (
            TreasuryPositionOperation::query()
                ->where('operation_reference', $reference)
                ->lockForUpdate()
                ->exists()
        ) {
            throw new TreasuryOperationConflict(
                'Treasury Position operation reference is already registered.',
            );
        }
    }

    private function existing(
        string $idempotencyKey,
        string $requestHash,
        TreasuryPositionOperationType $type,
        bool $lock = false,
    ): ?TreasuryPositionOperation {
        $query = TreasuryPositionOperation::query()
            ->with(['sourcePosition', 'destinationPosition'])
            ->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        $operation = $query->first();

        if ($operation === null) {
            return null;
        }

        if (
            $operation->operation_type !== $type
            || ! hash_equals($operation->request_hash, $requestHash)
        ) {
            throw new TreasuryOperationConflict(
                'Treasury Position operation idempotency key was reused with different input.',
            );
        }

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(
        TreasuryPositionOperationType $type,
        array $payload,
    ): string {
        foreach ([
            'destinationTransactionId',
            'destinationTransactionUuid',
            'sourceTransactionId',
            'sourceTransactionUuid',
            'transferId',
            'transferUuid',
        ] as $output) {
            unset($payload[$output]);
        }

        try {
            return hash('sha256', json_encode([
                'type' => $type->value,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new TreasuryInvariantViolation(
                'Treasury Position operation metadata must be JSON encodable.',
                previous: $exception,
            );
        }
    }

    private function recognitionData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionRecognitionData {
        $operation->loadMissing('destinationPosition');

        return new TreasuryPositionRecognitionData(
            operationReference: $operation->operation_reference,
            destinationPositionReference: $operation->destinationPosition->position_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            idempotencyKey: $operation->idempotency_key,
            externalReference: $operation->external_reference,
            destinationTransactionId: $operation->destination_transaction_id,
            destinationTransactionUuid: $operation->destination_transaction_uuid,
            metadata: $operation->metadata ?? [],
        );
    }

    private function allocationData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionAllocationData {
        $operation->loadMissing(['sourcePosition', 'destinationPosition']);

        return new TreasuryPositionAllocationData(
            operationReference: $operation->operation_reference,
            sourcePositionReference: $operation->sourcePosition->position_reference,
            destinationPositionReference: $operation->destinationPosition->position_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            idempotencyKey: $operation->idempotency_key,
            externalReference: $operation->external_reference,
            transferId: $operation->transfer_id,
            transferUuid: $operation->transfer_uuid,
            sourceTransactionId: $operation->source_transaction_id,
            sourceTransactionUuid: $operation->source_transaction_uuid,
            destinationTransactionId: $operation->destination_transaction_id,
            destinationTransactionUuid: $operation->destination_transaction_uuid,
            metadata: $operation->metadata ?? [],
        );
    }
}
