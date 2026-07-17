# Collateralized Liquidity Abstraction Bootstrap Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: Phase 0 documentation and planning only
- Result: canonical Treasury documents bootstrapped; no production code, migrations, or tests changed

## Files created

- `docs/architecture/treasury/00-why-treasury.md`
- `docs/architecture/treasury/01-evolution-of-wallet.md`
- `docs/architecture/treasury/02-treasury-grammar.md`
- `docs/architecture/treasury/03-accounting-semantics.md`
- `docs/architecture/treasury/04-package-boundaries.md`
- `docs/architecture/treasury/05-migration-plan.md`
- `docs/architecture/treasury/reports/000-collateralized-liquidity-abstraction-bootstrap.md`

## Files updated

- `docs/architecture/treasury/TREASURY_COMPASS.md`

## Files inspected

### Package and architecture

- `composer.json`
- `composer.lock` (validation state)
- `README.md`
- `docs/architecture/treasury/TREASURY_COMPASS.md`
- `config/wallet.php`
- `config/account.php`

### Production source

- `src/WalletServiceProvider.php`
- `src/Actions/TopupWalletAction.php`
- `src/Actions/WithdrawCash.php`
- `src/Classes/BalanceUpdatedAssembler.php`
- `src/Data/TransactionData.php`
- `src/Enums/WalletType.php`
- `src/Events/BalanceUpdated.php`
- `src/Events/DepositConfirmed.php`
- `src/Events/DisbursementConfirmed.php`
- `src/Events/DisbursementEvent.php`
- `src/Events/DisbursementFailed.php`
- `src/Jobs/BroadcastBalanceUpdated.php`
- `src/Listeners/DispatchBalanceUpdatedBroadcast.php`
- `src/Providers/EventServiceProvider.php`
- `src/Services/SystemUserResolverService.php`
- `src/Services/WalletProvisioningService.php`
- `src/Traits/HasPlatformWallets.php`

### Tests and fixtures

- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/Models/User.php`
- `tests/Feature/UserWalletsTest.php`
- `tests/Unit/Actions/TopupWalletActionTest.php`
- `tests/Unit/Data/TransactionDataTest.php`
- `tests/Unit/Models/UserTest.php`
- `tests/Unit/Services/SystemUserResolverServiceTest.php`
- `tests/Unit/Services/WalletProvisioningServiceTest.php`
- `tests/database/migrations/0001_01_01_000000_create_users_table.php` (inventory/path confirmation)

## Current wallet behavior

- Bavix supplies wallet interfaces/models, transaction and transfer primitives, balance accounting, services/repositories, and production migrations.
- This package merges/publishes Bavix integration configuration and account configuration through `WalletServiceProvider`.
- A configured system user is resolved by model, identifier column, and identifier; the result must implement Bavix's `Wallet` interface.
- wallet holders can be provisioned idempotently with platform, rewards, escrow, and commission wallet slugs.
- top-up resolves the system user and invokes a Bavix float transfer into the target wallet.
- cash withdrawal rejects zero balance and over-withdrawal, then withdraws either an explicit minor-unit amount or the entire balance with confirmed disbursement metadata.
- `TransactionData` maps Bavix minor-unit amounts to PHP `Money`, plus confirmation state and payload.
- package classes assemble, listen for, queue, and broadcast balance changes and expose deposit/disbursement events.
- tests load a package-owned test-user migration and locate Bavix migrations dynamically; the package does not ship production wallet migrations.

## Current gaps

- no Inventory, Allocation, or Slice contracts;
- no allocate/reserve, draw/capture, release, repay, or reverse API;
- no caller-supplied idempotency contract for Treasury operations;
- no allocation ledger or allocation read model;
- no usable-balance semantics net of active commitments;
- no durable collateralized-liquidity representation;
- no approved package-neutral reference between x-change and Treasury;
- no current package test characterizes `WithdrawCash`;
- event compatibility is not comprehensively characterized.

## Treasury grammar established

- **Settlement Resource:** a controlled source of settlement capacity.
- **Inventory:** Treasury's accounting representation of an eligible resource position.
- **Allocation:** a bounded commitment of Inventory to an opaque external context.
- **Slice:** an independently actionable subdivision of an Allocation.
- **Draw / Capture:** consumption of Allocation/Slice capacity.
- **Release:** return of undrawn committed capacity.
- **Repay:** reduction/restoration associated with already drawn utilization.
- **Reverse:** a traceable compensating operation against an earlier operation.

Reservation is explicitly subordinate to Allocation: it is a possible technical mechanism, not the cross-package domain abstraction.

## Package ownership decisions

- `3neti/wallet` evolves into Treasury; no separate Treasury package is created.
- Treasury owns Wallet integration, Inventory, Allocation, Slice, accounting operations, idempotency, ledger/read-model semantics, and Bavix adaptation decisions.
- x-change owns Facility, Lien, Voucher / Pay Code, Settlement Envelope, Execution Engine, commercial lifecycle, claim workflows, and provider orchestration.
- x-change communicates with Treasury through package-neutral contracts, DTOs, operation keys, and opaque scalar references.
- Bavix continues to own the current wallet/transaction/transfer schema and money-movement primitives.
- Cockpit presents views; x-journal preserves evidence; neither owns Treasury accounting state.

## Legacy Boundary Debt

`3neti/wallet` currently has a direct dependency on `LBHurtado\Voucher\Models\Voucher` through `src/Events/DisbursementFailed.php`. This contradicts the target Treasury boundary where wallet must not know vouchers. The dependency is pre-existing and must be addressed in a separately approved implementation slice.

The bootstrap does not remove or refactor it because that could affect a public event payload or existing listeners. Potential future replacement with `voucher_code`, `voucher_id`, `external_reference`, or a package-neutral `DisbursementFailureContextData` DTO is recorded as direction only, not authorization.

## Migration phases

0. Documentation Bootstrap.
1. Current Behavior Characterization.
2. Planning-only Treasury DTOs and Contracts.
3. Null Treasury Runtime.
4. Inventory Read Models.
5. Allocation/Slice Planning.
6. Reservation Ledger Decision.
7. Durable Reservation/Allocation Storage.
8. x-change Adapter Boundary.
9. Lifecycle Scenario Demonstration.
10. Production Hardening.

Only Phase 0 is authorized and completed by this report.

## Risks

- treating Bavix wallet balance as usable balance can overstate deployable value once commitments exist;
- implementing reservation and Allocation as separate deductions could double-count committed funds;
- concurrency can allow over-allocation or overdraw without atomic invariants;
- partial draw, repayment, reversal, and provider-failure policies remain undecided;
- using Bavix transaction metadata as a Treasury ledger may be insufficient for durable state and replay requirements;
- new Treasury tables may eventually be necessary, but no migration is authorized;
- cross-package model imports can erode the ownership boundary;
- changing `DisbursementFailed` without characterization could break serialization, listeners, or consumers;
- `composer.lock` currently does not match `composer.json`;
- dependencies are not installed, so the existing test suite could not be executed in this checkout.

## Open questions

- What are the exact Inventory, Allocation, and Slice persistence models?
- Can Bavix transactions express all required ledger transitions, or is a dedicated append-only Treasury ledger required?
- How are operation IDs scoped and enforced for allocate, draw, release, repay, and reverse?
- What locking/atomicity strategy prevents over-allocation and overdraw?
- What are the gross, net, repaid, reversed, and outstanding read-model definitions?
- How does revolving capacity differ from non-revolving repayment?
- What policy applies after provider failure following capture?
- How are heterogeneous resources valued and combined?
- Is a `SettlementResourceInterface`, `AllocationReference`, or both required?
- What compatibility/deprecation path safely removes the Voucher model from `DisbursementFailed`?

## Recommended next slice

Proceed with **Phase 1 — Current Behavior Characterization** after explicit approval. Prioritize `WithdrawCash`, current event payloads/serialization, and unchanged top-up/transfer behavior.

Track separately:

**Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model**

This debt slice is an implementation/API compatibility change and was not performed here.

## Commands executed

- `pwd`
- file discovery with `rg --files` and `find`
- targeted source/document inspection with `sed -n`
- targeted integration/config search with `rg -n`
- dependency availability check for `vendor/bin/pest`
- `git status --short`
- `git diff --stat`
- `git diff -- docs/architecture/treasury/TREASURY_COMPASS.md`
- `git ls-files ...`
- `composer validate --strict`
- required-file and required-term coverage checks with `test` and `rg -n`
- documentation inventory/line-count checks with `find` and `wc -l`
- `git add docs/architecture/treasury`
- `git diff --cached --check`
- staged change review with `git status --short`, `git diff --cached --stat`, `--name-only`, and `--numstat`

## Test and check results

- `composer validate --strict`: **failed strict validation**. `composer.json` is valid, but `composer.lock` is not up to date with `composer.json`.
- Existing package test command from Composer: `vendor/bin/pest`.
- Test suite: **not run**, because `vendor/bin/pest` is unavailable and no network installation was authorized.
- Staged diff whitespace check: **passed**.
- Production source changes: none.
- Migration changes: none.
- Test changes: none.
