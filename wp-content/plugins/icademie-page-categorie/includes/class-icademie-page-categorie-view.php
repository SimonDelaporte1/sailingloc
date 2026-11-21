<?php
/**
 * Données affichées par la page catégorie.
 *
 * @package Icademie_Page_Categorie
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prépare le contenu de chaque zone : fil d'Ariane, en-tête, recherche, filtres, tri, bateaux,
 * pagination, contenu éditorial, questions et maillage.
 *
 * Trois listes de bateaux ont cette présentation : les catégories de produits, la boutique (tous les
 * bateaux, où mène le moteur de recherche sans destination) et les résultats de la recherche de
 * produits (loupe de l'en-tête du thème). Le contenu éditorial, les questions et le maillage sont
 * propres aux catégories.
 *
 * La liste des bateaux est celle de la page (requête principale de WordPress, avec les filtres, le
 * tri et la pagination appliqués par WooCommerce). Dans l'aperçu de l'éditeur, une catégorie qui a
 * des bateaux sert d'exemple.
 */
final class Icademie_Page_Categorie_View {

	/** Nombre de photos par carte (photo principale et galerie). */
	const PHOTOS_LIMIT = 5;

	/** Cartes dont la première photo est chargée tout de suite (première ligne à l'écran). */
	const EAGER_CARDS = 3;

	/**
	 * Données de toutes les zones.
	 *
	 * @return array|null Null hors d'une liste de produits (bloc placé ailleurs).
	 */
	public static function data() {
		$context = self::context();

		if ( ! $context ) {
			return null;
		}

		$term    = $context['term'];
		$query   = $context['query'];
		$base    = Icademie_Page_Categorie_Filters::base_url( $term );
		$params  = $context['preview'] ? array() : Icademie_Page_Categorie_Filters::current_params();
		$stats   = Icademie_Page_Categorie_Filters::stats( $term );
		$chips   = Icademie_Page_Categorie_Filters::chips( $params, $base );
		$paged   = max( 1, (int) $query->get( 'paged' ) );
		$content = self::content( $term );

		$is_search = 'search' === $context['type'];

		$data = array(
			'preview'     => $context['preview'],
			'type'        => $context['type'],
			'base'        => $base,
			// Mots cherchés, gardés par les formulaires des filtres et du tri.
			'base_args'   => Icademie_Page_Categorie_Filters::base_args(),
			'params'      => $params,
			'breadcrumb'  => self::breadcrumb( $context ),
			'header'      => self::header( $term, $stats, $context['type'] ),
			// Résultats d'une recherche : le champ de la recherche à la place du moteur de recherche.
			'search'      => $is_search ? null : self::search( $term, $params ),
			'text_search' => $is_search ? array(
				'action' => home_url( '/' ),
				'query'  => (string) get_search_query( false ),
			) : null,
			'filters'     => self::filters( $params, $stats ),
			'chips'       => $chips,
			'clear_url'   => Icademie_Page_Categorie_Filters::url( $base, Icademie_Page_Categorie_Filters::without_filters( $params ) ),
			'sort'        => array(
				'options' => Icademie_Page_Categorie_Filters::sort_options(),
				'current' => Icademie_Page_Categorie_Filters::current_sort( $params ),
				'hidden'  => array_diff_key( $params, array( 'orderby' => true ) ),
			),
			'results'     => array(
				'total' => (int) $query->found_posts,
				'cards' => self::cards( $query ),
			),
			'pagination'  => self::pagination( $query, $paged, $context['preview'] ),
			// Contenu éditorial et questions : sur la première page seulement (texte de référencement).
			'editorial'   => 1 === $paged ? $content['editorial'] : null,
			'faq'         => 1 === $paged ? $content['faq'] : array(),
			'links'       => $content['links'],
		);

		$data['setup'] = ( ! $context['preview'] && current_user_can( 'manage_woocommerce' ) ) ? self::missing( $data, $term, $stats ) : array();

		return $data;
	}

	/**
	 * Liste affichée : catégorie (ou autre taxonomie de produits), boutique ou résultats d'une
	 * recherche de produits, et requête.
	 *
	 * @return array|null « term », « query », « type » (category, shop, search), « preview », ou null
	 *                    hors d'une liste de produits.
	 */
	private static function context() {
		global $wp_query;

		if ( Icademie_Page_Categorie::is_editor_preview() ) {
			return self::preview_context();
		}

		if ( ! function_exists( 'is_product_taxonomy' ) || ! ( is_product_taxonomy() || is_shop() ) ) {
			return null;
		}

		$term = is_product_taxonomy() ? get_queried_object() : null;
		$term = $term instanceof WP_Term ? $term : null;

		if ( $term ) {
			$type = 'category';
		} else {
			$type = Icademie_Page_Categorie_Filters::is_search() ? 'search' : 'shop';
		}

		return array(
			'term'    => $term,
			'query'   => $wp_query,
			'type'    => $type,
			'preview' => false,
		);
	}

	/**
	 * Aperçu de l'éditeur : la première catégorie qui a des bateaux, ses premiers bateaux.
	 *
	 * @return array|null
	 */
	private static function preview_context() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 1,
			)
		);

		$term = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : null;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => Icademie_Page_Categorie::PER_PAGE,
			'paged'          => 1,
		);

		if ( $term ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Aperçu de l'éditeur seulement.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => (int) $term->term_id,
				),
			);
		}

		return array(
			'term'    => $term,
			'query'   => new WP_Query( $args ),
			'type'    => $term ? 'category' : 'shop',
			'preview' => true,
		);
	}

	/**
	 * Zone 2 : fil d'Ariane de WooCommerce (Accueil › France › Ajaccio).
	 *
	 * Les données structurées du fil d'Ariane sont ajoutées par WooCommerce, comme avec le bloc
	 * « Fil d'Ariane » du thème.
	 *
	 * @param array $context Liste affichée.
	 * @return array « items » (libellé, adresse) et « parent » (niveau au-dessus, pour le smartphone).
	 */
	private static function breadcrumb( $context ) {
		if ( ! class_exists( 'WC_Breadcrumb' ) ) {
			return array(
				'items'  => array(),
				'parent' => null,
			);
		}

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

		if ( $context['preview'] ) {
			if ( $context['term'] ) {
				foreach ( array_reverse( get_ancestors( $context['term']->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, 'product_cat' );

					if ( $ancestor instanceof WP_Term ) {
						$breadcrumbs->add_crumb( $ancestor->name, get_term_link( $ancestor ) );
					}
				}

				$breadcrumbs->add_crumb( $context['term']->name );
			}

			$crumbs = $breadcrumbs->get_breadcrumb();
		} else {
			$crumbs = $breadcrumbs->generate();

			$args['breadcrumb'] = $crumbs;

			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Action de WooCommerce (données structurées du fil d'Ariane).
			do_action( 'woocommerce_breadcrumb', $breadcrumbs, $args );
		}

		$items = array();

		foreach ( $crumbs as $crumb ) {
			$items[] = array(
				// Libellés de WooCommerce avec entités (« &ldquo; ») : décodés, échappés à l'affichage.
				'label' => html_entity_decode( wp_strip_all_tags( (string) $crumb[0] ), ENT_QUOTES, 'UTF-8' ),
				'url'   => isset( $crumb[1] ) ? (string) $crumb[1] : '',
			);
		}

		// Le dernier élément est la page affichée : pas de lien.
		if ( $items ) {
			$items[ count( $items ) - 1 ]['url'] = '';
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
	 * Zone 3 : titre (nom de la catégorie), introduction (description), nombre de bateaux, visuel
	 * (miniature de la catégorie).
	 *
	 * Boutique : titre de la page de la boutique. Résultats d'une recherche : « Résultats pour « … » »
	 * et nombre de bateaux trouvés.
	 *
	 * @param WP_Term|null $term  Catégorie.
	 * @param array        $stats Bateaux de la liste.
	 * @param string       $type  Liste affichée : category, shop ou search.
	 * @return array
	 */
	private static function header( $term, $stats, $type ) {
		$count = (int) $stats['count'];

		if ( $term instanceof WP_Term ) {
			$title       = $term->name;
			$description = term_description( $term );
			$image_id    = 'product_cat' === $term->taxonomy ? absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) ) : 0;
		} elseif ( 'search' === $type ) {
			$query       = trim( (string) get_search_query( false ) );
			/* translators: %s: mots cherchés. */
			$title       = '' !== $query ? sprintf( __( 'Résultats pour « %s »', 'icademie-page-categorie' ), $query ) : __( 'Résultats de la recherche', 'icademie-page-categorie' );
			$description = '';
			$image_id    = 0;
		} else {
			$shop_id     = wc_get_page_id( 'shop' );
			$title       = $shop_id > 0 ? get_the_title( $shop_id ) : __( 'Tous les bateaux', 'icademie-page-categorie' );
			$description = '';
			$image_id    = 0;
		}

		if ( 'search' === $type ) {
			/* translators: %s: nombre de bateaux trouvés. */
			$count_text = $count > 0 ? sprintf( _n( '%s bateau trouvé', '%s bateaux trouvés', $count, 'icademie-page-categorie' ), number_format_i18n( $count ) ) : __( 'Aucun bateau trouvé', 'icademie-page-categorie' );
		} else {
			/* translators: %s: nombre de bateaux de la liste. */
			$count_text = $count > 0 ? sprintf( _n( '%s bateau disponible', '%s bateaux disponibles', $count, 'icademie-page-categorie' ), number_format_i18n( $count ) ) : __( 'Aucun bateau pour le moment', 'icademie-page-categorie' );
		}

		// Chargement différé : le visuel n'est pas affiché sur smartphone, il n'y est pas téléchargé.
		$image = ( $image_id && wp_attachment_is_image( $image_id ) ) ? wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'class'    => 'icademie-categorie__head-image',
				'alt'      => '',
				'sizes'    => '(max-width: 1023px) 180px, 320px',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		) : '';

		return array(
			'title'       => $title,
			'description' => is_string( $description ) ? trim( $description ) : '',
			'count'       => $count,
			'count_text'  => $count_text,
			'image'       => $image,
		);
	}

	/**
	 * Zone 4 : moteur de recherche de la page d'accueil, rempli avec la recherche en cours.
	 *
	 * Destination = catégorie affichée ; passagers = minimum du filtre « Passagers » ; dates de
	 * l'adresse. Les autres filtres sont gardés dans des champs cachés.
	 *
	 * @param WP_Term|null $term   Catégorie.
	 * @param array        $params Filtres en cours.
	 * @return array|null Null sans l'extension de la page d'accueil.
	 */
	private static function search( $term, $params ) {
		if ( ! class_exists( 'Icademie_Page_Accueil_Search' ) ) {
			return null;
		}

		$form       = Icademie_Page_Accueil_Search::form_data();
		$attribute  = Icademie_Page_Accueil_Search::PASSENGERS_ATTRIBUTE;
		$passengers = Icademie_Page_Categorie_Filters::minimum_of( $attribute, Icademie_Page_Categorie_Filters::chosen( $params, $attribute ) );
		$current    = ( $term instanceof WP_Term && 'product_cat' === $term->taxonomy ) ? $term->slug : '';

		// La catégorie affichée est toujours proposée, même sans bateau.
		if ( '' !== $current && ! in_array( $current, wp_list_pluck( $form['destinations'], 'value' ), true ) ) {
			array_unshift(
				$form['destinations'],
				array(
					'value' => $current,
					'label' => $term->name,
					'depth' => 0,
				)
			);
		}

		$hidden = array_diff_key(
			$params,
			array_flip( array( 'filter_' . $attribute, 'query_type_' . $attribute, 'depart', 'retour' ) )
		);

		$destination_label = __( 'Toutes les destinations', 'icademie-page-categorie' );

		foreach ( $form['destinations'] as $option ) {
			if ( $option['value'] === $current ) {
				$destination_label = $option['label'];
			}
		}

		return $form + array(
			'destination' => $current,
			'depart'      => isset( $params['depart'] ) ? $params['depart'] : '',
			'retour'      => isset( $params['retour'] ) ? $params['retour'] : '',
			'passengers'  => $passengers ? (int) ceil( $passengers ) : 0,
			'hidden'      => $hidden,
			'summary'     => array(
				'destination' => $destination_label,
				'dates'       => self::dates_text( isset( $params['depart'] ) ? $params['depart'] : '', isset( $params['retour'] ) ? $params['retour'] : '' ),
				/* translators: %s: nombre de passagers. */
				'passengers'  => $passengers ? sprintf( _n( '%s passager', '%s passagers', (int) ceil( $passengers ), 'icademie-page-categorie' ), number_format_i18n( ceil( $passengers ) ) ) : '',
			),
		);
	}

	/**
	 * Dates de la recherche, courtes : « 12 juil. → 19 juil. ».
	 *
	 * @param string $depart Départ (AAAA-MM-JJ).
	 * @param string $retour Retour (AAAA-MM-JJ).
	 * @return string Vide sans date de départ.
	 */
	private static function dates_text( $depart, $retour ) {
		if ( '' === $depart ) {
			return '';
		}

		$format = static function ( $date ) {
			return wp_date( 'j M', (int) strtotime( $date . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
		};

		return '' === $retour ? $format( $depart ) : $format( $depart ) . ' → ' . $format( $retour );
	}

	/**
	 * Zone 5 : groupes de filtres, avec leurs valeurs et l'état choisi.
	 *
	 * Un groupe d'attribut n'est affiché que si au moins un bateau de la catégorie a une valeur pour
	 * cet attribut, ou si le filtre est déjà choisi. Une case à cocher n'est proposée que si un
	 * bateau de la catégorie a cette valeur (ou si elle est cochée).
	 *
	 * @param array $params Filtres en cours.
	 * @param array $stats  Bateaux de la catégorie.
	 * @return array « groups » (affichés) et « hidden » (libellés des filtres masqués, sans valeur).
	 */
	private static function filters( $params, $stats ) {
		$groups = array();
		$empty  = array();

		foreach ( Icademie_Page_Categorie_Filters::definitions() as $key => $definition ) {
			$group = self::filter_group( $key, $definition, $params, $stats );

			if ( $group ) {
				$groups[] = $group;
			} elseif ( isset( $definition['attribute'] ) && taxonomy_exists( wc_attribute_taxonomy_name( $definition['attribute'] ) ) ) {
				$empty[] = $definition['label'];
			}
		}

		return array(
			'groups' => $groups,
			'empty'  => $empty,
		);
	}

	/**
	 * Un groupe de filtres.
	 *
	 * @param string $key        Nom du champ.
	 * @param array  $definition Filtre.
	 * @param array  $params     Filtres en cours.
	 * @param array  $stats      Bateaux de la catégorie.
	 * @return array|null Null si le groupe n'a rien à proposer.
	 */
	private static function filter_group( $key, $definition, $params, $stats ) {
		$group = array(
			'key'     => $key,
			'control' => $definition['control'],
			'label'   => $definition['label'],
			'id'      => 'icademie-categorie-filtre-' . sanitize_html_class( $key ),
		);

		if ( 'price' === $definition['control'] ) {
			$bounds = $stats['price'];
			$min    = isset( $params['min_price'] ) ? (float) $params['min_price'] : null;
			$max    = isset( $params['max_price'] ) ? (float) $params['max_price'] : null;

			// Affiché dès qu'un bateau de la catégorie a un prix, même s'il est seul.
			if ( ( ! $bounds || $bounds['max'] <= 0 ) && null === $min && null === $max ) {
				return null;
			}

			$floor = $bounds ? (int) floor( $bounds['min'] ) : 0;
			$ceil  = (int) ceil( max( $bounds ? $bounds['max'] : 0, (float) $min, (float) $max ) );

			// Un seul prix (ou le même pour tous) : le curseur part de 0.
			if ( $ceil <= $floor ) {
				$floor = 0;
			}

			$ceil = max( $ceil, $floor + 1 );
			$span = $ceil - $floor;
			$step = $span > 2000 ? 50 : ( $span > 500 ? 10 : ( $span > 100 ? 5 : 1 ) );

			// Bornes sur le pas du curseur : son bout droit est atteignable (pas de maximum).
			$floor = (int) ( floor( $floor / $step ) * $step );
			$ceil  = (int) ( ceil( $ceil / $step ) * $step );

			return $group + array(
				'floor'    => $floor,
				'ceil'     => $ceil,
				'step'     => $step,
				'min'      => $min,
				'max'      => $max,
				'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			);
		}

		if ( 'rating' === $definition['control'] ) {
			$current = isset( $params[ Icademie_Page_Categorie_Filters::RATING_PARAM ] ) ? $params[ Icademie_Page_Categorie_Filters::RATING_PARAM ] : '';

			if ( ! wc_review_ratings_enabled() || ( $stats['rated'] < 1 && '' === $current ) ) {
				return null;
			}

			$choices = array();

			foreach ( Icademie_Page_Categorie_Filters::rating_choices() as $choice ) {
				$choices[] = array(
					'value'   => $choice,
					'label'   => Icademie_Page_Categorie_Filters::rating_label( $choice ),
					/* translators: %s: note sur 5. */
					'sr'      => sprintf( __( '%s sur 5 ou plus', 'icademie-page-categorie' ), number_format_i18n( (float) $choice, floor( (float) $choice ) === (float) $choice ? 0 : 1 ) ),
					'checked' => $current === $choice,
				);
			}

			return $group + array(
				'choices' => $choices,
				'current' => $current,
			);
		}

		if ( empty( $definition['attribute'] ) ) {
			return null;
		}

		$attribute = $definition['attribute'];
		$taxonomy  = wc_attribute_taxonomy_name( $attribute );
		$chosen    = Icademie_Page_Categorie_Filters::chosen( $params, $attribute );
		$available = isset( $stats['terms'][ $taxonomy ] ) ? $stats['terms'][ $taxonomy ] : array();

		if ( ! taxonomy_exists( $taxonomy ) || ( ! $available && ! $chosen ) ) {
			return null;
		}

		$group['attribute'] = $attribute;

		switch ( $definition['control'] ) {
			case 'checkboxes':
			case 'choice':
				$options = array();

				foreach ( Icademie_Page_Categorie_Filters::values( $attribute ) as $slug => $value ) {
					$checked = in_array( (string) $slug, $chosen, true );

					if ( $checked || isset( $available[ $slug ] ) ) {
						$options[] = array(
							'value'   => (string) $slug,
							'label'   => $value['name'],
							'checked' => $checked,
						);
					}
				}

				if ( ! $options ) {
					return null;
				}

				$visible = isset( $definition['visible'] ) ? max( 1, (int) $definition['visible'] ) : 0;

				return $group + array(
					'options' => $options,
					// Valeurs repliées sous « Voir plus » (jamais une valeur cochée).
					'visible' => self::visible_count( $options, $visible ),
				);

			case 'range':
				$scale = Icademie_Page_Categorie_Filters::scale( $attribute );

				if ( count( $scale ) < 2 ) {
					return null;
				}

				$positions = array();

				foreach ( $scale as $position => $value ) {
					if ( in_array( $value['slug'], $chosen, true ) ) {
						$positions[] = $position;
					}
				}

				$unit  = isset( $definition['unit'] ) ? $definition['unit'] : '';
				$steps = array();

				foreach ( $scale as $value ) {
					$steps[] = Icademie_Page_Categorie_Filters::with_unit( $value['name'], $unit );
				}

				return $group + array(
					'steps' => $steps,
					'min'   => $positions ? min( $positions ) : 0,
					'max'   => $positions ? max( $positions ) : count( $scale ) - 1,
				);

			case 'stepper':
				$scale   = Icademie_Page_Categorie_Filters::scale( $attribute );
				$numbers = wp_list_pluck( $scale, 'number' );
				$open    = in_array( true, wp_list_pluck( $scale, 'open' ), true );
				$minimum = Icademie_Page_Categorie_Filters::minimum_of( $attribute, $chosen );

				return $group + array(
					'value' => $minimum ? (int) ceil( $minimum ) : 0,
					'max'   => ( $numbers && ! $open ) ? (int) ceil( max( $numbers ) ) : Icademie_Page_Categorie_Filters::STEPPER_LIMIT,
				);

			case 'minimum':
				$scale   = Icademie_Page_Categorie_Filters::scale( $attribute );
				$minimum = Icademie_Page_Categorie_Filters::minimum_of( $attribute, $chosen );
				$options = array();
				$current = '';

				foreach ( $scale as $value ) {
					if ( null !== $minimum && '' === $current && $value['number'] >= $minimum ) {
						$current = $value['slug'];
					}

					$options[] = array(
						'value' => $value['slug'],
						'label' => preg_replace( '/\s*\+\s*$/u', "\u{00A0}+", $value['name'] ),
					);
				}

				if ( ! $options ) {
					return null;
				}

				return $group + array(
					'options' => $options,
					'current' => $current,
				);
		}

		return null;
	}

	/**
	 * Nombre de cases affichées avant « Voir plus » : toutes les cases cochées restent visibles.
	 *
	 * @param array[] $options Cases.
	 * @param int     $visible Nombre souhaité (0 : toutes).
	 * @return int
	 */
	private static function visible_count( $options, $visible ) {
		if ( ! $visible || count( $options ) <= $visible + 1 ) {
			return count( $options );
		}

		$last_checked = -1;

		foreach ( $options as $index => $option ) {
			if ( $option['checked'] ) {
				$last_checked = $index;
			}
		}

		return max( $visible, $last_checked + 1 );
	}

	/**
	 * Zone 7 : cartes des bateaux de la page.
	 *
	 * @param WP_Query $query Requête de la liste.
	 * @return array[]
	 */
	private static function cards( $query ) {
		return self::product_cards( $query->posts, false );
	}

	/**
	 * Cartes de bateaux (aussi utilisées par la fiche annonce : « Bateaux similaires »).
	 *
	 * @param array $products Bateaux : objets WC_Product, articles ou identifiants.
	 * @param bool  $lazy     Toutes les photos en chargement différé (liste en bas de page).
	 * @return array[] Cartes, pour render_card().
	 */
	public static function product_cards( $products, $lazy = true ) {
		$list   = array();
		$images = array();

		foreach ( (array) $products as $item ) {
			$product = $item instanceof WC_Product ? $item : wc_get_product( $item );

			if ( $product instanceof WC_Product ) {
				$list[] = $product;
				$images = array_merge( $images, self::photo_ids( $product ) );
			}
		}

		// Photos des galeries chargées en une requête.
		if ( $images ) {
			_prime_post_caches( array_unique( $images ), false, true );
		}

		$cards = array();

		foreach ( $list as $index => $product ) {
			// Au-delà des premières positions : photos en chargement différé.
			$cards[] = self::card( $product, $lazy ? $index + self::EAGER_CARDS : $index );
		}

		return $cards;
	}

	/**
	 * Code HTML d'une carte (élément de liste), comme dans les pages catégorie.
	 *
	 * La feuille de styles de l'extension doit être chargée, et la carte placée dans un élément
	 * de classe « icademie-categorie ».
	 *
	 * @param array $card Carte (voir product_cards()).
	 * @return string
	 */
	public static function render_card( $card ) {
		$icon = array( 'Icademie_Page_Categorie', 'icon' );
		/* translators: Note moyenne, par exemple « 4,5 ». */
		$rating_format = __( '%s sur 5', 'icademie-page-categorie' );

		ob_start();
		include dirname( __DIR__ ) . '/templates/card.php';

		return (string) ob_get_clean();
	}

	/**
	 * Photos d'un bateau : photo principale puis galerie.
	 *
	 * @param WC_Product $product Bateau.
	 * @return int[]
	 */
	private static function photo_ids( $product ) {
		$ids = array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) );

		return array_slice( array_values( array_unique( $ids ) ), 0, self::PHOTOS_LIMIT );
	}

	/**
	 * Carte d'un bateau : photos, type et port, titre, capacité, note, prix par jour.
	 *
	 * Mêmes informations que les cartes de la page d'accueil, avec les cabines comme dans le zoning.
	 *
	 * @param WC_Product $product Bateau.
	 * @param int        $index   Position sur la page.
	 * @return array
	 */
	private static function card( $product, $index ) {
		$zones  = 'Icademie_Page_Accueil_Zones';
		$has_hp = class_exists( $zones );

		$type_attribute       = $has_hp ? Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE : 'type-de-bateau';
		$passengers_attribute = $has_hp ? Icademie_Page_Accueil_Zones::PASSENGERS_ATTRIBUTE : 'passagers';

		$kicker = array_filter(
			array(
				self::attribute_text( $product, $type_attribute ),
				$has_hp ? Icademie_Page_Accueil_Zones::port( $product ) : '',
			)
		);

		$specs  = array();
		$people = self::attribute_max( $product, $passengers_attribute );
		$cabins = self::attribute_max( $product, 'cabine' );
		$length = self::attribute_text( $product, 'longueur-du-bateau' );

		if ( $people ) {
			/* translators: %s: nombre de passagers. */
			$specs[] = sprintf( _n( '%s passager', '%s passagers', (int) $people['number'], 'icademie-page-categorie' ), $people['text'] );
		}

		if ( $cabins ) {
			/* translators: %s: nombre de cabines. */
			$specs[] = sprintf( _n( '%s cabine', '%s cabines', (int) $cabins['number'], 'icademie-page-categorie' ), $cabins['text'] );
		}

		if ( '' !== $length ) {
			$specs[] = $has_hp ? Icademie_Page_Accueil_Zones::length_text( $length ) : $length;
		}

		$photos = array();

		foreach ( self::photo_ids( $product ) as $position => $image_id ) {
			$first = 0 === $position;

			$attributes = array(
				'alt'      => '',
				'sizes'    => '(max-width: 599px) calc(100vw - 32px), (max-width: 1023px) calc(50vw - 42px), 290px',
				'loading'  => ( $first && $index < self::EAGER_CARDS ) ? 'eager' : 'lazy',
				'decoding' => 'async',
			);

			// Première photo de la première carte : image principale de la page.
			if ( $first && 0 === $index ) {
				$attributes['fetchpriority'] = 'high';
			}

			$photos[] = wp_get_attachment_image( $image_id, 'medium_large', false, $attributes );
		}

		return array(
			'id'      => $product->get_id(),
			'title'   => $product->get_name(),
			'url'     => $product->get_permalink(),
			'photos'  => array_filter( $photos ),
			'kicker'  => implode( ' · ', $kicker ),
			'specs'   => implode( ' · ', $specs ),
			'rating'  => (float) $product->get_average_rating(),
			'reviews' => (int) $product->get_review_count(),
			'price'   => $product->get_price_html(),
			'per_day' => class_exists( 'Icademie_Dates_Produit' ) && Icademie_Dates_Produit::requires_dates( $product ),
		);
	}

	/**
	 * Valeurs d'un attribut du produit, séparées par des virgules.
	 *
	 * @param WC_Product $product   Produit.
	 * @param string     $attribute Slug de l'attribut, sans « pa_ ».
	 * @return string
	 */
	private static function attribute_text( $product, $attribute ) {
		if ( class_exists( 'Icademie_Page_Accueil_Zones' ) ) {
			return Icademie_Page_Accueil_Zones::attribute_text( $product, $attribute );
		}

		return trim( wp_strip_all_tags( html_entity_decode( (string) $product->get_attribute( wc_attribute_taxonomy_name( $attribute ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Plus grande valeur chiffrée d'un attribut du produit : un bateau pour 1 à 11 passagers a les
	 * valeurs 1, 2… 11 ; sa capacité est 11. « 4+ » s'affiche « 4+ ».
	 *
	 * @param WC_Product $product   Produit.
	 * @param string     $attribute Slug de l'attribut, sans « pa_ ».
	 * @return array|null « number » et « text », ou null si l'attribut n'est pas renseigné.
	 */
	public static function attribute_max( $product, $attribute ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$slugs  = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'slugs' ) );
		$values = Icademie_Page_Categorie_Filters::values( $attribute );
		$best   = null;

		foreach ( $slugs as $slug ) {
			if ( ! isset( $values[ $slug ] ) || null === $values[ $slug ]['number'] ) {
				continue;
			}

			$value = $values[ $slug ];

			if ( null === $best || array( $value['number'], (int) $value['open'] ) > array( $best['number'], (int) $best['open'] ) ) {
				$best = $value;
			}
		}

		if ( ! $best ) {
			return null;
		}

		$text = number_format_i18n( $best['number'], floor( $best['number'] ) === $best['number'] ? 0 : 1 );

		return array(
			'number' => $best['number'],
			'text'   => $best['open'] ? $text . '+' : $text,
		);
	}

	/**
	 * Zone 8 : pagination (ordinateur et tablette) et « Afficher plus » (smartphone).
	 *
	 * Les adresses gardent les filtres, le tri et les dates.
	 *
	 * @param WP_Query $query   Requête de la liste.
	 * @param int      $paged   Page affichée.
	 * @param bool     $preview Aperçu de l'éditeur.
	 * @return array
	 */
	private static function pagination( $query, $paged, $preview ) {
		$total = (int) $query->max_num_pages;
		$found = (int) $query->found_posts;
		$per   = max( 1, (int) $query->get( 'posts_per_page' ) );
		$links = array();

		if ( $total > 1 && ! $preview ) {
			$links = paginate_links(
				array(
					'base'      => esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) ),
					'format'    => '',
					'add_args'  => false,
					'current'   => $paged,
					'total'     => $total,
					'type'      => 'array',
					'mid_size'  => 1,
					'end_size'  => 1,
					'prev_text' => '<span aria-hidden="true">‹</span><span class="icademie-categorie__sr">' . esc_html__( 'Page précédente', 'icademie-page-categorie' ) . '</span>',
					'next_text' => '<span aria-hidden="true">›</span><span class="icademie-categorie__sr">' . esc_html__( 'Page suivante', 'icademie-page-categorie' ) . '</span>',
				)
			);
		}

		return array(
			'links'  => is_array( $links ) ? $links : array(),
			'next'   => ( ! $preview && $paged < $total ) ? get_pagenum_link( $paged + 1 ) : '',
			'shown'  => min( $found, $paged * $per ),
			'total'  => $found,
			'paged'  => $paged,
			'pages'  => $total,
		);
	}

	/**
	 * Zones 9 et 10 : contenu éditorial, questions et maillage de la catégorie.
	 *
	 * @param WP_Term|null $term Catégorie.
	 * @return array
	 */
	private static function content( $term ) {
		$empty = array(
			'editorial' => null,
			'faq'       => array(),
			'links'     => array(),
		);

		if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
			return $empty;
		}

		$editorial = Icademie_Page_Categorie_Content::editorial( $term->term_id );
		$faq       = array();

		foreach ( Icademie_Page_Categorie_Content::faq( $term->term_id ) as $item ) {
			$faq[] = array(
				'question' => $item['question'],
				'answer'   => Icademie_Page_Categorie_Content::format( $item['answer'] ),
			);
		}

		$links = Icademie_Page_Categorie_Content::links( $term->term_id );

		return array(
			'editorial' => ( '' !== $editorial['title'] || '' !== $editorial['content'] ) ? array(
				'title'   => $editorial['title'],
				'content' => '' !== $editorial['content'] ? Icademie_Page_Categorie_Content::format( $editorial['content'] ) : '',
			) : null,
			'faq'       => $faq,
			'links'     => $links,
		);
	}

	/**
	 * Données structurées des questions fréquentes (FAQPage, schema.org).
	 *
	 * @param array[] $faq Questions et réponses mises en forme.
	 * @return string JSON, à placer dans une balise script.
	 */
	public static function faq_schema( $faq ) {
		$allowed = array(
			'a'      => array( 'href' => true ),
			'p'      => array(),
			'br'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
		);

		$entities = array();

		foreach ( $faq as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => trim( wp_kses( $item['answer'], $allowed ) ),
				),
			);
		}

		return (string) wp_json_encode(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			),
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);
	}

	/**
	 * Ce qui reste à compléter, pour les gestionnaires de la boutique : libellé et adresse.
	 *
	 * @param array        $data  Données des zones.
	 * @param WP_Term|null $term  Catégorie.
	 * @param array        $stats Bateaux de la catégorie.
	 * @return array[]
	 */
	private static function missing( $data, $term, $stats ) {
		if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
			return array();
		}

		$edit  = (string) get_edit_term_link( $term, 'product_cat' );
		$items = array();
		$add   = static function ( $label, $url ) use ( &$items ) {
			$items[] = array(
				'label' => $label,
				'url'   => $url,
			);
		};

		if ( '' === $data['header']['description'] ) {
			$add( __( 'Introduction : la description de la catégorie est vide', 'icademie-page-categorie' ), $edit );
		}

		if ( '' === $data['header']['image'] ) {
			$add( __( 'Visuel de l\'en-tête : aucune miniature choisie pour la catégorie', 'icademie-page-categorie' ), $edit );
		}

		$content = self::content( $term );

		if ( ! $content['editorial'] ) {
			$add( __( 'Contenu éditorial : vide, la zone est masquée', 'icademie-page-categorie' ), $edit . '#icademie-categorie-page' );
		}

		if ( ! $content['faq'] ) {
			$add( __( 'Questions fréquentes : aucune question avec sa réponse', 'icademie-page-categorie' ), $edit . '#icademie-categorie-page' );
		}

		if ( ! $content['links'] ) {
			$add( __( 'Maillage interne : aucun lien, la zone est masquée', 'icademie-page-categorie' ), $edit . '#icademie-categorie-page' );
		}

		if ( $data['filters']['empty'] && $stats['count'] > 0 ) {
			$add(
				sprintf(
					/* translators: %s: noms des filtres. */
					__( 'Filtres masqués, aucun bateau de la catégorie n\'a de valeur pour ces attributs : %s', 'icademie-page-categorie' ),
					implode( ', ', $data['filters']['empty'] )
				),
				admin_url( 'edit.php?post_type=product' )
			);
		}

		return $items;
	}
}
