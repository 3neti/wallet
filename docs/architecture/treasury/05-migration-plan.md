# Treasury Migration Plan

## Status and authorization boundary

Only Phase 0 is authorized by this bootstrap. Every later phase requires explicit approval. No phase may silently change current top-up, transfer, withdrawal, balance, event, or Bavix persistence behavior.

## Phase 0 — Documentation Bootstrap

Establish the canonical rationale, grammar, accounting semantics, ownership boundary, migration phases, compass memory, and reconciliation report.

Exit criteria:

- all canonical Treasury documents exist and agree;
- current source and tests are reconciled;
- legacy boundary debt is recorded;
- no production code, migrations, or tests are changed;
- safe documentation/package checks are reported.

## Phase 1 — Current Behavior Characterization

Add or strengthen tests that freeze existing behavior before introducing Treasury seams.

Coverage should include:

- system-user resolution failures and success;
- default wallet provisioning and idempotency;
- top-up sender/recipient balances and transfer metadata;
- direct Bavix transfer behavior relied on by consumers;
- full and partial `WithdrawCash`, zero balance, and over-withdrawal;
- transaction DTO conversion;
- balance and disbursement event payload/serialization behavior;
- the legacy `DisbursementFailed` consumer surface before decoupling.

Exit criterion: the compatibility baseline is explicit and green.

## Phase 2 — Planning-only Treasury DTOs and Contracts

Design additive package-owned types for Inventory, Allocation, Slice, Draw, Release, Repayment, Reversal, operation references, and read models.

Constraints:

- no money movement or persistence;
- no x-change imports;
- explicit units/currency, identifiers, status, and idempotency keys;
- public review of invariants and names.

Exit criterion: contracts express the grammar without implying an implementation.

## Phase 3 — Null Treasury Runtime

Provide a deliberately non-mutating runtime for integration planning and boundary tests.

It must prove that Treasury requests can be wired without changing Bavix balances, transfers, withdrawals, or database state.

Exit criterion: null operations are observable, deterministic, and incapable of moving money.

## Phase 4 — Inventory Read Models

Define read-only Inventory views over selected current state and synthetic/planning inputs. Separate wallet balance from eligible, allocated, drawn, and usable measures.

Exit criterion: read-model definitions and source-of-truth labels are unambiguous; no new write path exists.

## Phase 5 — Allocation/Slice Planning

Specify Allocation and Slice state machines, amount invariants, partial draws, release/repay/reverse transitions, concurrency needs, and lifecycle examples.

Exit criterion: scenario tables cover happy paths, replay, partial operations, invalid transitions, and failure recovery.

## Phase 6 — Reservation Ledger Decision

Decide whether Bavix transactions/metadata are sufficient, whether a dedicated Treasury ledger is needed, and how a wallet hold relates to an Allocation.

Required decision record:

- append/compensation model;
- idempotency and unique-key strategy;
- locking and atomicity strategy;
- read-model rebuild strategy;
- audit/evidence relationship;
- currency and valuation constraints;
- schema ownership and migration implications.

Exit criterion: an approved ADR precedes any durable implementation.

## Phase 7 — Durable Reservation/Allocation Storage

Implement the approved storage and ledger model with migrations only after separate authorization.

Exit criterion: invariant, concurrency, replay, migration, rollback, and compatibility tests pass; existing wallet flows remain unchanged.

## Phase 8 — x-change Adapter Boundary

Add an adapter in the owning integration layer so x-change can request Treasury operations using contracts and opaque references. Treasury must not import x-change classes.

This phase should also schedule the separately approved boundary-debt work needed to remove the direct Voucher model dependency safely.

Exit criterion: dependency direction is enforced and compatibility/deprecation is demonstrated.

## Phase 9 — Lifecycle Scenario Demonstration

Demonstrate issuance/allocation, full and partial draw, expiry/cancellation release, repayment, provider failure, reversal/reconciliation, and replay across package boundaries in a non-production scenario.

Exit criterion: accounting/read-model outcomes match approved examples and provider failures do not corrupt Treasury state.

## Phase 10 — Production Hardening

Add operational controls: authorization, limits, monitoring, reconciliation, backfill/rebuild procedures, performance tests, incident playbooks, and staged rollout/rollback.

Exit criterion: financial, operational, security, and migration owners approve production activation.

## Cross-phase stop conditions

Stop and obtain explicit direction if a phase would:

- change real money movement;
- alter current public top-up, transfer, withdrawal, balance, or event behavior;
- require a Bavix schema change or new migration not already approved;
- make package ownership ambiguous;
- import x-change domain classes into Treasury;
- introduce a second source of accounting truth without a reconciliation design.

## Recommended next slice

After Phase 0 approval, proceed with **Phase 1 — Current Behavior Characterization**. Keep **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model** separately scoped because it changes a production event contract and may require consumer coordination.
