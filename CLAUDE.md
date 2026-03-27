# CLAUDE.md

## Package
`zislogic/ebay-mip` — eBay MIP (Merchant Integration Platform) CSV feed integration for Laravel 12.
Provides order import from MIP SFTP and fulfillment export back to eBay via CSV feeds.

Depends on `zislogic/ebay-connector` for OAuth tokens and credential management.

## Tech Stack
- PHP 8.2+, Laravel 12
- phpseclib v3 for SFTP (pure PHP, no system extensions)
- Orchestra Testbench for testing
- PHPStan level 8

## Namespace
`Zislogic\Ebay\Mip\`

## Commands
- `composer test` — run PHPUnit
- `composer analyse` — run PHPStan level 8
- `php artisan ebay:mip-import-orders` — download and import orders from MIP SFTP
- `php artisan ebay:mip-export-fulfillment` — upload fulfillment CSV to MIP SFTP

## Architecture

### Data Flow
```
eBay MIP SFTP                    Database                      Plugin (e.g., towbars)
/store/order/order-latest/  →  mip_orders + mip_order_lines  →  Reads unfulfilled orders
/store/order-fulfillment/   ←  mip_order_lines (shipped)     ←  Sets tracking info
```

### SFTP Connection
- Host: `mip.ebay.com` (sandbox: `mip.sandbox.ebay.com`)
- Port: 22
- Username: `EbayCredential::$ebay_user_id` (eBay account name)
- Password: OAuth access token from `EbayTokenManager`

### CSV Column Mapping
- Defined in `config/ebay-mip.php` under `column_map`
- Maps CSV header names → DB column names
- Unmapped CSV columns automatically stored in `meta` JSON column
- Update config (not PHP code) if eBay renames columns

### Database Strategy
- Essential/queryable fields → proper DB columns
- Rarely used fields (~80 of 104 CSV columns) → `meta` JSON column
- Order-level vs line-level fields split between two tables

## Key Files
```
src/
├── EbayMipServiceProvider.php
├── Sftp/
│   └── MipSftpClient.php              # Wraps phpseclib3\Net\SFTP
├── Csv/
│   ├── CsvReader.php                   # CSV string → array of assoc arrays
│   └── CsvWriter.php                  # Array → CSV string
├── Models/
│   ├── MipOrder.php                    # Order header (belongs to EbayCredential)
│   └── MipOrderLine.php               # Line items (fulfillment tracking)
├── Services/
│   ├── OrderImportService.php          # SFTP download → parse → upsert to DB
│   └── FulfillmentExportService.php    # DB → CSV → SFTP upload
├── Commands/
│   ├── ImportOrdersCommand.php         # artisan ebay:mip-import-orders
│   └── ExportFulfillmentCommand.php    # artisan ebay:mip-export-fulfillment
└── Exceptions/
    └── MipException.php
```

## Database Tables
- `mip_orders` — order header with ship-to address, buyer info, totals + meta JSON
- `mip_order_lines` — line items with SKU, quantity, price, fulfillment tracking + meta JSON

## Code Style
- `declare(strict_types=1)` everywhere
- `final` classes by default
- `readonly` properties on value objects
- Return type declarations on all methods
- No `mixed` types unless absolutely unavoidable

## Important Rules
- NEVER store access tokens — always get fresh from EbayTokenManager
- NEVER overwrite fulfillment data during import (tracking, carrier, shipped_at)
- Column mapping lives in config — update config, not PHP, when CSV structure changes
- All SFTP errors throw MipException with descriptive static factories

## Dependency Role
```
zislogic/ebay-connector (base package)
    ↑
    └── zislogic/ebay-mip (this package)
            ↑
            ├── towbars-plugin (future)
            └── other-plugin (future)
```
