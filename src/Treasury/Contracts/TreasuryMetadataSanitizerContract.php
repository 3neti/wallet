<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Contracts;

interface TreasuryMetadataSanitizerContract
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function forPersistence(array $metadata): array;
}
