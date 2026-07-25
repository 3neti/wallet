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
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialChargeData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionCommercialReversalData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDerecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
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

                if ($source->purpose === TreasuryPositionPurpose::CommercialClearing) {
                    $this->assertPositionPurpose(
                        $destination,
                        $this->commercialDestinationPurposes(),
                        $allocation->currency,
                    );
                } else {
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
                }
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

    public function charge(
        TreasuryPositionCommercialChargeData $charge,
    ): TreasuryPositionCommercialChargeData {
        return $this->commercialChargeData($this->transferPositionBalance(
            movement: $charge,
            type: TreasuryPositionOperationType::CommercialCharge,
            sourcePurpose: TreasuryPositionPurpose::ClientFunds,
            destinationPurpose: TreasuryPositionPurpose::CommercialClearing,
        ));
    }

    public function reverseCommercialMovement(
        TreasuryPositionCommercialReversalData $reversal,
    ): TreasuryPositionCommercialReversalData {
        $this->assertRequest(
            $reversal->operationReference,
            $reversal->idempotencyKey,
            $reversal->amountMinor,
            $reversal->currency,
            $reversal->externalReference,
        );

        if ($reversal->sourcePositionReference === $reversal->destinationPositionReference) {
            throw new TreasuryInvariantViolation(
                'Commercial reversal requires distinct source and destination Positions.',
            );
        }

        $requestHash = $this->requestHash(
            TreasuryPositionOperationType::CommercialReversal,
            $reversal->toArray(),
        );
        $existing = $this->existing(
            $reversal->idempotencyKey,
            $requestHash,
            TreasuryPositionOperationType::CommercialReversal,
        );

        if ($existing !== null) {
            return $this->commercialReversalData($existing);
        }

        try {
            return DB::transaction(function () use ($reversal, $requestHash): TreasuryPositionCommercialReversalData {
                $positions = $this->lockedPositions([
                    $reversal->sourcePositionReference,
                    $reversal->destinationPositionReference,
                ]);
                $source = $positions->get($reversal->sourcePositionReference);
                $destination = $positions->get($reversal->destinationPositionReference);

                if (! $source instanceof TreasuryPosition || ! $destination instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation(
                        'Commercial reversal Position was not found.',
                    );
                }

                $existing = $this->existing(
                    $reversal->idempotencyKey,
                    $requestHash,
                    TreasuryPositionOperationType::CommercialReversal,
                    true,
                );

                if ($existing !== null) {
                    return $this->commercialReversalData($existing);
                }

                $reversedOperation = TreasuryPositionOperation::query()
                    ->where('operation_reference', $reversal->reversesOperationReference)
                    ->lockForUpdate()
                    ->first();

                if (! $reversedOperation instanceof TreasuryPositionOperation) {
                    throw new TreasuryInvariantViolation(
                        'Commercial reversal source operation was not found.',
                    );
                }

                $this->assertCommercialReversal(
                    $reversal,
                    $reversedOperation,
                    $source,
                    $destination,
                );
                $this->assertCompatiblePositions($source, $destination);
                $this->assertOperationReferenceAvailable($reversal->operationReference);

                if (TreasuryPositionOperation::query()
                    ->where('operation_type', TreasuryPositionOperationType::CommercialReversal)
                    ->where('external_reference', $reversal->reversesOperationReference)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new TreasuryOperationConflict(
                        'Commercial movement has already been reversed.',
                    );
                }

                $ledgers = $this->lockedLedgers([
                    (int) $source->internal_ledger_id,
                    (int) $destination->internal_ledger_id,
                ]);
                $sourceLedger = $ledgers->get((int) $source->internal_ledger_id);
                $destinationLedger = $ledgers->get((int) $destination->internal_ledger_id);

                if (! $sourceLedger instanceof Wallet || ! $destinationLedger instanceof Wallet) {
                    throw new TreasuryInvariantViolation(
                        'Commercial reversal ledger was not found.',
                    );
                }

                $transfer = $sourceLedger->transfer(
                    $destinationLedger,
                    $reversal->amountMinor,
                    [
                        ...$reversal->metadata,
                        'reverses_treasury_operation_reference' => $reversal->reversesOperationReference,
                        'treasury_position_operation_reference' => $reversal->operationReference,
                        'treasury_source_position_reference' => $source->position_reference,
                        'treasury_destination_position_reference' => $destination->position_reference,
                        'treasury_operation_type' => TreasuryPositionOperationType::CommercialReversal->value,
                    ],
                );
                $transfer->loadMissing(['withdraw', 'deposit']);

                return $this->commercialReversalData(TreasuryPositionOperation::query()->create([
                    'operation_reference' => $reversal->operationReference,
                    'idempotency_key' => $reversal->idempotencyKey,
                    'request_hash' => $requestHash,
                    'operation_type' => TreasuryPositionOperationType::CommercialReversal,
                    'source_position_id' => $source->getKey(),
                    'destination_position_id' => $destination->getKey(),
                    'amount_minor' => $reversal->amountMinor,
                    'currency' => $reversal->currency,
                    'external_reference' => $reversal->reversesOperationReference,
                    'transfer_id' => $transfer->getKey(),
                    'transfer_uuid' => $transfer->uuid,
                    'source_transaction_id' => $transfer->withdraw->getKey(),
                    'source_transaction_uuid' => $transfer->withdraw->uuid,
                    'destination_transaction_id' => $transfer->deposit->getKey(),
                    'destination_transaction_uuid' => $transfer->deposit->uuid,
                    'status' => 'committed',
                    'metadata' => [
                        ...$reversal->metadata,
                        'requested_external_reference' => $reversal->externalReference,
                        'reverses_operation_reference' => $reversal->reversesOperationReference,
                    ],
                ]));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing(
                $reversal->idempotencyKey,
                $requestHash,
                TreasuryPositionOperationType::CommercialReversal,
            );

            if ($existing === null) {
                throw $exception;
            }

            return $this->commercialReversalData($existing);
        }
    }

    public function reserve(
        TreasuryPositionReservationData $reservation,
    ): TreasuryPositionReservationData {
        return $this->reservationData($this->transferPositionBalance(
            movement: $reservation,
            type: TreasuryPositionOperationType::Reservation,
            sourcePurpose: TreasuryPositionPurpose::ClientFunds,
            destinationPurpose: TreasuryPositionPurpose::PayCodeReserve,
        ));
    }

    public function release(
        TreasuryPositionReleaseData $release,
    ): TreasuryPositionReleaseData {
        return $this->releaseData($this->transferPositionBalance(
            movement: $release,
            type: TreasuryPositionOperationType::Release,
            sourcePurpose: TreasuryPositionPurpose::PayCodeReserve,
            destinationPurpose: TreasuryPositionPurpose::ClientFunds,
        ));
    }

    public function derecognize(
        TreasuryPositionDerecognitionData $derecognition,
    ): TreasuryPositionDerecognitionData {
        $this->assertRequest(
            $derecognition->operationReference,
            $derecognition->idempotencyKey,
            $derecognition->amountMinor,
            $derecognition->currency,
            $derecognition->externalReference,
        );
        $requestHash = $this->requestHash(
            TreasuryPositionOperationType::Derecognition,
            $derecognition->toArray(),
        );
        $existing = $this->existing(
            $derecognition->idempotencyKey,
            $requestHash,
            TreasuryPositionOperationType::Derecognition,
        );

        if ($existing !== null) {
            return $this->derecognitionData($existing);
        }

        try {
            return DB::transaction(function () use ($derecognition, $requestHash): TreasuryPositionDerecognitionData {
                $source = $this->lockedPosition(
                    $derecognition->sourcePositionReference,
                );
                $existing = $this->existing(
                    $derecognition->idempotencyKey,
                    $requestHash,
                    TreasuryPositionOperationType::Derecognition,
                    true,
                );

                if ($existing !== null) {
                    return $this->derecognitionData($existing);
                }

                $this->assertPositionPurpose(
                    $source,
                    [
                        TreasuryPositionPurpose::PayCodeReserve,
                        TreasuryPositionPurpose::LegacyUnattributed,
                    ],
                    $derecognition->currency,
                );
                $this->assertOperationReferenceAvailable(
                    $derecognition->operationReference,
                );
                $ledger = $this->lockedLedger((int) $source->internal_ledger_id);
                $transaction = $ledger->withdraw($derecognition->amountMinor, [
                    ...$derecognition->metadata,
                    'treasury_position_operation_reference' => $derecognition->operationReference,
                    'treasury_position_reference' => $source->position_reference,
                    'treasury_operation_type' => TreasuryPositionOperationType::Derecognition->value,
                ], true);

                return $this->derecognitionData(TreasuryPositionOperation::query()->create([
                    'operation_reference' => $derecognition->operationReference,
                    'idempotency_key' => $derecognition->idempotencyKey,
                    'request_hash' => $requestHash,
                    'operation_type' => TreasuryPositionOperationType::Derecognition,
                    'source_position_id' => $source->getKey(),
                    'amount_minor' => $derecognition->amountMinor,
                    'currency' => $derecognition->currency,
                    'external_reference' => $derecognition->externalReference,
                    'source_transaction_id' => $transaction->getKey(),
                    'source_transaction_uuid' => $transaction->uuid,
                    'status' => 'committed',
                    'metadata' => $derecognition->metadata,
                ]));
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing(
                $derecognition->idempotencyKey,
                $requestHash,
                TreasuryPositionOperationType::Derecognition,
            );

            if ($existing === null) {
                throw $exception;
            }

            return $this->derecognitionData($existing);
        }
    }

    private function transferPositionBalance(
        TreasuryPositionReservationData|TreasuryPositionReleaseData|TreasuryPositionCommercialChargeData $movement,
        TreasuryPositionOperationType $type,
        TreasuryPositionPurpose $sourcePurpose,
        TreasuryPositionPurpose $destinationPurpose,
    ): TreasuryPositionOperation {
        $this->assertRequest(
            $movement->operationReference,
            $movement->idempotencyKey,
            $movement->amountMinor,
            $movement->currency,
            $movement->externalReference,
        );

        if ($movement->sourcePositionReference === $movement->destinationPositionReference) {
            throw new TreasuryInvariantViolation(
                'Treasury Position movement requires distinct source and destination Positions.',
            );
        }

        $requestHash = $this->requestHash($type, $movement->toArray());
        $existing = $this->existing(
            $movement->idempotencyKey,
            $requestHash,
            $type,
        );

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $movement,
                $type,
                $sourcePurpose,
                $destinationPurpose,
                $requestHash,
            ): TreasuryPositionOperation {
                $positions = $this->lockedPositions([
                    $movement->sourcePositionReference,
                    $movement->destinationPositionReference,
                ]);
                $source = $positions->get($movement->sourcePositionReference);
                $destination = $positions->get($movement->destinationPositionReference);

                if (! $source instanceof TreasuryPosition || ! $destination instanceof TreasuryPosition) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Position movement Position was not found.',
                    );
                }

                $existing = $this->existing(
                    $movement->idempotencyKey,
                    $requestHash,
                    $type,
                    true,
                );

                if ($existing !== null) {
                    return $existing;
                }

                $this->assertPosition($source, $sourcePurpose, $movement->currency);
                $this->assertPosition(
                    $destination,
                    $destinationPurpose,
                    $movement->currency,
                );
                $this->assertCompatiblePositions($source, $destination);
                $this->assertOperationReferenceAvailable($movement->operationReference);
                $ledgers = $this->lockedLedgers([
                    (int) $source->internal_ledger_id,
                    (int) $destination->internal_ledger_id,
                ]);
                $sourceLedger = $ledgers->get((int) $source->internal_ledger_id);
                $destinationLedger = $ledgers->get((int) $destination->internal_ledger_id);

                if (! $sourceLedger instanceof Wallet || ! $destinationLedger instanceof Wallet) {
                    throw new TreasuryInvariantViolation(
                        'Treasury Position movement ledger was not found.',
                    );
                }

                $transfer = $sourceLedger->transfer(
                    $destinationLedger,
                    $movement->amountMinor,
                    [
                        ...$movement->metadata,
                        'treasury_position_operation_reference' => $movement->operationReference,
                        'treasury_source_position_reference' => $source->position_reference,
                        'treasury_destination_position_reference' => $destination->position_reference,
                        'treasury_operation_type' => $type->value,
                    ],
                );
                $transfer->loadMissing(['withdraw', 'deposit']);

                return TreasuryPositionOperation::query()->create([
                    'operation_reference' => $movement->operationReference,
                    'idempotency_key' => $movement->idempotencyKey,
                    'request_hash' => $requestHash,
                    'operation_type' => $type,
                    'source_position_id' => $source->getKey(),
                    'destination_position_id' => $destination->getKey(),
                    'amount_minor' => $movement->amountMinor,
                    'currency' => $movement->currency,
                    'external_reference' => $movement->externalReference,
                    'transfer_id' => $transfer->getKey(),
                    'transfer_uuid' => $transfer->uuid,
                    'source_transaction_id' => $transfer->withdraw->getKey(),
                    'source_transaction_uuid' => $transfer->withdraw->uuid,
                    'destination_transaction_id' => $transfer->deposit->getKey(),
                    'destination_transaction_uuid' => $transfer->deposit->uuid,
                    'status' => 'committed',
                    'metadata' => $movement->metadata,
                ]);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing(
                $movement->idempotencyKey,
                $requestHash,
                $type,
            );

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
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

    /**
     * @return list<TreasuryPositionPurpose>
     */
    private function commercialDestinationPurposes(): array
    {
        return [
            TreasuryPositionPurpose::ProviderCostPayable,
            TreasuryPositionPurpose::ProductRevenue,
            TreasuryPositionPurpose::PartnerCommissionPayable,
            TreasuryPositionPurpose::RoyaltyPayable,
            TreasuryPositionPurpose::TaxPayable,
            TreasuryPositionPurpose::CommercialRevenue,
        ];
    }

    private function assertCommercialReversal(
        TreasuryPositionCommercialReversalData $reversal,
        TreasuryPositionOperation $reversedOperation,
        TreasuryPosition $source,
        TreasuryPosition $destination,
    ): void {
        if (
            $reversedOperation->amount_minor !== $reversal->amountMinor
            || $reversedOperation->currency !== $reversal->currency
            || $reversedOperation->destination_position_id !== $source->getKey()
            || $reversedOperation->source_position_id !== $destination->getKey()
        ) {
            throw new TreasuryInvariantViolation(
                'Commercial reversal must exactly compensate its source operation.',
            );
        }

        if ($reversedOperation->operation_type === TreasuryPositionOperationType::CommercialCharge) {
            $this->assertPosition($source, TreasuryPositionPurpose::CommercialClearing, $reversal->currency);
            $this->assertPosition($destination, TreasuryPositionPurpose::ClientFunds, $reversal->currency);

            return;
        }

        if (
            $reversedOperation->operation_type === TreasuryPositionOperationType::Allocation
            && $destination->purpose === TreasuryPositionPurpose::CommercialClearing
        ) {
            $this->assertPositionPurpose($source, $this->commercialDestinationPurposes(), $reversal->currency);

            return;
        }

        throw new TreasuryInvariantViolation(
            'Treasury Position operation is not an eligible commercial movement.',
        );
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

    private function commercialChargeData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionCommercialChargeData {
        $operation->loadMissing(['sourcePosition', 'destinationPosition']);

        return new TreasuryPositionCommercialChargeData(
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

    private function commercialReversalData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionCommercialReversalData {
        $operation->loadMissing(['sourcePosition', 'destinationPosition']);

        return new TreasuryPositionCommercialReversalData(
            operationReference: $operation->operation_reference,
            reversesOperationReference: (string) data_get(
                $operation->metadata,
                'reverses_operation_reference',
                $operation->external_reference,
            ),
            sourcePositionReference: $operation->sourcePosition->position_reference,
            destinationPositionReference: $operation->destinationPosition->position_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            idempotencyKey: $operation->idempotency_key,
            externalReference: (string) data_get(
                $operation->metadata,
                'requested_external_reference',
                $operation->external_reference,
            ),
            transferId: $operation->transfer_id,
            transferUuid: $operation->transfer_uuid,
            sourceTransactionId: $operation->source_transaction_id,
            sourceTransactionUuid: $operation->source_transaction_uuid,
            destinationTransactionId: $operation->destination_transaction_id,
            destinationTransactionUuid: $operation->destination_transaction_uuid,
            metadata: $operation->metadata ?? [],
        );
    }

    private function reservationData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionReservationData {
        $operation->loadMissing(['sourcePosition', 'destinationPosition']);

        return new TreasuryPositionReservationData(
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

    private function releaseData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionReleaseData {
        $operation->loadMissing(['sourcePosition', 'destinationPosition']);

        return new TreasuryPositionReleaseData(
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

    private function derecognitionData(
        TreasuryPositionOperation $operation,
    ): TreasuryPositionDerecognitionData {
        $operation->loadMissing('sourcePosition');

        return new TreasuryPositionDerecognitionData(
            operationReference: $operation->operation_reference,
            sourcePositionReference: $operation->sourcePosition->position_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            idempotencyKey: $operation->idempotency_key,
            externalReference: $operation->external_reference,
            sourceTransactionId: $operation->source_transaction_id,
            sourceTransactionUuid: $operation->source_transaction_uuid,
            metadata: $operation->metadata ?? [],
        );
    }
}
