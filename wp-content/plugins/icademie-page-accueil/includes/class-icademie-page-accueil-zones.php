<?php
/**
 * Données des zones de la page d'accueil.
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prépare le contenu de chaque zone : textes des réglages, produits, catégories, avis.
 *
 * Une zone sans contenu (aucun avis, aucune destination mise en avant…) n'est pas affichée ; les
 * administrateurs voient la liste de ce qui reste à compléter en haut de la page.
 */
final class Icademie_Page_Accueil_Zones {

	/** Attributs de produit utilisés (slug sans « pa_ »). */
	const TYPE_ATTRIBUTE       = 'type-de-bateau';
	const PASSENGERS_ATTRIBUTE = 'passagers';
	const BERTHS_ATTRIBUTE     = 'nombre-de-couchages';
	const LENGTH_ATTRIBUTE     = 'longueur-du-bateau';

	/** Nombre d'éléments affichés. */
	const BOATS_LIMIT        = 8;
	const DESTINATIONS_LIMIT = 5;
	const REVIEWS_LIMIT      = 3;

	/**
	 * Données de toutes les zones.
	 *
	 * @return array
	 */
	public static function all() {
		$woocommerce = function_exists( 'wc_get_products' );

		return array(
			'hero'         => self::hero(),
			'search'       => $woocommerce ? Icademie_Page_Accueil_Search::form_data() : null,
			'reassurance'  => self::reassurance(),
			'types'        => $woocommerce ? self::boat_types() : array(),
			'shop_url'     => $woocommerce ? wc_get_page_permalink( 'shop' ) : '',
			'boats'        => $woocommerce ? self::boats() : array(
				'items'    => array(),
				'featured' => false,
			),
			'destinations' => $woocommerce ? self::destinations() : array(),
			'steps'        => self::steps(),
			'owner'        => self::owner(),
			'reviews'      => $woocommerce ? self::reviews() : array(
				'items'   => array(),
				'average' => 0,
				'count'   => 0,
				'url'     => '',
			),
			'newsletter'   => self::newsletter(),
		);
	}

	/**
	 * Zone 2 : titre, sous-titre et photo du bandeau.
	 *
	 * @return array
	 */
	private static function hero() {
		$image_id = (int) Icademie_Page_Accueil_Settings::get( 'bandeau_image' );
		$image    = $image_id ? wp_get_attachment_image(
			$image_id,
			'full',
			false,
			array(
				'class'         => 'icademie-accueil__hero-image',
				'alt'           => '',
				'sizes'         => '100vw',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		) : '';

		return array(
			'title' => Icademie_Page_Accueil_Settings::get( 'bandeau_titre' ),
			'text'  => Icademie_Page_Accueil_Settings::get( 'bandeau_texte' ),
			'image' => $image,
		);
	}

	/**
	 * Zone 3 : les quatre arguments, avec leur pictogramme et leur page (lien).
	 *
	 * @return array[]
	 */
	private static function reassurance() {
		$icons = array(
			1 => 'lock',
			2 => 'shield',
			3 => 'user-check',
			4 => 'lifebuoy',
		);
		$items = array();

		foreach ( $icons as $n => $icon ) {
			$title = Icademie_Page_Accueil_Settings::get( 'reassurance_' . $n . '_titre' );

			if ( '' === $title ) {
				continue;
			}

			$items[] = array(
				'title' => $title,
				'text'  => Icademie_Page_Accueil_Settings::get( 'reassurance_' . $n . '_texte' ),
				'url'   => Icademie_Page_Accueil_Settings::get( 'reassurance_' . $n . '_url' ),
				'icon'  => $icon,
			);
		}

		return $items;
	}

	/**
	 * Zone 4 : une vignette par valeur de l'attribut « Type de bateau », dans l'ordre de l'attribut.
	 *
	 * @return array[]
	 */
	private static function boat_types() {
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

		$items = array();

		foreach ( $terms as $term ) {
			$items[] = array(
				'name'  => $term->name,
				'url'   => Icademie_Page_Accueil_Search::type_url( $term ),
				'image' => self::type_image( $term ),
				'icon'  => self::type_icon( $term->slug ),
			);
		}

		return $items;
	}

	/**
	 * Image d'un type : celle choisie sur la valeur, sinon la photo du dernier bateau de ce type.
	 *
	 * @param WP_Term $term Valeur de l'attribut.
	 * @return string HTML de l'image, ou vide (pictogramme).
	 */
	private static function type_image( $term ) {
		$image_id = Icademie_Page_Accueil_Terms::image_id( $term->term_id );

		if ( ! $image_id ) {
			$products = get_posts(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Une requête par type, page mise en cache.
					'tax_query'              => array(
						array(
							'taxonomy' => $term->taxonomy,
							'terms'    => (int) $term->term_id,
						),
					),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Idem.
					'meta_query'             => array(
						array(
							'key'     => '_thumbnail_id',
							'compare' => 'EXISTS',
						),
					),
				)
			);

			$image_id = $products ? (int) get_post_thumbnail_id( $products[0] ) : 0;
		}

		if ( ! $image_id ) {
			return '';
		}

		return wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'alt'     => '',
				'sizes'   => '(max-width: 599px) 132px, (max-width: 1023px) 33vw, 200px',
				'loading' => 'lazy',
			)
		);
	}

	/**
	 * Pictogramme d'un type de bateau, d'après le slug de la valeur.
	 *
	 * @param string $slug Slug de la valeur (voile, moteur…).
	 * @return string Nom de l'icône.
	 */
	private static function type_icon( $slug ) {
		$icons = array(
			'electr' => 'type-electrique',
			'hybrid' => 'type-hybride',
			'rame'   => 'type-rame',
			'aviron' => 'type-rame',
			'moteur' => 'type-moteur',
			'voil'   => 'type-voile',
		);

		foreach ( $icons as $needle => $icon ) {
			if ( false !== strpos( $slug, $needle ) ) {
				return $icon;
			}
		}

		return 'type-voile';
	}

	/**
	 * Zone 5 : bateaux mis en avant (étoile dans la liste des produits), sinon les plus récents.
	 *
	 * @return array Bateaux (« items ») et « featured » (faux si ce sont les plus récents).
	 */
	private static function boats() {
		$args = array(
			'status'     => 'publish',
			'limit'      => self::BOATS_LIMIT,
			'visibility' => 'catalog',
			'orderby'    => 'date',
			'order'      => 'DESC',
		);

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['stock_status'] = 'instock';
		}

		$products = wc_get_products( $args + array( 'featured' => true ) );
		$featured = ! empty( $products );

		if ( ! $featured ) {
			$products = wc_get_products( $args );
		}

		return array(
			'items'    => array_map( array( __CLASS__, 'boat_card' ), $products ),
			'featured' => $featured,
		);
	}

	/**
	 * Carte d'un bateau : photo, type et port, titre, capacité, note, prix par jour.
	 *
	 * @param WC_Product $product Bateau.
	 * @return array
	 */
	private static function boat_card( $product ) {
		$kicker = array_filter(
			array(
				self::attribute_text( $product, self::TYPE_ATTRIBUTE ),
				self::port( $product ),
			)
		);

		$specs  = array();
		$people = self::attribute_number( $product, self::PASSENGERS_ATTRIBUTE );
		$berths = self::attribute_number( $product, self::BERTHS_ATTRIBUTE );
		$length = self::attribute_text( $product, self::LENGTH_ATTRIBUTE );

		if ( $people ) {
			/* translators: %s: nombre de passagers. */
			$specs[] = sprintf( _n( '%s passager', '%s passagers', $people, 'icademie-page-accueil' ), number_format_i18n( $people ) );
		}

		if ( $berths ) {
			/* translators: %s: nombre de couchages. */
			$specs[] = sprintf( _n( '%s couchage', '%s couchages', $berths, 'icademie-page-accueil' ), number_format_i18n( $berths ) );
		}

		if ( '' !== $length ) {
			$specs[] = self::length_text( $length );
		}

		$image_id = (int) $product->get_image_id();

		return array(
			'title'    => $product->get_name(),
			'url'      => $product->get_permalink(),
			'image'    => $image_id ? wp_get_attachment_image(
				$image_id,
				'medium_large',
				false,
				array(
					'alt'     => '',
					'sizes'   => '(max-width: 599px) 84vw, (max-width: 1023px) 44vw, 290px',
					'loading' => 'lazy',
				)
			) : '',
			'kicker'   => implode( ' · ', $kicker ),
			'specs'    => implode( ' · ', $specs ),
			'rating'   => (float) $product->get_average_rating(),
			'reviews'  => (int) $product->get_review_count(),
			'price'    => $product->get_price_html(),
			'per_day'  => class_exists( 'Icademie_Dates_Produit' ) && Icademie_Dates_Produit::requires_dates( $product ),
		);
	}

	/**
	 * Valeurs d'un attribut du produit, séparées par des virgules.
	 *
	 * Utilisée aussi par les cartes de la page catégorie (extension « Icademie – Page catégorie »).
	 *
	 * @param WC_Product $product   Produit.
	 * @param string     $attribute Slug de l'attribut, sans « pa_ ».
	 * @return string
	 */
	public static function attribute_text( $product, $attribute ) {
		return trim( wp_strip_all_tags( html_entity_decode( (string) $product->get_attribute( wc_attribute_taxonomy_name( $attribute ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Plus grand nombre d'un attribut du produit : « 8 » pour 8 couchages ; « 11 » pour un bateau
	 * dont l'attribut « Passagers » a les valeurs 1, 2… 11 (de 1 à 11 passagers).
	 *
	 * @param WC_Product $product   Produit.
	 * @param string     $attribute Slug de l'attribut, sans « pa_ ».
	 * @return int 0 si l'attribut n'est pas renseigné.
	 */
	private static function attribute_number( $product, $attribute ) {
		return preg_match_all( '/\d+/', self::attribute_text( $product, $attribute ), $numbers ) ? (int) max( array_map( 'intval', $numbers[0] ) ) : 0;
	}

	/**
	 * Longueur lisible : « 12 m », « plus de 15 m ».
	 *
	 * Utilisée aussi par les cartes de la page catégorie.
	 *
	 * @param string $value Valeur de l'attribut « Longueur du bateau ».
	 * @return string
	 */
	public static function length_text( $value ) {
		if ( ! preg_match( '/\d+(?:[.,]\d+)?/', $value, $number ) ) {
			return $value;
		}

		$number = str_replace( '.', ',', $number[0] );

		if ( preg_match( '/^\s*(>|plus de)/iu', $value ) ) {
			/* translators: %s: longueur en mètres. */
			return sprintf( __( 'plus de %s m', 'icademie-page-accueil' ), $number );
		}

		/* translators: %s: longueur en mètres. */
		return sprintf( __( '%s m', 'icademie-page-accueil' ), $number );
	}

	/**
	 * Port de départ : la catégorie la plus précise du bateau (« Ajaccio » plutôt que « France »).
	 *
	 * Utilisé aussi par les cartes de la page catégorie.
	 *
	 * @param WC_Product $product Produit.
	 * @return string
	 */
	public static function port( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$default = (int) get_option( 'default_product_cat' );
		$best    = null;
		$depth   = -1;

		foreach ( $terms as $term ) {
			if ( (int) $term->term_id === $default ) {
				continue;
			}

			$term_depth = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );

			if ( $term_depth > $depth ) {
				$best  = $term;
				$depth = $term_depth;
			}
		}

		return $best ? $best->name : '';
	}

	/**
	 * Zone 6 : catégories cochées « Destination populaire », dans l'ordre des catégories.
	 *
	 * @return array[]
	 */
	private static function destinations() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$featured = array_values(
			array_filter(
				$terms,
				static function ( $term ) {
					return Icademie_Page_Accueil_Terms::is_featured( $term->term_id );
				}
			)
		);

		$items = array();

		foreach ( array_slice( $featured, 0, self::DESTINATIONS_LIMIT ) as $index => $term ) {
			$link     = get_term_link( $term );
			$image_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );

			$items[] = array(
				'name'  => $term->name,
				'url'   => is_wp_error( $link ) ? '' : $link,
				'image' => $image_id ? wp_get_attachment_image(
					$image_id,
					0 === $index ? 'large' : 'medium_large',
					false,
					array(
						'alt'     => '',
						'sizes'   => 0 === $index ? '(max-width: 1023px) 100vw, 600px' : '(max-width: 1023px) 50vw, 300px',
						'loading' => 'lazy',
					)
				) : '',
			);
		}

		return $items;
	}

	/**
	 * Zone 7 : les deux onglets et leurs trois étapes.
	 *
	 * @return array[]
	 */
	private static function steps() {
		$groups = array();

		foreach ( Icademie_Page_Accueil_Settings::steps_tabs() as $group => $label ) {
			$steps = array();

			foreach ( array( 1, 2, 3 ) as $n ) {
				$title = Icademie_Page_Accueil_Settings::get( 'etapes_' . $group . '_' . $n . '_titre' );
				$text  = Icademie_Page_Accueil_Settings::get( 'etapes_' . $group . '_' . $n . '_texte' );

				if ( '' !== $title || '' !== $text ) {
					$steps[] = array(
						'title' => $title,
						'text'  => $text,
					);
				}
			}

			if ( $steps ) {
				$groups[ $group ] = array(
					'label' => $label,
					'steps' => $steps,
				);
			}
		}

		return $groups;
	}

	/**
	 * Zone 8 : titre, texte, image et boutons de l'encart « Devenir loueur ».
	 *
	 * @return array
	 */
	private static function owner() {
		$image_id = (int) Icademie_Page_Accueil_Settings::get( 'loueur_image' );

		return array(
			'title'   => Icademie_Page_Accueil_Settings::get( 'loueur_titre' ),
			'text'    => Icademie_Page_Accueil_Settings::get( 'loueur_texte' ),
			'image'   => $image_id ? wp_get_attachment_image(
				$image_id,
				'large',
				false,
				array(
					'alt'     => '',
					'sizes'   => '(max-width: 599px) 100vw, (max-width: 1023px) 240px, 420px',
					'loading' => 'lazy',
				)
			) : '',
			'propose'  => Icademie_Page_Accueil_Settings::get( 'loueur_url_proposer' ),
			'estimate' => Icademie_Page_Accueil_Settings::get( 'loueur_url_estimer' ),
		);
	}

	/**
	 * Zone 9 : note moyenne de tous les avis publiés et derniers avis, les vérifiés en premier.
	 *
	 * @return array
	 */
	private static function reviews() {
		$summary  = self::rating_summary();
		$comments = get_comments(
			array(
				'type'        => 'review',
				'status'      => 'approve',
				'post_type'   => 'product',
				'post_status' => 'publish',
				'number'      => 20,
				'orderby'     => 'comment_date_gmt',
				'order'       => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Avis notés seulement ; page mise en cache.
				'meta_query'  => array(
					array(
						'key'     => 'rating',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$verified = array();
		$others   = array();

		foreach ( $comments as $comment ) {
			if ( '1' === (string) get_comment_meta( $comment->comment_ID, 'verified', true ) ) {
				$verified[] = $comment;
			} else {
				$others[] = $comment;
			}
		}

		$items = array();

		foreach ( array_slice( array_merge( $verified, $others ), 0, self::REVIEWS_LIMIT ) as $comment ) {
			$items[] = self::review_card( $comment );
		}

		return array(
			'items'   => $items,
			'average' => $summary['average'],
			'count'   => $summary['count'],
			'url'     => Icademie_Page_Accueil_Settings::get( 'avis_url' ),
		);
	}

	/**
	 * Carte d'un avis : prénom, bateau, mois, note, texte.
	 *
	 * Seul le prénom est affiché, et pas de ville : un avis WooCommerce ne demande pas la ville, et
	 * l'adresse de facturation n'a pas à être publiée.
	 *
	 * @param WP_Comment $comment Avis.
	 * @return array
	 */
	private static function review_card( $comment ) {
		$name = '';

		if ( $comment->user_id ) {
			$name = trim( (string) get_user_meta( (int) $comment->user_id, 'first_name', true ) );
		}

		$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( $comment->comment_author ) ), -1, PREG_SPLIT_NO_EMPTY );

		if ( '' === $name ) {
			$name = $words ? $words[0] : __( 'Un membre', 'icademie-page-accueil' );
		}

		$initials = self::initial( $name );

		if ( count( $words ) > 1 ) {
			$initials .= self::initial( end( $words ) );
		}

		return array(
			'name'     => $name,
			'initials' => $initials,
			'boat'     => get_the_title( $comment->comment_post_ID ),
			'date'     => wp_date( 'F Y', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) ),
			'rating'   => (int) get_comment_meta( $comment->comment_ID, 'rating', true ),
			'text'     => wp_html_excerpt( wp_strip_all_tags( $comment->comment_content ), 220, '…' ),
			'verified' => '1' === (string) get_comment_meta( $comment->comment_ID, 'verified', true ),
		);
	}

	/**
	 * Première lettre d'un mot, en majuscule.
	 *
	 * @param string $word Mot.
	 * @return string
	 */
	private static function initial( $word ) {
		$letter = mb_substr( $word, 0, 1 );

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter );
	}

	/**
	 * Note moyenne et nombre des avis notés et publiés sur les bateaux publiés.
	 *
	 * @return array « average » (0 à 5) et « count ».
	 */
	private static function rating_summary() {
		global $wpdb;

		$key     = 'notes:' . wp_cache_get_last_changed( 'comment' ) . ':' . wp_cache_get_last_changed( 'posts' );
		$summary = wp_cache_get( $key, 'icademie_page_accueil' );

		if ( false === $summary ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Agrégat sans fonction WordPress équivalente, mis en cache.
			$row = $wpdb->get_row(
				"SELECT COUNT(*) AS total, AVG( CAST( m.meta_value AS DECIMAL(4,2) ) ) AS average
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'rating'
				INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID AND p.post_type = 'product' AND p.post_status = 'publish'
				WHERE c.comment_approved = '1' AND c.comment_type = 'review' AND CAST( m.meta_value AS UNSIGNED ) > 0"
			);

			$summary = array(
				'average' => $row ? round( (float) $row->average, 1 ) : 0,
				'count'   => $row ? (int) $row->total : 0,
			);

			wp_cache_set( $key, $summary, 'icademie_page_accueil', DAY_IN_SECONDS );
		}

		return $summary;
	}

	/**
	 * Zone 10 : textes, adresse de la newsletter et formulaire lu sur sa page.
	 *
	 * @return array
	 */
	private static function newsletter() {
		$page = Icademie_Page_Accueil_Newsletter::page();

		return array(
			'title'       => Icademie_Page_Accueil_Settings::get( 'newsletter_titre' ),
			'text'        => Icademie_Page_Accueil_Settings::get( 'newsletter_texte' ),
			'url'         => Icademie_Page_Accueil_Newsletter::url(),
			'form'        => $page ? Icademie_Page_Accueil_Newsletter::form( $page ) : null,
			'privacy_url' => get_privacy_policy_url(),
		);
	}

	/**
	 * Ce qui reste à compléter, pour les administrateurs : libellé et adresse où le faire.
	 *
	 * @param array $data Données des zones.
	 * @return array[]
	 */
	public static function missing( $data ) {
		$items = array();
		$add   = static function ( $label, $url ) use ( &$items ) {
			$items[] = array(
				'label' => $label,
				'url'   => $url,
			);
		};

		if ( '' === $data['hero']['image'] ) {
			$add( __( 'Photo du bandeau (une illustration la remplace en attendant)', 'icademie-page-accueil' ), Icademie_Page_Accueil_Settings::url( 'bandeau' ) );
		}

		$unlinked = count(
			array_filter(
				$data['reassurance'],
				static function ( $item ) {
					return '' === $item['url'];
				}
			)
		);

		if ( $unlinked ) {
			/* translators: %d: nombre d'arguments sans adresse de lien. */
			$add( sprintf( _n( 'Réassurance : %d argument sans adresse de lien (pas encore cliquable)', 'Réassurance : %d arguments sans adresse de lien (pas encore cliquables)', $unlinked, 'icademie-page-accueil' ), $unlinked ), Icademie_Page_Accueil_Settings::url( 'reassurance' ) );
		}

		if ( null !== $data['search'] && ! $data['boats']['featured'] && $data['boats']['items'] ) {
			$add( __( 'Bateaux à la une : aucun bateau mis en avant, les plus récents sont affichés (étoile de la liste des produits)', 'icademie-page-accueil' ), admin_url( 'edit.php?post_type=product' ) );
		}

		if ( null !== $data['search'] && ! $data['destinations'] ) {
			$add( __( 'Destinations populaires : aucune catégorie cochée, la zone est masquée', 'icademie-page-accueil' ), admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) );
		}

		if ( '' === $data['owner']['propose'] || '' === $data['owner']['estimate'] ) {
			$add( __( 'Devenir loueur : adresses des boutons « Proposer mon bateau » et « Estimer mes revenus » (bouton masqué sans adresse)', 'icademie-page-accueil' ), Icademie_Page_Accueil_Settings::url( 'loueur' ) );
		}

		if ( null !== $data['search'] && ! $data['reviews']['items'] ) {
			$add( __( 'Avis des membres : la zone s\'affichera avec le premier avis publié sur un bateau', 'icademie-page-accueil' ), '' );
		}

		if ( '' === $data['newsletter']['url'] ) {
			$add( __( 'Newsletter : aucune page « newsletter » publiée et aucune adresse indiquée, la zone est masquée', 'icademie-page-accueil' ), Icademie_Page_Accueil_Settings::url( 'newsletter' ) );
		} elseif ( ! $data['newsletter']['form'] ) {
			$add( __( 'Newsletter : aucun formulaire HubSpot lu à l\'adresse de la newsletter (un bouton vers cette adresse le remplace)', 'icademie-page-accueil' ), Icademie_Page_Accueil_Settings::url( 'newsletter' ) );
		}

		return $items;
	}
}
