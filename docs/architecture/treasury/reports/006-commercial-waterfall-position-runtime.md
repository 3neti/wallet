# 006: Commercial Waterfall Position Runtime

## Status

Implemented.

## Purpose

This slice gives an external commercial orchestrator a package-neutral accounting surface for collecting one approved commercial charge and classifying it across a Commercial Waterfall without mixing the charge with Pay Code principal.

## Added Treasury Position Purposes

- `commercial_clearing`;
- `provider_cost_payable`;
- `product_revenue`;
- `partner_commission_payable`;
- `royalty_payable`;
- `tax_payable`;
- `commercial_revenue`.

The purpose names are accounting classifications. They do not allow Treasury to calculate a price, select a participant, approve attribution, or create a commercial entitlement.

## Added Operations

`charge()` moves a positive integer-minor-unit amount from Client Funds into Commercial Clearing.

`allocate()` now accepts Commercial Clearing as a source only when the destination is one of the approved commercial purposes.

`reverseCommercialMovement()` exactly compensates one prior Commercial Charge or commercial Allocation. It requires the reversed operation reference, reverses the same positions, currency, and amount, and refuses a second reversal.

Every operation retains caller and idempotency references and uses the existing durable Treasury Position operation ledger and Bavix adapter.

## Invariants

- Client Funds can enter Commercial Clearing only through `charge()`.
- Commercial Clearing cannot allocate to Client Funds.
- Commercial allocations cannot cross provider connections or Settlement Resources.
- Pay Code Reserve is not an eligible commercial source or destination.
- Identical replay returns the existing operation without another transfer.
- Reused idempotency input with different facts fails closed.
- Reversals are append-only compensating movements.
- Provider Cost Payable is not an external provider payment.

## Package Boundary

x-commerce defines the catalog, quote, attribution, and Commercial Waterfall allocation plan.

x-change owns the commercial lifecycle and maps the plan to opaque Treasury Position references.

3neti/wallet validates and records the accounting movements without importing x-commerce or x-change classes.

## Verification

Focused feature coverage proves exact charge conservation, multi-recipient posting, replay safety, append-only reversal, invalid destination rejection, and the existing Treasury Position suite.
