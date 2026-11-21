<?php
/**
 * Filtres, tri et adresse des résultats de la page catégorie.
 *
 * @package Icademie_Page_Categorie
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filtres de la liste des bateaux (zone 5 des zonings), tri et filtres actifs (zone 6).
 *
 * Les choix sont gardés dans l'adresse de la page, au format standard de WooCommerce : celui de la
 * recherche de la page d'accueil et des blocs de filtres de WooCommerce.
 * - Attributs : « filter_{attribut}=slug1,slug2 », avec « query_type_{attribut}=or » quand un seul
 *   des choix suffit. Chaque filtre correspond à un attribut des produits.
 * - Prix par jour : « min_price » et « max_price ».
 * - Tri : « orderby ».
 * WooCommerce applique lui-même ces paramètres à la liste des produits. Seule la note minimale
 * (« note_min ») est propre à cette extension : bateaux dont la moyenne des avis atteint la note.
 *
 * Le formulaire des filtres envoie des champs simples (cases à cocher, curseurs, compteur) avec
 * « filtrer=1 » : la page les convertit au format de WooCommerce et redirige vers l'adresse
 * obtenue. Le moteur de recherche (celui de la page d'accueil) passe par la même étape pour garder
 * les filtres déjà choisis.
 */
final class Icademie_Page_Categorie_Filters {

	/** Paramètre qui signale l'envoi du formulaire des filtres. */
	const MARKER = 'filtrer';

	/** Paramètre ajouté au moteur de recherche de la page catégorie (en plus de « recherche »). */
	const SEARCH_MARKER = 'depuis-categorie';

	/** Paramètre de la note minimale. */
	const RATING_PARAM = 'note_min';

	/** Groupe du cache des données calculées. */
	const CACHE_GROUP = 'icademie_page_categorie';

	/** Nombre maximal accepté pour le compteur (passagers). */
	const STEPPER_LIMIT = 99;

	/**
	 * Branche les formulaires et la note minimale sur WordPress.
	 */
	public static function init() {
		// Avant la redirection de l'accueil (priorité 5) et celle des adresses canoniques (10).
		add_action( 'template_redirect', array( __CLASS__, 'handle_forms' ), 4 );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'apply_rating_filter' ) );
	}

	/**
	 * Filtres proposés, dans l'ordre du zoning.
	 *
	 * « control » donne le type de champ et la façon de filtrer :
	 * - « price » : prix par jour, curseur du prix maximum et champs minimum / maximum ;
	 * - « checkboxes » : cases à cocher, « query_type » « or » (un des choix suffit) ou « and »
	 *   (tous les choix) ;
	 * - « range » : curseur double (minimum et maximum) sur les valeurs, rangées par nombre ;
	 * - « stepper » : compteur, bateaux qui acceptent ce nombre ou plus ;
	 * - « minimum » : pastilles, ce nombre ou plus (« 4+ » compris) ;
	 * - « choice » : boutons radio, un seul choix ou « Indifférent » ;
	 * - « rating » : pastilles de la note minimale.
	 *
	 * La clé sert de nom aux champs du formulaire. Un filtre d'attribut n'est affiché que si des
	 * bateaux de la catégorie ont une valeur pour cet attribut.
	 *
	 * @return array[]
	 */
	public static function definitions() {
		$type_attribute       = class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE : 'type-de-bateau';
		$passengers_attribute = class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::PASSENGERS_ATTRIBUTE : 'passagers';

		$definitions = array(
			'prix'        => array(
				'control' => 'price',
				'label'   => __( 'Prix par jour', 'icademie-page-categorie' ),
			),
			'type'        => array(
				'control'    => 'checkboxes',
				'attribute'  => $type_attribute,
				'label'      => __( 'Type de bateau', 'icademie-page-categorie' ),
				'query_type' => 'or',
			),
			'longueur'    => array(
				'control'   => 'range',
				'attribute' => 'longueur-du-bateau',
				'label'     => __( 'Longueur', 'icademie-page-categorie' ),
				'unit'      => 'm',
			),
			'passagers'   => array(
				'control'   => 'stepper',
				'attribute' => $passengers_attribute,
				'label'     => __( 'Passagers', 'icademie-page-categorie' ),
			),
			'cabines'     => array(
				'control'   => 'minimum',
				'attribute' => 'cabine',
				'label'     => __( 'Cabines', 'icademie-page-categorie' ),
			),
			'skipper'     => array(
				'control'   => 'choice',
				'attribute' => 'skipper',
				'label'     => __( 'Skipper', 'icademie-page-categorie' ),
			),
			'permis'      => array(
				'control'    => 'checkboxes',
				'attribute'  => 'permis-bateau',
				'label'      => __( 'Permis bateau', 'icademie-page-categorie' ),
				'query_type' => 'or',
			),
			'equipements' => array(
				'control'    => 'checkboxes',
				'attribute'  => 'equipements',
				'label'      => __( 'Équipements', 'icademie-page-categorie' ),
				'query_type' => 'and',
				// Au-delà, les valeurs sont dans « Voir plus ».
				'visible'    => 6,
			),
			'note'        => array(
				'control' => 'rating',
				'label'   => __( 'Note minimale', 'icademie-page-categorie' ),
				'choices' => array( '3', '4', '4.5' ),
			),
		);

		/**
		 * Filtres de la page catégorie.
		 *
		 * @param array[] $definitions Filtres, par nom de champ (voir la description de la méthode).
		 */
		return (array) apply_filters( 'icademie_page_categorie_filtres', $definitions );
	}

	/**
	 * Filtre qui utilise un attribut.
	 *
	 * @param string $attribute Slug de l'attribut, sans « pa_ ».
	 * @return array|null Définition (avec sa clé dans « key »), ou null.
	 */
	public static function definition_for( $attribute ) {
		foreach ( self::definitions() as $key => $definition ) {
			if ( isset( $definition['attribute'] ) && $attribute === $definition['attribute'] ) {
				return array( 'key' => $key ) + $definition;
			}
		}

		return null;
	}

	/**
	 * Valeurs d'un attribut, dans l'ordre choisi pour l'attribut (Produits › Attributs).
	 *
	 * Le nom est lu comme un nombre quand il en contient un (« 12 », « 4+ », « >15 ») : « 4+ » et
	 * « >15 » sont ouvertes (ce nombre ou plus).
	 *
	 * @param string $attribute Slug de l'attribut, sans « pa_ ».
	 * @return array[] Par slug : slug, nom, nombre (ou null), ouverte.
	 */
	public static function values( $attribute ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute );
		$key      = 'valeurs:' . $taxonomy . ':' . wp_cache_get_last_changed( 'terms' );
		$values   = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false !== $values ) {
			return $values;
		}

		$values = array();

		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
				$name   = trim( wp_strip_all_tags( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) );
				$number = null;
				$open   = false;

				if ( preg_match( '/\d+(?:[.,]\d+)?/', $name, $match ) ) {
					$number = (float) str_replace( ',', '.', $match[0] );
					$open   = (bool) preg_match( '/^\s*(>|≥|plus de)|\+\s*$/iu', $name );
				}

				$values[ (string) $term->slug ] = array(
					'slug'   => (string) $term->slug,
					'name'   => $name,
					'number' => $number,
					'open'   => $open,
				);
			}
		}

		wp_cache_set( $key, $values, self::CACHE_GROUP );

		return $values;
	}

	/**
	 * Valeurs chiffrées d'un attribut, de la plus petite à la plus grande (« >15 » après « 15 »).
	 *
	 * @param string $attribute Slug de l'attribut, sans « pa_ ».
	 * @return array[] Liste de valeurs (voir values()).
	 */
	public static function scale( $attribute ) {
		$values = array_filter(
			self::values( $attribute ),
			static function ( $value ) {
				return null !== $value['number'];
			}
		);

		uasort(
			$values,
			static function ( $a, $b ) {
				return array( $a['number'], (int) $a['open'] ) <=> array( $b['number'], (int) $b['open'] );
			}
		);

		return array_values( $values );
	}

	/**
	 * Valeurs qui conviennent à un minimum : ce nombre ou plus, et les valeurs ouvertes (« 4+ »).
	 *
	 * Même règle que le champ « Passagers » de la recherche de l'accueil.
	 *
	 * @param string    $attribute Slug de l'attribut, sans « pa_ ».
	 * @param int|float $minimum   Nombre demandé.
	 * @return string[] Slugs.
	 */
	public static function slugs_from( $attribute, $minimum ) {
		$slugs = array();

		foreach ( self::scale( $attribute ) as $value ) {
			if ( $value['open'] || $value['number'] >= $minimum ) {
				$slugs[] = $value['slug'];
			}
		}

		return $slugs;
	}

	/**
	 * Plus petit nombre parmi des valeurs choisies (minimum d'un filtre « ce nombre ou plus »).
	 *
	 * Un slug inconnu mais chiffré compte aussi : la recherche de l'accueil met le nombre demandé
	 * quand aucune valeur n'est assez grande (aucun bateau trouvé, mais le compteur le garde).
	 *
	 * @param string   $attribute Slug de l'attribut, sans « pa_ ».
	 * @param string[] $slugs     Valeurs choisies.
	 * @return float|null
	 */
	public static function minimum_of( $attribute, $slugs ) {
		$values  = self::values( $attribute );
		$minimum = null;

		foreach ( $slugs as $slug ) {
			if ( isset( $values[ $slug ] ) ) {
				$number = $values[ $slug ]['number'];
			} else {
				$number = is_numeric( $slug ) ? (float) $slug : null;
			}

			if ( null !== $number && ( null === $minimum || $number < $minimum ) ) {
				$minimum = $number;
			}
		}

		return $minimum;
	}

	/**
	 * Filtres choisis dans l'adresse de la page, au format de WooCommerce.
	 *
	 * Seuls les paramètres connus sont repris, nettoyés : filtres d'attributs, prix, note, dates de la
	 * recherche et tri. L'ordre est stable : il sert aux adresses des pastilles et des pages.
	 *
	 * @return string[] Paramètres, par nom.
	 */
	public static function current_params() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filtres publics en GET, sans effet sur le site.
		$params = array();

		if ( class_exists( 'WC_Query' ) ) {
			foreach ( WC_Query::get_layered_nav_chosen_attributes() as $taxonomy => $data ) {
				$attribute = wc_attribute_taxonomy_slug( $taxonomy );

				$params[ 'filter_' . $attribute ] = implode( ',', $data['terms'] );

				if ( 'or' === $data['query_type'] && count( $data['terms'] ) > 1 ) {
					$params[ 'query_type_' . $attribute ] = 'or';
				}
			}
		}

		foreach ( array( 'min_price', 'max_price' ) as $key ) {
			$value = isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? self::price_value( wp_unslash( $_GET[ $key ] ) ) : null;

			if ( null !== $value ) {
				$params[ $key ] = self::format_number( $value );
			}
		}

		$rating = self::current_rating();

		if ( '' !== $rating ) {
			$params[ self::RATING_PARAM ] = $rating;
		}

		if ( class_exists( 'Icademie_Page_Accueil_Search' ) ) {
			$dates = Icademie_Page_Accueil_Search::dates( wp_unslash( $_GET ) );

			foreach ( $dates as $key => $date ) {
				if ( '' !== $date ) {
					$params[ $key ] = $date;
				}
			}
		}

		$orderby = isset( $_GET['orderby'] ) && is_scalar( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : '';

		if ( '' !== $orderby && array_key_exists( $orderby, self::sort_options() ) ) {
			$params['orderby'] = $orderby;
		}
		// phpcs:enable

		return $params;
	}

	/**
	 * Valeurs choisies pour un attribut (paramètre « filter_… » de l'adresse).
	 *
	 * @param array  $params    Paramètres (voir current_params()).
	 * @param string $attribute Slug de l'attribut, sans « pa_ ».
	 * @return string[] Slugs.
	 */
	public static function chosen( $params, $attribute ) {
		$key = 'filter_' . $attribute;

		return isset( $params[ $key ] ) && '' !== $params[ $key ] ? explode( ',', $params[ $key ] ) : array();
	}

	/**
	 * Note minimale de l'adresse (« note_min »), parmi celles proposées.
	 *
	 * @return string « 4 », « 4.5 »… ou vide.
	 */
	public static function current_rating() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$value = isset( $_GET[ self::RATING_PARAM ] ) && is_scalar( $_GET[ self::RATING_PARAM ] ) ? str_replace( ',', '.', trim( (string) wp_unslash( $_GET[ self::RATING_PARAM ] ) ) ) : '';
		// phpcs:enable

		return self::rating_choice( $value );
	}

	/**
	 * Note proposée qui correspond à une valeur envoyée.
	 *
	 * @param mixed $value Valeur envoyée.
	 * @return string Note proposée, ou vide.
	 */
	private static function rating_choice( $value ) {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return '';
		}

		foreach ( self::rating_choices() as $choice ) {
			if ( abs( (float) $choice - (float) $value ) < 0.001 ) {
				return $choice;
			}
		}

		return '';
	}

	/**
	 * Notes minimales proposées.
	 *
	 * @return string[]
	 */
	public static function rating_choices() {
		foreach ( self::definitions() as $definition ) {
			if ( 'rating' === $definition['control'] && ! empty( $definition['choices'] ) ) {
				return array_map( 'strval', (array) $definition['choices'] );
			}
		}

		return array();
	}

	/**
	 * Applique la note minimale à la liste des produits de la page (requête principale).
	 *
	 * La moyenne des avis est celle que WooCommerce garde sur chaque produit.
	 *
	 * @param WP_Query $query Requête principale des produits.
	 */
	public static function apply_rating_filter( $query ) {
		$rating = self::current_rating();

		if ( '' === $rating ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query['icademie_note_min'] = array(
			'key'     => '_wc_average_rating',
			'value'   => $rating,
			'compare' => '>=',
			'type'    => 'DECIMAL(3,2)',
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Tris proposés : ceux de WooCommerce, avec des libellés courts.
	 *
	 * Comme WooCommerce : « Pertinence » (ordre des produits) n'est proposé que si c'est le tri par
	 * défaut de la boutique, et le tri par note seulement si les notes sont activées. Sur les
	 * résultats d'une recherche, « Pertinence » est le classement de la recherche.
	 *
	 * @return string[] Libellés, par valeur de « orderby ».
	 */
	public static function sort_options() {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filtre de WooCommerce.
		$options = (array) apply_filters(
			'woocommerce_catalog_orderby',
			array(
				'menu_order' => __( 'Default sorting', 'woocommerce' ),
				'popularity' => __( 'Sort by popularity', 'woocommerce' ),
				'rating'     => __( 'Sort by average rating', 'woocommerce' ),
				'date'       => __( 'Sort by latest', 'woocommerce' ),
				'price'      => __( 'Sort by price: low to high', 'woocommerce' ),
				'price-desc' => __( 'Sort by price: high to low', 'woocommerce' ),
			)
		);

		$labels = array(
			'menu_order' => __( 'Pertinence', 'icademie-page-categorie' ),
			'popularity' => __( 'Popularité', 'icademie-page-categorie' ),
			'rating'     => __( 'Meilleures notes', 'icademie-page-categorie' ),
			'date'       => __( 'Nouveautés', 'icademie-page-categorie' ),
			'price'      => __( 'Prix croissant', 'icademie-page-categorie' ),
			'price-desc' => __( 'Prix décroissant', 'icademie-page-categorie' ),
		);

		if ( self::is_search() ) {
			$options = array( 'relevance' => __( 'Relevance', 'woocommerce' ) ) + $options;
			unset( $options['menu_order'] );
		} elseif ( 'menu_order' !== self::default_sort() ) {
			unset( $options['menu_order'] );
		}

		$labels['relevance'] = __( 'Pertinence', 'icademie-page-categorie' );

		if ( ! wc_review_ratings_enabled() ) {
			unset( $options['rating'] );
		}

		foreach ( $options as $key => $label ) {
			$options[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : wp_strip_all_tags( (string) $label );
		}

		return $options;
	}

	/**
	 * Tri par défaut de la boutique (WooCommerce › Réglages › Produits).
	 *
	 * @return string
	 */
	private static function default_sort() {
		if ( self::is_search() ) {
			return 'relevance';
		}

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filtre de WooCommerce.
		return (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
	}

	/**
	 * La page affiche-t-elle les résultats d'une recherche de produits (loupe de l'en-tête) ?
	 *
	 * @return bool
	 */
	public static function is_search() {
		return function_exists( 'is_search' ) && is_search() && is_post_type_archive( 'product' );
	}

	/**
	 * Paramètres de la recherche à garder dans toutes les adresses de la liste (mots cherchés).
	 *
	 * @return string[] « s » et « post_type » sur les résultats d'une recherche, sinon rien.
	 */
	public static function base_args() {
		if ( ! self::is_search() ) {
			return array();
		}

		return array(
			's'         => (string) get_search_query( false ),
			'post_type' => 'product',
		);
	}

	/**
	 * Tri affiché : celui de l'adresse, sinon le tri par défaut.
	 *
	 * @param array $params Paramètres (voir current_params()).
	 * @return string
	 */
	public static function current_sort( $params ) {
		$options = self::sort_options();

		if ( isset( $params['orderby'] ) && array_key_exists( $params['orderby'], $options ) ) {
			return $params['orderby'];
		}

		$default = self::default_sort();

		return array_key_exists( $default, $options ) ? $default : (string) key( $options );
	}

	/**
	 * Adresse de la liste (première page), sans filtres.
	 *
	 * Catégorie : son adresse. Boutique : celle de la boutique. Résultats d'une recherche : l'adresse
	 * de la recherche (mots cherchés compris).
	 *
	 * @param WP_Term|null $term Catégorie (ou autre taxonomie de produits) ; null pour la boutique.
	 * @return string
	 */
	public static function base_url( $term ) {
		if ( $term instanceof WP_Term ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		$args = self::base_args();

		return $args ? self::url( home_url( '/' ), $args ) : wc_get_page_permalink( 'shop' );
	}

	/**
	 * Adresse avec des paramètres : valeurs encodées, virgules lisibles (séparateur de WooCommerce).
	 *
	 * @param string   $base   Adresse de départ.
	 * @param string[] $params Paramètres.
	 * @return string
	 */
	public static function url( $base, $params ) {
		$encoded = array();

		foreach ( $params as $key => $value ) {
			$encoded[ $key ] = str_replace( '%2C', ',', rawurlencode( (string) $value ) );
		}

		return add_query_arg( $encoded, $base );
	}

	/**
	 * Paramètres sans les filtres : dates de la recherche et tri seulement (« Tout effacer »).
	 *
	 * @param array $params Paramètres (voir current_params()).
	 * @return string[]
	 */
	public static function without_filters( $params ) {
		return array_intersect_key( $params, array_flip( array( 'depart', 'retour', 'orderby' ) ) );
	}

	/**
	 * Nombre de filtres actifs : un par pastille.
	 *
	 * @param array $chips Pastilles (voir chips()).
	 * @return int
	 */
	public static function count_active( $chips ) {
		return count( $chips );
	}

	/**
	 * Pastilles des filtres actifs : libellé et adresse sans ce filtre.
	 *
	 * Une pastille par valeur cochée (types, équipements…), une pour un intervalle ou un minimum
	 * (longueur, passagers, cabines), une pour le prix et une pour la note. Un filtre d'attribut
	 * venu d'ailleurs (lien, autre extension) a aussi ses pastilles, pour pouvoir le retirer.
	 *
	 * @param array  $params Paramètres (voir current_params()).
	 * @param string $base   Adresse de la liste, sans paramètres.
	 * @return array[] Libellé (« label »), libellé pour les lecteurs d'écran (« sr »), adresse (« url »).
	 */
	public static function chips( $params, $base ) {
		$chips = array();
		$add   = static function ( $label, $without ) use ( &$chips, $base ) {
			$chips[] = array(
				'label' => $label,
				'url'   => self::url( $base, $without ),
			);
		};

		foreach ( $params as $key => $value ) {
			if ( 0 !== strpos( $key, 'filter_' ) ) {
				continue;
			}

			$attribute  = substr( $key, strlen( 'filter_' ) );
			$slugs      = self::chosen( $params, $attribute );
			$definition = self::definition_for( $attribute );
			$control    = $definition ? $definition['control'] : 'checkboxes';
			$without    = $params;

			unset( $without[ $key ], $without[ 'query_type_' . $attribute ] );

			if ( 'range' === $control ) {
				$add( self::range_label( $attribute, $slugs, isset( $definition['unit'] ) ? $definition['unit'] : '' ), $without );
				continue;
			}

			if ( 'stepper' === $control || 'minimum' === $control ) {
				$add( self::minimum_label( $definition, self::minimum_of( $attribute, $slugs ) ), $without );
				continue;
			}

			$values = self::values( $attribute );

			foreach ( $slugs as $slug ) {
				$rest = array_values( array_diff( $slugs, array( $slug ) ) );
				$less = $without;

				if ( $rest ) {
					$less[ $key ] = implode( ',', $rest );

					if ( count( $rest ) > 1 && isset( $params[ 'query_type_' . $attribute ] ) ) {
						$less[ 'query_type_' . $attribute ] = $params[ 'query_type_' . $attribute ];
					}

					$less = self::reorder( $less, $params );
				}

				$add( isset( $values[ $slug ] ) ? $values[ $slug ]['name'] : $slug, $less );
			}
		}

		if ( isset( $params['min_price'] ) || isset( $params['max_price'] ) ) {
			$without = $params;
			unset( $without['min_price'], $without['max_price'] );

			$add( self::price_label( isset( $params['min_price'] ) ? (float) $params['min_price'] : null, isset( $params['max_price'] ) ? (float) $params['max_price'] : null ), $without );
		}

		if ( isset( $params[ self::RATING_PARAM ] ) ) {
			$without = $params;
			unset( $without[ self::RATING_PARAM ] );

			$add( self::rating_label( $params[ self::RATING_PARAM ] ), $without );
		}

		return $chips;
	}

	/**
	 * Remet les paramètres dans l'ordre d'origine (adresses stables).
	 *
	 * @param array $params Paramètres modifiés.
	 * @param array $order  Paramètres d'origine.
	 * @return array
	 */
	private static function reorder( $params, $order ) {
		return array_merge( array_intersect_key( $order, $params ), $params );
	}

	/**
	 * Libellé d'un intervalle : « 8 à 12 m », « 8 m et plus », « jusqu'à 12 m ».
	 *
	 * @param string   $attribute Slug de l'attribut.
	 * @param string[] $slugs     Valeurs choisies.
	 * @param string   $unit      Unité (« m »).
	 * @return string
	 */
	public static function range_label( $attribute, $slugs, $unit ) {
		$scale = self::scale( $attribute );
		$index = array();

		foreach ( $scale as $position => $value ) {
			if ( in_array( $value['slug'], $slugs, true ) ) {
				$index[] = $position;
			}
		}

		if ( ! $index ) {
			return implode( ', ', $slugs );
		}

		$first = $scale[ min( $index ) ];
		$last  = $scale[ max( $index ) ];
		$top   = count( $scale ) - 1 === max( $index );

		if ( $first['slug'] === $last['slug'] ) {
			return self::with_unit( $first['name'], $unit );
		}

		if ( 0 === min( $index ) ) {
			/* translators: %s: valeur maximale avec son unité (« 12 m »). */
			return sprintf( __( 'jusqu\'à %s', 'icademie-page-categorie' ), self::with_unit( $last['name'], $unit ) );
		}

		if ( $top ) {
			/* translators: %s: valeur minimale avec son unité (« 8 m »). */
			return sprintf( __( '%s et plus', 'icademie-page-categorie' ), self::with_unit( $first['name'], $unit ) );
		}

		/* translators: 1: valeur minimale, 2: valeur maximale avec son unité (« 8 à 12 m »). */
		return sprintf( __( '%1$s à %2$s', 'icademie-page-categorie' ), $first['name'], self::with_unit( $last['name'], $unit ) );
	}

	/**
	 * Valeur suivie de son unité : « 12 m », « > 15 m ».
	 *
	 * @param string $name Nom de la valeur.
	 * @param string $unit Unité.
	 * @return string
	 */
	public static function with_unit( $name, $unit ) {
		$name = preg_replace( '/^\s*>\s*/u', '> ', $name );

		return '' === $unit ? $name : $name . "\u{00A0}" . $unit;
	}

	/**
	 * Libellé d'un minimum : « 2 passagers », « 2 cabines ou plus ».
	 *
	 * @param array|null $definition Filtre.
	 * @param float|null $minimum    Nombre.
	 * @return string
	 */
	public static function minimum_label( $definition, $minimum ) {
		$number = null === $minimum ? 0.0 : (float) $minimum;
		$text   = number_format_i18n( $number, floor( $number ) === $number ? 0 : 1 );

		if ( $definition && 'passagers' === $definition['key'] ) {
			/* translators: %s: nombre de passagers. */
			return sprintf( _n( '%s passager', '%s passagers', (int) ceil( $number ), 'icademie-page-categorie' ), $text );
		}

		if ( $definition && 'cabines' === $definition['key'] ) {
			/* translators: %s: nombre de cabines. */
			return sprintf( _n( '%s cabine ou plus', '%s cabines ou plus', (int) ceil( $number ), 'icademie-page-categorie' ), $text );
		}

		$label = $definition ? $definition['label'] : '';

		/* translators: 1: nom du filtre, 2: nombre minimal. */
		return trim( sprintf( __( '%1$s : %2$s ou plus', 'icademie-page-categorie' ), $label, $text ), ' :' );
	}

	/**
	 * Libellé du prix : « 20 € à 150 € », « dès 20 € », « jusqu'à 150 € ».
	 *
	 * @param float|null $min Prix minimum.
	 * @param float|null $max Prix maximum.
	 * @return string
	 */
	public static function price_label( $min, $max ) {
		$format = static function ( $value ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $value, array( 'decimals' => floor( $value ) === $value ? 0 : wc_get_price_decimals() ) ) ), ENT_QUOTES, 'UTF-8' );
		};

		if ( null !== $min && null !== $max ) {
			/* translators: 1: prix minimum, 2: prix maximum (par jour). */
			return sprintf( __( '%1$s à %2$s', 'icademie-page-categorie' ), $format( $min ), $format( $max ) );
		}

		if ( null !== $min ) {
			/* translators: %s: prix minimum (par jour). */
			return sprintf( __( 'dès %s', 'icademie-page-categorie' ), $format( $min ) );
		}

		/* translators: %s: prix maximum (par jour). */
		return sprintf( __( 'jusqu\'à %s', 'icademie-page-categorie' ), $format( (float) $max ) );
	}

	/**
	 * Libellé de la note minimale : « ★ 4,5 + ».
	 *
	 * @param string $rating Note.
	 * @return string
	 */
	public static function rating_label( $rating ) {
		return '★ ' . number_format_i18n( (float) $rating, floor( (float) $rating ) === (float) $rating ? 0 : 1 ) . "\u{00A0}+";
	}

	/**
	 * Nombre sans zéros inutiles (« 20 », « 19.5 »).
	 *
	 * @param float $value Nombre.
	 * @return string
	 */
	private static function format_number( $value ) {
		return wc_format_decimal( round( (float) $value, 2 ), false, true );
	}

	/**
	 * Prix envoyé (« 20 », « 19,50 »), positif ou nul.
	 *
	 * @param mixed $value Valeur envoyée.
	 * @return float|null Null si vide ou invalide.
	 */
	private static function price_value( $value ) {
		$value = is_scalar( $value ) ? str_replace( array( ',', ' ', "\u{00A0}" ), array( '.', '', '' ), trim( (string) $value ) ) : '';

		return ( '' !== $value && is_numeric( $value ) && (float) $value >= 0 ) ? (float) $value : null;
	}

	/**
	 * Reçoit le formulaire des filtres ou le moteur de recherche et redirige vers les résultats.
	 */
	public static function handle_forms() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Formulaires publics en GET, sans effet sur le site.
		if ( ! function_exists( 'wc_get_page_permalink' ) || empty( $_GET ) ) {
			return;
		}

		$request = wp_unslash( $_GET );
		// phpcs:enable

		if ( isset( $request[ self::MARKER ] ) && ( is_product_taxonomy() || is_shop() ) ) {
			$term = is_product_taxonomy() ? get_queried_object() : null;
			$url  = self::filters_url( $request, $term instanceof WP_Term ? $term : null );
		} elseif ( isset( $request[ self::SEARCH_MARKER ] ) && class_exists( 'Icademie_Page_Accueil_Search' ) && isset( $request[ Icademie_Page_Accueil_Search::MARKER ] ) ) {
			$url = self::search_url( $request );
		} else {
			return;
		}

		wp_safe_redirect( $url, 302, 'Icademie - Page categorie' );
		exit;
	}

	/**
	 * Adresse des résultats du moteur de recherche de la page catégorie.
	 *
	 * La recherche de l'accueil donne la destination (page de la catégorie), les passagers et les
	 * dates ; les autres filtres déjà choisis (champs cachés du formulaire) et le tri sont gardés.
	 *
	 * @param array $request Champs envoyés.
	 * @return string
	 */
	public static function search_url( $request ) {
		$url  = Icademie_Page_Accueil_Search::results_url( $request );
		$keep = self::current_params();

		$passengers = Icademie_Page_Accueil_Search::PASSENGERS_ATTRIBUTE;

		unset( $keep[ 'filter_' . $passengers ], $keep[ 'query_type_' . $passengers ], $keep['depart'], $keep['retour'] );

		return self::url( $url, $keep );
	}

	/**
	 * Adresse des résultats du formulaire des filtres.
	 *
	 * Chaque filtre est converti au format de WooCommerce. Un filtre laissé à sa valeur la plus
	 * large (curseur au bout, compteur vide, « Indifférent ») n'est pas ajouté. Les dates de la
	 * recherche et le tri sont gardés. La liste repart de la première page.
	 *
	 * @param array        $request Champs envoyés.
	 * @param WP_Term|null $term    Catégorie affichée (null : boutique).
	 * @return string
	 */
	public static function filters_url( $request, $term ) {
		$params = array();
		$stats  = null;

		foreach ( self::definitions() as $key => $definition ) {
			$attribute = isset( $definition['attribute'] ) ? $definition['attribute'] : '';

			switch ( $definition['control'] ) {
				case 'price':
					if ( null === $stats ) {
						$stats = self::stats( $term );
					}

					$params += self::price_params( $request, $key, $stats['price'] );
					break;

				case 'checkboxes':
					$slugs = self::requested_slugs( $request, $key, $attribute );
					$params += self::attribute_params( $attribute, $slugs, isset( $definition['query_type'] ) ? $definition['query_type'] : 'or' );
					break;

				case 'range':
					$params += self::attribute_params( $attribute, self::requested_range( $request, $key, $attribute ), 'or' );
					break;

				case 'stepper':
					$number = isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? absint( $request[ $key ] ) : 0;

					if ( $number > 0 ) {
						$number = min( $number, self::STEPPER_LIMIT );
						$slugs  = self::slugs_from( $attribute, $number );

						// Aucune valeur assez grande : le nombre demandé, qui ne trouve aucun bateau.
						$params += self::attribute_params( $attribute, $slugs ? $slugs : array( (string) $number ), 'or' );
					}
					break;

				case 'minimum':
					$slug   = isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? sanitize_title( (string) $request[ $key ] ) : '';
					$values = self::values( $attribute );

					if ( '' !== $slug && isset( $values[ $slug ] ) && null !== $values[ $slug ]['number'] ) {
						$params += self::attribute_params( $attribute, self::slugs_from( $attribute, $values[ $slug ]['number'] ), 'or' );
					}
					break;

				case 'choice':
					$slug = isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? sanitize_title( (string) $request[ $key ] ) : '';

					if ( '' !== $slug && array_key_exists( $slug, self::values( $attribute ) ) ) {
						$params += self::attribute_params( $attribute, array( $slug ), 'or' );
					}
					break;

				case 'rating':
					$rating = isset( $request[ $key ] ) ? self::rating_choice( str_replace( ',', '.', (string) ( is_scalar( $request[ $key ] ) ? $request[ $key ] : '' ) ) ) : '';

					if ( '' !== $rating ) {
						$params[ self::RATING_PARAM ] = $rating;
					}
					break;
			}
		}

		// Dates de la recherche et tri : gardés.
		$params += array_intersect_key( self::current_params(), array_flip( array( 'depart', 'retour', 'orderby' ) ) );

		return self::url( self::base_url( $term ), $params );
	}

	/**
	 * Paramètres de WooCommerce pour des valeurs d'attribut.
	 *
	 * @param string   $attribute  Slug de l'attribut.
	 * @param string[] $slugs      Valeurs.
	 * @param string   $query_type « or » (une valeur suffit) ou « and » (toutes).
	 * @return string[]
	 */
	private static function attribute_params( $attribute, $slugs, $query_type ) {
		if ( ! $slugs || ! taxonomy_exists( wc_attribute_taxonomy_name( $attribute ) ) ) {
			return array();
		}

		$params = array( 'filter_' . $attribute => implode( ',', $slugs ) );

		if ( 'or' === $query_type && count( $slugs ) > 1 ) {
			$params[ 'query_type_' . $attribute ] = 'or';
		}

		return $params;
	}

	/**
	 * Valeurs cochées d'un groupe de cases, parmi celles de l'attribut, dans son ordre.
	 *
	 * @param array  $request   Champs envoyés.
	 * @param string $key       Nom du champ.
	 * @param string $attribute Slug de l'attribut.
	 * @return string[]
	 */
	private static function requested_slugs( $request, $key, $attribute ) {
		if ( ! isset( $request[ $key ] ) ) {
			return array();
		}

		$requested = array_map( 'sanitize_title', array_filter( (array) $request[ $key ], 'is_scalar' ) );

		return array_values( array_intersect( array_keys( self::values( $attribute ) ), $requested ) );
	}

	/**
	 * Valeurs entre les deux poignées d'un curseur double (positions sur l'échelle des valeurs).
	 *
	 * @param array  $request   Champs envoyés.
	 * @param string $key       Nom du filtre (champs « {clé}_min » et « {clé}_max »).
	 * @param string $attribute Slug de l'attribut.
	 * @return string[] Slugs, ou aucun si tout l'intervalle est choisi.
	 */
	private static function requested_range( $request, $key, $attribute ) {
		$scale = self::scale( $attribute );
		$last  = count( $scale ) - 1;

		if ( $last < 1 ) {
			return array();
		}

		$min = isset( $request[ $key . '_min' ] ) && is_numeric( $request[ $key . '_min' ] ) ? (int) $request[ $key . '_min' ] : 0;
		$max = isset( $request[ $key . '_max' ] ) && is_numeric( $request[ $key . '_max' ] ) ? (int) $request[ $key . '_max' ] : $last;
		$min = max( 0, min( $last, $min ) );
		$max = max( 0, min( $last, $max ) );

		if ( $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}

		if ( 0 === $min && $last === $max ) {
			return array();
		}

		return wp_list_pluck( array_slice( $scale, $min, $max - $min + 1 ), 'slug' );
	}

	/**
	 * Paramètres du prix par jour : un minimum au plus bas ou un maximum au plus haut ne filtre rien.
	 *
	 * @param array      $request Champs envoyés.
	 * @param string     $key     Nom du filtre (champs « {clé}_min » et « {clé}_max »).
	 * @param array|null $bounds  Prix le plus bas et le plus haut de la catégorie.
	 * @return string[]
	 */
	private static function price_params( $request, $key, $bounds ) {
		$min = isset( $request[ $key . '_min' ] ) ? self::price_value( $request[ $key . '_min' ] ) : null;
		$max = isset( $request[ $key . '_max' ] ) ? self::price_value( $request[ $key . '_max' ] ) : null;

		if ( null !== $min && null !== $max && $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}

		$params = array();

		if ( null !== $min && $min > 0 && ( ! $bounds || $min > $bounds['min'] ) ) {
			$params['min_price'] = self::format_number( $min );
		}

		if ( null !== $max && ( ! $bounds || $max < $bounds['max'] ) ) {
			$params['max_price'] = self::format_number( $max );
		}

		return $params;
	}

	/**
	 * Bateaux de la catégorie, avant les filtres : nombre, valeurs d'attributs, prix, notes.
	 *
	 * Sert à n'afficher que les filtres et les valeurs utiles (au moins un bateau de la catégorie
	 * a cette valeur), et à borner le curseur du prix.
	 *
	 * @param WP_Term|null $term Catégorie (ou autre taxonomie de produits) ; null pour la boutique.
	 * @return array « count », « terms » (par taxonomie : nombre de bateaux par slug), « price »
	 *               (« min » et « max », ou null), « rated » (bateaux qui ont une note).
	 */
	public static function stats( $term ) {
		$search = self::is_search() ? (string) get_search_query( false ) : '';
		$key    = 'stats:' . ( $term instanceof WP_Term ? $term->term_taxonomy_id : 0 ) . ':' . md5( $search ) . ':' . wp_cache_get_last_changed( 'posts' ) . ':' . wp_cache_get_last_changed( 'terms' );
		$stats = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false !== $stats ) {
			return $stats;
		}

		$ids   = self::product_ids( $term, $search );
		$stats = array(
			'count' => count( $ids ),
			'terms' => array(),
			'price' => null,
			'rated' => 0,
		);

		if ( $ids ) {
			$taxonomies = array();

			foreach ( self::definitions() as $definition ) {
				if ( isset( $definition['attribute'] ) && taxonomy_exists( wc_attribute_taxonomy_name( $definition['attribute'] ) ) ) {
					$taxonomies[] = wc_attribute_taxonomy_name( $definition['attribute'] );
				}
			}

			$terms = $taxonomies ? wp_get_object_terms(
				$ids,
				array_unique( $taxonomies ),
				array(
					'fields'                 => 'all_with_object_id',
					'update_term_meta_cache' => false,
				)
			) : array();

			foreach ( is_wp_error( $terms ) ? array() : $terms as $object_term ) {
				$slug = (string) $object_term->slug;

				if ( ! isset( $stats['terms'][ $object_term->taxonomy ][ $slug ] ) ) {
					$stats['terms'][ $object_term->taxonomy ][ $slug ] = 0;
				}

				++$stats['terms'][ $object_term->taxonomy ][ $slug ];
			}

			global $wpdb;

			$in = implode( ',', array_map( 'absint', $ids ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Agrégat sur la table de WooCommerce (identifiants entiers), mis en cache.
			$row = $wpdb->get_row( "SELECT MIN( min_price ) AS min_price, MAX( max_price ) AS max_price, SUM( CASE WHEN average_rating > 0 THEN 1 ELSE 0 END ) AS rated FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ( {$in} )" );

			if ( $row && null !== $row->min_price && null !== $row->max_price ) {
				$stats['price'] = array(
					'min' => (float) $row->min_price,
					'max' => (float) $row->max_price,
				);
			}

			$stats['rated'] = $row ? (int) $row->rated : 0;
		}

		wp_cache_set( $key, $stats, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Bateaux publiés et visibles dans le catalogue, de la catégorie et de ses sous-catégories, ou
	 * trouvés par une recherche.
	 *
	 * @param WP_Term|null $term   Catégorie ; null pour toute la boutique.
	 * @param string       $search Mots cherchés (résultats d'une recherche), ou vide.
	 * @return int[]
	 */
	private static function product_ids( $term, $search = '' ) {
		$visibility = wc_get_product_visibility_term_ids();

		// Recherche : bateaux masqués des résultats de recherche, comme la liste de WooCommerce.
		$hidden_key = '' !== $search ? 'exclude-from-search' : 'exclude-from-catalog';
		$hidden     = array( isset( $visibility[ $hidden_key ] ) ? (int) $visibility[ $hidden_key ] : 0 );

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && isset( $visibility['outofstock'] ) ) {
			$hidden[] = (int) $visibility['outofstock'];
		}

		$tax_query = array();
		$hidden    = array_filter( $hidden );

		if ( $hidden ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $hidden,
				'operator' => 'NOT IN',
			);
		}

		if ( $term instanceof WP_Term ) {
			$tax_query[] = array(
				'taxonomy'         => $term->taxonomy,
				'field'            => 'term_id',
				'terms'            => (int) $term->term_id,
				'include_children' => true,
			);
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Une requête par page, mise en cache.
			'tax_query'              => $tax_query,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return array_map( 'intval', get_posts( $args ) );
	}
}
