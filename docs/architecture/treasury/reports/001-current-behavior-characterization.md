# Phase 1 Current Behavior Characterization Report

## Report metadata

- Date: 2026-07-17
- Package: `3neti/wallet`
- Scope: Phase 1 protection and characterization
- Result: characterization tests authored; PHP syntax checks pass; Pest execution pending because package dependencies are unavailable

## Files inspected

### Architecture and package configuration

- `composer.json`
- `composer.lock` (dependency/version and validation state)
- `docs/architecture/treasury/TREASURY_COMPASS.md`
- `docs/architecture/treasury/00-why-treasury.md`
- `docs/architecture/treasury/01-evolution-of-wallet.md`
- `docs/architecture/treasury/02-treasury-grammar.md`
- `docs/architecture/treasury/03-accounting-semantics.md`
- `docs/architecture/treasury/04-package-boundaries.md`
- `docs/architecture/treasury/05-migration-plan.md`
- `docs/architecture/treasury/reports/000-collateralized-liquidity-abstraction-bootstrap.md`

### Production source

- `src/Actions/TopupWalletAction.php`
- `src/Actions/WithdrawCash.php`
- `src/Data/TransactionData.php`
- `src/Enums/WalletType.php`
- `src/Events/BalanceUpdated.php`
- `src/Events/DepositConfirmed.php`
- `src/Events/DisbursementConfirmed.php`
- `src/Events/DisbursementEvent.php`
- `src/Events/DisbursementFailed.php`
- `src/Services/SystemUserResolverService.php`
- `src/Services/WalletProvisioningService.php`
- `src/Traits/HasPlatformWallets.php`

### Existing tests and fixtures

- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/Models/User.php`
- `tests/Feature/UserWalletsTest.php`
- `tests/Unit/Actions/TopupWalletActionTest.php`
- `tests/Unit/Data/TransactionDataTest.php`
- `tests/Unit/Models/UserTest.php`
- `tests/Unit/Services/SystemUserResolverServiceTest.php`
- `tests/Unit/Services/WalletProvisioningServiceTest.php`
- `tests/database/migrations/0001_01_01_000000_create_users_table.php`

Installed sibling-package copies of Bavix Wallet, Laravel, Brick Money, and Pest source were inspected only to confirm relevant method signatures and test API shapes without installing dependencies into this repository. The wallet package's locked versions remain the intended runtime baseline.

## Existing test coverage found

- `SystemUserResolverServiceTest` covered successful resolution and a missing user, although the missing-user test was labeled as a non-Wallet case.
- `UserWalletsTest` covered creation for all enum cases, zero starting balances, and repeated-provisioning idempotency. Exact metadata was not asserted.
- `WalletProvisioningServiceTest` covered invocation of provisioning from the test user model's created hook.
- `TopupWalletActionTest` covered resolver/transfer interaction and sender/recipient balance effects, plus signed transfer amounts.
- `TransactionDataTest` asserted only that conversion returned a `TransactionData` instance.
- No package tests characterized `WithdrawCash`.
- No package tests characterized the four requested event contracts.

## Tests added

### `WithdrawCash`

Created `tests/Unit/Actions/WithdrawCashTest.php` with coverage for:

- full-balance withdrawal when amount is null;
- explicit minor-unit withdrawal and remaining balance;
- zero-balance rejection and current message;
- over-withdrawal rejection and current message;
- returned Bavix `Transaction`, signed amount, type, and confirmation;
- generated `type`, `operation_id`, `notes`, and `withdrawn_at` metadata;
- additional metadata and its precedence over generated keys.

### Events

Created `tests/Unit/Events/BalanceUpdatedTest.php` to protect:

- implemented event/broadcast interfaces;
- wallet ID, UUID, integer balance, float balance, and update time;
- `balance.updated` event name;
- private holder channel;
- exact broadcast payload keys and values.

Created `tests/Unit/Events/DisbursementEventsTest.php` to protect:

- `DepositConfirmed` and `DisbursementConfirmed` broadcast interfaces;
- public transaction property;
- event names, private holder channel, UUID, and signed PHP amount payload;
- `DisbursementFailed` trait set, direct public properties, constructor parameter order/types/nullability/default;
- the legacy `LBHurtado\Voucher\Models\Voucher` constructor dependency;
- current absence of `ShouldBroadcast` on `DisbursementFailed`.

## Tests strengthened

- `SystemUserResolverServiceTest`: separated invalid configured model, resolved non-Wallet model, and missing user into real distinct cases.
- `UserWalletsTest`: added exact per-wallet metadata and a stable enum definition matrix for slugs, labels, and metadata.
- `TopupWalletActionTest`: removed a conditional that could silently skip assertions and added Bavix transfer status plus paired confirmed withdraw/deposit transaction types.
- `TransactionDataTest`: added exact PHP currency/minor-unit mapping, unconfirmed flag preservation, nested payload extraction, ignored outer metadata, empty-payload default, and public DTO field shape.

## Existing behavior characterized

### System user resolution

The configured model class, identifier column, and identifier drive lookup. An invalid model is rejected before querying. A missing row and a resolved model that does not implement Bavix's `Wallet` interface both produce `SystemUserNotFoundException`.

### Wallet provisioning

The stable wallet types are platform, rewards, escrow, and commission. Each has a stable slug, label, and description metadata. Provisioning is repeatable without duplicate slugs, and new wallets begin at zero balance.

### Top-up

Top-up resolves the system user and delegates to Bavix `transferFloat`. The system balance decreases, the recipient balance increases, and Bavix creates a `transfer` with paired confirmed withdrawal/deposit transactions and signed amounts. No Allocation, Slice, or other Treasury state is introduced.

### Cash withdrawal

`WithdrawCash` reads current Bavix balance in minor units. Null amount drains the balance; an explicit amount leaves the remainder. Zero and excessive requests throw `InvalidArgumentException`. The action produces a confirmed Bavix withdrawal transaction with generated disbursement metadata, and caller-supplied metadata wins on duplicate keys. It does not express Treasury Release or Allocation behavior.

### Transaction DTO

`TransactionData` maps the signed Bavix minor-unit amount into `Brick\Money\Money` with PHP currency, preserves the confirmation flag, and extracts only `meta.payload`, defaulting to an empty array. Its public fields remain `amount`, `confirmed`, and `payload`; it exposes no Treasury allocation state.

### Events

`BalanceUpdated` exposes wallet identity/current balance/update time and broadcasts a four-field payload on a private holder channel. `DepositConfirmed` and `DisbursementConfirmed` expose their Bavix transaction and broadcast UUID plus the transaction's signed PHP amount on the same channel pattern.

`DisbursementFailed` is a dispatchable/serializable non-broadcast event with public `voucher`, `exception`, and nullable `mobile` constructor-promoted properties. The Voucher property remains directly typed to the x-change-owned Voucher model.

## Legacy boundary debt confirmed

The direct `LBHurtado\Voucher\Models\Voucher` dependency in `DisbursementFailed` remains unchanged and is now explicitly protected as the current compatibility surface. The Voucher package is not declared in this package's Composer requirements, so constructing the event depends on the consuming application making that class available.

This is not target ownership. Removal or replacement still requires **Treasury Boundary Debt Slice 1 — Decouple Wallet DisbursementFailed Event From Voucher Model**, including listener/serialization compatibility analysis.

## Architecture discrepancies

- The known direct Voucher model type contradicts the target package boundary and remains approved legacy debt.
- The direct Voucher type is not represented in `composer.json`, creating an implicit consumer-provided class dependency.
- No existing runtime behavior contradicted the Phase 0 wallet descriptions.

## Commands executed

- required architecture/source/test inspection with `sed -n`
- repository and file discovery with `find`, `rg`, and `git status --short`
- Composer script/runtime discovery from `composer.json` and `vendor/bin/pest`
- dependency API reference searches with `rg -n` against already installed sibling-package vendor sources
- PHP runtime/version inspection with `php -v`
- syntax validation for all test PHP files with `php -l`
- `composer validate --strict`
- whitespace validation with `git diff --check`
- changed-file review with `git diff --name-only` and `git status --short`
- targeted Phase 1 staging with `git add`
- staged whitespace and scope review with `git diff --cached --check`, `--stat`, `--name-only`, and `--numstat`

## Test and check results

- PHP syntax checks: **passed** for all test files.
- `git diff --check`: **passed**.
- Staged diff whitespace/scope review: **passed**; only the nine Phase 1 test/documentation files are staged.
- `composer validate --strict`: **failed strict validation** because `composer.lock` is not up to date with `composer.json`; `composer.json` itself is valid. The lock file was not changed.
- Focused Pest tests: **not run** because `vendor/bin/pest` is unavailable.
- Full Pest suite: **not run** for the same reason.
- Dependency installation: **not attempted**, per Phase 1 instructions.
- Production source changes: none.
- Migration changes: none.
- Public API/event signature changes: none.

## Remaining risks

- The new characterizations are not runtime-verified until the locked test dependencies are restored and Pest runs.
- Exact compatibility with installed Bavix/Laravel versions must be confirmed by that run; sibling vendor source was used only as a read-only signature reference.
- The out-of-date lock file makes the intended reproducible dependency set ambiguous and remains unresolved by instruction.
- `DisbursementFailed` still exposes the cross-package Voucher model and relies on an undeclared class dependency.
- Event listeners, queued serialization round trips, and broadcast transport integration are not characterized beyond the event methods/public signatures requested here.
- Current float-based top-up behavior is frozen as legacy behavior; precision policy for future Treasury contracts remains undecided.

## Recommended next slice

First restore dependencies without updating `composer.lock`, then run focused Phase 1 tests and the full package suite. Phase 1 should be marked green only after both pass.

After that verification, the recommended architecture slice is **Phase 2 — Planning-only Treasury DTOs and Contracts**, subject to explicit approval. Keep the Voucher boundary-debt slice separate because it may alter public event compatibility.
