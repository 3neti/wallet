# Changelog

All notable changes to `3neti/wallet` are documented in this file.

## [Unreleased]

### Release gate

- Validate clean consumers for `3neti/cash`, `3neti/emi-netbank`,
  `3neti/voucher`, and `3neti/x-change`.
- Create `v2.0.0-beta.1` only after every consumer passes.

## [2.0.0-beta.1] - Unreleased

### Added

- Provider-neutral Treasury Inventory and Position contracts, DTOs, models,
  read models, and durable runtimes.
- Package-owned migrations for settlement resources, Inventory, Inventory
  operations, Positions, and Position operations.
- Append-only, idempotent recognition, adjustment, reservation, allocation,
  release, reversal, commercial charge, and derecognition operations.
- Provider-connection portfolio reads and Inventory-to-Position attribution.
- Account Funding Reserve and commercial waterfall Position purposes.
- Federated system-principal resolution with fail-closed candidate agreement.
- Recursive sensitive Treasury metadata sanitization.

### Changed

- `SystemUserResolverService` and `TopupWalletAction` are container-resolved
  services with constructor dependencies.
- The package now owns durable Treasury accounting schema in addition to its
  Bavix integration layer.
- Supported platforms are PHP 8.3–8.4 and Laravel 12–13.

### Security

- Treasury read models omit internal Bavix ledger identifiers.
- Conflicting operation replays fail closed.
- Configured sensitive metadata is redacted before durable storage.

### Upgrade note

This release intentionally uses a 2.x boundary. Consumers that directly
constructed resolver services or actions must use Laravel container resolution
before upgrading.
