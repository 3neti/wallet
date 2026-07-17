# Accounting Semantics

## Status

Intended future semantics only. The equations and lifecycle mappings in this document are design targets, not descriptions of an implemented API or current wallet behavior.

## Measures that must remain distinct

| Measure | Meaning | Current source of truth |
| --- | --- | --- |
| Wallet balance | Amount booked by Bavix for a wallet from its transactions | Bavix wallet state |
| Reserved balance | Wallet-backed amount technically held against other spending; a possible implementation detail of active Allocations | Not implemented |
| Allocated balance | Eligible Inventory capacity committed through active Allocations, whether or not implemented as a wallet hold | Not implemented |
| Drawn balance | Gross amount/capacity captured from Allocations; repayments and reversals require separately visible net-utilization views | Not implemented |
| Outstanding liability | Unsettled obligation owed to a beneficiary, provider, funder, or other party under external commercial semantics | Not owned as wallet balance; future Treasury may expose referenced accounting measures |
| Collateralized liquidity | Eligible settlement capacity supported by recognized collateral or another non-cash backing resource | Not implemented |
| Usable balance | Policy-approved Inventory still deployable after active commitments, holds, limits, and adjustments | Not implemented; current Bavix balance is not automatically this value |
| Provider/bank balance | Cash or settlement position reported by an external provider or bank | External system/reconciliation source |

Reserved balance and allocated balance may describe the same committed value at different layers. They must not be double-counted. The future ledger/read-model design must state whether a reservation is the wallet-backed implementation of an Allocation or a separate constraint.

## Planning equations

For a single Inventory position, a starting planning model is:

```text
Active Allocation Remainder = Original Allocation - Gross Draws - Releases
                              + Approved Draw Reversals
                              + Approved Revolving Replenishment
Deployable Inventory = Eligible Inventory - Active Allocations - Other Policy Holds
```

For a wallet-backed Inventory where reservation implements Allocation:

```text
Usable Wallet-Backed Amount = Wallet Balance - Active Reserved Amount - Other Holds
```

For non-cash Inventory, wallet balance may be irrelevant:

```text
Usable Resource Capacity = Eligible Resource Capacity - Active Allocations - Other Holds
```

These are conceptual formulas. Restored capacity must remain capped by the approved facility/resource policy; non-revolving repayment may reduce outstanding utilization without restoring the Allocation remainder. Currency handling, valuation, concurrency, and aggregation require explicit design before implementation.

## Lifecycle mapping

Treasury does not own the listed Pay Code events. They are x-change lifecycle events that may request package-neutral Treasury operations.

### Pay Code issued -> Allocate / reserve Inventory

x-change requests an Allocation with an idempotency key and opaque references. Treasury validates eligible Inventory and commits capacity. For wallet-backed Inventory, an eventual implementation may reserve value so current wallet funds cannot be reused.

No current debit-at-issuance or balance behavior is changed by this documentation.

### Pay Code redeemed -> Draw / capture Allocation

x-change requests a draw against the referenced Allocation or Slice. Treasury verifies the active remainder, records the capture exactly once, and reduces remaining committed capacity.

The provider disbursement workflow remains outside Treasury.

### Pay Code partially claimed -> Draw a partial Slice

Treasury captures only the requested Slice/amount and keeps the undrawn remainder active. Repeated partial claims require independent idempotency keys and must never exceed the Allocation remainder.

### Pay Code expired -> Release remaining Allocation

x-change requests release of the undrawn remainder. Treasury returns that capacity to deployable Inventory and records why it was released through neutral metadata/reference data.

### Pay Code cancelled -> Release remaining Allocation

Cancellation follows the same Treasury accounting verb as expiry. x-change owns the distinction between cancellation and expiry; Treasury owns the amount invariant and release record.

### Provider disbursement failed after capture -> Reverse or reconcile by policy

A failed provider call does not imply that Treasury may delete the capture. x-change or a reconciliation policy requests one of the approved outcomes: retry with no Treasury change, compensate through Reverse, move to a suspense/reconciliation state, or escalate for manual resolution.

The reversal must reference the capture and be idempotent. The correct policy is an open decision.

### Repayment -> Replenish and reduce outstanding

Repayment is recorded against prior utilization. It reduces repayable outstanding amounts and may restore revolving Allocation capacity or replenish Inventory according to resource policy. It is not modeled as release of undrawn capacity.

## Illustrative partial-draw example

For an Allocation of PHP 10,000:

1. Allocation created: PHP 10,000 committed, PHP 0 drawn, PHP 10,000 active remainder.
2. First Slice drawn: PHP 3,000 drawn, PHP 7,000 remains active.
3. Second Slice drawn: PHP 2,000 additional draw, PHP 5,000 remains active.
4. Allocation expires: PHP 5,000 is released; cumulative draw remains PHP 5,000.
5. PHP 1,000 repayment: outstanding utilization reduces according to the selected revolving/non-revolving policy; it does not reopen the released amount automatically.

This example describes accounting intent and does not prescribe database records.

## Accounting and audit rules for later approval

- use append-only or compensating records for material state transitions;
- retain original and caller operation references;
- expose gross, reversed, repaid, and net values explicitly rather than overloading one balance;
- separate internal Treasury truth from provider/bank observations;
- reconcile external provider results without pretending an external balance is a wallet balance;
- prevent over-allocation and overdraw under concurrency;
- define valuation and currency rules before supporting heterogeneous resources;
- make every operation replay-safe and observable.
