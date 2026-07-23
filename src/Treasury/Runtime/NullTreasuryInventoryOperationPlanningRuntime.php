<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Runtime;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationPlanningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryAdjustmentData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryReclassificationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;

final class NullTreasuryInventoryOperationPlanningRuntime implements TreasuryInventoryOperationPlanningContract
{
    public function planRecognition(TreasuryInventoryRecognitionData $recognition): TreasuryInventoryRecognitionData
    {
        return new TreasuryInventoryRecognitionData(
            operationReference: $recognition->operationReference,
            inventoryReference: $recognition->inventoryReference,
            settlementResourceReference: $recognition->settlementResourceReference,
            amountMinor: $recognition->amountMinor,
            currency: $recognition->currency,
            status: NullTreasuryPlanningRuntime::STATUS,
            idempotencyKey: $recognition->idempotencyKey,
            effectiveAt: $recognition->effectiveAt,
            externalReference: $recognition->externalReference,
            metadata: $this->metadata($recognition->metadata, 'inventory-recognition'),
        );
    }

    public function planReclassification(TreasuryInventoryReclassificationData $reclassification): TreasuryInventoryReclassificationData
    {
        return new TreasuryInventoryReclassificationData(
            operationReference: $reclassification->operationReference,
            sourceInventoryReference: $reclassification->sourceInventoryReference,
            destinationInventoryReference: $reclassification->destinationInventoryReference,
            amountMinor: $reclassification->amountMinor,
            currency: $reclassification->currency,
            status: NullTreasuryPlanningRuntime::STATUS,
            idempotencyKey: $reclassification->idempotencyKey,
            effectiveAt: $reclassification->effectiveAt,
            externalReference: $reclassification->externalReference,
            metadata: $this->metadata($reclassification->metadata, 'inventory-reclassification'),
        );
    }

    public function planAdjustment(TreasuryInventoryAdjustmentData $adjustment): TreasuryInventoryAdjustmentData
    {
        return new TreasuryInventoryAdjustmentData(
            operationReference: $adjustment->operationReference,
            inventoryReference: $adjustment->inventoryReference,
            deltaAmountMinor: $adjustment->deltaAmountMinor,
            currency: $adjustment->currency,
            status: NullTreasuryPlanningRuntime::STATUS,
            idempotencyKey: $adjustment->idempotencyKey,
            effectiveAt: $adjustment->effectiveAt,
            externalReference: $adjustment->externalReference,
            metadata: $this->metadata($adjustment->metadata, 'inventory-adjustment'),
        );
    }

    public function planReversal(TreasuryOperationReversalData $reversal): TreasuryOperationReversalData
    {
        return new TreasuryOperationReversalData(
            operationReference: $reversal->operationReference,
            reversesOperationReference: $reversal->reversesOperationReference,
            amountMinor: $reversal->amountMinor,
            currency: $reversal->currency,
            status: NullTreasuryPlanningRuntime::STATUS,
            idempotencyKey: $reversal->idempotencyKey,
            effectiveAt: $reversal->effectiveAt,
            externalReference: $reversal->externalReference,
            metadata: $this->metadata($reversal->metadata, 'inventory-operation-reversal'),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadata(array $metadata, string $operation): array
    {
        return array_merge($metadata, [
            'treasury_runtime' => NullTreasuryPlanningRuntime::RUNTIME,
            'treasury_runtime_status' => NullTreasuryPlanningRuntime::RUNTIME_STATUS,
            'treasury_operation' => $operation,
        ]);
    }
}
