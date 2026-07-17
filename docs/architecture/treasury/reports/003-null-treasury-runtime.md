# Phase 3 Null Treasury Runtime Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: deterministic, observable, non-mutating implementation of `TreasuryPlanningContract`
- Result: **green**; focused and full suites pass with no database or wallet mutation

## Files inspected

- `src/Treasury/Contracts/TreasuryPlanningContract.php`
- all seven `src/Treasury/Data/*Data.php` DTOs
- `src/WalletServiceProvider.php`
- `tests/Unit/Treasury/TreasuryPlanningContractTest.php`
- `docs/architecture/treasury/TREASURY_COMPASS.md`
- `docs/architecture/treasury/reports/002-planning-only-treasury-dtos-and-contracts.md`
- `composer.json` and `composer.lock` validation state

## Files added

- `src/Treasury/Runtime/NullTreasuryPlanningRuntime.php`
- `tests/Unit/Treasury/NullTreasuryPlanningRuntimeTest.php`
- `docs/architecture/treasury/reports/003-null-treasury-runtime.md`

## Files updated

- `src/Treasury/Contracts/TreasuryPlanningContract.php`
- `src/WalletServiceProvider.php`
- `docs/architecture/treasury/TREASURY_COMPASS.md`

## Runtime design

`NullTreasuryPlanningRuntime` implements every method of `TreasuryPlanningContract`:

- `planInventory`;
- `planAllocation`;
- `planSlice`;
- `planDraw`;
- `planRelease`;
- `planRepayment`;
- `planReversal`.

Each call creates a new DTO of the same type. It preserves caller-supplied references, amount/capacity, currency, idempotency key, optional references, and non-conflicting metadata.

It replaces the caller status with:

```text
null-runtime-planned
```

It adds or overrides these metadata fields:

| Field | Value |
| --- | --- |
| `treasury_runtime` | `null` |
| `treasury_runtime_status` | `planned` |
| `treasury_operation` | canonical operation name |

Canonical operation names are `inventory`, `allocation`, `slice`, `draw`, `release`, `repayment`, and `reversal`.

Runtime markers override conflicting caller metadata. This prevents a caller from obscuring that the returned DTO is a null-runtime plan rather than an executed operation.

## Determinism and observability

The runtime has no clock, random generator, UUID generation, mutable state, or environment-dependent value. Calling the same method with the same DTO produces the same serialized output.

Observability is carried in the returned status and metadata. The runtime intentionally does not log, emit events, or persist evidence because those actions would add side effects and imply a lifecycle that is not approved in Phase 3.

## Binding decision

`WalletServiceProvider` binds `TreasuryPlanningContract` to `NullTreasuryPlanningRuntime` as a singleton.

The binding is appropriate because:

- the contract is additive and had no previous implementation;
- the runtime is stateless;
- resolving it has no side effect;
- consumers receive an explicit safe seam instead of a container-resolution failure;
- all outputs clearly identify themselves as null-runtime plans.

The singleton does not hold wallet, DTO, request, or operation state.

## Non-mutation proof

The test funds an existing test wallet, records its balance and row counts, invokes all seven methods through the container-bound contract, then verifies:

- wallet balance is unchanged;
- user row count is unchanged;
- wallet row count is unchanged;
- Bavix transaction row count is unchanged;
- Bavix transfer row count is unchanged.

The runtime source contains no database, wallet, transaction, transfer, deposit, withdrawal, or Bavix dependency.

## Package boundary proof

The existing recursive boundary test includes the new runtime and rejects imports from Bavix Wallet, Voucher, x-change, Facility, Lien, Settlement Envelope, and Execution Engine domains. The null runtime imports only the package-owned planning contract and DTOs.

`DisbursementFailed` was not touched. Its direct Voucher model remains separately documented legacy boundary debt.

## Existing behavior protection

The full suite includes all Phase 1 wallet characterization and Phase 2 planning-shape/boundary tests. All remain green. Top-up, withdrawal, transfer, balance, DTO, and event behavior is unchanged.

## Explicit non-changes

- no migrations or schema changes;
- no Treasury persistence;
- no deposits, withdrawals, transactions, or transfers initiated by Treasury;
- no wallet balance calculation change;
- no existing action or event signature change;
- no x-change/commercial-domain imports;
- no `DisbursementFailed` change;
- no `composer.lock` change.

## Commands executed

- source/document inspection with `sed -n` and repository status checks
- PHP syntax checks with `php -l`
- focused Treasury suite with `vendor/bin/pest tests/Unit/Treasury`
- complete package suite with `vendor/bin/pest`
- `composer validate --strict`
- lock, whitespace, scope, and staged-diff review with Git read-only checks

## Test and check results

- Phase 3 PHP syntax checks: **passed**.
- Focused Treasury suite: **8 passed (244 assertions)**.
- Full package suite: **35 passed (370 assertions)**.
- All seven deterministic output contracts: **passed**.
- Null-runtime status/metadata observability: **passed**.
- Container singleton binding: **passed**.
- Wallet and database non-mutation: **passed**.
- Package boundary test: **passed**.
- Phase 1 and Phase 2 behavior: **green within the full suite**.
- `composer validate --strict`: `composer.json` is valid; strict validation continues to report the pre-existing lock/manifest mismatch.
- `composer.lock`: unchanged.

## Remaining risks and open questions

- Consumers must not interpret a successful null-runtime response as execution; the authoritative markers mitigate but cannot prevent consumer misuse.
- The runtime performs no validation of amounts, currency, status, references, or idempotency keys.
- Idempotency is represented but not stored or enforced.
- There is no persisted audit/evidence record for planning calls by design.
- There is no Inventory read model or source-of-truth selection yet.
- No allocation state, concurrency protection, reservation, draw, release, repayment, or reversal semantics are executed.
- The existing `composer.lock` mismatch remains unresolved.
- `DisbursementFailed` remains separate legacy boundary debt.

## Recommended next slice

Proceed only with explicit approval to **Phase 4 — Inventory Read Models**. That phase should remain read-only and explicitly distinguish wallet balance, eligible inventory, active allocations, drawn amounts, and usable balance without introducing a write path.

Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separate until the Treasury seam and compatibility strategy are approved.
