# Phase 5 Allocation and Slice Read-Model Planning Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: read-only, non-persistent Allocation and Slice read-model scaffold
- Result: **green**; focused and full suites pass with no database or wallet mutation

## Files added

- `src/Treasury/Enums/TreasurySliceSemantics.php`
- `src/Treasury/Data/TreasuryAllocationReadModelQueryData.php`
- `src/Treasury/Data/TreasuryAllocationReadModelData.php`
- `src/Treasury/Data/TreasurySliceReadModelData.php`
- `src/Treasury/Contracts/TreasuryAllocationReadModelContract.php`
- `src/Treasury/ReadModels/AbsentTreasuryAllocationReadModelService.php`
- `tests/Unit/Treasury/AllocationSliceReadModelPlanningTest.php`
- `docs/architecture/treasury/reports/005-allocation-and-slice-read-model-planning.md`

## Files updated

- `src/WalletServiceProvider.php`
- `docs/architecture/treasury/TREASURY_COMPASS.md`

## Read-model shapes

`TreasuryAllocationReadModelData` and `TreasurySliceReadModelData` each expose these integer minor-unit measures:

- allocated amount;
- drawn amount;
- released amount;
- outstanding amount;
- usable amount.

The Allocation result also exposes `sliceCount` and a list of `TreasurySliceReadModelData` values. Both result types carry `hasTreasuryFacts` and metadata so consumers can distinguish an absent baseline from a real future projection.

The amount fields are representation slots only. Phase 5 does not approve formulas relating allocated, drawn, released, outstanding, and usable values. A future fact projector must define source-of-truth, arithmetic invariants, validation, and reconciliation before it can return real facts.

## Slice semantics

`TreasurySliceSemantics` is a package-owned backed enum:

| Value | Planning meaning |
| --- | --- |
| `open` | reporting identity does not prescribe a fixed or named subdivision |
| `fixed` | reported against a predefined amount boundary |
| `named` | carries a stable package-neutral reporting label |

The modes are mutually exclusive in this scaffold. `TreasurySliceReadModelData::name` is optional at the type level and meaningful for `named` semantics. Phase 5 intentionally does not add validation, state machines, or lifecycle behavior; a future approved slice must decide whether named Slices may also have fixed/open characteristics or whether additional orthogonal fields are needed.

## Absent facts versus real facts

`TreasuryAllocationReadModelQueryData` carries the requested Allocation reference, currency, optional Inventory reference, and metadata. Query references are correlation identifiers, not proof of stored Treasury state.

The bound `AbsentTreasuryAllocationReadModelService` always returns:

- all five amount fields set to zero;
- `sliceCount = 0`;
- an empty Slice list;
- `hasTreasuryFacts = false`;
- `treasury_facts = absent`;
- `treasury_read_model = allocation-slice-planning`;
- `treasury_read_model_status = read-only`.

The service's markers override conflicting caller metadata. It is deterministic, stateless, and package-neutral.

The DTOs can represent future real facts using non-zero measures, Slice rows, `hasTreasuryFacts = true`, and `treasury_facts = present`. Tests construct such DTOs solely to lock the representation. No Phase 5 service reads or creates real Treasury facts.

`sliceCount` is the future projected count and is expected to match the Slice list for real projections. The DTO does not enforce that invariant in this planning scaffold.

## Contract and binding

`TreasuryAllocationReadModelContract` accepts only `TreasuryAllocationReadModelQueryData` and returns only `TreasuryAllocationReadModelData`. No Bavix or commercial-domain object crosses the contract.

`WalletServiceProvider` binds the contract to `AbsentTreasuryAllocationReadModelService` as a singleton. The implementation holds no query, wallet, or request state and has no external dependency.

## Non-mutation and boundary proof

The Phase 5 test funds a wallet before the measurement window, records its balance and Bavix wallet/transaction/transfer counts, resolves the bound read-model service, and verifies that all values remain unchanged after a read.

The existing recursive boundary test covers every added Treasury file. Bavix remains confined to the explicit Phase 4 adapter, and no Phase 5 source imports Voucher, x-change, Facility, Lien, Settlement Envelope, or Execution Engine classes.

`DisbursementFailed` was not touched. Its Voucher model dependency remains separately documented legacy boundary debt.

## Explicit non-changes

- no money movement;
- no Treasury or Bavix writes;
- no persistence or real-fact projector;
- no migrations or schema changes;
- no balance calculation or existing wallet behavior change;
- no reservation, release, draw, repayment, or reversal behavior;
- no amount-derivation formulas or aggregate validation;
- no x-change, Voucher, Facility, Lien, Settlement Envelope, or Execution Engine imports;
- no event or `DisbursementFailed` change;
- no `composer.lock` change.

## Verification results

- Phase 5 PHP syntax checks: **passed**.
- Focused Treasury suite: **18 passed (408 assertions)**.
- Focused Phase 1 characterization suite: **23 passed (93 assertions)**.
- Full package suite: **45 passed (534 assertions)**.
- Contract and DTO shape stability: **passed**.
- Open/fixed/named semantics: **passed**.
- Absent-facts zero fields and authoritative markers: **passed**.
- Future real-fact representation: **passed**.
- Wallet and Bavix database non-mutation: **passed**.
- Package boundary confinement: **passed**.
- Phase 1 through Phase 4 behavior: **green within the full suite**.
- `composer validate --strict`: `composer.json` is valid; strict validation continues to report the pre-existing lock/manifest mismatch.
- `composer.lock`: unchanged.

The first focused run found a test-only characterization mismatch: Spatie's PHP `toArray()` preserves the backed enum instance rather than replacing it with the enum's string value. The test now asserts the typed enum and its stable backed value. No production code changed in response.

## Remaining risks and open questions

- There is no source of real Allocation or Slice facts.
- Amount relationships and reconciliation rules remain undefined.
- `sliceCount` consistency is documented but not enforced.
- Named Slice validation is not implemented.
- Open, fixed, and named may eventually need orthogonal attributes instead of one exclusive enum.
- Currency and cross-currency rules are not validated.
- No concurrency, idempotency, ledger, or lifecycle rules exist.
- The existing `composer.lock` mismatch remains unresolved.
- `DisbursementFailed` remains separate legacy boundary debt.

## Recommended next step

Review these read-model meanings with prospective x-change and Cockpit consumers before approving another slice. Any future real-fact projector or persistence design must be separately authorized and must define arithmetic invariants, sources of truth, concurrency, and compatibility without implying permission for money movement.
