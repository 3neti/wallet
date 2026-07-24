<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\ReadModels;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryPositionData;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;

final class DatabaseTreasuryInventoryPositionReadModel implements TreasuryInventoryPositionReadModelContract
{
    public function operationExists(string $operationReference): bool
    {
        return TreasuryInventoryOperation::query()
            ->where('operation_reference', trim($operationReference))
            ->exists();
    }

    public function find(string $inventoryReference): ?TreasuryInventoryPositionData
    {
        $inventory = TreasuryInventory::query()
            ->with('settlementResource')
            ->where('inventory_reference', $inventoryReference)
            ->first();

        if ($inventory === null) {
            return null;
        }

        $lastOperationReference = TreasuryInventoryOperation::query()
            ->where(function ($query) use ($inventory): void {
                $query->where('source_inventory_id', $inventory->getKey())
                    ->orWhere('destination_inventory_id', $inventory->getKey());
            })
            ->latest('id')
            ->value('operation_reference');

        return new TreasuryInventoryPositionData(
            inventoryReference: $inventory->inventory_reference,
            settlementResourceReference: $inventory->settlementResource->resource_reference,
            resourceType: $inventory->settlementResource->resource_type,
            currency: $inventory->currency,
            status: $inventory->status,
            balanceMinor: $inventory->balance_minor,
            version: $inventory->version,
            lastOperationReference: is_string($lastOperationReference) ? $lastOperationReference : null,
            hasTreasuryFacts: $lastOperationReference !== null,
            metadata: [
                'treasury_read_model' => 'inventory-ledger',
                'treasury_read_model_status' => 'read-only',
                'treasury_facts' => $lastOperationReference !== null ? 'present' : 'absent',
            ],
        );
    }
}
