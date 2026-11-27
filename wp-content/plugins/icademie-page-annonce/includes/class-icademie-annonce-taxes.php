<?php
/**
 * Prix TTC et détail de la TVA : module de réservation de la fiche, panier et validation de la
 * commande (Opquast 56).
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les prix des annonces sont saisis toutes taxes comprises (CGV, article 3) et les taxes de
 * WooCommerce ne sont pas activées : WooCommerce n'affiche donc ni la mention TTC, ni la TVA, ni le
 * montant hors taxes. Cette classe les affiche, d'après un taux de TVA unique (20 % par défaut,
 * filtre « icademie_annonce_taux_tva ») appliqué au total :
 * - module de réservation de la fiche : « Total TTC », « dont TVA (20 %) », « Total HT » ;
 * - blocs Panier et Validation de la commande : libellé « Total TTC » et deux lignes sous le total
 *   (emplacement prévu par WooCommerce pour les extensions) ;
 * - panier et commande classiques (codes courts) : mêmes lignes sous le total.
 *
 * Si les taxes de WooCommerce sont activées (WooCommerce › Réglages › Général), WooCommerce affiche
 * lui-même la TVA du panier et de la commande : ces lignes n'y sont plus ajoutées.
 */
final class Icademie_Annonce_Taxes {

	/** Taux de TVA par défaut (20 %). */
	const VAT_RATE = 0.20;

	/** Poignée du script des blocs Panier et Validation de la commande. */
	const HANDLE = 'icademie-page-annonce-taxes';

	/**
	 * Branche l'affichage.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'classic_rows' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( __CLASS__, 'classic_rows' ) );
	}

	/**
	 * Taux de TVA.
	 *
	 * @return float 0,2 pour 20 %.
	 */
	public static function vat_rate() {
		/**
		 * Taux de TVA compris dans les prix des locations (0,2 : 20 %).
		 *
		 * @param float $rate Taux.
		 */
		return max( 0, (float) apply_filters( 'icademie_annonce_taux_tva', self::VAT_RATE ) );
	}

	/**
	 * Taux lisible : « 20 % ».
	 *
	 * @return string
	 */
	public static function rate_text() {
		$percent = round( self::vat_rate() * 100, 1 );

		/* translators: %s: taux (« 20 », « 5,5 »). */
		return sprintf( __( '%s %%', 'icademie-page-annonce' ), number_format_i18n( $percent, (float) (int) $percent === $percent ? 0 : 1 ) );
	}

	/**
	 * Libellés communs au serveur et aux scripts.
	 *
	 * @return string[] « total », « tax », « net ».
	 */
	public static function labels() {
		return array(
			'total' => __( 'Total TTC', 'icademie-page-annonce' ),
			/* translators: %s: taux de TVA (« 20 % »). */
			'tax'   => sprintf( __( 'dont TVA (%s)', 'icademie-page-annonce' ), self::rate_text() ),
			'net'   => __( 'Total HT', 'icademie-page-annonce' ),
		);
	}

	/**
	 * Montant hors taxes et TVA d'un montant TTC, arrondis comme les prix de la boutique.
	 *
	 * @param float $total Montant TTC.
	 * @return float[] « net » (HT) et « tax » (TVA) ; net + tax = total.
	 */
	public static function split( $total ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$net      = round( (float) $total / ( 1 + self::vat_rate() ), $decimals );

		return array(
			'net' => $net,
			'tax' => round( (float) $total - $net, $decimals ),
		);
	}

	/**
	 * WooCommerce affiche-t-il déjà ses taxes ?
	 *
	 * @return bool
	 */
	private static function woocommerce_taxes() {
		return function_exists( 'wc_tax_enabled' ) && wc_tax_enabled();
	}

	/**
	 * Script des blocs Panier et Validation de la commande.
	 */
	public static function enqueue() {
		if ( self::woocommerce_taxes() || ! ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) ) ) {
			return;
		}

		if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/taxes-panier.js', Icademie_Page_Annonce::FILE ),
			array( 'wc-blocks-checkout', 'wc-price-format', 'wp-element', 'wp-plugins' ),
			Icademie_Page_Annonce::VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'icademieAnnonceTaxes',
			array(
				'rate'   => self::vat_rate(),
				'labels' => self::labels(),
			)
		);
	}

	/**
	 * Panier et commande classiques : lignes sous le total.
	 */
	public static function classic_rows() {
		if ( self::woocommerce_taxes() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$amounts = self::split( (float) WC()->cart->get_total( 'edit' ) );
		$labels  = self::labels();

		foreach ( array( 'tax', 'net' ) as $key ) {
			printf(
				'<tr class="icademie-annonce-taxes icademie-annonce-taxes--%1$s"><th>%2$s</th><td data-title="%3$s">%4$s</td></tr>',
				esc_attr( $key ),
				esc_html( $labels[ $key ] ),
				esc_attr( $labels[ $key ] ),
				wp_kses_post( wc_price( $amounts[ $key ] ) )
			);
		}
	}
}
