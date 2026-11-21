<?php
/**
 * Moteur de recherche du bandeau de la page d'accueil.
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recherche de bateaux : destination, dates, type de bateau, passagers.
 *
 * Correspondance avec les données de WooCommerce :
 * - destination : catégorie de produits (une catégorie parente inclut ses sous-catégories) ;
 * - type de bateau : attribut « Type de bateau » (pa_type-de-bateau) ;
 * - passagers : attribut « Passagers » (pa_passagers), au moins le nombre demandé (2 passagers :
 *   bateaux qui ont la valeur 2, 3… ; un bateau pour 1 à 6 passagers a les valeurs 1 à 6) ;
 * - départ et retour : l'attribut « Date » sert seulement à afficher les sélecteurs de date sur la
 *   fiche du bateau (extension « Icademie – Dates de début et de fin ») ; aucune disponibilité n'est
 *   enregistrée, il n'y a donc rien à filtrer. Les dates choisies sont reprises sur la fiche du
 *   bateau pour la réservation.
 *
 * Le formulaire est envoyé à la boutique ; la recherche redirige ensuite vers l'adresse standard de
 * WooCommerce : la page de la catégorie (ou la boutique) avec les filtres d'attributs
 * « filter_… », ceux des blocs de filtres de WooCommerce. Les dates restent dans l'adresse.
 */
final class Icademie_Page_Accueil_Search {

	/** Paramètre qui signale une recherche envoyée depuis l'accueil. */
	const MARKER = 'recherche';

	/** Attributs utilisés (slug sans « pa_ »). */
	const TYPE_ATTRIBUTE       = Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE;
	const PASSENGERS_ATTRIBUTE = Icademie_Page_Accueil_Zones::PASSENGERS_ATTRIBUTE;

	/** Nombre de passagers maximal accepté. */
	const PASSENGERS_LIMIT = 99;

	/**
	 * Branche la recherche sur WordPress.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_date_script' ) );
	}

	/**
	 * Données du formulaire : adresse d'envoi et choix des listes.
	 *
	 * @return array
	 */
	public static function form_data() {
		return array(
			'action'         => wc_get_page_permalink( 'shop' ),
			'marker'         => self::MARKER,
			'destinations'   => self::destination_options(),
			'types'          => self::type_options(),
			'has_passengers' => taxonomy_exists( wc_attribute_taxonomy_name( self::PASSENGERS_ATTRIBUTE ) ),
			'passengers_max' => self::max_passengers(),
			'today'          => current_datetime()->format( 'Y-m-d' ),
		);
	}

	/**
	 * Destinations proposées : catégories qui ont des bateaux (ou des sous-catégories qui en ont),
	 * dans l'ordre des catégories, les sous-catégories sous leur parente.
	 *
	 * @return array[] Options : slug, nom, profondeur (0 pour une catégorie principale).
	 */
	private static function destination_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$children = array();

		foreach ( $terms as $term ) {
			$children[ (int) $term->parent ][] = $term;
		}

		$options = array();
		$known   = wp_list_pluck( $terms, 'term_id' );

		// Catégories principales, plus les catégories dont la parente n'est pas proposée.
		foreach ( $terms as $term ) {
			if ( 0 === (int) $term->parent || ! in_array( (int) $term->parent, $known, true ) ) {
				self::add_destination( $options, $term, $children, 0 );
			}
		}

		return $options;
	}

	/**
	 * Ajoute une destination et ses sous-catégories à la liste.
	 *
	 * @param array   $options  Liste en cours.
	 * @param WP_Term $term     Catégorie.
	 * @param array   $children Sous-catégories, par ID de la parente.
	 * @param int     $depth    Profondeur.
	 */
	private static function add_destination( &$options, $term, $children, $depth ) {
		$options[] = array(
			'value' => $term->slug,
			'label' => $term->name,
			'depth' => $depth,
		);

		if ( $depth < 5 && isset( $children[ (int) $term->term_id ] ) ) {
			foreach ( $children[ (int) $term->term_id ] as $child ) {
				self::add_destination( $options, $child, $children, $depth + 1 );
			}
		}
	}

	/**
	 * Types de bateau proposés : toutes les valeurs de l'attribut, dans son ordre.
	 *
	 * @return array[] Options : slug et nom.
	 */
	private static function type_options() {
		$taxonomy = wc_attribute_taxonomy_name( self::TYPE_ATTRIBUTE );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			static function ( $term ) {
				return array(
					'value' => $term->slug,
					'label' => $term->name,
				);
			},
			$terms
		);
	}

	/**
	 * Valeurs de l'attribut « Passagers », lues comme des nombres.
	 *
	 * « 10+ », « > 10 » ou « plus de 10 » sont ouvertes : elles conviennent à toute demande.
	 *
	 * @return array[] Par slug : nombre et « ouverte ».
	 */
	private static function passenger_values() {
		$taxonomy = wc_attribute_taxonomy_name( self::PASSENGERS_ATTRIBUTE );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$values = array();

		foreach ( $terms as $term ) {
			$name = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );

			if ( ! preg_match( '/\d+/', $name, $number ) ) {
				continue;
			}

			$values[ $term->slug ] = array(
				'number' => (int) $number[0],
				'open'   => (bool) preg_match( '/^\s*(>|plus de)|\+\s*$/iu', $name ),
			);
		}

		return $values;
	}

	/**
	 * Plus grande valeur de l'attribut « Passagers » (maximum du champ « Passagers »).
	 *
	 * @return int 0 s'il n'y a pas de valeur, ou si une valeur est ouverte (« 10+ »).
	 */
	private static function max_passengers() {
		$max = 0;

		foreach ( self::passenger_values() as $value ) {
			if ( $value['open'] ) {
				return 0;
			}

			$max = max( $max, $value['number'] );
		}

		return $max;
	}

	/**
	 * Valeurs de l'attribut qui conviennent à un nombre de passagers (ce nombre ou plus).
	 *
	 * @param int $passengers Nombre de passagers demandé.
	 * @return string[] Slugs.
	 */
	public static function passenger_slugs( $passengers ) {
		$slugs = array();

		foreach ( self::passenger_values() as $slug => $value ) {
			if ( $value['open'] || $value['number'] >= $passengers ) {
				$slugs[] = (string) $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Adresse de la liste des bateaux d'un type (vignettes « Catégories de bateaux »).
	 *
	 * @param WP_Term $term Valeur de l'attribut « Type de bateau ».
	 * @return string
	 */
	public static function type_url( $term ) {
		return self::results_url( array( 'type' => $term->slug ) );
	}

	/**
	 * Reçoit une recherche envoyée depuis l'accueil et redirige vers les résultats.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Recherche publique en GET, sans effet sur le site.
		if ( ! isset( $_GET[ self::MARKER ] ) || '1' !== $_GET[ self::MARKER ] || ! function_exists( 'wc_get_page_permalink' ) ) {
			return;
		}

		$request = wp_unslash( $_GET );
		// phpcs:enable

		wp_safe_redirect( self::results_url( is_array( $request ) ? $request : array() ), 302, 'Icademie - Page d\'accueil' );
		exit;
	}

	/**
	 * Adresse des résultats d'une recherche.
	 *
	 * @param array $request Champs envoyés : destination, depart, retour, type, passagers.
	 * @return string
	 */
	public static function results_url( $request ) {
		$base = wc_get_page_permalink( 'shop' );
		$args = array();

		// Destination : page de la catégorie.
		$destination = isset( $request['destination'] ) && is_scalar( $request['destination'] ) ? sanitize_title( (string) $request['destination'] ) : '';

		if ( '' !== $destination ) {
			$term = get_term_by( 'slug', $destination, 'product_cat' );
			$link = $term ? get_term_link( $term ) : '';

			if ( $link && ! is_wp_error( $link ) ) {
				$base = $link;
			}
		}

		// Type de bateau : filtre de l'attribut, comme les blocs de filtres de WooCommerce.
		$type          = isset( $request['type'] ) && is_scalar( $request['type'] ) ? sanitize_title( (string) $request['type'] ) : '';
		$type_taxonomy = wc_attribute_taxonomy_name( self::TYPE_ATTRIBUTE );

		if ( '' !== $type && taxonomy_exists( $type_taxonomy ) && term_exists( $type, $type_taxonomy ) ) {
			$args[ 'filter_' . self::TYPE_ATTRIBUTE ] = $type;
		}

		// Passagers : toutes les valeurs de l'attribut égales ou supérieures.
		$passengers = isset( $request['passagers'] ) && is_scalar( $request['passagers'] ) ? absint( $request['passagers'] ) : 0;

		if ( $passengers > 0 && taxonomy_exists( wc_attribute_taxonomy_name( self::PASSENGERS_ATTRIBUTE ) ) ) {
			$slugs = self::passenger_slugs( min( $passengers, self::PASSENGERS_LIMIT ) );

			// Aucune valeur assez grande : un filtre sur le nombre demandé, qui ne trouve aucun bateau.
			$args[ 'filter_' . self::PASSENGERS_ATTRIBUTE ] = $slugs ? implode( ',', $slugs ) : (string) $passengers;

			if ( count( $slugs ) > 1 ) {
				$args[ 'query_type_' . self::PASSENGERS_ATTRIBUTE ] = 'or';
			}
		}

		// Dates : gardées dans l'adresse, reprises ensuite sur la fiche du bateau.
		$dates = self::dates( $request );

		if ( '' !== $dates['depart'] ) {
			$args['depart'] = $dates['depart'];
		}

		if ( '' !== $dates['retour'] ) {
			$args['retour'] = $dates['retour'];
		}

		// Valeurs encodées ; les virgules restent lisibles (séparateur des filtres de WooCommerce).
		$args = array_map(
			static function ( $value ) {
				return str_replace( '%2C', ',', rawurlencode( $value ) );
			},
			$args
		);

		return add_query_arg( $args, $base );
	}

	/**
	 * Dates de départ et de retour valides : AAAA-MM-JJ, départ à partir d'aujourd'hui, retour à
	 * partir du départ. Une date invalide est ignorée.
	 *
	 * @param array $request Champs envoyés.
	 * @return string[] « depart » et « retour » (vides si absentes ou invalides).
	 */
	public static function dates( $request ) {
		$today  = current_datetime()->format( 'Y-m-d' );
		$depart = self::clean_date( isset( $request['depart'] ) ? $request['depart'] : '' );
		$retour = self::clean_date( isset( $request['retour'] ) ? $request['retour'] : '' );

		// Au format AAAA-MM-JJ, l'ordre alphabétique est l'ordre chronologique.
		if ( '' !== $depart && $depart < $today ) {
			$depart = '';
		}

		if ( '' !== $retour && ( $retour < $today || ( '' !== $depart && $retour < $depart ) ) ) {
			$retour = '';
		}

		return array(
			'depart' => $depart,
			'retour' => $retour,
		);
	}

	/**
	 * Date au format AAAA-MM-JJ, ou chaîne vide.
	 *
	 * @param mixed $value Valeur envoyée.
	 * @return string
	 */
	private static function clean_date( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		// Rejette les dates impossibles (2026-02-30) que PHP corrigerait en silence.
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Script qui reprend les dates de la recherche sur la fiche du bateau.
	 *
	 * Chargé sur la boutique, les catégories et les fiches produit, si l'extension des dates est
	 * active. Il garde les dates de l'adresse des résultats, puis remplit les champs « Date de
	 * début » et « Date de fin » de la fiche s'ils sont vides.
	 */
	public static function enqueue_date_script() {
		if ( ! class_exists( 'Icademie_Dates_Produit' ) || ! function_exists( 'is_product' ) ) {
			return;
		}

		if ( ! is_product() && ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}

		wp_enqueue_script(
			'icademie-page-accueil-dates',
			plugins_url( 'assets/reprise-dates.js', Icademie_Page_Accueil::FILE ),
			array(),
			Icademie_Page_Accueil::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
