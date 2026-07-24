<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Adapters\Bavix;

use Bavix\Wallet\Models\Wallet;
use Bavix\Wallet\Services\WalletServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryPositionConflict;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasurySettlementResource;

final readonly class BavixTreasuryPositionRuntime implements TreasuryPositionProvisioningContract
{
    public function __construct(
        private WalletServiceInterface $ledgers,
    ) {}

    public function provision(
        Model $principal,
        TreasuryPositionDefinitionData $definition,
    ): TreasuryPositionData {
        $this->assertPrincipal($principal);
        $this->assertDefinition($definition);

        $requestHash = $this->requestHash($principal, $definition);
        $existing = TreasuryPosition::query()
            ->where('registration_idempotency_key', $definition->idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->existingPosition($existing, $requestHash);
        }

        try {
            return DB::transaction(function () use ($principal, $definition, $requestHash): TreasuryPositionData {
                $principal->newQuery()
                    ->whereKey($principal->getKey())
                    ->lockForUpdate()
                    ->sole();

                $existing = TreasuryPosition::query()
                    ->where('registration_idempotency_key', $definition->idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->existingPosition($existing, $requestHash);
                }

                $resource = $this->settlementResource($definition);
                $this->assertPositionReferenceAvailable($definition);
                $ledger = $this->internalLedger($principal, $definition);

                $position = TreasuryPosition::query()->create([
                    'settlement_resource_id' => $resource->getKey(),
                    'position_reference' => $definition->positionReference,
                    'registration_idempotency_key' => $definition->idempotencyKey,
                    'registration_hash' => $requestHash,
                    'scope_hash' => $this->scopeHash($principal, $definition),
                    'principal_type' => $principal->getMorphClass(),
                    'principal_id' => $principal->getKey(),
                    'principal_reference' => $definition->principalReference,
                    'mandate_reference' => $definition->mandateReference,
                    'internal_ledger_id' => $ledger->getKey(),
                    'internal_ledger_uuid' => $ledger->uuid,
                    'provider' => $definition->provider,
                    'connection_reference' => $definition->connectionReference,
                    'currency' => $definition->currency,
                    'decimal_places' => $definition->decimalPlaces,
                    'purpose' => $definition->purpose,
                    'custody_mode' => $definition->custodyMode,
                    'legal_profile' => $definition->legalProfile,
                    'legal_profile_version' => $definition->legalProfileVersion,
                    'reconciliation_reference' => $definition->reconciliationReference,
                    'status' => 'active',
                    'metadata' => $definition->metadata,
                ]);

                return $this->positionData($position);
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = TreasuryPosition::query()
                ->where('registration_idempotency_key', $definition->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $this->existingPosition($existing, $requestHash);
            }

            throw new TreasuryPositionConflict(
                'The Treasury Position conflicts with an existing principal or internal ledger mapping.',
                previous: $exception,
            );
        }
    }

    private function settlementResource(
        TreasuryPositionDefinitionData $definition,
    ): TreasurySettlementResource {
        $resource = TreasurySettlementResource::query()
            ->where('resource_reference', $definition->settlementResourceReference)
            ->lockForUpdate()
            ->first();

        if ($resource === null) {
            return TreasurySettlementResource::query()->create([
                'resource_reference' => $definition->settlementResourceReference,
                'resource_type' => $definition->settlementResourceType,
                'currency' => $definition->currency,
                'status' => 'active',
                'external_reference' => $definition->settlementResourceReference,
                'metadata' => [
                    'provider' => $definition->provider,
                    'connection_reference' => $definition->connectionReference,
                ],
            ]);
        }

        if (
            $resource->resource_type !== $definition->settlementResourceType
            || $resource->currency !== $definition->currency
        ) {
            throw new TreasuryPositionConflict(
                'The Settlement Resource conflicts with its existing registration.',
            );
        }

        $resourceMetadata = $resource->metadata ?? [];
        $registeredProvider = $resourceMetadata['provider'] ?? null;
        $registeredConnection = $resourceMetadata['connection_reference'] ?? null;

        if (
            (is_string($registeredProvider) && $registeredProvider !== $definition->provider)
            || (is_string($registeredConnection) && $registeredConnection !== $definition->connectionReference)
        ) {
            throw new TreasuryPositionConflict(
                'The Settlement Resource is already assigned to another provider connection.',
            );
        }

        return $resource;
    }

    private function assertPositionReferenceAvailable(
        TreasuryPositionDefinitionData $definition,
    ): void {
        $existing = TreasuryPosition::query()
            ->where('position_reference', $definition->positionReference)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            throw new TreasuryPositionConflict(
                'The Treasury Position reference is registered under another idempotency key.',
            );
        }
    }

    private function internalLedger(
        Model $principal,
        TreasuryPositionDefinitionData $definition,
    ): Wallet {
        $slug = 'treasury-position-'.substr(hash('sha256', $definition->positionReference), 0, 24);
        $ledger = $this->ledgers->findBySlug($principal, $slug);

        if ($ledger === null) {
            return $this->ledgers->create($principal, [
                'name' => $definition->purpose->label(),
                'slug' => $slug,
                'description' => 'Internal ledger backing a Treasury Position.',
                'decimal_places' => $definition->decimalPlaces,
                'meta' => [
                    'treasury_managed' => true,
                    'position_reference' => $definition->positionReference,
                    'provider' => $definition->provider,
                    'connection_reference' => $definition->connectionReference,
                    'currency' => $definition->currency,
                    'purpose' => $definition->purpose->value,
                ],
            ]);
        }

        if (($ledger->meta['position_reference'] ?? null) !== $definition->positionReference) {
            throw new TreasuryPositionConflict(
                'The internal ledger slug is already assigned to another Treasury Position.',
            );
        }

        return $ledger;
    }

    private function existingPosition(
        TreasuryPosition $position,
        string $requestHash,
    ): TreasuryPositionData {
        if (! hash_equals($position->registration_hash, $requestHash)) {
            throw new TreasuryPositionConflict(
                'The Treasury Position idempotency key was reused with a different definition.',
            );
        }

        return $this->positionData($position);
    }

    private function positionData(TreasuryPosition $position): TreasuryPositionData
    {
        $position->loadMissing('settlementResource');
        $ledger = $this->ledgers->getById((int) $position->internal_ledger_id);

        return new TreasuryPositionData(
            positionReference: $position->position_reference,
            principalReference: $position->principal_reference,
            mandateReference: $position->mandate_reference,
            settlementResourceReference: $position->settlementResource->resource_reference,
            provider: $position->provider,
            connectionReference: $position->connection_reference,
            currency: $position->currency,
            decimalPlaces: $position->decimal_places,
            purpose: $position->purpose,
            custodyMode: $position->custody_mode,
            legalProfile: $position->legal_profile,
            legalProfileVersion: $position->legal_profile_version,
            balanceMinor: $ledger->getBalanceIntAttribute(),
            status: $position->status,
            reconciliationReference: $position->reconciliation_reference,
            metadata: $position->metadata ?? [],
        );
    }

    private function assertPrincipal(Model $principal): void
    {
        if (! $principal->exists || $principal->getKey() === null) {
            throw new TreasuryPositionConflict(
                'A persisted principal is required to provision a Treasury Position.',
            );
        }
    }

    private function assertDefinition(TreasuryPositionDefinitionData $definition): void
    {
        foreach ([
            ['Position reference', $definition->positionReference, 191],
            ['Principal reference', $definition->principalReference, 191],
            ['Mandate reference', $definition->mandateReference, 191],
            ['Settlement Resource reference', $definition->settlementResourceReference, 191],
            ['Settlement Resource type', $definition->settlementResourceType, 64],
            ['Connection reference', $definition->connectionReference, 191],
            ['Legal profile', $definition->legalProfile, 191],
            ['Legal profile version', $definition->legalProfileVersion, 64],
            ['Idempotency key', $definition->idempotencyKey, 191],
        ] as [$name, $value, $maximumLength]) {
            if (trim($value) === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new TreasuryPositionConflict("{$name} must be a non-empty safe reference.");
            }

            if (mb_strlen($value) > $maximumLength) {
                throw new TreasuryPositionConflict("{$name} exceeds the supported length.");
            }
        }

        if (
            mb_strlen($definition->provider) > 64
            || preg_match('/^[a-z][a-z0-9_-]*$/', $definition->provider) !== 1
        ) {
            throw new TreasuryPositionConflict(
                'Provider must be a canonical lower-case identifier.',
            );
        }

        if (preg_match('/^[A-Z]{3}$/', $definition->currency) !== 1) {
            throw new TreasuryPositionConflict('Currency must be a three-letter ISO code.');
        }

        if ($definition->decimalPlaces < 0 || $definition->decimalPlaces > 6) {
            throw new TreasuryPositionConflict(
                'Decimal places must be between zero and six.',
            );
        }
    }

    /**
     * @throws JsonException
     */
    private function requestHash(
        Model $principal,
        TreasuryPositionDefinitionData $definition,
    ): string {
        return hash('sha256', json_encode([
            'principal_type' => $principal->getMorphClass(),
            'principal_id' => (string) $principal->getKey(),
            'definition' => $definition->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    private function scopeHash(
        Model $principal,
        TreasuryPositionDefinitionData $definition,
    ): string {
        return hash('sha256', implode('|', [
            $principal->getMorphClass(),
            (string) $principal->getKey(),
            $definition->provider,
            $definition->connectionReference,
            $definition->currency,
            $definition->purpose->value,
        ]));
    }
}
