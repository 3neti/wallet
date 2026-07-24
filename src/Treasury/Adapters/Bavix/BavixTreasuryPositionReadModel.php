<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Adapters\Bavix;

use Bavix\Wallet\Services\WalletServiceInterface;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;

final readonly class BavixTreasuryPositionReadModel implements TreasuryPositionReadModelContract
{
    public function __construct(
        private WalletServiceInterface $ledgers,
    ) {}

    public function find(string $positionReference): ?TreasuryPositionData
    {
        $position = TreasuryPosition::query()
            ->with('settlementResource')
            ->where('position_reference', $positionReference)
            ->first();

        return $position === null ? null : $this->positionData($position);
    }

    public function forPrincipal(string $principalReference): array
    {
        return TreasuryPosition::query()
            ->with('settlementResource')
            ->where('principal_reference', $principalReference)
            ->orderBy('provider')
            ->orderBy('connection_reference')
            ->orderBy('currency')
            ->orderBy('purpose')
            ->get()
            ->map(fn (TreasuryPosition $position): TreasuryPositionData => $this->positionData($position))
            ->all();
    }

    public function forConnection(
        string $provider,
        string $connectionReference,
        string $currency,
    ): array {
        return TreasuryPosition::query()
            ->with('settlementResource')
            ->where('provider', mb_strtolower(trim($provider)))
            ->where('connection_reference', trim($connectionReference))
            ->where('currency', mb_strtoupper(trim($currency)))
            ->orderBy('purpose')
            ->orderBy('principal_reference')
            ->get()
            ->map(fn (TreasuryPosition $position): TreasuryPositionData => $this->positionData($position))
            ->all();
    }

    private function positionData(TreasuryPosition $position): TreasuryPositionData
    {
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
}
