<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Services;

use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;

final class ConfigTreasuryMetadataSanitizer implements TreasuryMetadataSanitizerContract
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function forPersistence(array $metadata): array
    {
        $sensitiveKeys = array_fill_keys(
            array_values(array_filter(array_map(
                static fn (mixed $key): string => trim((string) $key),
                (array) config(
                    'wallet.treasury.sensitive_metadata_keys',
                    [
                        'pay_code',
                        'raw_pay_code',
                        'inspection_token',
                    ],
                ),
            ))),
            true,
        );

        return $this->sanitize($metadata, $sensitiveKeys);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @param  array<string, true>  $sensitiveKeys
     * @return array<array-key, mixed>
     */
    private function sanitize(
        array $metadata,
        array $sensitiveKeys,
    ): array {
        foreach ($metadata as $key => $value) {
            if (
                is_string($key)
                && isset($sensitiveKeys[$key])
            ) {
                unset($metadata[$key]);

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitize(
                    $value,
                    $sensitiveKeys,
                );
            }
        }

        return $metadata;
    }
}
