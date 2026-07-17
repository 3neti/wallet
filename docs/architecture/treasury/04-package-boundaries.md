# Package Boundaries

## Status

Canonical target ownership boundary. Current exceptions are recorded as debt and are not changed by this documentation bootstrap.

## Boundary rule

Treasury owns accounting/resource truth. x-change owns commercial meaning and settlement orchestration.

```text
x-change lifecycle/orchestration
        |
        | package-neutral Treasury request + opaque references
        v
3neti/wallet (Treasury contracts, accounting, read models)
        |
        v
Bavix wallet primitives and approved Treasury persistence
```

## `3neti/wallet` / Treasury owns

- Wallet integration;
- Settlement Resource accounting abstractions;
- Inventory;
- Allocation;
- Slice;
- reservation implementation semantics;
- draw/capture, release, repay, and reverse semantics;
- operation idempotency and concurrency invariants;
- Treasury ledger semantics and accounting read models;
- Bavix integration and schema adaptation decisions;
- deployable, allocated, drawn, repaid, reversed, and outstanding accounting measures.

## `3neti/wallet` / Treasury must not own or import

- Voucher or Pay Code models/classes;
- Facility models/classes;
- Lien models/classes;
- Settlement Envelope models/classes;
- Execution Engine classes;
- claim UX;
- x-change commercial lifecycle orchestration;
- provider-specific settlement business rules;
- Cockpit presentation models.

Treasury can store opaque scalar references supplied by x-change, but those references do not transfer ownership of the external aggregate.

## x-change owns

- Facilities and their commercial lifecycle;
- Liens and collateral business rules;
- Voucher / Pay Code issuance, claim, redemption, expiry, and cancellation;
- Settlement Envelopes;
- Execution Engine and provider workflow orchestration;
- decisions to request allocate, draw, release, repay, reverse, retry, or reconcile;
- Cockpit-oriented integration/read models.

## Interaction contract

x-change should request Treasury operations through interfaces and package-neutral DTOs/references. A future request might contain values such as:

- Treasury operation/idempotency key;
- Inventory or Allocation reference;
- external aggregate type and opaque identifier;
- amount, currency/unit, and effective time;
- neutral reason code and metadata.

It must not require Treasury to type-hint or query an x-change model. Likewise, Treasury should not expose Bavix internals as the commercial contract if a stable Treasury reference is required.

The exact interfaces and DTOs are reserved for a separately approved planning slice.

## Adjacent ownership

- Cockpit presents operator/read-model views but does not own accounting truth.
- x-journal may preserve immutable evidence but does not own Treasury state.
- external providers and banks own their reported balances; Treasury reconciles references/observations rather than adopting those values as wallet truth.

## Legacy Boundary Debt

`3neti/wallet` currently has a direct dependency on `LBHurtado\Voucher\Models\Voucher` through `src/Events/DisbursementFailed.php`. This contradicts the target Treasury boundary where wallet must not know vouchers. The dependency is pre-existing and must be addressed in a separately approved implementation slice.

It is not removed or refactored in this bootstrap because doing so could change a public event payload or break existing listeners. It also is not evidence that Voucher belongs in Treasury.

### Recommended future slice

**Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model**

Potential directions to evaluate, not authorized here:

- replace the model with scalar/reference data such as `voucher_code`, `voucher_id`, or `external_reference`;
- introduce a package-neutral `DisbursementFailureContextData` DTO;
- provide a compatibility/deprecation path for existing event consumers;
- characterize current event serialization and listener expectations before changing the signature.

## Dependency enforcement target

Future boundary checks should ensure source under `LBHurtado\Wallet` does not import x-change Voucher, Facility, Lien, Settlement Envelope, or Execution Engine namespaces. The legacy event dependency requires an explicit temporary exception until the debt slice is approved and completed.
