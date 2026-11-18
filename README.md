# Coverage Calculator

WooCommerce plugin for products sold by the piece but bought by area (boards, panels, tiles).

- Coverage per unit on the product (General tab) and per variation (`_coverage_m2_per_unit`).
- Product page: area input linked to the quantity, hint line, price per area unit under the price.
- Cart, checkout (classic and Blocks) and order items show the covered area (`_coverage_m2` order item meta).
- `_coverage_price_per_m2` on the parent product: lowest price per area unit, kept current on product,
  variation (incl. delete, trash, restore) and scheduled sale changes. External filters read this key
  (sieve-pro); do not rename it.
- Paid samples as separate cart lines, built on one hidden internal product. The button sits after each
  product type's add-to-cart template (`woocommerce_{type}_add_to_cart`, priority 35), so it shows on
  out-of-stock products too. The internal product is excluded from every non-admin `WP_Query` for
  products (shop, search, feeds, core sitemap, `/wp/v2/product`, Store API listing) and from the Yoast
  sitemap; other SEO plugins' sitemaps are not handled.

## Develop

```
composer install
composer analyse   # PHPStan
composer cs        # PHPCS
composer test      # PHPUnit, pure math
```

Integration checks run inside wp-env: `wp eval-file tests/integration/cart-check.php` (see the file header).

## Extension points

- PHP: `coverage/booted` action (the PRO add-on boots here).
- JS: the calculator box dispatches a bubbling `coverage:change` event with
  `{ area, pieces, perUnit, price, productId, variationId }`.

## Changelog

- 0.1.0: initial version (not released yet).
