# Phase 2 Planning-only Treasury DTOs and Contracts Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: additive planning DTOs, planning contract, tests, and documentation
- Result: **green**; focused and full test suites pass with no production money movement or schema change

## Files inspected

- `composer.json`
- `composer.lock` (validation and unchanged-state checks)
- `src/Data/TransactionData.php`
- `tests/Unit/Data/TransactionDataTest.php`
- `docs/architecture/treasury/TREASURY_COMPASS.md`
- `docs/architecture/treasury/02-treasury-grammar.md`
- `docs/architecture/treasury/03-accounting-semantics.md`
- `docs/architecture/treasury/04-package-boundaries.md`
- `docs/architecture/treasury/05-migration-plan.md`
- `docs/architecture/treasury/reports/001-current-behavior-characterization.md`
- current `src/` and `tests/` file inventories

## Files added

### Planning DTOs

- `src/Treasury/Data/TreasuryInventoryData.php`
- `src/Treasury/Data/TreasuryAllocationData.php`
- `src/Treasury/Data/TreasurySliceData.php`
- `src/Treasury/Data/TreasuryDrawData.php`
- `src/Treasury/Data/TreasuryReleaseData.php`
- `src/Treasury/Data/TreasuryRepaymentData.php`
- `src/Treasury/Data/TreasuryReversalData.php`

### Contract

- `src/Treasury/Contracts/TreasuryPlanningContract.php`

### Tests

- `tests/Unit/Treasury/TreasuryDataShapeTest.php`
- `tests/Unit/Treasury/TreasuryPlanningContractTest.php`

### Documentation

- `docs/architecture/treasury/reports/002-planning-only-treasury-dtos-and-contracts.md`

## File updated

- `docs/architecture/treasury/TREASURY_COMPASS.md`

## DTO shapes established

All Phase 2 DTOs extend Spatie Laravel Data and expose immutable constructor-promoted properties. They intentionally contain data only.

| DTO | Stable fields |
| --- | --- |
| `TreasuryInventoryData` | `inventoryReference`, `resourceType`, `currency`, `capacityMinor`, `status`, `idempotencyKey`, `externalReference`, `metadata` |
| `TreasuryAllocationData` | `allocationReference`, `inventoryReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `externalReference`, `metadata` |
| `TreasurySliceData` | `sliceReference`, `allocationReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `externalReference`, `metadata` |
| `TreasuryDrawData` | `operationReference`, `allocationReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `sliceReference`, `metadata` |
| `TreasuryReleaseData` | `operationReference`, `allocationReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `sliceReference`, `metadata` |
| `TreasuryRepaymentData` | `operationReference`, `allocationReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `sliceReference`, `drawReference`, `metadata` |
| `TreasuryReversalData` | `operationReference`, `reversesOperationReference`, `allocationReference`, `amountMinor`, `currency`, `status`, `idempotencyKey`, `sliceReference`, `metadata` |

Amounts are integer minor units. Currency is explicit. References are scalar strings. `externalReference` is opaque and package-neutral. Optional references default to null and metadata defaults to an empty array.

`status` and `idempotencyKey` are planning fields only. This phase does not approve status transitions, validation policy, unique-key enforcement, storage, or replay behavior.

## Contract established

`TreasuryPlanningContract` defines:

- `planInventory(TreasuryInventoryData): TreasuryInventoryData`;
- `planAllocation(TreasuryAllocationData): TreasuryAllocationData`;
- `planSlice(TreasurySliceData): TreasurySliceData`;
- `planDraw(TreasuryDrawData): TreasuryDrawData`;
- `planRelease(TreasuryReleaseData): TreasuryReleaseData`;
- `planRepayment(TreasuryRepaymentData): TreasuryRepaymentData`;
- `planReversal(TreasuryReversalData): TreasuryReversalData`.

The `plan*` naming makes the Phase 2 boundary explicit. The interface imports only package-owned Treasury DTOs. No production class implements it, and no service-provider binding was added.

## Package-neutral boundary proof

The boundary test scans every import in `src/Treasury` and rejects Bavix Wallet plus Voucher, x-change, Facility, Lien, Settlement Envelope, and Execution Engine dependencies. Reflection tests also prove that every contract parameter and return type is a package-owned Treasury DTO.

The pre-existing `DisbursementFailed` Voucher dependency was not touched. It remains separate legacy boundary debt and does not enter any Phase 2 type.

## Non-mutation proof

A test-local anonymous implementation returns each planning DTO unchanged. Against a funded test wallet, the test invokes all seven planning methods and then proves:

- wallet balance is unchanged;
- Bavix transaction count is unchanged;
- Bavix transfer count is unchanged.

This test-local implementation is not production runtime. Phase 3 remains the separately approved null-runtime phase.

## Existing behavior protection

The complete package suite includes all Phase 1 characterization tests for system-user resolution, provisioning, top-up, withdrawal, transaction DTOs, and events. All remain green, proving Phase 2 did not change existing wallet behavior.

## Explicit non-changes

- no migrations;
- no schema changes;
- no service-provider bindings;
- no production Treasury implementation;
- no wallet deposits, withdrawals, transfers, or balance behavior changes;
- no event changes;
- no `DisbursementFailed` refactor;
- no x-change, Voucher, Facility, or Lien imports;
- no `composer.lock` change.

## Commands executed

- architecture/source/test inspection with `sed -n`, `find`, and `git status --short`
- PHP syntax checks with `php -l`
- focused Phase 2 suite with `vendor/bin/pest tests/Unit/Treasury`
- full package suite with `vendor/bin/pest`
- `composer validate --strict`
- unchanged-lock review with `git diff -- composer.lock`
- whitespace and scope review with `git diff --check`, `git status --short`, and staged-diff commands

## Test and check results

- Phase 2 PHP syntax checks: **passed**.
- Focused Phase 2 suite: **5 passed (142 assertions)**.
- Full package suite: **32 passed (268 assertions)**.
- Phase 1 behavior: **green within the full suite**.
- Planning balance/transaction/transfer non-mutation: **passed**.
- Package-neutral import boundary: **passed**.
- `composer validate --strict`: `composer.json` is valid; strict validation still reports the pre-existing `composer.lock` mismatch.
- `composer.lock`: unchanged.

## Remaining risks and open questions

- DTOs do not validate positive amounts, currency formats, status vocabulary, or reference formats.
- Idempotency keys are represented but not persisted or enforced.
- Status is descriptive and has no approved transition model.
- No concurrency, over-allocation, overdraw, or replay behavior exists.
- No persistence or read model exists.
- Whether the seven operation DTOs should later split request/result shapes remains open.
- Whether `SettlementResourceInterface` or a dedicated `AllocationReference` value type is needed remains open.
- `composer.lock` still does not match the current manifest.
- `DisbursementFailed` remains approved legacy boundary debt.

## Recommended next slice

Proceed only with explicit approval to **Phase 3 — Null Treasury Runtime**. That runtime must remain deterministic, observable, non-persistent, and incapable of changing wallet balances or Bavix transaction/transfer state.

Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separate.
