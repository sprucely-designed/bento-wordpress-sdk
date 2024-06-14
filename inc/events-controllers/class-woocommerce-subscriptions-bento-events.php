<?php
/**
 * WooCommerce Subscription - Bento Events Controller
 *
 * @package BentoHelper
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Subscriptions' ) && ! class_exists( 'WooCommerce_Subscriptions_Bento_Events', false ) ) {
	/**
	 * WooCommerce Subscriptions Events
	 */
	class WooCommerce_Subscriptions_Bento_Events extends WooCommerce_Bento_Events {

		/**
		 * Constructor.
		 *
		 * @return void
		 */
		public function __construct() {

			/**
			 * Triggered when a subscription is created after a subscription product or products are purchased.
			 *
			 * @param WC_Subscription $subscription An instance representing the subscription just created at checkout.
			 */
			add_action(
				'woocommerce_checkout_subscription_created',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionCreated',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered after the subscription status changed to activated.
			 *
			 * The status may have transitioned from pending to active, or on-hold to active or some other custom status to active.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that changed status.
			 */
			add_action(
				'woocommerce_subscription_status_active',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionActive',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered immediately when an active subscription is cancelled by a customer or admin and there is prepaid time left on the subscription.
			 *
			 * This is an intermediate step before the subscription status is changed to cancelled.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that changed status.
			 */
			add_action(
				'woocommerce_subscription_status_pending-cancel',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionPendingCancel',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a subscription status changes to cancelled.
			 *
			 * The status may have transitioned from on-hold to cancelled, or pending-cancel to cancelled, or active to cancelled, or some other custom status to cancelled.
			 * This is the final status change that occurs when a subscription is cancelled.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that changed status.
			 */
			add_action(
				'woocommerce_subscription_status_cancelled',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionCancelled',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a subscription status changes to expired.
			 *
			 * This could be the end of the original term length when it was purchased or if an end date was otherwise set.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that changed status.
			 */
			add_action(
				'woocommerce_subscription_status_expired',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionExpired',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a subscription is set on-hold (suspended or paused).
			 *
			 * This can occur directly if the an admin manually suspends the subscription or if the customer manually suspends their subscription from their my-account page.
			 * This can also occur automatically when a renewal payment is pending. For automatic renewal payments, the status will be on-hold until the payment is processed successfully. For manual renewal payments, the status will be on-hold until the customer manually logs in and pays for the renewal.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that changed status.
			 */
			add_action(
				'woocommerce_subscription_status_on-hold',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					self::send_event(
						$user_id,
						'$SubscriptionOnHold',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a subscription trial ends.
			 *
			 * @param integer WC_Subscription $subscription The ID of the subscription that has ended the trial period.
			 */
			add_action(
				'woocommerce_scheduled_subscription_trial_end',
				function ( $subscription_id ) {
					$subscription = wcs_get_subscription( $subscription_id );
					$user_id      = self::maybe_get_user_id_from_order( $subscription );
					$details      = self::prepare_subscription_event_details( $subscription, false );

					self::send_event(
						$user_id,
						'$SubscriptionTrialEnded',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered one time only when a subscription renewal payment should be processed.
			 *
			 * This can happen automatically when a subscription is due to renew or manually if the admin takes the action to process renewal payment from the subscription edit screen.
			 * This does not indicate the renewal payment has been processed successfully.
			 *
			 * @param integer WC_Subscription $subscription The ID of the subscription that has ended the trial period.
			 */
			add_action(
				'woocommerce_scheduled_subscription_payment',
				function ( $subscription_id ) {
					$subscription = wcs_get_subscription( $subscription_id );
					$user_id      = self::maybe_get_user_id_from_order( $subscription );
					$details      = self::prepare_subscription_event_details( $subscription, false );

					$order = $subscription->get_last_order( 'all' );

					if ( $order->get_total() > 0 ) {
						$details['value'] = array(
							'currency' => $subscription->get_currency(),
							'amount'   => $subscription->get_total(),
						);
					}

					self::send_event(
						$user_id,
						'$SubscriptionRenewed',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a renewal payment is processed on a subscription.
			 *
			 * This will occur for both manual and automatic renewal payments.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that was paid.
			 */
			add_action(
				'woocommerce_subscription_renewal_payment_complete',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, true );

					$order = $subscription->get_last_order( 'all' );

					// Ensure the order is valid and retrieve the total value.
					if ( $order ) {
						$total    = $order->get_total();
						$currency = $order->get_currency();

						$details['value'] = array(
							'currency' => $currency,
							'amount'   => $total,
						);
					}

					self::send_event(
						$user_id,
						'$SubscriptionRenewalPaymentComplete',
						$subscription->get_billing_email(),
						$details
					);
				}
			);

			/**
			 * Triggered when a payment fails on a subscription renewal.
			 *
			 * @param object WC_Subscription $subscription An object representing the subscription that failed payment.
			 */
			add_action(
				'woocommerce_subscription_renewal_payment_failed',
				function ( $subscription ) {
					$user_id = self::maybe_get_user_id_from_order( $subscription );
					$details = self::prepare_subscription_event_details( $subscription, false );

					$order = $subscription->get_last_order( 'all' );

					// Ensure the order is valid and retrieve the total value.
					if ( $order ) {
						$total    = $order->get_total();
						$currency = $order->get_currency();

						$details['value'] = array(
							'currency' => $currency,
							'amount'   => $total,
						);
					}

					self::send_event(
						$user_id,
						'$SubscriptionRenewalPaymentFailed',
						$subscription->get_billing_email(),
						$details
					);
				}
			);
		}

		/**
		 * Prepare the subscription details.
		 *
		 * @param WC_Subscription $subscription The subscription object.
		 * @param bool $include_unique Whether to include the unique key.
		 *
		 * @return array
		 */
		private static function prepare_subscription_event_details( $subscription, $include_unique = false ) {
			$order = $subscription->get_last_order( 'all' );

			$details = array(
				'subscription' => array(
					'id'     => $subscription->get_id(),
					'status' => $subscription->get_status(),
					'order'  => array(
						'items' => self::get_cart_items( $subscription ),
					),
				),
			);

			// Conditionally add the unique key if requested.
			if ( $include_unique && $order ) {
				$details['unique'] = array(
					'key' => $order->get_order_key(),
				);
			}

			return $details;
		}
	}

	new WooCommerce_Subscriptions_Bento_Events();
}
