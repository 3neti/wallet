# Treasury Compass

## Status

Phase 0 Treasury documentation bootstrap completed on 2026-07-17.

Phase 1 current-behavior characterization tests were authored on 2026-07-17. PHP syntax and diff checks pass, but the Pest suite has not run because `vendor/bin/pest` is unavailable in this checkout. Phase 1 runtime verification therefore remains pending.

This document captures the current architectural direction for evolving `3neti/wallet` into the Treasury layer of Settlement OS. It is intended as persisted migration memory for future Codex sessions working inside the wallet package.

No Treasury runtime implementation has been approved by this compass entry.

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

Observed current gaps:

- no first-class Inventory contract;
- no first-class Allocation contract;
- no first-class Slice contract;
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
- the unchanged `DisbursementFailed` traits, public promoted properties, constructor types/default, and direct Voucher type dependency.

These tests are authored but not yet runtime-verified in this checkout because dependencies are unavailable.

## Legacy Boundary Debt

`3neti/wallet` currently has a direct dependency on `LBHurtado\Voucher\Models\Voucher` through `src/Events/DisbursementFailed.php`. This contradicts the target Treasury boundary where wallet must not know vouchers. The dependency is pre-existing and must be addressed in a separately approved implementation slice.

It remains unchanged during the documentation bootstrap because refactoring it may change a public event payload or existing listeners. It must not be interpreted as Treasury ownership of Voucher.

Recommended future slice:

```text
Treasury Boundary Debt Slice 1 —
Decouple Wallet DisbursementFailed Event From Voucher Model
```

Potential future directions, not authorized now, include scalar/reference fields (`voucher_code`, `voucher_id`, `external_reference`) or a package-neutral `DisbursementFailureContextData` DTO with a characterized compatibility path.

Phase 1 confirms that `DisbursementFailed` currently exposes public `voucher`, `exception`, and nullable `mobile` properties, directly type-hints `LBHurtado\Voucher\Models\Voucher`, uses Laravel dispatch/socket/serialization traits, and does not implement `ShouldBroadcast`. The direct Voucher class is also not declared in this package's Composer requirements, so class availability depends on the consuming application. Both facts remain legacy boundary debt; no event signature or dependency was changed.

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

Restore the package's local dependencies without updating the lock file, then run the focused Phase 1 tests followed by the complete Pest suite. Do not mark the Phase 1 exit criterion green until those tests pass.

After a green Phase 1 run, the next planned architecture slice is **Phase 2 — Planning-only Treasury DTOs and Contracts**, subject to explicit approval. Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separately approved and scoped because it may affect public events or listeners.

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

Initial scaffolding may add planning-only DTOs/contracts/tests for:

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

Initial implementations should be null/planning-only unless explicitly approved.

## Required Test Posture

Future slices should prove:

- current top-up behavior remains unchanged;
- current transfer behavior remains unchanged;
- current cash withdrawal behavior remains unchanged;
- planning-only Treasury operations do not mutate balances;
- idempotency requirements are represented;
- package boundaries prevent wallet from importing x-change facilities, liens, vouchers, settlement envelopes, or execution engine classes.

## Current Compass Position

Treasury grammar is the canonical target for wallet evolution, and the documents in this directory are the Phase 0 architecture baseline.

x-change's current reservation/release terminology should be treated as bridge terminology until wallet-side Treasury contracts stabilize. Reservation remains an implementation detail of Allocation.

No Treasury runtime, contract, migration, balance change, event change, or production-code refactor has been approved. Phase 1 adds protection through tests and documentation only.
