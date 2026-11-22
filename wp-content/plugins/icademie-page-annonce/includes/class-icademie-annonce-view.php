<?php
/**
 * Données affichées par la fiche annonce.
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prépare le contenu de chaque zone des zonings « Produit ».
 */
final class Icademie_Annonce_View {

	/** Équipements visibles avant « Voir tous les équipements » (ordinateur, tablette, smartphone). */
	const EQUIPMENTS_VISIBLE = array(
		'desktop' => 9,
		'tablet'  => 8,
		'phone'   => 6,
	);

	/** Avis visibles avant « Voir les avis ». */
	const REVIEWS_VISIBLE = 2;

	/** Bateaux similaires. */
	const SIMILAR_LIMIT = 8;

	/**
	 * Données de toutes les zones.
	 *
	 * @return array|null Null hors d'une fiche produit (bloc placé ailleurs).
	 */
	public static function data() {
		$preview = Icademie_Page_Annonce::is_editor_preview();
		$product = $preview ? self::preview_product() : ( is_singular( 'product' ) ? wc_get_product( get_queried_object_id() ) : null );

		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$id   = $product->get_id();
		$port = Icademie_Annonce_Ports::port( $product );

		$data = array(
			'preview'     => $preview,
			'id'          => $id,
			'title'       => $product->get_name(),
			'url'         => $product->get_permalink(),
			'breadcrumb'  => self::breadcrumb( $product, $port, $preview ),
			'header'      => self::header( $product, $port ),
			'share'       => self::share( $product ),
			'photos'      => self::photos( $product ),
			'booking'     => self::booking( $product ),
			'specs'       => self::specs( $product ),
			'description' => self::description( $product ),
			'equipments'  => self::equipments( $product ),
			'calendar'    => self::calendar( $product ),
			'conditions'  => Icademie_Annonce_Conditions::values( $id ),
			'map'         => $port ? Icademie_Annonce_Ports::map( $port ) : null,
			'reviews'     => self::reviews( $product, $preview ),
			'similar'     => self::similar( $product ),
		);

		$data['setup'] = ( ! $preview && current_user_can( 'edit_product', $id ) ) ? self::missing( $data, $product, $port ) : array();

		return $data;
	}

	/**
	 * Aperçu de l'éditeur du site : le dernier bateau publié.
	 *
	 * @return WC_Product|null
	 */
	private static function preview_product() {
		$ids = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);

		return $ids ? wc_get_product( $ids[0] ) : null;
	}

	/**
	 * Valeurs d'un attribut du bateau, séparées par des virgules.
	 *
	 * @param WC_Product $product   Bateau.
	 * @param string     $attribute Slug de l'attribut, sans « pa_ ».
	 * @return string
	 */
	private static function attribute_text( $product, $attribute ) {
		return trim( wp_strip_all_tags( html_entity_decode( (string) $product->get_attribute( wc_attribute_taxonomy_name( $attribute ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Zone 2 : fil d'Ariane de WooCommerce (Accueil › France › Ajaccio › bateau).
	 *
	 * @param WC_Product   $product Bateau.
	 * @param WP_Term|null $port    Port de départ.
	 * @param bool         $preview Aperçu de l'éditeur.
	 * @return array « items » et « parent » (retour au port, smartphone).
	 */
	private static function breadcrumb( $product, $port, $preview ) {
		$items = array();

		if ( class_exists( 'WC_Breadcrumb' ) ) {
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filtre de WooCommerce.
			$args = (array) apply_filters(
				'woocommerce_breadcrumb_defaults',
				array(
					'delimiter'   => '&nbsp;&#47;&nbsp;',
					'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">',
					'wrap_after'  => '</nav>',
					'before'      => '',
					'after'       => '',
					'home'        => _x( 'Home', 'breadcrumb', 'woocommerce' ),
				)
			);

			$breadcrumbs = new WC_Breadcrumb();

			if ( ! empty( $args['home'] ) ) {
				// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filtre de WooCommerce.
				$breadcrumbs->add_crumb( $args['home'], apply_filters( 'woocommerce_breadcrumb_home_url', home_url() ) );
			}

			if ( $preview ) {
				if ( $port ) {
					foreach ( array_reverse( get_ancestors( $port->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor_id ) {
						$ancestor = get_term( $ancestor_id, 'product_cat' );

						if ( $ancestor instanceof WP_Term ) {
							$breadcrumbs->add_crumb( $ancestor->name, get_term_link( $ancestor ) );
						}
					}

					$breadcrumbs->add_crumb( $port->name, get_term_link( $port ) );
				}

				$breadcrumbs->add_crumb( $product->get_name() );
				$crumbs = $breadcrumbs->get_breadcrumb();
			} else {
				$crumbs             = $breadcrumbs->generate();
				$args['breadcrumb'] = $crumbs;

				// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Action de WooCommerce (données structurées du fil d'Ariane).
				do_action( 'woocommerce_breadcrumb', $breadcrumbs, $args );
			}

			foreach ( $crumbs as $crumb ) {
				$items[] = array(
					'label' => html_entity_decode( wp_strip_all_tags( (string) $crumb[0] ), ENT_QUOTES, 'UTF-8' ),
					'url'   => isset( $crumb[1] ) ? (string) $crumb[1] : '',
				);
			}

			if ( $items ) {
				$items[ count( $items ) - 1 ]['url'] = '';
			}
		}

		$parent = null;

		for ( $i = count( $items ) - 2; $i >= 0; $i-- ) {
			if ( '' !== $items[ $i ]['url'] ) {
				$parent = $items[ $i ];
				break;
			}
		}

		return array(
			'items'  => $items,
			'parent' => $parent,
		);
	}

	/**
	 * Zone 3 : type de bateau (attribut), port de départ (catégorie), note.
	 *
	 * @param WC_Product   $product Bateau.
	 * @param WP_Term|null $port    Port de départ.
	 * @return array
	 */
	private static function header( $product, $port ) {
		$type_attribute = class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE : 'type-de-bateau';
		$link           = $port ? get_term_link( $port ) : '';

		return array(
			'type'     => self::attribute_text( $product, $type_attribute ),
			'port'     => $port ? $port->name : '',
			'port_url' => is_wp_error( $link ) ? '' : (string) $link,
			'rating'   => (float) $product->get_average_rating(),
			'reviews'  => (int) $product->get_review_count(),
			// Badges sous le titre (maquette) : écologie, skipper, sans permis.
			'badges'   => method_exists( 'Icademie_Page_Accueil_Zones', 'badges' ) ? Icademie_Page_Accueil_Zones::badges( $product ) : array(),
		);
	}

	/**
	 * Partage de l'annonce : réseaux sociaux, e-mail, lien à copier.
	 *
	 * @param WC_Product $product Bateau.
	 * @return array « url », « title », « links » (« network », « label », « url »).
	 */
	private static function share( $product ) {
		$url   = $product->get_permalink();
		$title = html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' );
		/* translators: 1: nom du bateau, 2: nom du site. */
		$text = sprintf( __( '%1$s à louer sur %2$s', 'icademie-page-annonce' ), $title, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );

		$links = array(
			array(
				'network' => 'facebook',
				'label'   => 'Facebook',
				'url'     => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
			),
			array(
				'network' => 'x',
				'label'   => 'X (Twitter)',
				'url'     => 'https://twitter.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $text ),
			),
			array(
				'network' => 'whatsapp',
				'label'   => 'WhatsApp',
				'url'     => 'https://wa.me/?text=' . rawurlencode( $text . ' ' . $url ),
			),
			array(
				'network' => 'linkedin',
				'label'   => 'LinkedIn',
				'url'     => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
			),
			array(
				'network' => 'pinterest',
				'label'   => 'Pinterest',
				'url'     => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&description=' . rawurlencode( $text ),
			),
			array(
				'network' => 'mail',
				'label'   => __( 'E-mail', 'icademie-page-annonce' ),
				'url'     => 'mailto:?subject=' . rawurlencode( $text ) . '&body=' . rawurlencode( $url ),
			),
		);

		foreach ( $links as $index => $link ) {
			$links[ $index ]['icon'] = ( 'mail' !== $link['network'] && function_exists( 'block_core_social_link_get_icon' ) ) ? block_core_social_link_get_icon( $link['network'] ) : '';
		}

		/**
		 * Liens de partage d'une annonce.
		 *
		 * @param array[]    $links   Réseau, libellé, adresse, icône SVG.
		 * @param WC_Product $product Bateau.
		 */
		return array(
			'url'   => $url,
			'title' => $title,
			'text'  => $text,
			'links' => (array) apply_filters( 'icademie_annonce_partage', $links, $product ),
		);
	}

	/**
	 * Zone 4 : photos (principale, puis galerie).
	 *
	 * @param WC_Product $product Bateau.
	 * Dans la grille, les images sont dans des liens « Agrandir la photo n sur N » : texte alternatif vide.
	 *
	 * @return array[] « grid » (image de la grille), « full » (adresse de la grande image), « large » (image de la visionneuse).
	 */
	private static function photos( $product ) {
		$ids = array_values( array_unique( array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) ) ) );

		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}

		$photos = array();

		foreach ( $ids as $index => $image_id ) {
			$full = wp_get_attachment_image_url( $image_id, 'full' );

			if ( ! $full ) {
				continue;
			}

			$first = 0 === $index;

			$photos[] = array(
				'grid'  => wp_get_attachment_image(
					$image_id,
					$first ? 'large' : 'medium_large',
					false,
					array(
						'alt'           => '',
						'sizes'         => $first ? '(max-width: 599px) 100vw, (max-width: 1023px) calc(100vw - 64px), 400px' : '(max-width: 599px) 100vw, (max-width: 1023px) 20vw, 200px',
						'loading'       => $first ? 'eager' : 'lazy',
						'fetchpriority' => $first ? 'high' : 'auto',
						'decoding'      => 'async',
					)
				),
				// Visionneuse : texte alternatif de la médiathèque (par défaut de WordPress).
				'large' => wp_get_attachment_image(
					$image_id,
					'full',
					false,
					array(
						'sizes'    => '100vw',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				),
				'full'  => $full,
			);
		}

		return $photos;
	}

	/**
	 * Zone 5 : module de réservation.
	 *
	 * @param WC_Product $product Bateau.
	 * @return array
	 */
	private static function booking( $product ) {
		$rental  = Icademie_Annonce_Reservation::is_rental( $product );
		$price   = (float) wc_get_price_to_display( $product );
		$deposit = Icademie_Annonce_Conditions::deposit( $product->get_id() );
		$today   = current_datetime()->format( 'Y-m-d' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Ajout au panier refusé : on réaffiche les choix.
		$start      = Icademie_Annonce_Reservation::clean_date( isset( $_POST['icademie_date_debut'] ) ? sanitize_text_field( wp_unslash( $_POST['icademie_date_debut'] ) ) : '' );
		$end        = Icademie_Annonce_Reservation::clean_date( isset( $_POST['icademie_date_fin'] ) ? sanitize_text_field( wp_unslash( $_POST['icademie_date_fin'] ) ) : '' );
		$passengers = isset( $_POST[ Icademie_Annonce_Reservation::FIELD_PASSENGERS ] ) ? absint( $_POST[ Icademie_Annonce_Reservation::FIELD_PASSENGERS ] ) : 0;
		// phpcs:enable

		$max = Icademie_Annonce_Reservation::max_passengers( $product );

		return array(
			'available'     => $product->is_purchasable() && $product->is_in_stock() && $price > 0,
			'rental'        => $rental,
			'price'         => $price,
			'price_html'    => wc_price( $price ),
			'per_day'       => $rental,
			'deposit'       => $deposit,
			'deposit_text'  => null === $deposit ? '' : ( 0 === $deposit ? __( 'Sans caution', 'icademie-page-annonce' ) : Icademie_Annonce_Conditions::amount_text( $deposit ) ),
			'fee_rate'      => Icademie_Annonce_Reservation::fee_rate(),
			'max'           => $max,
			'passengers'    => $passengers ? min( $passengers, $max ? $max : $passengers ) : min( 2, max( 1, $max ) ),
			'today'         => $today,
			'start'         => $start,
			'end'           => $end,
			'price_format'  => array(
				'decimals'  => wc_get_price_decimals(),
				'decimal'   => wc_get_price_decimal_separator(),
				'thousand'  => wc_get_price_thousand_separator(),
				'format'    => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			),
		);
	}

	/**
	 * Zone 6 : caractéristiques clés (renseignées seulement).
	 *
	 * @param WC_Product $product Bateau.
	 * @return array[] « icon », « label », « value ».
	 */
	private static function specs( $product ) {
		$type_attribute = class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE : 'type-de-bateau';
		$max            = static function ( $attribute ) use ( $product ) {
			$value = class_exists( 'Icademie_Page_Categorie_View' ) ? Icademie_Page_Categorie_View::attribute_max( $product, $attribute ) : null;
			return $value ? $value['text'] : '';
		};
		$length         = self::attribute_text( $product, 'longueur-du-bateau' );
		$conditions     = Icademie_Annonce_Conditions::values( $product->get_id() );
		$licence        = isset( $conditions['permis'] ) ? $conditions['permis']['text'] : self::attribute_text( $product, 'permis-bateau' );

		$specs = array(
			array( 'type', __( 'Type', 'icademie-page-annonce' ), self::attribute_text( $product, $type_attribute ) ),
			array( 'calendar', __( 'Année', 'icademie-page-annonce' ), self::attribute_text( $product, 'annee-de-construction' ) ),
			array( 'ruler', __( 'Longueur', 'icademie-page-annonce' ), '' !== $length && class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::length_text( $length ) : $length ),
			array( 'users', __( 'Passagers max.', 'icademie-page-annonce' ), $max( class_exists( 'Icademie_Page_Accueil_Zones' ) ? Icademie_Page_Accueil_Zones::PASSENGERS_ATTRIBUTE : 'passagers' ) ),
			array( 'door', __( 'Cabines', 'icademie-page-annonce' ), $max( 'cabine' ) ),
			array( 'bed', __( 'Couchages', 'icademie-page-annonce' ), $max( 'nombre-de-couchages' ) ),
			array( 'engine', __( 'Motorisation', 'icademie-page-annonce' ), self::attribute_text( $product, 'puissance-du-moteur' ) ),
			array( 'licence', __( 'Permis requis', 'icademie-page-annonce' ), $licence ),
		);

		$list = array();

		foreach ( $specs as $spec ) {
			if ( '' !== $spec[2] ) {
				$list[] = array(
					'icon'  => $spec[0],
					'label' => $spec[1],
					'value' => $spec[2],
				);
			}
		}

		return $list;
	}

	/**
	 * Zone 7 : description du propriétaire, mise en forme comme le contenu d'une page.
	 *
	 * @param WC_Product $product Bateau.
	 * @return string
	 */
	private static function description( $product ) {
		$content = (string) $product->get_description();

		if ( '' === trim( $content ) ) {
			return '';
		}

		// Filtre « the_content » de WordPress, comme l'onglet « Description » de WooCommerce.
		return (string) apply_filters( 'the_content', $content );
	}

	/**
	 * Zone 8 : équipements choisis pour ce bateau (attribut « Équipements »).
	 *
	 * @param WC_Product $product Bateau.
	 * @return string[]
	 */
	private static function equipments( $product ) {
		$taxonomy = wc_attribute_taxonomy_name( 'equipements' );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$names = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) );

		return array_map(
			static function ( $name ) {
				return html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
			},
			is_array( $names ) ? $names : array()
		);
	}

	/**
	 * Zone 9 : jours déjà réservés (commandes du bateau) à partir du mois en cours.
	 *
	 * @param WC_Product $product Bateau.
	 * @return array « booked » (jours AAAA-MM-JJ), « periods » (texte des périodes à venir), « today ».
	 */
	private static function calendar( $product ) {
		$now   = current_datetime();
		$today = $now->format( 'Y-m-d' );
		$from  = $now->format( 'Y-m-01' );

		$periods = array();

		foreach ( Icademie_Annonce_Reservation::booked_periods( $product->get_id() ) as $period ) {
			if ( $period['fin'] < $today ) {
				continue;
			}

			$start = self::date_text( $period['debut'] );
			$end   = self::date_text( $period['fin'] );

			/* translators: 1: premier jour, 2: dernier jour. */
			$periods[] = $period['debut'] === $period['fin'] ? sprintf( __( 'le %s', 'icademie-page-annonce' ), $start ) : sprintf( __( 'du %1$s au %2$s', 'icademie-page-annonce' ), $start, $end );
		}

		return array(
			'booked'  => Icademie_Annonce_Reservation::booked_days( $product->get_id(), $from ),
			'periods' => $periods,
			'today'   => $today,
		);
	}

	/**
	 * Date lisible : « 12 juillet 2027 ».
	 *
	 * @param string $date AAAA-MM-JJ.
	 * @return string
	 */
	private static function date_text( $date ) {
		return wp_date( 'j F Y', (int) strtotime( $date . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Zone 12 : note générale, notes détaillées, avis (les plus récents d'abord), formulaire.
	 *
	 * @param WC_Product $product Bateau.
	 * @param bool       $preview Aperçu de l'éditeur.
	 * @return array
	 */
	private static function reviews( $product, $preview ) {
		$id      = $product->get_id();
		$email   = function_exists( 'wp_get_unapproved_comment_author_email' ) ? wp_get_unapproved_comment_author_email() : '';
		$args    = array(
			'post_id' => $id,
			'status'  => 'approve',
			'type'    => 'review',
			'parent'  => 0,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		// Avis de ce visiteur en attente de modération (après l'envoi), comme WordPress.
		if ( $email ) {
			$args['include_unapproved'] = array( $email );
		}

		$items = array();

		foreach ( get_comments( $args ) as $comment ) {
			$items[] = self::review_card( $comment );
		}

		$criteria = array();

		if ( class_exists( 'Icademie_Avis_Criteres' ) ) {
			$labels = Icademie_Avis_Criteres::criteria();

			foreach ( Icademie_Avis_Criteres::averages( $id ) as $key => $average ) {
				$criteria[] = array(
					'label'   => isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
					'average' => $average['average'],
					'count'   => $average['count'],
				);
			}
		}

		return array(
			'average'  => (float) $product->get_average_rating(),
			'count'    => (int) $product->get_review_count(),
			'criteria' => $criteria,
			'items'    => $items,
			'open'     => comments_open( $id ),
			'form'     => ( ! $preview && comments_open( $id ) ) ? self::review_form( $id ) : '',
		);
	}

	/**
	 * Carte d'un avis : prénom, date du dépôt de l'avis, note, texte, notes détaillées.
	 *
	 * Seul le prénom est affiché, comme sur la page d'accueil.
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
			$name = $words ? $words[0] : __( 'Un locataire', 'icademie-page-annonce' );
		}

		$initial = static function ( $word ) {
			$letter = function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter );
		};

		$initials = $initial( $name ) . ( count( $words ) > 1 ? $initial( end( $words ) ) : '' );

		return array(
			'id'       => (int) $comment->comment_ID,
			'name'     => $name,
			'initials' => $initials,
			'date'     => wp_date( 'j F Y', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) ),
			'datetime' => gmdate( 'Y-m-d', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) ),
			'rating'   => (int) get_comment_meta( $comment->comment_ID, 'rating', true ),
			'text'     => (string) apply_filters( 'comment_text', get_comment_text( $comment ), $comment, array() ),
			'notes'    => class_exists( 'Icademie_Avis_Criteres' ) ? Icademie_Avis_Criteres::review_html( (int) $comment->comment_ID ) : '',
			'pending'  => '0' === (string) $comment->comment_approved,
			'verified' => '1' === (string) get_comment_meta( $comment->comment_ID, 'verified', true ),
		);
	}

	/**
	 * Formulaire de dépôt d'avis de WooCommerce (note générale et notes détaillées).
	 *
	 * @param int $product_id Bateau.
	 * @return string
	 */
	private static function review_form( $product_id ) {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'woocommerce/product-review-form' ) ) {
			return '';
		}

		$parsed = parse_blocks( '<!-- wp:woocommerce/product-review-form /-->' );
		$block  = new WP_Block(
			$parsed[0],
			array(
				'postId'   => $product_id,
				'postType' => 'product',
			)
		);

		return (string) $block->render();
	}

	/**
	 * Zone 13 : bateaux similaires (produits apparentés de WooCommerce : même catégorie ou étiquette).
	 *
	 * @param WC_Product $product Bateau.
	 * @return array[] Cartes des pages catégorie.
	 */
	private static function similar( $product ) {
		if ( ! class_exists( 'Icademie_Page_Categorie_View' ) ) {
			return array();
		}

		$ids = wc_get_related_products( $product->get_id(), self::SIMILAR_LIMIT );

		return $ids ? Icademie_Page_Categorie_View::product_cards( $ids, true ) : array();
	}

	/**
	 * Ce qui manque à l'annonce, pour les personnes qui peuvent la modifier.
	 *
	 * @param array        $data    Données des zones.
	 * @param WC_Product   $product Bateau.
	 * @param WP_Term|null $port    Port de départ.
	 * @return array[] « label » et « url ».
	 */
	private static function missing( $data, $product, $port ) {
		$items = array();
		$edit  = (string) get_edit_post_link( $product->get_id(), 'raw' );
		$add   = static function ( $label, $url ) use ( &$items ) {
			$items[] = array(
				'label' => $label,
				'url'   => $url,
			);
		};

		$missing = array_diff_key( Icademie_Annonce_Conditions::definitions(), $data['conditions'] );

		if ( $missing ) {
			/* translators: %s: rubriques non renseignées. */
			$add( sprintf( __( 'Conditions de location non renseignées : %s', 'icademie-page-annonce' ), implode( ', ', wp_list_pluck( $missing, 'label' ) ) ), $edit );
		}

		if ( ! $port ) {
			$add( __( 'Aucune catégorie : pas de port de départ ni de carte', 'icademie-page-annonce' ), $edit );
		} elseif ( ! Icademie_Annonce_Ports::position( $port ) && current_user_can( 'edit_term', $port->term_id ) ) {
			/* translators: %s: nom du port. */
			$add( sprintf( __( 'Position du port « %s » non renseignée : la carte est remplacée par un lien', 'icademie-page-annonce' ), $port->name ), (string) get_edit_term_link( $port, 'product_cat' ) );
		}

		if ( ! $data['photos'] ) {
			$add( __( 'Aucune photo', 'icademie-page-annonce' ), $edit );
		}

		if ( ! $data['booking']['rental'] ) {
			$add( __( 'Pas d\'attribut « Date » : réservation par dates impossible', 'icademie-page-annonce' ), $edit );
		}

		return $items;
	}
}
