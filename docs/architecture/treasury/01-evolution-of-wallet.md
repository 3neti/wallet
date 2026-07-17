# Evolution of Wallet

## Status

Reconciled current-state description and planning-only evolution path.

## Current package role

`3neti/wallet` is currently a Laravel integration and behavior-standardization package around `bavix/laravel-wallet`. It is not an independent wallet engine and does not ship production wallet migrations.

### Bavix currently owns

- wallet, transaction, and transfer models and interfaces;
- the wallet, transaction, and transfer persistence schema and migrations;
- deposit, withdrawal, transfer, confirmation, locking, balance refresh, and related accounting primitives;
- the underlying balance computation and internal repositories/services.

The package's `config/wallet.php` exposes and customizes Bavix configuration, including model/table names and a balance-event assembler. Shipping or publishing that integration configuration does not transfer schema ownership to `3neti/wallet`.

### `3neti/wallet` currently owns

- `WalletServiceProvider`, which merges and publishes wallet/account configuration and registers event integration;
- `SystemUserResolverService`, which resolves a configured system wallet holder;
- `WalletProvisioningService`, `WalletType`, and `HasPlatformWallets`, which standardize creation and lookup of platform, rewards, escrow, and commission wallets;
- `TopupWalletAction`, which resolves the system user and invokes a Bavix float transfer to the recipient;
- `WithdrawCash`, which withdraws a requested minor-unit amount or drains the entire current balance and records disbursement metadata;
- `TransactionData`, which maps a Bavix transaction to PHP-denominated `Money`, confirmation state, and payload;
- balance-update assembly, listening, queuing, and broadcasting integration;
- deposit/disbursement event classes used by current consumers;
- package test scaffolding, including a test user migration and dynamic loading of Bavix migrations.

The package currently has no Treasury runtime.

## Current behavior that must remain stable

### System-user top-up

`TopupWalletAction` resolves the configured system user and calls `transferFloat()` to transfer value into the target wallet. The existing test characterizes both the call boundary and resulting sender/recipient balances.

### Wallet provisioning

The package provisions one wallet per `WalletType` and uses a slug-based `firstOrCreate` path to avoid duplicates. `ESCROW` indicates held-funds intent but is not an Allocation or allocation ledger.

### Cash withdrawal

`WithdrawCash` reads the Bavix wallet balance in minor units, rejects a zero balance and over-withdrawal, and performs a confirmed withdrawal with disbursement metadata. It supports either an explicit amount or full drain. This behavior is not changed by the Treasury plan.

### Transaction and balance views

`TransactionData` is a DTO over a Bavix transaction. `BalanceUpdated` and its integration classes expose current Bavix wallet balance updates. They do not expose allocated, drawn, outstanding, or usable Treasury measures.

## Gaps between wallet and Treasury

The package currently has:

- no Inventory contract or resource eligibility model;
- no Allocation contract;
- no Slice contract;
- no allocate/reserve, draw/capture, release, repay, or reverse API;
- no idempotent operation or reservation keys;
- no allocation ledger or allocation-oriented read model;
- no usable-balance computation net of active allocations;
- no durable distinction between committed, drawn, repaid, reversed, and released amounts;
- no package-neutral cross-package Treasury reference contract;
- no characterization test for `WithdrawCash` in this package.

## Reusable foundations

Potentially reusable foundations, subject to later design decisions, include:

- Bavix integer-minor-unit transaction and transfer primitives;
- Bavix locks and atomic services;
- wallet UUIDs and transaction UUIDs as underlying identifiers;
- `brick/money` and the current DTO conventions;
- service-provider configuration and Laravel container integration;
- current wallet provisioning and event integration;
- the package's existing Pest/Testbench characterization setup.

Reuse is not yet an implementation decision. In particular, it remains open whether Bavix transactions can express the complete Treasury ledger or whether dedicated durable Allocation/Slice storage is required.

## Evolution strategy

The package evolves in place and behind additive contracts:

1. preserve existing wallet APIs and Bavix semantics;
2. characterize current top-up, transfer, withdrawal, provisioning, DTO, and event behavior;
3. introduce planning-only Treasury language and contracts;
4. provide a null runtime that cannot move money;
5. design read models before durable writes;
6. decide the ledger and persistence model explicitly;
7. add x-change adapters through package-neutral references;
8. demonstrate lifecycle scenarios before production hardening.

Treasury is an expansion of the package's accounting responsibility, not a replacement of Bavix in this bootstrap and not a separate package.

## Existing boundary debt

`DisbursementFailed` directly type-hints `LBHurtado\Voucher\Models\Voucher`. This is a pre-existing contradiction of the target boundary. It remains unchanged in this documentation-only bootstrap because removing it may alter a public event or existing listeners. It must be handled by a separately approved implementation slice.
