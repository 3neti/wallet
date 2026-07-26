<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Runtime;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryInventoryOperationType;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryOperationConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasurySettlementResource;

final class DatabaseTreasuryInventoryOperationRuntime implements TreasuryInventoryOperationContract
{
    private const STATUS = 'committed';

    public function __construct(
        private readonly TreasuryMetadataSanitizerContract $metadataSanitizer,
    ) {}

    public function registerInventory(TreasuryInventoryData $inventory): TreasuryInventoryData
    {
        $this->assertInternalReference($inventory->inventoryReference, 'Inventory reference');
        $this->assertInternalReference($inventory->externalReference, 'Settlement Resource reference');
        $this->assertInternalReference($inventory->idempotencyKey, 'Inventory registration idempotency key');
        $this->assertInternalReference($inventory->resourceType, 'Settlement Resource type', 64);
        $this->assertCurrency($inventory->currency);

        if ($inventory->capacityMinor !== 0) {
            throw new TreasuryInvariantViolation('Inventory registration must start with zero recognized capacity.');
        }

        $requestHash = $this->requestHash('inventory-registration', $inventory->toArray());
        $existing = TreasuryInventory::query()
            ->where('registration_idempotency_key', $inventory->idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->registeredInventoryData($existing, $requestHash);
        }

        try {
            return DB::transaction(function () use ($inventory, $requestHash): TreasuryInventoryData {
                $existing = TreasuryInventory::query()
                    ->where('registration_idempotency_key', $inventory->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->registeredInventoryData($existing, $requestHash);
                }

                $resource = TreasurySettlementResource::query()
                    ->where('resource_reference', $inventory->externalReference)
                    ->lockForUpdate()
                    ->first();

                if ($resource === null) {
                    $resource = TreasurySettlementResource::query()->create([
                        'resource_reference' => $inventory->externalReference,
                        'resource_type' => $inventory->resourceType,
                        'currency' => $inventory->currency,
                        'status' => 'active',
                        'external_reference' => $inventory->externalReference,
                        'metadata' => $inventory->metadata,
                    ]);
                } elseif ($resource->resource_type !== $inventory->resourceType || $resource->currency !== $inventory->currency) {
                    throw new TreasuryOperationConflict('Settlement Resource definition conflicts with its existing registration.');
                }

                $existingReference = TreasuryInventory::query()
                    ->where('inventory_reference', $inventory->inventoryReference)
                    ->lockForUpdate()
                    ->first();

                if ($existingReference !== null) {
                    throw new TreasuryOperationConflict('Inventory reference is already registered under another idempotency key.');
                }

                $registered = TreasuryInventory::query()->create([
                    'settlement_resource_id' => $resource->getKey(),
                    'inventory_reference' => $inventory->inventoryReference,
                    'registration_idempotency_key' => $inventory->idempotencyKey,
                    'registration_hash' => $requestHash,
                    'currency' => $inventory->currency,
                    'status' => 'active',
                    'balance_minor' => 0,
                    'version' => 0,
                    'metadata' => $inventory->metadata,
                ]);

                return $this->registeredInventoryData($registered, $requestHash);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = TreasuryInventory::query()
                ->where('registration_idempotency_key', $inventory->idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->registeredInventoryData($existing, $requestHash);
        }
    }

    public function recognize(TreasuryInventoryRecognitionData $recognition): TreasuryInventoryRecognitionData
    {
        $this->assertPositiveAmount($recognition->amountMinor);
        $this->assertCurrency($recognition->currency);
        $this->assertReference($recognition->externalReference, 'Recognition evidence reference');
        $this->assertInternalReference($recognition->operationReference, 'Operation reference');
        $this->assertInternalReference($recognition->idempotencyKey, 'Idempotency key');
        $this->assertInternalReference($recognition->inventoryReference, 'Inventory reference');
        $this->assertInternalReference($recognition->settlementResourceReference, 'Settlement Resource reference');
        $requestHash = $this->requestHash('recognition', $recognition->toArray());
        $existing = $this->existingOperation($recognition->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Recognition);

        if ($existing !== null) {
            return $this->recognitionData($existing);
        }

        try {
            return DB::transaction(function () use ($recognition, $requestHash): TreasuryInventoryRecognitionData {
                $existing = $this->existingOperation($recognition->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Recognition, true);

                if ($existing !== null) {
                    return $this->recognitionData($existing);
                }

                $inventory = $this->lockedInventory($recognition->inventoryReference);
                $this->assertInventoryAccepts($inventory, $recognition->currency);
                $inventory->loadMissing('settlementResource');

                if ($inventory->settlementResource->resource_reference !== $recognition->settlementResourceReference) {
                    throw new TreasuryInvariantViolation('Recognition Settlement Resource does not match the registered Inventory.');
                }

                $this->assertOperationReferenceAvailable($recognition->operationReference);
                $operation = $this->createOperation(
                    type: TreasuryInventoryOperationType::Recognition,
                    operationReference: $recognition->operationReference,
                    idempotencyKey: $recognition->idempotencyKey,
                    requestHash: $requestHash,
                    amountMinor: $recognition->amountMinor,
                    currency: $recognition->currency,
                    destination: $inventory,
                    effectiveAt: $recognition->effectiveAt,
                    externalReference: $recognition->externalReference,
                    metadata: $recognition->metadata,
                );

                $this->credit($inventory, $recognition->amountMinor);

                return $this->recognitionData($operation);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingOperation($recognition->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Recognition);

            if ($existing === null) {
                throw $exception;
            }

            return $this->recognitionData($existing);
        }
    }

    public function reclassify(TreasuryInventoryReclassificationData $reclassification): TreasuryInventoryReclassificationData
    {
        $this->assertPositiveAmount($reclassification->amountMinor);
        $this->assertCurrency($reclassification->currency);
        $this->assertInternalReference($reclassification->operationReference, 'Operation reference');
        $this->assertInternalReference($reclassification->idempotencyKey, 'Idempotency key');
        $this->assertInternalReference($reclassification->sourceInventoryReference, 'Source Inventory reference');
        $this->assertInternalReference($reclassification->destinationInventoryReference, 'Destination Inventory reference');

        if ($reclassification->sourceInventoryReference === $reclassification->destinationInventoryReference) {
            throw new TreasuryInvariantViolation('Reclassification requires distinct source and destination Inventories.');
        }

        $requestHash = $this->requestHash('reclassification', $reclassification->toArray());
        $existing = $this->existingOperation($reclassification->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reclassification);

        if ($existing !== null) {
            return $this->reclassificationData($existing);
        }

        try {
            return DB::transaction(function () use ($reclassification, $requestHash): TreasuryInventoryReclassificationData {
                $existing = $this->existingOperation($reclassification->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reclassification, true);

                if ($existing !== null) {
                    return $this->reclassificationData($existing);
                }

                $inventories = $this->lockedInventories([
                    $reclassification->sourceInventoryReference,
                    $reclassification->destinationInventoryReference,
                ]);
                $source = $inventories->get($reclassification->sourceInventoryReference);
                $destination = $inventories->get($reclassification->destinationInventoryReference);

                if (! $source instanceof TreasuryInventory || ! $destination instanceof TreasuryInventory) {
                    throw new TreasuryInvariantViolation('Reclassification Inventory was not found.');
                }

                $this->assertInventoryAccepts($source, $reclassification->currency);
                $this->assertInventoryAccepts($destination, $reclassification->currency);
                $this->assertSufficientBalance($source, $reclassification->amountMinor);
                $this->assertOperationReferenceAvailable($reclassification->operationReference);

                $operation = $this->createOperation(
                    type: TreasuryInventoryOperationType::Reclassification,
                    operationReference: $reclassification->operationReference,
                    idempotencyKey: $reclassification->idempotencyKey,
                    requestHash: $requestHash,
                    amountMinor: $reclassification->amountMinor,
                    currency: $reclassification->currency,
                    source: $source,
                    destination: $destination,
                    effectiveAt: $reclassification->effectiveAt,
                    externalReference: $reclassification->externalReference,
                    metadata: $reclassification->metadata,
                );

                $this->debit($source, $reclassification->amountMinor);
                $this->credit($destination, $reclassification->amountMinor);

                return $this->reclassificationData($operation);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingOperation($reclassification->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reclassification);

            if ($existing === null) {
                throw $exception;
            }

            return $this->reclassificationData($existing);
        }
    }

    public function adjust(TreasuryInventoryAdjustmentData $adjustment): TreasuryInventoryAdjustmentData
    {
        if ($adjustment->deltaAmountMinor >= 0) {
            throw new TreasuryInvariantViolation('Durable Inventory adjustments must be negative; restore capacity by reversing the adjustment.');
        }

        if ($adjustment->deltaAmountMinor === PHP_INT_MIN) {
            throw new TreasuryInvariantViolation('Inventory adjustment delta exceeds the supported integer range.');
        }

        $this->assertCurrency($adjustment->currency);
        $this->assertInternalReference($adjustment->operationReference, 'Operation reference');
        $this->assertInternalReference($adjustment->idempotencyKey, 'Idempotency key');
        $this->assertInternalReference($adjustment->inventoryReference, 'Inventory reference');
        $requestHash = $this->requestHash('adjustment', $adjustment->toArray());
        $existing = $this->existingOperation($adjustment->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Adjustment);

        if ($existing !== null) {
            return $this->adjustmentData($existing);
        }

        try {
            return DB::transaction(function () use ($adjustment, $requestHash): TreasuryInventoryAdjustmentData {
                $existing = $this->existingOperation($adjustment->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Adjustment, true);

                if ($existing !== null) {
                    return $this->adjustmentData($existing);
                }

                $inventory = $this->lockedInventory($adjustment->inventoryReference);
                $this->assertInventoryAccepts($inventory, $adjustment->currency);
                $amountMinor = abs($adjustment->deltaAmountMinor);

                $this->assertOperationReferenceAvailable($adjustment->operationReference);
                $operation = $this->createOperation(
                    type: TreasuryInventoryOperationType::Adjustment,
                    operationReference: $adjustment->operationReference,
                    idempotencyKey: $adjustment->idempotencyKey,
                    requestHash: $requestHash,
                    amountMinor: $amountMinor,
                    currency: $adjustment->currency,
                    source: $adjustment->deltaAmountMinor < 0 ? $inventory : null,
                    destination: $adjustment->deltaAmountMinor > 0 ? $inventory : null,
                    effectiveAt: $adjustment->effectiveAt,
                    externalReference: $adjustment->externalReference,
                    metadata: $adjustment->metadata,
                );

                $adjustment->deltaAmountMinor < 0
                    ? $this->debit($inventory, $amountMinor)
                    : $this->credit($inventory, $amountMinor);

                return $this->adjustmentData($operation);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingOperation($adjustment->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Adjustment);

            if ($existing === null) {
                throw $exception;
            }

            return $this->adjustmentData($existing);
        }
    }

    public function reverse(TreasuryOperationReversalData $reversal): TreasuryOperationReversalData
    {
        $this->assertPositiveAmount($reversal->amountMinor);
        $this->assertCurrency($reversal->currency);
        $this->assertInternalReference($reversal->operationReference, 'Operation reference');
        $this->assertInternalReference($reversal->idempotencyKey, 'Idempotency key');
        $this->assertInternalReference($reversal->reversesOperationReference, 'Reversed operation reference');
        $requestHash = $this->requestHash('reversal', $reversal->toArray());
        $existing = $this->existingOperation($reversal->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reversal);

        if ($existing !== null) {
            return $this->reversalData($existing);
        }

        try {
            return DB::transaction(function () use ($reversal, $requestHash): TreasuryOperationReversalData {
                $existing = $this->existingOperation($reversal->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reversal, true);

                if ($existing !== null) {
                    return $this->reversalData($existing);
                }

                $target = TreasuryInventoryOperation::query()
                    ->where('operation_reference', $reversal->reversesOperationReference)
                    ->lockForUpdate()
                    ->first();

                if ($target === null || $target->operation_type === TreasuryInventoryOperationType::Reversal) {
                    throw new TreasuryInvariantViolation('Reversal target must be an existing non-reversal Treasury operation.');
                }

                if ($target->currency !== $reversal->currency) {
                    throw new TreasuryInvariantViolation('Reversal currency must match the target operation.');
                }

                $alreadyReversedMinor = (int) TreasuryInventoryOperation::query()
                    ->where('reverses_operation_id', $target->getKey())
                    ->where('status', self::STATUS)
                    ->sum('amount_minor');

                if ($alreadyReversedMinor + $reversal->amountMinor > $target->amount_minor) {
                    throw new TreasuryInvariantViolation('Reversal exceeds the unreversed amount of the target operation.');
                }

                $inventoryIds = array_values(array_filter([
                    $target->source_inventory_id,
                    $target->destination_inventory_id,
                ]));
                $inventories = TreasuryInventory::query()
                    ->whereKey($inventoryIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $source = $target->destination_inventory_id !== null
                    ? $inventories->get($target->destination_inventory_id)
                    : null;
                $destination = $target->source_inventory_id !== null
                    ? $inventories->get($target->source_inventory_id)
                    : null;

                $this->assertOperationReferenceAvailable($reversal->operationReference);
                $operation = $this->createOperation(
                    type: TreasuryInventoryOperationType::Reversal,
                    operationReference: $reversal->operationReference,
                    idempotencyKey: $reversal->idempotencyKey,
                    requestHash: $requestHash,
                    amountMinor: $reversal->amountMinor,
                    currency: $reversal->currency,
                    source: $source,
                    destination: $destination,
                    reverses: $target,
                    effectiveAt: $reversal->effectiveAt,
                    externalReference: $reversal->externalReference,
                    metadata: $reversal->metadata,
                );

                if ($source instanceof TreasuryInventory) {
                    $this->debit($source, $reversal->amountMinor);
                }

                if ($destination instanceof TreasuryInventory) {
                    $this->credit($destination, $reversal->amountMinor);
                }

                return $this->reversalData($operation);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingOperation($reversal->idempotencyKey, $requestHash, TreasuryInventoryOperationType::Reversal);

            if ($existing === null) {
                throw $exception;
            }

            return $this->reversalData($existing);
        }
    }

    private function registeredInventoryData(TreasuryInventory $inventory, string $requestHash): TreasuryInventoryData
    {
        if ($inventory->registration_hash !== $requestHash) {
            throw new TreasuryOperationConflict('Inventory registration idempotency key was reused with different input.');
        }

        $inventory->loadMissing('settlementResource');

        return new TreasuryInventoryData(
            inventoryReference: $inventory->inventory_reference,
            resourceType: $inventory->settlementResource->resource_type,
            currency: $inventory->currency,
            capacityMinor: 0,
            status: self::STATUS,
            idempotencyKey: $inventory->registration_idempotency_key,
            externalReference: $inventory->settlementResource->resource_reference,
            metadata: $inventory->metadata ?? [],
        );
    }

    private function existingOperation(
        string $idempotencyKey,
        string $requestHash,
        TreasuryInventoryOperationType $type,
        bool $lock = false,
    ): ?TreasuryInventoryOperation {
        $query = TreasuryInventoryOperation::query()->where('idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        $operation = $query->first();

        if ($operation === null) {
            return null;
        }

        if ($operation->request_hash !== $requestHash || $operation->operation_type !== $type) {
            throw new TreasuryOperationConflict('Treasury idempotency key was reused with different input.');
        }

        return $operation;
    }

    private function lockedInventory(string $inventoryReference): TreasuryInventory
    {
        $inventory = TreasuryInventory::query()
            ->where('inventory_reference', $inventoryReference)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            throw new TreasuryInvariantViolation('Treasury Inventory was not found.');
        }

        return $inventory;
    }

    /**
     * @param  array<int, string>  $inventoryReferences
     * @return Collection<string, TreasuryInventory>
     */
    private function lockedInventories(array $inventoryReferences): Collection
    {
        return TreasuryInventory::query()
            ->whereIn('inventory_reference', $inventoryReferences)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('inventory_reference');
    }

    private function assertInventoryAccepts(TreasuryInventory $inventory, string $currency): void
    {
        if ($inventory->status !== 'active') {
            throw new TreasuryInvariantViolation('Treasury Inventory is not active.');
        }

        if ($inventory->currency !== $currency) {
            throw new TreasuryInvariantViolation('Treasury Inventory currency does not match the operation.');
        }
    }

    private function assertSufficientBalance(TreasuryInventory $inventory, int $amountMinor): void
    {
        if ($inventory->balance_minor < $amountMinor) {
            throw new TreasuryInvariantViolation('Treasury Inventory has insufficient recognized capacity.');
        }
    }

    private function assertOperationReferenceAvailable(string $operationReference): void
    {
        if (TreasuryInventoryOperation::query()->where('operation_reference', $operationReference)->exists()) {
            throw new TreasuryOperationConflict('Treasury operation reference is already in use.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createOperation(
        TreasuryInventoryOperationType $type,
        string $operationReference,
        string $idempotencyKey,
        string $requestHash,
        int $amountMinor,
        string $currency,
        ?TreasuryInventory $source = null,
        ?TreasuryInventory $destination = null,
        ?TreasuryInventoryOperation $reverses = null,
        ?string $effectiveAt = null,
        ?string $externalReference = null,
        array $metadata = [],
    ): TreasuryInventoryOperation {
        return TreasuryInventoryOperation::query()->create([
            'operation_reference' => $operationReference,
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'operation_type' => $type,
            'source_inventory_id' => $source?->getKey(),
            'destination_inventory_id' => $destination?->getKey(),
            'reverses_operation_id' => $reverses?->getKey(),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => self::STATUS,
            'effective_at' => $effectiveAt !== null
                ? $this->normalizedEffectiveAt($effectiveAt)
                : CarbonImmutable::now('UTC'),
            'external_reference' => $externalReference,
            'metadata' => $this->metadataSanitizer->forPersistence(
                $metadata,
            ),
        ]);
    }

    private function credit(TreasuryInventory $inventory, int $amountMinor): void
    {
        if ($inventory->balance_minor > PHP_INT_MAX - $amountMinor) {
            throw new TreasuryInvariantViolation('Treasury Inventory credit exceeds the supported integer range.');
        }

        $inventory->forceFill([
            'balance_minor' => $inventory->balance_minor + $amountMinor,
            'version' => $inventory->version + 1,
        ])->save();
    }

    private function debit(TreasuryInventory $inventory, int $amountMinor): void
    {
        if ($inventory->balance_minor < PHP_INT_MIN + $amountMinor) {
            throw new TreasuryInvariantViolation('Treasury Inventory debit exceeds the supported integer range.');
        }

        $inventory->forceFill([
            'balance_minor' => $inventory->balance_minor - $amountMinor,
            'version' => $inventory->version + 1,
        ])->save();
    }

    private function recognitionData(TreasuryInventoryOperation $operation): TreasuryInventoryRecognitionData
    {
        $operation->loadMissing('destinationInventory.settlementResource');

        return new TreasuryInventoryRecognitionData(
            operationReference: $operation->operation_reference,
            inventoryReference: $operation->destinationInventory->inventory_reference,
            settlementResourceReference: $operation->destinationInventory->settlementResource->resource_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            status: $operation->status,
            idempotencyKey: $operation->idempotency_key,
            effectiveAt: $operation->effective_at?->toIso8601String(),
            externalReference: $operation->external_reference,
            metadata: $operation->metadata ?? [],
        );
    }

    private function reclassificationData(TreasuryInventoryOperation $operation): TreasuryInventoryReclassificationData
    {
        $operation->loadMissing(['sourceInventory', 'destinationInventory']);

        return new TreasuryInventoryReclassificationData(
            operationReference: $operation->operation_reference,
            sourceInventoryReference: $operation->sourceInventory->inventory_reference,
            destinationInventoryReference: $operation->destinationInventory->inventory_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            status: $operation->status,
            idempotencyKey: $operation->idempotency_key,
            effectiveAt: $operation->effective_at?->toIso8601String(),
            externalReference: $operation->external_reference,
            metadata: $operation->metadata ?? [],
        );
    }

    private function adjustmentData(TreasuryInventoryOperation $operation): TreasuryInventoryAdjustmentData
    {
        $operation->loadMissing(['sourceInventory', 'destinationInventory']);
        $inventory = $operation->sourceInventory ?? $operation->destinationInventory;
        $deltaAmountMinor = $operation->source_inventory_id !== null
            ? -$operation->amount_minor
            : $operation->amount_minor;

        return new TreasuryInventoryAdjustmentData(
            operationReference: $operation->operation_reference,
            inventoryReference: $inventory->inventory_reference,
            deltaAmountMinor: $deltaAmountMinor,
            currency: $operation->currency,
            status: $operation->status,
            idempotencyKey: $operation->idempotency_key,
            effectiveAt: $operation->effective_at?->toIso8601String(),
            externalReference: $operation->external_reference,
            metadata: $operation->metadata ?? [],
        );
    }

    private function reversalData(TreasuryInventoryOperation $operation): TreasuryOperationReversalData
    {
        $operation->loadMissing('reversedOperation');

        return new TreasuryOperationReversalData(
            operationReference: $operation->operation_reference,
            reversesOperationReference: $operation->reversedOperation->operation_reference,
            amountMinor: $operation->amount_minor,
            currency: $operation->currency,
            status: $operation->status,
            idempotencyKey: $operation->idempotency_key,
            effectiveAt: $operation->effective_at?->toIso8601String(),
            externalReference: $operation->external_reference,
            metadata: $operation->metadata ?? [],
        );
    }

    private function assertPositiveAmount(int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            throw new TreasuryInvariantViolation('Treasury operation amount must be greater than zero minor units.');
        }
    }

    private function assertCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new TreasuryInvariantViolation('Treasury currency must be a three-letter uppercase code.');
        }
    }

    private function assertReference(?string $reference, string $label, int $maxLength = 191): void
    {
        if ($reference === null || trim($reference) === '') {
            throw new TreasuryInvariantViolation("{$label} is required.");
        }

        if (mb_strlen($reference) > $maxLength) {
            throw new TreasuryInvariantViolation("{$label} must not exceed {$maxLength} characters.");
        }
    }

    private function assertInternalReference(?string $reference, string $label, int $maxLength = 191): void
    {
        $this->assertReference($reference, $label, $maxLength);

        if (preg_match('/^[a-z0-9][a-z0-9:._-]*$/', (string) $reference) !== 1) {
            throw new TreasuryInvariantViolation("{$label} must use lowercase ASCII letters, numbers, colon, dot, underscore, or hyphen.");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(string $operation, array $payload): string
    {
        unset($payload['status']);

        if (isset($payload['effectiveAt'])) {
            $payload['effectiveAt'] = $this->normalizedEffectiveAt((string) $payload['effectiveAt'])->toIso8601String();
        }

        try {
            return hash('sha256', json_encode([
                'operation' => $operation,
                'payload' => $this->canonicalize($payload),
            ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } catch (JsonException $exception) {
            throw new TreasuryInvariantViolation('Treasury operation payload could not be canonicalized.', previous: $exception);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    private function normalizedEffectiveAt(string $effectiveAt): CarbonImmutable
    {
        foreach ([DateTimeInterface::RFC3339_EXTENDED, DateTimeInterface::RFC3339] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $effectiveAt);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return CarbonImmutable::instance($date)->utc();
            }
        }

        throw new TreasuryInvariantViolation('Treasury effective time must use RFC3339 format.');
    }
}
