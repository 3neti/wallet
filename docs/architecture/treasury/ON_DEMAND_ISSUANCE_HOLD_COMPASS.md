# Provider-Neutral Order-Bound Treasury Hold Compass

Last updated: 2026-09-29

## North Star

`3neti/wallet` can safely encumber value for an opaque external order, accept
authorized additional backing, consume the held balance into governed
Positions, release unused value, and reverse eligible operations without
knowing the external product or workflow.

## Current position

Status: **Narrow provider-neutral hold façade implemented and locally proven,
including one x-change sandbox lifecycle; concurrency and reversal hardening
remain.**

Available foundations:

- durable Treasury Inventory, Positions, and immutable Position Operations;
- Client Funds-to-Pay Code Reserve reservation and release;
- durable `TreasuryAllocation` and Allocation Operations;
- activation, draw, replenish, release, and reversal;
- atomic row locking and database-backed idempotency;
- allocation balance, version, and status;
- metadata sanitization; and
- allocation and activity read models.

Implemented contract:

- `TreasuryHoldOperationContract` with place, replenish, consume, and release;
- opaque external order and hold references;
- durable backing through the existing reservation and allocation runtime;
- no x-change, voucher, provider, pricing, or browser dependency; and
- focused parity tests alongside the legacy allocation runtime.

The x-change sandbox also exercised placement, settlement-driven funding,
consumption, and idempotent replay through a synthetic PHP 25.00 on-demand
issuance lifecycle. No real provider call or money movement was used.

Current constraint:

`BavixTreasuryAllocationOperationRuntime` requires one committed reservation
from `ClientFunds` to `PayCodeReserve`. It is intentionally not a generic
externally funded order hold.

## Settled decisions

1. The accounting primitive belongs in `3neti/wallet`.
2. The On-Demand Issuance feature does not belong in `3neti/wallet`.
3. No Bavix wallet is created per order.
4. The wallet contract uses opaque scalar external references.
5. Existing Pay Code Reserve behavior remains backward-compatible.
6. The generic held-value label may be `Encumbered Client Funds`.
7. x-change's `Client Funds — On-Demand Issuance Hold` is presentation and
   business meaning, not wallet contract vocabulary.
8. Provider evidence, exact bank amounts, QR Ph, pricing, fees, Maker/Checker,
   Funding Intents, and issuance jobs remain outside this package.
9. The wallet package enforces conservation and accounting invariants; the
   caller authorizes the movement and classifies destinations.
10. A narrow generalization of `TreasuryAllocation` is preferred over a
    parallel ledger when public-contract safety permits it.

## Invariants

- An allocation is not a wallet.
- A hold is backed by committed Treasury operations.
- Amounts are integer minor units.
- Currency is stable.
- Balances cannot become negative.
- Conflicting idempotent replay fails closed.
- Concurrent operations cannot double-book or overspend.
- Original committed operations remain immutable.
- Release and reversal are explicit, separately evidenced operations.
- Sensitive or product-specific data does not enter Treasury metadata.
- The package does not import x-change or voucher domain classes.

## Next controlled gate

**Gate W2 — Complete reversal and concurrency characterization.**

1. add explicit hold-level reversal only if the existing allocation reversal
   cannot express the required operator action safely;
2. add focused concurrent place, replenish, consume, and release proofs;
3. verify conflicting replays continue to fail closed;
4. preserve the existing Client Funds-to-Pay Code Reserve semantics; and
5. review the public DTO vocabulary before package publication.

No x-change dependency, provider adapter, release, or host deployment belongs
in this gate.

## Following gates

After W2 review, prepare a controlled wallet release for x-change adoption.

## Stop conditions

Stop rather than improvise if:

- the design requires a voucher or x-change model;
- a new Bavix wallet appears necessary for each order;
- existing reserve/release behavior would change silently;
- value can be consumed without committed backing;
- concurrent operations cannot be proven safe;
- a movement cannot be reversed or released without mutating history;
- metadata would contain raw provider evidence or personal data; or
- implementation requires a major-version contract break without approval.

## Companion documents

- Wallet plan: `docs/architecture/treasury/ON_DEMAND_ISSUANCE_HOLD_PLAN.md`
- x-change plan: `3neti/x-change: docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md`
- x-change compass: `3neti/x-change: docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_COMPASS.md`

Future agents must update this compass after every completed or blocked wallet
gate.
