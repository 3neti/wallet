<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Exceptions\SystemUserNotFoundException;

final class SystemUserResolverService implements SystemUserResolverContract
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function resolve(): Wallet
    {
        $resolved = [];
        $candidateNames = [];

        foreach ($this->candidates() as $name => $candidate) {
            $candidateNames[] = $name;
            $wallet = $this->resolveCandidate($name, $candidate);

            if (! $wallet instanceof Model) {
                continue;
            }

            $resolved[$wallet->getMorphClass().':'.$wallet->getKey()] = $wallet;
        }

        if ($resolved === []) {
            throw new SystemUserNotFoundException(sprintf(
                'No configured system-user candidate resolved to a wallet. Attempted candidates: %s.',
                implode(', ', $candidateNames),
            ));
        }

        if (count($resolved) > 1) {
            throw new SystemUserNotFoundException(
                'Configured system-user candidates resolved to different wallets. '
                .'Remove the ambiguity before enabling system wallet operations.'
            );
        }

        return array_values($resolved)[0];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function candidates(): array
    {
        $candidates = $this->config->get('account.system_user.candidates');

        if (is_array($candidates) && $candidates !== []) {
            return $candidates;
        }

        return [
            'legacy' => [
                'model' => $this->config->get('account.system_user.model'),
                'identifier' => $this->config->get('account.system_user.identifier'),
                'identifier_column' => $this->config->get('account.system_user.identifier_column', 'uuid'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function resolveCandidate(string $name, array $candidate): ?Wallet
    {
        $modelClass = $candidate['model'] ?? null;
        $identifier = $candidate['identifier'] ?? null;
        $column = $candidate['identifier_column'] ?? null;

        if (! is_string($modelClass)
            || $modelClass === ''
            || ! class_exists($modelClass)
            || ! is_subclass_of($modelClass, Model::class)) {
            throw new SystemUserNotFoundException(
                "System-user candidate [{$name}] has an invalid Eloquent model."
            );
        }

        if ((! is_string($identifier) && ! is_int($identifier))
            || (is_string($identifier) && trim($identifier) === '')) {
            throw new SystemUserNotFoundException(
                "System-user candidate [{$name}] has no valid identifier."
            );
        }

        if (! is_string($column)
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column) !== 1) {
            throw new SystemUserNotFoundException(
                "System-user candidate [{$name}] has an invalid identifier column."
            );
        }

        $matches = $modelClass::query()
            ->where($column, $identifier)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new SystemUserNotFoundException(
                "System-user candidate [{$name}] matched more than one record."
            );
        }

        $resolved = $matches->first();

        if ($resolved === null) {
            return null;
        }

        if (! $resolved instanceof Wallet) {
            throw new SystemUserNotFoundException(
                "System-user candidate [{$name}] resolved to a model without wallet capability."
            );
        }

        return $resolved;
    }
}
