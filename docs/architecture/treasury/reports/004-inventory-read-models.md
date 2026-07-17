# Phase 4 Inventory Read Models Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: read-only Inventory baseline over current wallet/Bavix state
- Result: **green**; focused and full suites pass with no database or wallet mutation

## Files inspected

- `src/WalletServiceProvider.php`
- `src/Treasury/Contracts/TreasuryPlanningContract.php`
- all Phase 2 Treasury DTOs
- `src/Treasury/Runtime/NullTreasuryPlanningRuntime.php`
- `tests/Unit/Treasury/*Test.php`
- Bavix `Wallet` model balance and currency accessors
- `docs/architecture/treasury/TREASURY_COMPASS.md`
- `docs/architecture/treasury/reports/003-null-treasury-runtime.md`
- current repository and lock-file state

## Files added

- `src/Treasury/Data/TreasuryWalletBalanceData.php`
- `src/Treasury/Data/TreasuryInventoryReadModelData.php`
- `src/Treasury/Contracts/TreasuryInventoryReadModelContract.php`
- `src/Treasury/ReadModels/WalletBalanceInventoryReadModelService.php`
- `src/Treasury/Adapters/Bavix/BavixInventoryReadModelService.php`
- `tests/Unit/Treasury/InventoryReadModelTest.php`
- `docs/architecture/treasury/reports/004-inventory-read-models.md`

## Files updated

- `src/WalletServiceProvider.php`
- `tests/Unit/Treasury/TreasuryPlanningContractTest.php`
- `docs/architecture/treasury/TREASURY_COMPASS.md`

## Read-model design

`TreasuryWalletBalanceData` carries a package-neutral snapshot with:

- opaque wallet reference;
- currency;
- wallet accounting balance in integer minor units;
- optional metadata.

`TreasuryInventoryReadModelData` exposes:

| Field | Phase 4 source or value |
| --- | --- |
| `walletBalanceMinor` | current Bavix wallet accounting balance |
| `eligibleInventoryMinor` | non-negative wallet balance |
| `allocatedAmountMinor` | explicit zero baseline |
| `drawnAmountMinor` | explicit zero baseline |
| `releasedAmountMinor` | explicit zero baseline |
| `outstandingAmountMinor` | explicit zero baseline |
| `usableAmountMinor` | eligible inventory because no allocation facts exist |
| `hasTreasuryFacts` | `false` |
| `inventoryReference` | `null` |
| `allocationReference` | `null` |

The separate fields prevent accounting balance from becoming an implicit synonym for usable balance. At this baseline they can have the same positive value, but they have different meanings. A negative accounting balance remains visible while eligible and usable inventory clamp to zero.

Explicit zero Treasury amounts do not assert that a persisted Treasury ledger recorded zero activity. `hasTreasuryFacts = false`, null Treasury references, and `treasury_facts = absent` identify them as the no-persistence baseline.

## Contract and binding

`TreasuryInventoryReadModelContract` accepts only `TreasuryWalletBalanceData` and returns `TreasuryInventoryReadModelData`. It does not expose Bavix or any commercial-domain class.

`WalletBalanceInventoryReadModelService` is the stateless package-neutral implementation. `WalletServiceProvider` binds it to the contract as a singleton because resolution is side-effect free and no request or wallet state is retained.

The service adds authoritative metadata:

```text
treasury_read_model = wallet-baseline
treasury_read_model_status = read-only
treasury_facts = absent
```

These values override conflicting caller metadata so a baseline cannot be presented as a persisted Treasury projection.

## Bavix adapter

`BavixInventoryReadModelService` is isolated under `Treasury/Adapters/Bavix`. It reads an existing Bavix `Wallet` model's UUID, derived currency, and `balanceInt`, converts those values to the package-owned snapshot, and delegates to the contract.

The adapter does not call `refreshBalance`. In the installed Bavix version, refresh can synchronize stored state, which would violate the Phase 4 read-only rule. The adapter also has no deposit, withdrawal, transaction, transfer, save, or update path.

## Non-mutation proof

The integration test funds a wallet before the measurement window, records the wallet balance and Bavix wallet/transaction/transfer counts, invokes the adapter, and then verifies:

- current wallet balance is unchanged;
- wallet row count is unchanged;
- transaction row count is unchanged;
- transfer row count is unchanged;
- the returned balance matches the current wallet;
- all allocation measures are present and zero;
- Treasury facts and references are explicitly absent.

The negative-snapshot test separately proves that accounting balance and usable balance are distinct fields with distinct behavior.

## Package boundary proof

The recursive Treasury boundary test now permits a Bavix import only when the importing file is under `Treasury/Adapters/Bavix`. All other Treasury classes remain Bavix-neutral.

Every Treasury source file continues to reject imports from Voucher, x-change, Facility, Lien, Settlement Envelope, and Execution Engine domains.

`DisbursementFailed` was not touched. Its direct Voucher model dependency remains documented legacy boundary debt for a separately approved slice.

## Explicit non-changes

- no migrations or schema changes;
- no Treasury database rows or persistence;
- no deposits, withdrawals, transactions, or transfers initiated by the read model;
- no Bavix balance refresh or mutation;
- no balance-calculation or existing wallet behavior change;
- no x-change, Voucher, Facility, Lien, Settlement Envelope, or Execution Engine imports;
- no event or `DisbursementFailed` change;
- no `composer.lock` change.

## Commands executed

- source, dependency, document, and repository inspection
- PHP syntax checks for all Phase 4 PHP files
- focused Treasury suite with `vendor/bin/pest tests/Unit/Treasury`
- focused Phase 1 characterization suite with `vendor/bin/pest tests/Unit/Actions tests/Unit/Data tests/Unit/Events tests/Unit/Models tests/Unit/Services`
- complete package suite with `vendor/bin/pest`
- Composer and Git scope/lock/whitespace checks

## Test and check results

- Phase 4 PHP syntax checks: **passed**.
- Focused Treasury suite: **13 passed (324 assertions)**.
- Focused Phase 1 characterization suite: **23 passed (93 assertions)**.
- Full package suite: **40 passed (450 assertions)**.
- Stable DTO and read-model shapes: **passed**.
- Accounting versus usable balance distinction: **passed**.
- Explicit zero allocation fields and absent-fact markers: **passed**.
- Wallet and Bavix database non-mutation: **passed**.
- Package boundary confinement: **passed**.
- Phase 1, Phase 2, and Phase 3 behavior: **green within the full suite**.
- `composer validate --strict`: `composer.json` is valid; strict validation continues to report the pre-existing lock/manifest mismatch.
- `composer.lock`: unchanged.

## Remaining risks and open questions

- The baseline is a current wallet view, not a durable Treasury projection.
- Explicit zero measures must always be interpreted with the absent-fact markers.
- There is no Treasury persistence, ledger, reconciliation, or history.
- No allocation state, concurrency protection, reservation, draw, release, repayment, or reversal semantics are executed.
- The exact source and eligibility rules for non-cash Inventory remain open.
- Currency currently follows the Bavix wallet accessor and is not independently validated.
- The existing `composer.lock` mismatch remains unresolved.
- `DisbursementFailed` remains separate legacy boundary debt.

## Recommended next slice

Proceed only with explicit approval to a separately scoped **Phase 5 — Allocation and Slice Read-Model Planning** or another read-only consumer-driven slice. Keep persistence and money movement out until ownership, invariants, idempotency, and concurrency rules are approved.

Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separate because the Phase 4 read-model path is not blocked by that event dependency.
