# Zero_Promotions

Two more actions for Magento's cart price rules (Marketing > Cart Price
Rules > Actions > Apply), with everything else a rule has: conditions,
coupons, dates, customer groups, priorities, labels.

- **Every Nth item, the cheapest** (`zero_nth_cheapest`): of the cart's
  items the rule's action conditions match, every Nth unit gets Discount
  Amount % off, the cheapest units first. N is Discount Qty Step; Maximum
  Qty Discount is Applied To limits how many units. "3 for 2": 100, step 3.
  "Second at half price": 50, step 2.
- **Free gift** (`zero_gift`): while the rule applies, its **Gift SKUs**
  (simple or virtual products, comma-separated; a field under Discount
  Amount) are in the cart at 0, Discount Qty of each (1 if empty), marked
  "Gift: <rule label>" and kept apart from the same product bought. When the
  cart no longer qualifies, the gift goes. "A gift from $100": condition
  Subtotal ≥ 100, no coupon (a coupon is dropped from a cart it doesn't
  apply to, as Magento does).

The gift must be salable: a sold-out gift is not added (logged in
`system.log`). Gift lines take no other discounts.

Column `salesrule.zero_gift_skus`.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-promotions
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

## Tests

`Test/Unit/Model/NthCheapestTest.php`; `dev/tests/zero-e2e/tests/promotions.spec.ts`
(on a store with a free-gift rule: `ZERO_GIFT_NAME`, `ZERO_GIFT_TRIGGER`,
`ZERO_GIFT_TRIGGER_QTY`).

What changed in each release: [CHANGELOG.md](CHANGELOG.md).

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
