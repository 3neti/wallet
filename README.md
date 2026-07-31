# 3neti/wallet

`3neti/wallet` is the provider-neutral value-accounting and Treasury position
layer used by x-change. It extends
[Bavix Laravel Wallet](https://github.com/bavix/laravel-wallet) with explicit
system-principal resolution, durable Treasury Inventory, principal Positions,
and append-only recognition, reservation, release, allocation, reversal, and
derecognition operations.

The package records internal financial attribution. It does not call banks,
EMIs, payout providers, or webhook endpoints.

## Supported platforms

- PHP 8.3 and 8.4
- Laravel 12 and 13
- Bavix Laravel Wallet 11

Laravel 12 and Laravel 13 are tested as separate compatibility lanes.

## Installation

```bash
composer require 3neti/wallet:^2.0@beta
php artisan migrate
```

Laravel package discovery registers
`LBHurtado\Wallet\WalletServiceProvider`. The provider loads the package-owned
Treasury migrations and the Bavix provider continues to own its wallet,
transaction, and transfer migrations.

Publish configuration only when the application needs to override the package
defaults:

```bash
php artisan vendor:publish --provider="LBHurtado\Wallet\WalletServiceProvider" --tag=config
```

Published files:

- `config/account.php`
- `config/wallet.php`

## Schema ownership

The package owns these Treasury tables:

- `treasury_settlement_resources`
- `treasury_inventories`
- `treasury_inventory_operations`
- `treasury_positions`
- `treasury_position_operations`

The migrations are additive. Applications must run `php artisan migrate` after
installing or upgrading the package. Do not copy or rename package migrations
inside the host application.

Bavix continues to own the underlying wallet ledgers, transactions, and
transfers. Treasury records reference those ledgers without exposing internal
wallet identifiers through the package read models.

## Accounting model

```text
Provider evidence
      │
      ▼
Treasury Inventory
      │
      ├── Client Funds
      ├── Pay Code Reserve
      ├── Account Funding Reserve
      ├── Provider Cost
      ├── Product Revenue
      ├── Partner Commission
      └── Commercial Revenue
```

- **Inventory** is recognized provider value available to the settlement
  system.
- **Positions** attribute that Inventory to a principal, purpose, provider
  connection, and currency.
- **Operations** are append-only and idempotent.
- **Planning contracts** are non-mutating. Their default runtimes fail closed
  by returning no executable plan.
- **Read models** omit internal Bavix ledger identifiers.

The integrating settlement package remains responsible for authenticating
provider evidence, enforcing authorization, and deciding when an operation is
permitted. `3neti/wallet` records the resulting accounting operation; it does
not manufacture settlement authority.

## System principal

Applications should resolve their system principal through
`SystemUserResolverContract`:

```php
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;

$systemPrincipal = app(SystemUserResolverContract::class)->resolve();
```

The legacy single-candidate configuration remains available:

```php
'system_user' => [
    'model' => App\Models\User::class,
    'identifier_column' => 'email',
    'identifier' => env('SYSTEM_USER_ID'),
    'candidates' => [],
],
```

An integrating package may supply multiple named `candidates` that all identify
the same principal. Resolution fails closed when candidates disagree, a model
does not implement the Bavix Wallet contract, an identifier column is unsafe,
or no principal is found.

Resolve the contract through Laravel's container. Direct construction of
`SystemUserResolverService` and actions that consume it is not part of the 2.x
public API.

## Runtime contracts

The service provider binds contracts for:

- Treasury Inventory reads and operations;
- Treasury Position provisioning, reads, and operations;
- Inventory-to-Position portfolio reads;
- commercial allocation reads;
- Treasury planning;
- operation planning; and
- sensitive metadata sanitization.

Provider packages should implement or decorate these contracts rather than add
provider-specific behavior to this package.

## Security boundaries

- Amounts are persisted in integer minor units.
- Inventory and Position mutations are append-only and idempotent.
- Conflicting reuse of an operation reference fails closed.
- Metadata keys configured in
  `wallet.treasury.sensitive_metadata_keys` are recursively redacted before
  persistence.
- Provider credentials, raw provider responses, Pay Codes, and inspection
  tokens must not be stored in Treasury metadata.
- Top-up helpers transfer existing value from the resolved system principal;
  they do not create provider value.

## Upgrade from 1.x

Version 2 is a deliberate major-version boundary.

Before upgrading:

1. update every direct consumer to accept `3neti/wallet:^2.0@beta`;
2. remove direct construction of `SystemUserResolverService` and
   `TopupWalletAction`;
3. resolve their contracts or actions through Laravel's container;
4. review the configured system-principal candidates;
5. run the package migrations; and
6. reconcile Treasury Inventory and Positions before enabling issuance.

Do not infer opening Inventory from a Bavix balance. Opening recognition must
come from an authorized, provider-backed reconciliation workflow owned by the
integrating settlement system.

## Testing

```bash
composer install
composer test
```

The package suite covers legacy Bavix wallet behavior, system-principal
resolution, Treasury DTOs and contracts, durable Inventory and Position
operations, commercial waterfall positions, idempotency, reversals, and
metadata sanitization.

CI runs the suite against every supported Laravel generation. A release tag is
created only after downstream clean-consumer tests pass.

## License

MIT
