# Treasury Compass

## Status

Phase 0 Treasury documentation bootstrap completed on 2026-07-17.

Phase 1 current-behavior characterization was runtime-verified on 2026-07-17. Locked dependencies were restored without changing `composer.lock`; the focused Phase 1 suite passed with 22 tests / 118 assertions, and the full package suite passed with 27 tests / 126 assertions.

The first focused run exposed one test-only characterization error: Laravel's `InteractsWithSockets` trait contributes a public `socket` property to `DisbursementFailed`. The expectation was corrected without changing production code. No existing production-behavior mismatch was found.

Phase 2 planning-only Treasury DTOs and contracts were completed and runtime-verified on 2026-07-17. The focused Phase 2 suite passed with 5 tests / 142 assertions, and the full package suite passed with 32 tests / 268 assertions.

Phase 3 Null Treasury Runtime was completed and runtime-verified on 2026-07-17. The focused Treasury suite passed with 8 tests / 244 assertions, and the full package suite passed with 35 tests / 370 assertions.

This document captures the current architectural direction for evolving `3neti/wallet` into the Treasury layer of Settlement OS. It is intended as persisted migration memory for future Codex sessions working inside the wallet package.

No stateful, persistent, or money-moving Treasury runtime has been approved by this compass entry. The approved Phase 3 implementation is null/planning-only.

## Context Source

This compass records the `Collateralized Liquidity Abstraction` brainstorming handoff from the x-change / Settlement OS workstream.

The central conclusion is:

```text
Settlement consumes Settlement Resources, not necessarily cash.
```

Cash is one implementation of a broader Treasury resource model.

## Core Problem

The current Settlement OS still tends to assume:

```text
Wallet Balance = Available Balance
```

That assumption blocks future treasury products such as:

- collateral-backed facilities;
- partial draw facilities;
- revolving facilities;
- underwriter-backed liquidity;
- receivables-backed settlement;
- guarantee-backed settlement;
- future non-cash settlement resources.

The wallet layer needs to distinguish:

- accounting balance;
- deployable inventory;
- committed liquidity;
- outstanding obligations;
- settlement rights.

## Core Thesis

`3neti/wallet` should evolve into the Treasury package.

Treasury owns the accounting/resource grammar:

- Wallet;
- Inventory;
- Allocation;
- Slice;
- reservation semantics;
- draw/capture semantics;
- release semantics;
- repayment semantics;
- reversal semantics;
- ledger/read-model semantics.

x-change owns the commercial/settlement grammar:

- Facility;
- Lien;
- Voucher / Pay Code;
- Settlement Envelope;
- Execution Engine;
- claim and settlement orchestration.

## Ownership Boundary

### `3neti/wallet` Owns

- Wallets;
- Inventory;
- Allocations;
- Slices;
- reservation/capture/release/repay/reverse accounting;
- durable accounting ledger semantics;
- available/deployable/allocated/outstanding read models;
- Bavix wallet integration and schema adaptation.

### `3neti/wallet` Must Not Own

- vouchers;
- Pay Codes;
- facilities;
- liens;
- settlement envelopes;
- claim UX;
- x-change execution workflows;
- provider-specific settlement business rules.

### x-change Owns

- Facility;
- Lien;
- Voucher / Pay Code;
- Settlement Envelope;
- Execution Engine;
- commercial lifecycle orchestration;
- Cockpit integration/read models.

### Cockpit Owns

- read-model presentation;
- operator visibility.

Cockpit does not own accounting truth.

### x-journal Owns

- evidence;
- immutable audit;
- lifecycle evidence.

It does not own Treasury state.

## Target Treasury Grammar

### Inventory

A Treasury-managed resource that can support settlement.

Inventory may be backed by:

- cash;
- underwriter inventory;
- receivables;
- guarantees;
- future settlement resources.

### Allocation

A committed portion of inventory made available to a commercial facility or settlement context.

Allocation is the target abstraction that replaces cash-only assumptions.

### Slice

A subdivision of an allocation that can be drawn, released, repaid, or reversed independently.

Slices support:

- fixed claim portions;
- open/partial draws;
- stored-value style usage;
- facility-specific utilization rules.

### Draw / Capture

Consumption of allocation capacity during redemption or settlement execution.

### Release

Return of unused allocation capacity, usually on expiry, cancellation, or facility termination.

### Repay

Restoration of allocation capacity after repayment.

### Reverse

Compensating operation for failed provider settlement, failed execution, or reconciliation policy.

## Current Wallet Package Baseline

Current `3neti/wallet` is a helper package around `bavix/laravel-wallet`.

Observed current capabilities:

- system user resolver;
- wallet provisioning;
- platform/rewards/escrow/commission wallet types;
- top-up via system-user transfer;
- full or partial cash withdrawal action in minor units;
- transaction DTO;
- balance updated event assembly, listening, queuing, and broadcasting;
- deposit/disbursement event classes;
- published Bavix integration configuration;
- package tests dynamically load Bavix-owned migrations.

Bavix owns the wallet, transaction, and transfer schema/migrations and the current money-movement primitives. `3neti/wallet` owns the integration configuration and standardizing behavior around those primitives. Configuring Bavix model and table names does not transfer schema ownership to this package.

Observed current gaps after Phase 3:

- planning DTOs and a bound null runtime exist for Inventory, Allocation, Slice, Draw, Release, Repayment, and Reversal, but no Treasury state or persistence exists;
- no first-class reserve/capture/release/repay/reverse API;
- no idempotent reservation keys;
- no reservation ledger/read model;
- no available balance net of allocation semantics;
- no durable collateralized liquidity abstraction yet.

The existing `ESCROW` wallet type is a useful signal but is not a complete allocation ledger.

The Phase 1 test suite now characterizes:

- configured system-user resolution plus invalid-model, non-wallet-model, and missing-user failures;
- stable platform/rewards/escrow/commission slugs, labels, metadata, zero balances, and idempotent provisioning;
- top-up resolver/transfer interaction, sender/recipient balance changes, and paired Bavix withdraw/deposit records;
- full and explicit-minor-unit cash withdrawal, error paths, metadata, and metadata override precedence;
- `TransactionData` PHP minor-unit mapping, confirmation flag, payload extraction, empty-payload default, and public field shape;
- `BalanceUpdated` identity, balances, timestamp, channel, event name, and broadcast payload;
- `DepositConfirmed` and `DisbursementConfirmed` transaction, signed amount, channel, event name, and broadcast payload;
- the unchanged `DisbursementFailed` traits, public properties (including trait-provided `socket`), constructor types/default, and direct Voucher type dependency.

These tests are runtime-verified against the versions recorded in the existing lock file.

## Phase 2 Planning Baseline

Phase 2 adds immutable package-owned planning DTOs:

- `TreasuryInventoryData`;
- `TreasuryAllocationData`;
- `TreasurySliceData`;
- `TreasuryDrawData`;
- `TreasuryReleaseData`;
- `TreasuryRepaymentData`;
- `TreasuryReversalData`.

The DTOs use scalar references, integer minor units, explicit currency, descriptive status, caller-supplied idempotency keys, optional opaque external references, and metadata. Status values describe a plan only; Phase 2 does not define or enforce a state machine. Idempotency keys are represented but not persisted or enforced.

`TreasuryPlanningContract` provides `planInventory`, `planAllocation`, `planSlice`, `planDraw`, `planRelease`, `planRepayment`, and `planReversal`. It accepts and returns only package-owned DTOs.

There is no production implementation, service-provider binding, persistence, Bavix dependency, money movement, or balance mutation. A test-local pass-through implementation proves that exercising every planning method leaves wallet balance, Bavix transaction count, and transfer count unchanged.

## Phase 3 Null Runtime Baseline

`NullTreasuryPlanningRuntime` is the production-safe implementation of `TreasuryPlanningContract`. `WalletServiceProvider` binds the contract to this stateless runtime as a singleton for package-local and consumer resolution.

Every planning method returns a fresh DTO with all caller references, amounts, currency, and idempotency keys preserved. The runtime replaces status with:

```text
null-runtime-planned
```

It also merges authoritative metadata:

```text
treasury_runtime = null
treasury_runtime_status = planned
treasury_operation = inventory | allocation | slice | draw | release | repayment | reversal
```

Caller metadata is otherwise preserved. The runtime markers override conflicting caller values so a null result cannot be presented as executed.

The runtime uses no time, randomness, logs, database, Bavix service/model, or external commercial class. Identical input produces identical output. Tests prove resolution is singleton, all seven plans are stable, wallet balance is unchanged, and user/wallet/transaction/transfer row counts are unchanged.

## Legacy Boundary Debt

`3neti/wallet` currently has a direct dependency on `LBHurtado\Voucher\Models\Voucher` through `src/Events/DisbursementFailed.php`. This contradicts the target Treasury boundary where wallet must not know vouchers. The dependency is pre-existing and must be addressed in a separately approved implementation slice.

It remains unchanged during the documentation bootstrap because refactoring it may change a public event payload or existing listeners. It must not be interpreted as Treasury ownership of Voucher.

Recommended future slice:

```text
Treasury Boundary Debt Slice 1 —
Decouple Wallet DisbursementFailed Event From Voucher Model
```

Potential future directions, not authorized now, include scalar/reference fields (`voucher_code`, `voucher_id`, `external_reference`) or a package-neutral `DisbursementFailureContextData` DTO with a characterized compatibility path.

Phase 1 confirms that `DisbursementFailed` currently exposes constructor-promoted public `voucher`, `exception`, and nullable `mobile` properties plus the trait-provided public `socket` property. It directly type-hints `LBHurtado\Voucher\Models\Voucher`, uses Laravel dispatch/socket/serialization traits, and does not implement `ShouldBroadcast`. The direct Voucher class is also not declared in this package's Composer requirements, so class availability depends on the consuming application. Both facts remain legacy boundary debt; no event signature or dependency was changed.

## Relationship to Recent x-change Money Semantics Work

x-change currently has a bridge layer that characterizes:

- current debit-at-issuance behavior;
- outstanding Pay Code liability;
- usable balance estimate;
- planning-only reservation/release decision;
- planning-only lifecycle trigger matrix.

That bridge is not the target Treasury model.

The target model should move from:

```text
wallet reservation / release
```

to:

```text
Inventory → Allocation → Slice → Draw / Release / Repay / Reverse
```

Reservation is an implementation detail of Allocation, not the top-level domain concept.

## Known Lifecycle Mapping

Current planning maps x-change lifecycle events to future Treasury operations:

| x-change Event | Future Treasury Operation |
| --- | --- |
| Pay Code issued | Allocate / reserve inventory |
| Pay Code redeemed | Draw / capture allocation |
| Pay Code partially claimed | Draw partial slice, keep remaining allocation active |
| Pay Code expired | Release remaining allocation |
| Pay Code cancelled | Release remaining allocation |
| Provider disbursement failed after capture | Reverse or reconcile by policy |

All are planning-only today.

## Firm Decisions

- Treasury evolves from `3neti/wallet`.
- Do not create a separate Treasury package yet.
- Backward compatibility is preferred.
- Bavix continues to own current wallet schema and migrations.
- Treasury owns Inventory.
- Treasury owns Allocation.
- Treasury owns Slices.
- x-change owns Facility.
- x-change owns Lien.
- x-change owns Settlement Envelope.
- x-change owns Execution Engine.
- There remains one universal Pay Code.
- Facility defines business semantics.
- Settlement Envelope remains intrinsic to execution.
- Execution Engine remains orchestration.
- The existing direct Voucher dependency is legacy boundary debt, not target ownership.
- Removing that dependency is outside the approved Phase 0/Phase 1 characterization scope.
- Phase 1 characterization must not introduce Inventory, Allocation, Slice, reservation, or other Treasury runtime semantics into existing wallet tests.
- Phase 2 DTOs and the planning contract are descriptive boundaries only, not executable Treasury semantics.
- Treasury amounts are represented as integer minor units with an explicit currency string.
- External references remain opaque strings; no x-change, Voucher, Facility, or Lien classes may cross the contract.
- Phase 2 status and idempotency fields carry planning intent but are not validated, stored, or enforced.
- `TreasuryPlanningContract` resolves to `NullTreasuryPlanningRuntime` by default.
- Null-runtime status and metadata markers are authoritative and override conflicting caller metadata.
- Null-runtime observability is returned in the DTO only; Phase 3 emits no logs, events, or persisted evidence.
- A null-runtime plan is not an allocation, draw, release, repayment, reversal, or settlement execution.

## Open Questions

- Exact Inventory persistence model.
- Exact Allocation persistence model.
- Exact Slice persistence model.
- Whether Bavix transactions are sufficient as underlying ledger primitives.
- Whether a new Treasury ledger table is required.
- Idempotency strategy for allocation/draw/release/repay/reverse.
- Partial draw and overdraw protection.
- Repayment semantics.
- Failed provider settlement reversal semantics.
- Multi-resource facilities.
- Cross-resource execution.
- Whether `SettlementResourceInterface` is needed.
- Whether `AllocationReference` should exist as a cross-package reference DTO/interface.
- Compatibility and deprecation strategy for decoupling `DisbursementFailed` from the Voucher model.

## Immediate Recommendation

Phase 3 is green. The next planned architecture slice is **Phase 4 — Inventory Read Models**, subject to explicit approval. Phase 4 must remain read-only and keep wallet balance distinct from eligible, allocated, drawn, and usable measures.

Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separately approved and scoped because it may affect public events or listeners.

The existing `composer.lock` mismatch remains a package-maintenance risk. It was not corrected during verification because the slice explicitly required using the existing lock where possible and avoiding a lock update.

## Implementation Guardrails

Do not implement real money movement yet.

Do not change:

- top-up behavior;
- transfer behavior;
- withdraw behavior;
- balance computation;
- Bavix migrations;
- existing wallet table semantics.

Do not add migrations until the Treasury grammar and package boundary are approved.

Phase 2 adds planning-only DTOs/contracts/tests for:

- `TreasuryInventoryData`;
- `TreasuryAllocationData`;
- `TreasurySliceData`;
- `TreasuryDrawData`;
- `TreasuryReleaseData`;
- `TreasuryRepaymentData`;
- `TreasuryReversalData`;
- inventory registration;
- allocation creation;
- slice planning;
- draw;
- release;
- repay;
- reverse.

Phase 3 adds only `NullTreasuryPlanningRuntime` and its singleton binding. It must remain deterministic, non-persistent, and incapable of money movement.

## Required Test Posture

Future slices should prove:

- current top-up behavior remains unchanged;
- current transfer behavior remains unchanged;
- current cash withdrawal behavior remains unchanged;
- planning-only Treasury operations do not mutate balances;
- idempotency requirements are represented;
- package boundaries prevent wallet from importing x-change facilities, liens, vouchers, settlement envelopes, or execution engine classes.

## Current Compass Position

Treasury grammar is the canonical target for wallet evolution. Phase 0 documentation, Phase 1 behavior characterization, Phase 2 planning types, and the Phase 3 null runtime now form the approved architecture baseline.

x-change's current reservation/release terminology should be treated as bridge terminology until wallet-side Treasury contracts stabilize. Reservation remains an implementation detail of Allocation.

The Phase 2 planning types and Phase 3 null runtime/binding are approved as a non-mutating seam. No stateful Treasury runtime, persistence, migration, balance change, money movement, event change, or `DisbursementFailed` refactor has been approved.
