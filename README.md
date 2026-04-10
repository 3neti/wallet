# 3neti/wallet

A lightweight helper package that extends and standardizes usage of **Bavix Laravel Wallet** within the x-change ecosystem.

---

## 🧭 Overview

`3neti/wallet` is **not a wallet implementation**.

Instead, it provides:

- Configuration standardization
- System user handling
- Integration glue for x-change services (voucher, cash, settlement)
- Test scaffolding for wallet-based flows

It builds on top of:

- `bavix/laravel-wallet`

---

## 🧠 Design Philosophy

> This package does NOT own the wallet schema.

All database tables (wallets, transactions, transfers) are owned by:

- `bavix/laravel-wallet`

This package focuses on:

- orchestration
- consistency
- extensibility

---

## 📦 Installation

```bash
composer require 3neti/wallet
```

---

## ⚙️ Configuration

Publish config files:

```bash
php artisan vendor:publish --tag=config
```

### Files

- `config/wallet.php`
- `config/account.php`

---

## 👤 System User

The package introduces a **system user abstraction**.

### Config

```php
'account' => [
    'system_user' => [
        'identifier' => env('SYSTEM_USER_ID', 'system@example.com'),
        'identifier_column' => 'email',
        'model' => App\Models\User::class,
    ],
]
```

### Purpose

- acts as source of funds
- supports top-ups, disbursements, internal transfers

---

## 🔄 Integration Role in x-change

```text
Voucher → Cash → Wallet → Settlement
```

Wallet is responsible for:

- balance tracking
- transfers
- transaction recording

---

## 🧪 Testing Strategy

This package:

- does NOT ship migrations
- uses **dependency migrations** from Bavix

### Test Setup

- loads:
  - test user migration
  - Bavix wallet migrations dynamically

### Key Principle

```text
Ownership != Dependency
```

- Bavix owns schema
- Wallet package depends on it

---

## 🏗️ Service Provider

```php
LBHurtado\Wallet\WalletServiceProvider
```

### Responsibilities

- merge configs
- register event provider
- expose configuration

---

## 🔌 Dependencies

- `bavix/laravel-wallet`
- `spatie/laravel-data`
- `lorisleiva/laravel-actions`
- `brick/money`

---

## ⚠️ Important Notes

### ❌ Do NOT:

- copy Bavix migrations into this package
- publish Bavix migrations from here
- override wallet tables

### ✅ DO:

- treat wallet as infrastructure
- extend behavior via services/actions
- keep this package stateless

---

## 🧠 Architectural Role

This package is part of the **financial core layer**:

```text
[Contact] → [Voucher] → [Cash] → [Wallet] → [Settlement]
```

Wallet sits at:

👉 **value storage + transfer layer**

---

## 🚀 Future Extensions

- multi-account support
- audit enhancements
- event-driven hooks
- reconciliation support

---

## 🧠 Final Thought

> This package does not implement wallets.  
> It ensures wallets behave correctly within x-change.

---

## License

MIT
