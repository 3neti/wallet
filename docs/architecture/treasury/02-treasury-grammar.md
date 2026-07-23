# Treasury Grammar

## Status

Canonical Treasury grammar. Planning-only contracts may expose this language, but durable accounting and money movement require separately approved runtime slices.

## Settlement Resource

A **Settlement Resource** is a controlled source of settlement capacity that Treasury can identify, measure, and account for under an eligibility policy.

Examples include cash, underwriter-backed liquidity, eligible receivables, guarantees, and future resource types. The commercial instrument that grants or governs a resource is not itself owned by Treasury.

## Inventory

**Inventory** is Treasury's accounting representation of a Settlement Resource position that may support settlement.

Inventory answers questions such as:

- which resource is represented;
- what amount or capacity is recognized;
- what portion is eligible and deployable;
- what portion has been allocated or drawn;
- which package-neutral external reference links it to its origin.

Inventory does not encode a Facility, Lien, Voucher, Pay Code, Settlement Envelope, or provider workflow.

## Recognize Inventory

**Recognize Inventory** records eligible capacity entering one Inventory from a verified Settlement Resource. Recognition requires an external evidence reference and an idempotency key; a provider notification by itself is not recognition.

## Reclassify Inventory

**Reclassify Inventory** moves already-recognized capacity between two Inventory positions without changing the linked Account balance. A typical example is settlement of an EMI receivable into bank cash. The source decrease and destination increase must conserve currency and amount.

## Adjust Inventory

**Adjust Inventory** records an explicit signed correction or impairment to one Inventory. It is a controlled accounting operation, not a substitute for provider evidence or a user-facing funding command.

## Allocation

An **Allocation** is a Treasury commitment of a bounded portion of Inventory to an external commercial or settlement context, identified only through package-neutral references.

An Allocation has a lifecycle, an original amount or capacity, a remaining amount, and idempotent operation references. Its implementation may reserve wallet funds or record a ledger commitment, but **reservation is an implementation detail of Allocation**, not the top-level domain abstraction.

## Slice

A **Slice** is an independently actionable subdivision of an Allocation.

Slices allow one Allocation to support fixed portions, partial claims, staged settlement, or repeated utilization. A Slice can be drawn, released, repaid, or reversed according to Treasury invariants and a request from the owning orchestration layer.

## Draw / Capture

**Draw** and **Capture** are equivalent Treasury verbs for consuming available capacity from an Allocation or Slice for settlement.

A draw reduces the remaining committed capacity and increases utilization/drawn accounting. It does not by itself define provider success, claim UX, or the commercial lifecycle that requested it.

## Release

**Release** returns undrawn committed capacity to deployable Inventory. Typical external reasons include expiry or cancellation, but Treasury receives an operation request and neutral reason/reference rather than owning those lifecycles.

A release never releases an amount that has already been drawn unless a separately defined compensating operation first changes that state.

## Repay

**Repay** records value returned against drawn or outstanding utilization. Depending on the resource policy, repayment may restore reusable Allocation capacity, replenish Inventory, reduce outstanding liability, or some combination represented explicitly in the ledger.

Repayment is not the same as release: release concerns undrawn commitment; repayment concerns utilization that has already occurred.

## Reverse

**Reverse** is a compensating accounting operation against a prior Treasury operation. It preserves the audit trail rather than deleting or rewriting the original operation.

A reversal must reference the operation it compensates and be idempotent. Whether failed provider disbursement results in reversal, retry, suspense, or manual reconciliation is a policy decision outside the basic Treasury grammar.

A reversal derives its affected Inventory or Allocation from the referenced operation. It does not require an Allocation when compensating Inventory recognition, reclassification, or adjustment.

## Reservation

**Reservation** is a technical mechanism that may prevent allocated wallet-backed value from being spent elsewhere. It may be implemented through a ledger, holds, Bavix-compatible primitives, or dedicated storage after an explicit decision.

Callers request an Allocation. They do not make reservation storage the cross-package domain contract.

## Reference flow

```text
Settlement Resource
  -> represented as Inventory
  -> increased by Recognize Inventory
  -> moved without duplication by Reclassify Inventory
  -> corrected or impaired by Adjust Inventory
  -> committed through Allocation
  -> optionally divided into Slices
  -> consumed by Draw/Capture
  -> unused capacity returned by Release
  -> utilization reduced/restored by Repay
  -> prior operations compensated by Reverse
```

## Required invariants for future design

- amounts use an explicit currency/unit and integer-safe representation;
- total active commitment cannot exceed eligible inventory under the selected policy;
- a draw cannot exceed the active remainder of its Allocation or Slice;
- a release cannot exceed the undrawn active remainder;
- a repayment cannot exceed the repayable utilization unless policy explicitly permits credit balances;
- a reversal references a prior operation and cannot silently erase history;
- recognition requires verified Settlement Resource evidence and cannot be inferred from an unverified webhook;
- reclassification conserves amount and currency and does not change the linked Account balance;
- an adjustment uses an explicit signed delta and cannot silently replace recognition;
- every mutating request is idempotent under a caller-supplied operation key;
- cross-package references are opaque to Treasury and never import x-change domain classes.
