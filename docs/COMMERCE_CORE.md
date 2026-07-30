# AGCP Commerce Core Architecture

## Scope

Phase 4 adds a unified commerce domain for physical products, digital products and structured services. It is still a modular monolith, but module boundaries are designed for a future Product Service and Order Service extraction.

## Catalog

`catalog_items` represents the sellable concept. Every item has one or more `catalog_variants`; even a simple item receives a default variant so that pricing, stock and order snapshots use one consistent identifier.

Supported item types:

- `physical` — inventory may be tracked and reserved.
- `digital` — no inventory is required in this phase; delivery remains manual.
- `service` — accepts a JSON field schema and validates required customer inputs.

## Pricing

Price lists are tenant- and currency-scoped. A list may optionally target a customer segment. Pricing is resolved by active period, segment priority and quantity tier. Checkout resolves the price again rather than trusting the price stored in the browser or cart.

Money continues to use integer minor units.

## Inventory

Inventory is stored per location and variant:

`available = on_hand - reserved - safety_stock`

Checkout locks inventory rows, creates reservation records and increments `reserved`. Completion consumes reservations and decrements `on_hand`; cancellation releases them. Backorders are allowed only when explicitly enabled on the item.

## Checkout and ledger

A wallet checkout runs inside one database transaction:

1. Lock active cart.
2. Reload active variants and current prices.
3. Verify wallet currency and availability.
4. Create order and immutable item snapshots.
5. Reserve tracked inventory.
6. Debit the customer wallet liability account.
7. Credit the commerce revenue account.
8. Convert the cart, write order history, audit event and transactional outbox message.

The ledger service performs its own deterministic account locking and rejects negative customer balances.

## Order lifecycle

Supported transitions:

- `pending -> confirmed | canceled`
- `confirmed -> processing | canceled`
- `processing -> completed`

Cancellation releases inventory and posts a balanced wallet refund. Completion consumes active inventory reservations.

## APIs

Public:

- `GET /api/v1/catalog`
- `GET /api/v1/catalog/categories`
- `GET /api/v1/catalog/{slug}`

Customer:

- `GET /api/v1/cart`
- `POST /api/v1/cart/items`
- `PATCH|DELETE /api/v1/cart/items/{cartItem}`
- `POST /api/v1/checkout`
- `GET /api/v1/orders`
- `POST /api/v1/orders/{order}/cancel`

Administrator:

- Catalog and categories
- Pricing
- Inventory
- Order listing and controlled transitions

## Deferred to Phase 5

- Supplier adapters and service mapping
- Automated supplier submission
- Supplier health scoring and failover
- Supplier status synchronization
- Automatic supplier-failure refunds
