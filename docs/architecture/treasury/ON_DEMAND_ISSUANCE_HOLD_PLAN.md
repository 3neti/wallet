# Provider-Neutral Order-Bound Treasury Hold Plan

Last updated: 2026-09-29

## Objective

Narrowly generalize the existing Treasury allocation machinery so a consuming
settlement package can place, consume, release, reverse, and inspect value held
for an opaque external order.

This is the wallet-side dependency for x-change On-Demand Issuance Funding,
but the wallet contract must remain provider-neutral and product-neutral.

## Boundary

`3neti/wallet` owns accounting mechanics. It does not own the business reason
for a hold.

The package may know:

- principal/account reference;
- Inventory and Position references;
- allocation/hold and operation references;
- integer amount and currency;
- source, held, and destination positions;
- idempotency keys;
- external opaque references;
- balance, version, and status; and
- sanitized metadata.

The package must not know:

- vouchers or Pay Codes;
- QR Ph, NetBank, or any provider adapter;
- pricing, fees, funding bases, or Quick Generate;
- x-change Funding Intents;
- Maker/Checker authorization;
- browser modal state; or
- automatic issuance jobs.

## Current baseline

The package already has:

- durable Treasury Inventory and Positions;
- append-only Position Operations;
- `TreasuryPositionOperationContract::reserve()` and `release()`;
- `TreasuryAllocation` and immutable Allocation Operations;
- atomic row locking;
- database-backed idempotency and request hashes;
- activate, draw, replenish, release, and reverse operations; and
- allocation and activity read models.

The current `BavixTreasuryAllocationOperationRuntime` is deliberately
specialized:

- activation requires a committed Client Funds-to-Pay Code Reserve
  reservation;
- the reserve Position must be `PayCodeReserve`; and
- release returns to `ClientFunds`.

That behavior is production-compatible and must remain unchanged while the
generic order-bound capability is added.

## Target abstraction

The target is an order-bound Treasury allocation, not another Bavix wallet.

Conceptual operations:

```php
interface TreasuryHoldOperationContract
{
    public function place(TreasuryHoldPlacementData $data): TreasuryHoldData;

    public function consume(TreasuryHoldConsumptionData $data): TreasuryHoldData;

    public function release(TreasuryHoldReleaseData $data): TreasuryHoldData;

    public function reverse(TreasuryHoldReversalData $data): TreasuryHoldData;
}
```

The exact public API is reserved for implementation review. Reusing or
generalizing `TreasuryAllocationOperationContract` is preferable when it can be
done without weakening existing invariants or producing ambiguous method
semantics.

## Neutral accounting terminology

The wallet layer may describe the held position as:

```text
Encumbered Client Funds
```

It must not name the position after On-Demand Issuance or Pay Codes. The
external consumer supplies an opaque order reference.

One held position may serve an Account/provider/currency portfolio, with
individual allocation rows and immutable operations identifying each order's
portion. A Bavix wallet per order is explicitly rejected.

## Required operations

### Place

- accept an authorized source or newly recognized position;
- atomically move or attribute the requested amount to the held position;
- create one immutable allocation under an external order reference;
- reject conflicting idempotent replay; and
- return the held balance and version.

### Add or replenish

This is needed only when the caller explicitly allows a multi-source order,
such as an existing Client Funds contribution plus a later provider payment.

- enforce the configured maximum;
- require compatible principal, provider scope, currency, and held position;
- preserve independent source-operation evidence; and
- prevent replenishment of a closed allocation.

### Consume

- lock the allocation and relevant Positions;
- reject an amount greater than the held balance;
- support one or more separately evidenced governed destinations;
- decrement the allocation exactly once; and
- close the allocation when its balance reaches zero and the caller requests
  final consumption.

The wallet package validates amount conservation. The caller decides which
destination represents principal, fee, tax, or another business meaning.

### Release

- return unconsumed value to the configured release Position;
- close the allocation;
- remain replay-safe; and
- retain complete evidence.

### Reverse

- compensate a prior eligible operation;
- identify the reversed operation explicitly;
- reject incompatible amount, currency, or already-reversed evidence; and
- never mutate the original operation.

## Invariants

1. All amounts use positive integer minor units.
2. Currency and decimal places remain stable for the allocation lifetime.
3. A hold cannot exceed its authorized backing operations.
4. A hold balance cannot become negative.
5. Place, replenish, consume, release, and reverse are database-idempotent.
6. Conflicting reuse of an operation or idempotency reference fails closed.
7. Allocation and Position rows are locked in deterministic order.
8. Committed operation rows cannot be updated or deleted.
9. Sum of held, consumed, released, and reversed values remains reconstructable.
10. Metadata is sanitized before persistence.
11. Raw provider payloads, credentials, personal data, and voucher instructions
    cannot be Treasury metadata.
12. Read models do not expose internal Bavix identifiers.

## Multiple destinations

An external order may eventually consume value into more than one governed
Position. For example, the consuming package may request separate movements
for principal and service cost.

The wallet contract must expose separate atomic operations or an atomic batch
whose sum cannot exceed the held balance. It does not classify those
destinations commercially.

The first implementation may deliberately support only one destination if
that keeps the public contract safe. Multi-destination consumption then
remains a separately reviewed additive gate before x-change automatic issuance.

## Read models

Package-neutral reads should expose:

- allocation reference;
- opaque external reference;
- currency;
- initial, replenished, consumed, released, reversed, and remaining amounts;
- status and version;
- created, effective, and closed timestamps; and
- redacted operation activity.

The read model must not label an allocation as a Pay Code, voucher, or
on-demand issuance order.

## Controlled gates

### Gate W1 — Characterization

- freeze current Position Reservation and Treasury Allocation behavior;
- prove current Client Funds-to-Pay Code Reserve activation, draw, replenish,
  release, and reverse semantics; and
- document public-contract compatibility requirements.

### Gate W2 — Contract design

- select narrow generalization versus a sibling hold contract;
- define immutable DTOs using scalar references;
- define allowed source/held/release Position relationships; and
- preserve the existing contract unchanged unless a major-version boundary is
  explicitly approved.

### Gate W3 — Durable placement and release

- implement place and release under row locks;
- add unique references and request hashes;
- enforce currency, amount, ownership, and backing invariants; and
- add package-neutral read models.

### Gate W4 — Consumption and replenishment

- implement bounded consumption;
- add optional replenishment for multi-source allocations;
- close fully consumed allocations; and
- prove concurrent calls cannot overspend.

### Gate W5 — Reversal and activity

- implement operation-targeted compensating reversal;
- expose redacted activity; and
- prove rebuildability from immutable operations.

### Gate W6 — Consumer contract release

- run focused and full package tests;
- validate Composer metadata;
- document the public API and upgrade path; and
- stop for explicit push/tag authorization.

## Test matrix

Minimum package tests must prove:

- no Bavix wallet is created per hold or external order;
- identical replay returns the same economic result;
- conflicting replay fails;
- simultaneous placement cannot double-book backing value;
- simultaneous consumption cannot overspend;
- partial consumption maintains the correct remaining balance;
- full consumption closes correctly;
- release returns only the remaining amount;
- replenishment cannot exceed the maximum;
- closed allocations reject new movements;
- reversal is compensating and exactly once;
- existing Pay Code Reserve allocation behavior is unchanged;
- metadata sanitization applies recursively; and
- read models exclude Bavix internals and sensitive metadata.

## Consumer handoff

After release, x-change may:

1. create its own frozen Issuance Funding Order;
2. supply the order reference as an opaque external reference;
3. request placement or replenishment after authoritative provider evidence;
4. request consumption into selected governed Positions;
5. request release on an authorized cancellation or expiry; and
6. read the allocation without querying wallet tables directly.

The x-change companion plan is:

`3neti/x-change: docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md`

No runtime implementation, release, or consumer adoption is authorized by
this plan.
