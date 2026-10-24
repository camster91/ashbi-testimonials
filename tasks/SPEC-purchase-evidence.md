# Order-backed purchase evidence

A public badge says “Verified purchase” only when a server-recorded order belongs to the authenticated submitter, contains the reviewed product with an unrefunded quantity, and remains paid. The existing manual Verified flag remains separate and cannot manufacture purchase evidence. A claimed email, CSV import, manager checkbox or client-supplied order ID is never proof.

Authenticated JavaScript obtains a per-user, product-bound WordPress nonce from a same-origin, no-cache AJAX endpoint immediately before submission. The server requires that nonce and an account-email match before looking through at most 50 of that user's most recent paid orders. Guests and non-JavaScript submissions remain valid reviews without an order-backed badge. Exhausting the bound fails closed; it does not launch an unbounded order scan. Store only private order, customer, product and line-item IDs against the review; exclude them from public output and portable CSV. Revalidate against WooCommerce CRUD APIs on display, including identity/product edits and refunds/deletion. WordPress privacy export includes the private evidence; erasing the review deletes it.

Tests use fictional orders in disposable sites and intercept outgoing mail. Exercise HPOS on and off. No real customer mail or order changes are authorized.

Official API references: https://developer.woocommerce.com/docs/features/orders/wc-get-orders/ and https://woocommerce.github.io/code-reference/classes/WC-Order.html . WooCommerce recommends CRUD/query APIs instead of direct order-table SQL and verification under both HPOS and legacy storage.
