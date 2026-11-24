=== Coverwick - Square Meter Calculator and Samples for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, square meters, flooring, samples, calculator
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell boards, panels and tiles by area: linked pieces and square metre inputs, price per square metre and paid samples.

== Description ==

Coverwick is for WooCommerce shops that sell boards, panels or tiles by the piece while customers think in square metres.

= Area calculator on the product page =

* Set the area one piece covers on the product (General tab), or per variation. A variation without its own value uses the product value.
* The product page gets an area field linked to the quantity field: type the square metres and the number of pieces is calculated, rounded up to a whole piece. Change the pieces and the area follows.
* On a variable product the area field stays empty until the shopper picks a variation, then uses that variation's coverage. An area the shopper typed is kept and sized again when the variation changes.
* The price per area unit is shown under the price, following the shop's tax display setting. Variable products show the lowest one ("from").
* The area unit is a setting (default m2).

= Cart, checkout and orders =

* The covered area is shown in the cart and at checkout, both on the classic cart and checkout pages and in the Cart and Checkout blocks.
* The area is always derived from the quantity, so changing the quantity in the cart updates it.
* Order items store the area and show it in emails, My Account and the order screen.

= Price per square metre for filters =

* The lowest price per area unit of each product is saved in the `_coverage_price_per_m2` product meta (dot decimal). It is updated when a product or variation is saved, deleted, trashed or restored, and when a scheduled sale starts or ends, so catalogue filters can read it.

= Paid samples =

* Switch samples on per product. The product page gets an "Order a sample" button, also when the product is out of stock.
* Each sample is a separate cart line named after the product (and variant), with its own price: a default price in the settings, overridable per product. The button shows the price with or without tax, as the shop displays prices.
* Set a maximum number of samples per order and a shipping class for sample lines.
* Samples are sold through one hidden internal product, so the stock and sales figures of the real product stay untouched. The internal product cannot be added to the cart directly and is kept out of the shop pages, search, the product RSS feed, the WordPress sitemap and the public REST product listings.

= For developers =

* PHP: the `coverage/booted` action fires after the plugin has booted.
* JavaScript: the calculator box dispatches a bubbling `coverage:change` event with `{ area, pieces, perUnit, price, productId, variationId }`.

== Coverwick Pro ==

Coverwick Pro is a separate paid plugin that extends Coverwick.

Included in Coverwick:

* Square metres to pieces and back
* Rounded up
* Coverage per variation
* Price per m2
* Area in cart and orders

Coverwick Pro adds:

* Complete the set box
* Quantities in packs
* Live recalculation
* The whole set in one click

The free edition is not a trial and its features stay available without a time limit.

* **Coverwick Pro** - [plogins.com/coverwick-pro/](https://plogins.com/coverwick-pro/)
* **Compare editions and pricing** - [plogins.com/coverwick-pro/pricing/](https://plogins.com/coverwick-pro/pricing/)
* **PRO documentation** - [plogins.com/coverwick-pro/docs/](https://plogins.com/coverwick-pro/docs/)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/coverwick`, or install it from Plugins, Add New.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce, Coverwick, to set the area unit and the sample options.
4. On each product, fill in "Coverage per unit" in the General tab (or per variation) and tick "Sample available" where you offer samples.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes.

= Where do I set how much one board covers? =

On the product edit screen, General tab, "Coverage per unit". Variations can set their own value; empty uses the product value. Leave it empty to turn the calculator off for that product.

= How is the number of pieces rounded? =

Always up to a whole piece. 7.6 m2 with 0.76 m2 per board gives exactly 10 boards; 7.7 m2 gives 11.

= What is the product called "Sample (internal product used by Coverwick, do not delete)"? =

The hidden product every sample line is sold as. If it is deleted, it is created again the next time a shopper orders a sample. Past orders keep their sample lines.

= Does it work with the Cart and Checkout blocks? =

Yes. The area and the sample lines show in both, and sample quantities cannot be changed in the block cart.

== Changelog ==

= 0.1.2 =
* Docs: the readme describes Coverwick Pro and links its pricing page.

= 0.1.1 =
* Changed: the plugin is now called Coverwick. Text domain, main file and folder are `coverwick`. Settings, product and order data, hooks (`coverage/booted`, `coverage:change`) and meta keys (`_coverage_*`) are unchanged, so an existing install keeps its data. A plugin folder named `coverage` must be replaced by `coverwick` and activated again.

= 0.1.0 =
* Initial release: area calculator linked to the quantity, price per area unit, area in cart, checkout and orders, `_coverage_price_per_m2` product meta, paid samples.
