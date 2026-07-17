# Why Treasury

## Status

Canonical architecture direction. This document describes a future model; it does not authorize implementation or change current balance behavior.

## The Settlement OS problem

The current wallet model is effective when value is already represented as cash in a Bavix wallet. Settlement OS must eventually answer a broader question: **what controlled resource can support this settlement, how much of it is already committed, and what remains deployable?**

The governing thesis is:

> Settlement consumes Settlement Resources, not necessarily cash.

A settlement resource can be cash-backed, but it can also be supported by collateral, an underwriter commitment, a receivable, a guarantee, or another future resource whose eligibility and use can be accounted for. Treasury is the resource and accounting layer that makes those forms legible without teaching wallet code the commercial meaning of a facility or Pay Code.

## Why wallet balance is insufficient

The shortcut below is valid only in the simplest cash-only case:

```text
Wallet Balance = Available Balance
```

It fails as soon as some value is committed but not yet moved, some liquidity is not cash, or an external obligation survives after a draw. A wallet can show a positive balance while the same value has already been promised to an active settlement. Conversely, an eligible guarantee or underwriter commitment may provide settlement capacity without appearing as cash in a wallet.

Treasury therefore needs separate answers for:

- what is booked in a wallet;
- what inventory is eligible for settlement;
- what inventory is committed;
- what has been drawn;
- what obligation remains outstanding;
- what is actually usable under current policy;
- what cash a provider or bank reports externally.

These are related measures, not synonyms.

## Resource forms Treasury must accommodate

### Cash-backed settlement

Cash held in or represented by a wallet is the current baseline. Future Treasury semantics must preserve the existing Bavix behavior while allowing cash to be inventoried and allocated before it is drawn.

### Collateral-backed facilities

Collateral can support liquidity without itself being spendable cash. Treasury records the eligible resource and its commitments; x-change owns the facility and lien rules that make the collateral commercially meaningful.

### Underwriter-backed liquidity

An underwriter can make a bounded commitment available for settlement. Treasury needs a neutral inventory representation and allocation accounting without owning underwriting workflows or contracts.

### Receivables

Eligible receivables may support settlement capacity subject to valuation and policy. The receivable business lifecycle remains outside Treasury; Treasury accounts only for the resource position and its use.

### Guarantees

A guarantee can support a settlement right even where no corresponding wallet cash exists. Its eligibility, limits, and commitments must not be collapsed into a wallet balance.

### Revolving facilities

Capacity can be drawn, repaid, and made available again. A single balance cannot explain original capacity, committed capacity, current draw, repayments, and restored availability.

### Partial-draw facilities

One allocation may be consumed in several slices. Treasury must keep the undrawn remainder active while accounting for each independent draw, release, repayment, or reversal.

### Future settlement resources

The grammar must be extensible to resource types not yet selected. New backing types should enter through Treasury-owned contracts and references, not through special cases in the wallet balance API.

## Architectural outcome

`3neti/wallet` evolves in place into Treasury. Bavix remains the current wallet and transaction foundation. Treasury adds a resource grammar around that foundation incrementally and compatibly:

```text
Settlement Resource -> Inventory -> Allocation -> Slice -> Draw / Release / Repay / Reverse
```

Reservation is a possible implementation mechanism for an Allocation. It is not the top-level domain abstraction.

## Guardrail

Nothing in this document changes current top-up, transfer, withdrawal, balance, event, or persistence behavior. Any implementation requires a separately approved slice.
