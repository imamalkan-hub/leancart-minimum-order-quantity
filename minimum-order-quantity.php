<?php
/**
 * Plugin Name: LeanCart Minimum Order Quantity
 * Plugin URI:  https://theradonlab.com/
 * Description: Set a minimum quantity per product. Customers cannot create a cart below that quantity; the warning is shown at the checkout step.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: minimum-order-quantity
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_MINIMUM_ORDER_QUANTITY_DEMO_SITE' ) ) {
	define( 'LEANCART_MINIMUM_ORDER_QUANTITY_DEMO_SITE', 'https://theradonlab.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Minimum_Order_Quantity {

	const SLUG = 'minimum-order-quantity';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Minimum Order Quantity',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_MINIMUM_ORDER_QUANTITY_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'minimum-order-quantity', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Minimum Order Quantity' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'minimum-order-quantity' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'default', __('Default minimum', 'minimum-order-quantity'), __('Used if the product has no custom value.', 'minimum-order-quantity'), 'number' );
		LeanCart_Framework::field_text( 'message', __('Warning message', 'minimum-order-quantity'), __('E.g.: You can order at least %d units.', 'minimum-order-quantity'), 'text' );
	}


	public static function hooks() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'show' ) );
	}

	public static function field() {
		echo '<div class="options_group">';
		woocommerce_wp_text_input( array(
			'id'                => '_lc_moq',
			'label'             => __( 'Minimum order quantity', 'minimum-order-quantity' ),
			'type'              => 'number',
			'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
		) );
		echo '</div>';
	}

	public static function save( $id ) {
		if ( ! current_user_can( 'edit_product', $id ) ) { return; }
		if ( isset( $_POST['woocommerce_meta_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) { return; }
		if ( isset( $_POST['_lc_moq'] ) ) { update_post_meta( $id, '_lc_moq', absint( wp_unslash( $_POST['_lc_moq'] ) ) ); }
	}

	private static function moq( $id ) {
		$v = (int) get_post_meta( $id, '_lc_moq', true );
		return $v > 0 ? $v : max( 1, (int) LeanCart_Framework::setting( 'default', 1 ) );
	}

	public static function validate() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) { return; }
		$totals = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$totals[ $pid ] = ( isset( $totals[ $pid ] ) ? $totals[ $pid ] : 0 ) + (int) $item['quantity'];
		}
		$msg = LeanCart_Framework::setting( 'message' );
		if ( ! $msg ) { $msg = __( 'You can order at least %d units.', 'minimum-order-quantity' ); }
		foreach ( $totals as $pid => $qty ) {
			$moq = self::moq( $pid );
			if ( $qty < $moq ) {
				wc_add_notice( esc_html( str_replace( '%d', (string) $moq, $msg ) ), 'error' );
			}
		}
	}

	public static function show() {
		global $product;
		if ( ! $product ) { return; }
		$moq = self::moq( $product->get_id() );
		if ( $moq > 1 ) {
			echo '<p class="lc-moq">' . esc_html( sprintf( __( 'Minimum order: %d units', 'minimum-order-quantity' ), $moq ) ) . '</p>';
		}
	}


}

LeanCart_Minimum_Order_Quantity::init();

