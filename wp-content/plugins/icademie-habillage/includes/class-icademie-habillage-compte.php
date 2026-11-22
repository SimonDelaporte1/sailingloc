<?php
/**
 * Compte client : libellés du menu adaptés à la location, colonne « Bateau et dates » de
 * « Mes locations », bandeau d'assistance (charte : présent dans l'espace « Mes locations »).
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compte client.
 */
final class Icademie_Habillage_Compte {

	/** Colonne ajoutée au tableau des commandes. */
	const COLUMN = 'icademie-location';

	/**
	 * Branche les filtres du compte.
	 */
	public static function init() {
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 20 );
		add_filter( 'woocommerce_endpoint_orders_title', array( __CLASS__, 'orders_title' ) );
		add_filter( 'woocommerce_endpoint_view-order_title', array( __CLASS__, 'order_title' ) );
		add_filter( 'woocommerce_account_orders_columns', array( __CLASS__, 'orders_columns' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-number', array( __CLASS__, 'order_number_column' ) );
		add_action( 'woocommerce_my_account_my_orders_column_' . self::COLUMN, array( __CLASS__, 'orders_column' ) );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'address_fields' ) );
		add_action( 'woocommerce_before_account_orders', array( __CLASS__, 'orders_assistance' ) );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'order_assistance' ), 5 );
	}

	/**
	 * Menu du compte : « Mes locations », « Proposer mon bateau », « Espace propriétaire » ; sans
	 * « Téléchargements » (aucune location ne se télécharge).
	 *
	 * @param string[] $items Entrées, par point d'accès.
	 * @return string[]
	 */
	public static function menu_items( $items ) {
		$labels = array(
			'orders'           => __( 'Mes locations', 'icademie-habillage' ),
			'become-a-vendor'  => __( 'Proposer mon bateau', 'icademie-habillage' ),
			'vendor-dashboard' => __( 'Espace propriétaire', 'icademie-habillage' ),
		);

		foreach ( $labels as $key => $label ) {
			if ( isset( $items[ $key ] ) ) {
				$items[ $key ] = $label;
			}
		}

		unset( $items['downloads'] );

		/**
		 * Entrées du menu du compte client, après l'habillage SailingLoc.
		 *
		 * @param string[] $items Libellés, par point d'accès.
		 */
		return (array) apply_filters( 'icademie_habillage_menu_compte', $items );
	}

	/**
	 * Titre de la liste des commandes.
	 *
	 * @return string
	 */
	public static function orders_title() {
		return __( 'Mes locations', 'icademie-habillage' );
	}

	/**
	 * Titre d'une commande : « Location n°123 ».
	 *
	 * @param string $title Titre de WooCommerce.
	 * @return string
	 */
	public static function order_title( $title ) {
		global $wp;

		$order = isset( $wp->query_vars['view-order'] ) ? wc_get_order( absint( $wp->query_vars['view-order'] ) ) : null;

		/* translators: %s: numéro de la commande. */
		return $order ? sprintf( __( 'Location n°%s', 'icademie-habillage' ), $order->get_order_number() ) : $title;
	}

	/**
	 * Colonnes de « Mes locations » : « Location » (numéro), puis « Bateau et dates ».
	 *
	 * @param string[] $columns Colonnes.
	 * @return string[]
	 */
	public static function orders_columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'order-number' === $key ) {
				$out[ $key ]         = __( 'Location', 'icademie-habillage' );
				$out[ self::COLUMN ] = __( 'Bateau et dates', 'icademie-habillage' );
			}
		}

		return isset( $out[ self::COLUMN ] ) ? $out : array_merge( array( self::COLUMN => __( 'Bateau et dates', 'icademie-habillage' ) ), $columns );
	}

	/**
	 * Numéro de la location, lien vers la location. Le nom du lien reprend le texte affiché
	 * (WCAG 2.5.3) : WooCommerce nomme « Voir la commande numéro 123 » un lien qui affiche « n°123 ».
	 *
	 * @param WC_Order $order Commande.
	 */
	public static function order_number_column( $order ) {
		echo '<a href="' . esc_url( $order->get_view_order_url() ) . '"><span class="icademie-habillage__sr">' . esc_html__( 'Voir la location', 'icademie-habillage' ) . ' </span>' . esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() ) . '</a>';
	}

	/**
	 * Complément d'adresse du compte : libellé visible au-dessus du champ, comme les autres champs
	 * (charte ; WooCommerce le réserve aux lecteurs d'écran et l'écrit dans le champ).
	 *
	 * @param array $fields Champs de l'adresse.
	 * @return array
	 */
	public static function address_fields( $fields ) {
		if ( isset( $fields['address_2'] ) && function_exists( 'is_account_page' ) && is_account_page() ) {
			$fields['address_2']['label_class'] = array_values( array_diff( (array) ( $fields['address_2']['label_class'] ?? array() ), array( 'screen-reader-text' ) ) );
			$fields['address_2']['placeholder'] = '';
		}

		return $fields;
	}

	/**
	 * Bateaux d'une commande et dates de location.
	 *
	 * @param WC_Order $order Commande.
	 */
	public static function orders_column( $order ) {
		foreach ( self::rentals( $order ) as $rental ) {
			echo '<span class="icademie-habillage__location">';

			if ( '' !== $rental['url'] ) {
				echo '<a class="icademie-habillage__location-bateau" href="' . esc_url( $rental['url'] ) . '">' . esc_html( $rental['name'] ) . '</a>';
			} else {
				echo '<span class="icademie-habillage__location-bateau">' . esc_html( $rental['name'] ) . '</span>';
			}

			if ( '' !== $rental['dates'] ) {
				echo '<span class="icademie-habillage__location-dates">' . esc_html( $rental['dates'] ) . '</span>';
			}

			echo '</span>';
		}
	}

	/**
	 * Locations d'une commande : bateau, adresse, dates lisibles, fin de la location.
	 *
	 * @param WC_Order $order Commande.
	 * @return array[]
	 */
	public static function rentals( $order ) {
		$rentals = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			$start   = (string) $item->get_meta( '_icademie_date_debut' );
			$end     = (string) $item->get_meta( '_icademie_date_fin' );

			$rentals[] = array(
				'name'  => $item->get_name(),
				'url'   => $product && $product->is_visible() ? (string) $product->get_permalink() : '',
				'dates' => self::dates_text( $start, $end ),
				'end'   => $end,
			);
		}

		return $rentals;
	}

	/**
	 * « Du 17 déc. au 18 déc. 2026 », « Le 17 déc. 2026 ».
	 *
	 * @param string $start Début (AAAA-MM-JJ).
	 * @param string $end   Fin (AAAA-MM-JJ).
	 * @return string
	 */
	public static function dates_text( $start, $end ) {
		$format = static function ( $date, $pattern ) {
			$value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

			return $value ? wp_date( $pattern, $value->getTimestamp() ) : '';
		};

		if ( '' === $start ) {
			return '';
		}

		if ( '' === $end || $end === $start ) {
			/* translators: %s: date. */
			return sprintf( __( 'Le %s', 'icademie-habillage' ), $format( $start, 'j M Y' ) );
		}

		$same_year = substr( $start, 0, 4 ) === substr( $end, 0, 4 );

		/* translators: 1: début, 2: fin. */
		return sprintf( __( 'Du %1$s au %2$s', 'icademie-habillage' ), $format( $start, $same_year ? 'j M' : 'j M Y' ), $format( $end, 'j M Y' ) );
	}

	/**
	 * Bandeau d'assistance au-dessus de « Mes locations » (version courte, sans bouton).
	 *
	 * @param bool $has_orders Des commandes ?
	 */
	public static function orders_assistance( $has_orders ) {
		if ( $has_orders ) {
			echo Icademie_Habillage_Blocks::render_assistance( array( 'court' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gabarit échappé.
		}
	}

	/**
	 * Bandeau d'assistance sur une location en cours ou à venir.
	 *
	 * @param int $order_id Commande.
	 */
	public static function order_assistance( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->has_status( array( 'processing', 'on-hold' ) ) ) {
			return;
		}

		$today = current_datetime()->format( 'Y-m-d' );

		foreach ( self::rentals( $order ) as $rental ) {
			if ( '' === $rental['end'] || $rental['end'] >= $today ) {
				echo Icademie_Habillage_Blocks::render_assistance(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Gabarit échappé.
				return;
			}
		}
	}
}
