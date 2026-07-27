/**
 * Prevent Paid Stripe Orders From Being Marked Failed
 *
 * Stops WooCommerce orders that have already been paid
 * from being automatically changed to Failed.
 *
 * UPDATED: Added a transient-based guard to prevent duplicate
 * "Completed order" emails from being sent when the status
 * revert (Failed -> previous status) triggers WooCommerce's
 * own status-changed emails multiple times in a row.
 */
add_action( 'woocommerce_order_status_changed', function( $order_id, $from, $to, $order ) {

    // Only intercept transitions to Failed.
    if ( 'failed' !== $to ) {
        return;
    }

    // Extra safety.
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // If the order has already been paid, restore the previous status.
    if ( $order->is_paid() || $order->get_date_paid() ) {

        // Guard: avoid reverting (and re-emailing) the same order
        // repeatedly if something keeps trying to mark it Failed.
        $guard_key = 'blocked_failed_revert_' . $order_id;

        if ( get_transient( $guard_key ) ) {
            // We already reverted this order recently — skip to avoid
            // re-triggering WooCommerce's status emails again.
            $order->add_order_note(
                sprintf(
                    'Repeated attempt to mark order Failed was blocked (already reverted within the last 60 seconds). Attempted transition: %s -> %s.',
                    $from,
                    $to
                )
            );
            return;
        }

        // Set the guard before reverting, so the resulting
        // status-changed event doesn't loop back into this function
        // or cause duplicate emails if fired again in quick succession.
        set_transient( $guard_key, 1, 60 ); // 60 second window

        $order->set_status(
            $from,
            'Automatic Failed status was blocked because the order had already been paid.'
        );
        $order->save();
    }

}, 1, 4 );
