<?php
/**
 * Réservation d'un bateau : disponibilités, passagers, frais de service (encarts 5 et 9).
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réservation depuis le module de la fiche annonce.
 *
 * Les dates sont celles de l'extension « Icademie – Dates de début et de fin » (mêmes champs,
 * même calcul : prix par jour × nombre de jours, début et fin compris). Cette classe ajoute :
 * - les jours déjà réservés : dates des commandes du bateau en cours, terminées ou en attente de
 *   paiement par virement (en cours, terminée, en attente) ; une réservation qui chevauche ces jours,
 *   ou un autre bateau identique du panier, est refusée à l'ajout au panier et à la commande ;
 * - le nombre de passagers, gardé dans le panier et la commande ;
 * - les frais de service : 10 % du montant de la location, ajoutés au panier ;
 * - une réservation par bateau et par période (le nombre ne se choisit pas), et le passage direct
 *   à la commande après « Demander à réserver ».
 */
final class Icademie_Annonce_Reservation {

	/** Champ du nombre de passagers. */
	const FIELD_PASSENGERS = 'icademie_passagers';

	/** Champ caché du module de réservation (passage direct à la commande). */
	const MARKER = 'icademie_reserver';

	/** Clé du nombre de passagers dans un article du panier. */
	const CART_PASSENGERS = 'icademie_passagers';

	/** Taux des frais de service. */
	const FEE_RATE = 0.10;

	/** Option : version des réservations (change à chaque commande modifiée, vide les caches). */
	const VERSION_OPTION = 'icademie_annonce_reservations_version';

	/**
	 * Branche la réservation sur WooCommerce.
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 20, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 20, 3 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'add_service_fee' ) );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_to_checkout' ), 20 );
		add_filter( 'woocommerce_is_sold_individually', array( __CLASS__, 'sold_individually' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );

		// Réservations modifiées : caches des jours réservés à recalculer.
		foreach ( array( 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_order_status_changed', 'woocommerce_delete_order', 'woocommerce_trash_order', 'woocommerce_untrash_order' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'bump_version' ) );
		}
	}

	/**
	 * Le bateau se réserve-t-il par dates (extension des dates) ?
	 *
	 * @param WC_Product|int $product Produit.
	 * @return bool
	 */
	public static function is_rental( $product ) {
		return class_exists( 'Icademie_Dates_Produit' ) && Icademie_Dates_Produit::requires_dates( $product );
	}

	/**
	 * État des commandes qui bloquent des jours.
	 *
	 * @return string[]
	 */
	public static function blocking_statuses() {
		/**
		 * États des commandes dont les dates rendent le bateau indisponible.
		 *
		 * @param string[] $statuses États, sans « wc- ».
		 */
		return (array) apply_filters( 'icademie_annonce_etats_reserves', array( 'processing', 'completed', 'on-hold' ) );
	}

	/**
	 * Nouvelle version des réservations : les jours réservés seront relus.
	 */
	public static function bump_version() {
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * Périodes réservées d'un bateau, d'après ses commandes.
	 *
	 * @param int $product_id Bateau.
	 * @return array[] Périodes « debut » et « fin » (AAAA-MM-JJ), triées, qui se chevauchent fusionnées.
	 */
	public static function booked_periods( $product_id ) {
		$product_id = (int) $product_id;
		$key        = 'icademie_reserve_' . $product_id . '_' . md5( (string) get_option( self::VERSION_OPTION, '0' ) . implode( ',', self::blocking_statuses() ) );
		$periods    = get_transient( $key );

		if ( is_array( $periods ) ) {
			return $periods;
		}

		global $wpdb;

		// Lignes de commande de ce bateau (tables des lignes communes aux deux stockages des commandes).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lecture des lignes de commande, mise en cache.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT items.order_item_id, items.order_id
				FROM {$wpdb->prefix}woocommerce_order_items AS items
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product ON product.order_item_id = items.order_item_id AND product.meta_key = '_product_id' AND product.meta_value = %d
				WHERE items.order_item_type = 'line_item'",
				$product_id
			)
		);

		$periods  = array();
		$statuses = self::blocking_statuses();

		foreach ( (array) $rows as $row ) {
			$order = wc_get_order( (int) $row->order_id );

			if ( ! $order || ! $order->has_status( $statuses ) ) {
				continue;
			}

			$start = self::clean_date( wc_get_order_item_meta( (int) $row->order_item_id, '_icademie_date_debut', true ) );
			$end   = self::clean_date( wc_get_order_item_meta( (int) $row->order_item_id, '_icademie_date_fin', true ) );

			if ( '' !== $start && '' !== $end && $end >= $start ) {
				$periods[] = array(
					'debut' => $start,
					'fin'   => $end,
				);
			}
		}

		$periods = self::merge( $periods );

		set_transient( $key, $periods, DAY_IN_SECONDS );

		return $periods;
	}

	/**
	 * Fusionne des périodes qui se chevauchent ou se suivent.
	 *
	 * @param array[] $periods Périodes.
	 * @return array[]
	 */
	private static function merge( $periods ) {
		usort(
			$periods,
			static function ( $a, $b ) {
				return strcmp( $a['debut'], $b['debut'] );
			}
		);

		$merged = array();

		foreach ( $periods as $period ) {
			$last = count( $merged ) - 1;

			if ( $last >= 0 && $period['debut'] <= self::next_day( $merged[ $last ]['fin'] ) ) {
				if ( $period['fin'] > $merged[ $last ]['fin'] ) {
					$merged[ $last ]['fin'] = $period['fin'];
				}
			} else {
				$merged[] = $period;
			}
		}

		return $merged;
	}

	/**
	 * Jours réservés, un par un, à partir d'une date (pour le calendrier).
	 *
	 * @param int    $product_id Bateau.
	 * @param string $from       Premier jour (AAAA-MM-JJ).
	 * @return string[]
	 */
	public static function booked_days( $product_id, $from ) {
		$days = array();

		foreach ( self::booked_periods( $product_id ) as $period ) {
			if ( $period['fin'] < $from ) {
				continue;
			}

			$day = max( $period['debut'], $from );

			// Au format AAAA-MM-JJ, l'ordre alphabétique est l'ordre chronologique.
			while ( $day <= $period['fin'] ) {
				$days[] = $day;
				$day    = self::next_day( $day );
			}
		}

		return array_values( array_unique( $days ) );
	}

	/**
	 * Une période chevauche-t-elle des jours réservés ?
	 *
	 * @param int    $product_id Bateau.
	 * @param string $start      Départ.
	 * @param string $end        Retour.
	 * @return bool
	 */
	public static function is_available( $product_id, $start, $end ) {
		foreach ( self::booked_periods( $product_id ) as $period ) {
			if ( $start <= $period['fin'] && $end >= $period['debut'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Lendemain d'une date.
	 *
	 * @param string $date AAAA-MM-JJ.
	 * @return string
	 */
	private static function next_day( $date ) {
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );

		return $day ? $day->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
	}

	/**
	 * Date AAAA-MM-JJ valide, ou vide.
	 *
	 * @param mixed $value Valeur.
	 * @return string
	 */
	public static function clean_date( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Nombre de passagers maximal d'un bateau (attribut « Passagers »).
	 *
	 * @param WC_Product $product Bateau.
	 * @return int 0 si l'attribut n'est pas renseigné.
	 */
	public static function max_passengers( $product ) {
		$attribute = class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::PASSENGERS_ATTRIBUTE : 'passagers';
		$max       = class_exists( 'Icademie_Page_Categorie_View' ) ? Icademie_Page_Categorie_View::attribute_max( $product, $attribute ) : null;

		return $max ? (int) floor( $max['number'] ) : 0;
	}

	/**
	 * Valeur envoyée d'un champ du formulaire d'ajout au panier.
	 *
	 * @param string $field Nom du champ.
	 * @return string
	 */
	private static function posted( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Formulaire d'ajout au panier de WooCommerce, sans jeton.
		return isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
	}

	/**
	 * Refuse une réservation sur des jours déjà réservés, ou un nombre de passagers impossible.
	 *
	 * Après le contrôle des dates de l'extension des dates (dates présentes et dans l'ordre).
	 *
	 * @param bool $passed     Contrôles précédents.
	 * @param int  $product_id Bateau.
	 * @return bool
	 */
	public static function validate( $passed, $product_id ) {
		if ( ! $passed || ! self::is_rental( $product_id ) ) {
			return $passed;
		}

		$start = self::clean_date( self::posted( 'icademie_date_debut' ) );
		$end   = self::clean_date( self::posted( 'icademie_date_fin' ) );

		if ( '' !== $start && '' !== $end && ! self::is_available( $product_id, $start, $end ) ) {
			wc_add_notice( __( 'Ce bateau est déjà réservé sur une partie de ces dates. Choisissez d\'autres dates dans le calendrier des disponibilités.', 'icademie-page-annonce' ), 'error' );
			return false;
		}

		if ( '' !== $start && '' !== $end && self::overlaps_cart( $product_id, $start, $end ) ) {
			wc_add_notice( __( 'Votre panier contient déjà une réservation de ce bateau sur une partie de ces dates.', 'icademie-page-annonce' ), 'error' );
			return false;
		}

		$passengers = self::posted( self::FIELD_PASSENGERS );

		if ( '' !== $passengers ) {
			$product = wc_get_product( $product_id );
			$max     = $product ? self::max_passengers( $product ) : 0;
			$number  = ctype_digit( $passengers ) ? (int) $passengers : 0;

			if ( $number < 1 || ( $max && $number > $max ) ) {
				/* translators: %d: nombre de passagers maximal. */
				wc_add_notice( $max ? sprintf( __( 'Choisissez de 1 à %d passagers.', 'icademie-page-annonce' ), $max ) : __( 'Choisissez un nombre de passagers.', 'icademie-page-annonce' ), 'error' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Le panier contient-il déjà ce bateau sur des jours communs ?
	 *
	 * @param int    $product_id Bateau.
	 * @param string $start      Départ.
	 * @param string $end        Retour.
	 * @return bool
	 */
	private static function overlaps_cart( $product_id, $start, $end ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] !== (int) $product_id || empty( $item['icademie_dates'] ) ) {
				continue;
			}

			if ( $start <= $item['icademie_dates']['fin'] && $end >= $item['icademie_dates']['debut'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Garde le nombre de passagers dans l'article du panier.
	 *
	 * @param array $cart_item_data Données de l'article.
	 * @param int   $product_id     Bateau.
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id ) {
		$passengers = self::posted( self::FIELD_PASSENGERS );

		if ( self::is_rental( $product_id ) && '' !== $passengers && ctype_digit( $passengers ) && (int) $passengers > 0 ) {
			$cart_item_data[ self::CART_PASSENGERS ] = (int) $passengers;
		}

		return $cart_item_data;
	}

	/**
	 * Nombre de passagers affiché dans le panier et la commande.
	 *
	 * @param array[] $item_data Lignes affichées.
	 * @param array   $cart_item Article.
	 * @return array[]
	 */
	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item[ self::CART_PASSENGERS ] ) ) {
			$item_data[] = array(
				'key'   => __( 'Passagers', 'icademie-page-annonce' ),
				'value' => number_format_i18n( (int) $cart_item[ self::CART_PASSENGERS ] ),
			);
		}

		return $item_data;
	}

	/**
	 * Copie le nombre de passagers sur la ligne de commande.
	 *
	 * @param WC_Order_Item_Product $item          Ligne de commande.
	 * @param string                $cart_item_key Clé de l'article.
	 * @param array                 $values        Article du panier.
	 */
	public static function add_order_item_meta( $item, $cart_item_key, $values ) {
		if ( ! empty( $values[ self::CART_PASSENGERS ] ) ) {
			$item->add_meta_data( __( 'Passagers', 'icademie-page-annonce' ), (int) $values[ self::CART_PASSENGERS ], true );
		}
	}

	/**
	 * Taux des frais de service.
	 *
	 * @return float
	 */
	public static function fee_rate() {
		/**
		 * Taux des frais de service ajoutés aux locations (0,10 : 10 %).
		 *
		 * @param float $rate Taux.
		 */
		return max( 0, (float) apply_filters( 'icademie_annonce_taux_frais_service', self::FEE_RATE ) );
	}

	/**
	 * Frais de service : 10 % du montant des locations du panier.
	 *
	 * @param WC_Cart $cart Panier.
	 */
	public static function add_service_fee( $cart ) {
		$base = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['icademie_dates'] ) && isset( $item['line_subtotal'] ) ) {
				$base += (float) $item['line_subtotal'];
			}
		}

		$fee = round( $base * self::fee_rate(), wc_get_price_decimals() );

		if ( $fee > 0 ) {
			/* translators: %s: taux des frais (« 10 »). */
			$cart->add_fee( sprintf( __( 'Frais de service (%s %%)', 'icademie-page-annonce' ), number_format_i18n( self::fee_rate() * 100 ) ), $fee, false );
		}
	}

	/**
	 * Après « Demander à réserver » : directement à la commande.
	 *
	 * @param string $url Adresse prévue par WooCommerce.
	 * @return string
	 */
	public static function redirect_to_checkout( $url ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Formulaire d'ajout au panier, sans jeton.
		if ( ! empty( $_POST[ self::MARKER ] ) && 0 === wc_notice_count( 'error' ) ) {
			return wc_get_checkout_url();
		}

		return $url;
	}

	/**
	 * Une location se réserve à l'unité : pas de choix de quantité.
	 *
	 * @param bool       $individually Valeur prévue.
	 * @param WC_Product $product      Produit.
	 * @return bool
	 */
	public static function sold_individually( $individually, $product ) {
		return $individually || self::is_rental( $product );
	}

	/**
	 * À la commande : refuse les dates réservées entre-temps par un autre locataire.
	 *
	 * @param array    $data   Données de la commande.
	 * @param WP_Error $errors Erreurs.
	 */
	public static function validate_checkout( $data, $errors ) {
		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['icademie_dates'] ) ) {
				continue;
			}

			if ( ! self::is_available( (int) $item['product_id'], $item['icademie_dates']['debut'], $item['icademie_dates']['fin'] ) ) {
				$errors->add(
					'icademie_indisponible',
					sprintf(
						/* translators: %s: nom du bateau. */
						__( '« %s » vient d\'être réservé sur une partie de vos dates. Retirez-le du panier et choisissez d\'autres dates.', 'icademie-page-annonce' ),
						$item['data'] instanceof WC_Product ? $item['data']->get_name() : ''
					)
				);
			}
		}
	}
}
