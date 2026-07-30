# Araabi Global Commerce Platform — Phase 4

AGCP Phase 4 adds the tenant-aware Commerce Core on top of the verified Identity and Enterprise Wallet foundations.

## Capabilities

- Physical products, digital products and structured services
- Categories and variants
- Currency price lists and quantity tiers
- Inventory locations, safety stock and atomic reservations
- Customer cart
- Wallet-funded, idempotent checkout
- Immutable order-item snapshots and status history
- Cancellation refund and inventory release
- Order completion and inventory consumption
- Customer catalog, cart and order pages
- Commerce administration and demonstration catalog

## Upgrade an existing Phase 3 project

Extract the Phase 4 upgrade patch and copy all files into `C:\Projects\agcp`, choosing **Replace All**. Keep the existing `.env`.

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase4.ps1
```

Verify:

```powershell
.\scripts\verify-phase4.ps1
```

## URLs

- Home: `http://localhost:8080`
- Catalog: `http://localhost:8080/catalog`
- Cart: `http://localhost:8080/cart`
- Orders: `http://localhost:8080/orders`
- Wallet: `http://localhost:8080/wallet`
- Commerce administration: `http://localhost:8080/admin/commerce`
- API health: `http://localhost:8080/api/v1/health`

## Test flow

1. Sign in as the administrator and confirm 2FA.
2. Open Commerce administration and create or inspect an item.
3. Use Wallet administration to approve a customer deposit.
4. Sign in as that customer, add an item to the cart and pay from the wallet.
5. Process and complete the order from Commerce administration.

Supplier API automation is intentionally reserved for Phase 5. See `docs/COMMERCE_CORE.md` and `docs/PHASE_4_REPORT.md`.
