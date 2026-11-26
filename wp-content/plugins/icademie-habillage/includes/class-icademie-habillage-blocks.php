<?php
/**
 * Blocs des modèles : en-tête de page, liste d'articles, articles liés, page introuvable,
 * bandeau d'assistance, suite de la réservation.
 *
 * Blocs déclarés uniquement en PHP : l'éditeur du site les affiche avec un aperçu calculé par le
 * serveur.
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blocs.
 */
final class Icademie_Habillage_Blocks {

	/** Articles par carte de la liste des articles liés. */
	const RELATED = 3;

	/** Mots lus par minute (temps de lecture). */
	const WORDS_PER_MINUTE = 200;

	/**
	 * Branche les blocs.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// Titres des listes d'articles sans « Catégorie : », « Étiquette : »… (le surtitre le dit).
		add_filter( 'get_the_archive_title_prefix', array( __CLASS__, 'archive_title_prefix' ) );
	}

	/**
	 * Enregistre les blocs.
	 */
	public static function register() {
		$blocks = array(
			'entete-page'       => array( __( 'En-tête de page SailingLoc', 'icademie-habillage' ), __( 'Fil d\'Ariane, surtitre, titre, date et temps de lecture (articles), étapes de la réservation (panier, validation, confirmation).', 'icademie-habillage' ), 'heading' ),
			'articles'          => array( __( 'Liste d\'articles SailingLoc', 'icademie-habillage' ), __( 'Articles de la liste affichée (catégorie, étiquette, recherche…) en cartes, avec la pagination.', 'icademie-habillage' ), 'grid-view' ),
			'articles-lies'     => array( __( 'Articles liés SailingLoc', 'icademie-habillage' ), __( 'Trois articles de la même catégorie, sous un article.', 'icademie-habillage' ), 'excerpt-view' ),
			'page-introuvable'  => array( __( 'Page introuvable SailingLoc', 'icademie-habillage' ), __( 'Message, recherche et liens utiles pour une adresse inconnue.', 'icademie-habillage' ), 'warning' ),
			'assistance'        => array( __( 'Assistance pendant la location', 'icademie-habillage' ), __( 'Bandeau de la charte : ligne d\'assistance (Réglages › Page contact) et bouton d\'appel.', 'icademie-habillage' ), 'sos' ),
			'suite-reservation' => array( __( 'Suite de la réservation SailingLoc', 'icademie-habillage' ), __( 'Après la commande : liens vers « Mes locations » et vers les bateaux.', 'icademie-habillage' ), 'arrow-right-alt' ),
		);

		foreach ( $blocks as $name => $block ) {
			$attributes = array();

			if ( 'entete-page' === $name ) {
				$attributes = array(
					'type'  => array(
						'type'    => 'string',
						'default' => 'page',
					),
					'etape' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'titre' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				);
			}

			register_block_type(
				'icademie/' . $name,
				array(
					'api_version'     => 3,
					'title'           => $block[0],
					'description'     => $block[1],
					'category'        => 'theme',
					'icon'            => $block[2],
					'attributes'      => $attributes,
					'supports'        => array(
						'autoRegister' => true,
						'html'         => false,
						'reusable'     => false,
						'inserter'     => 'assistance' === $name,
					),
					'render_callback' => array( __CLASS__, 'render_' . str_replace( '-', '_', $name ) ),
					'style_handles'   => array( Icademie_Habillage::HANDLE ),
				)
			);
		}
	}

	/**
	 * Préfixe du titre des listes d'articles : aucun.
	 *
	 * @return string
	 */
	public static function archive_title_prefix() {
		return '';
	}

	/**
	 * Gabarit de l'extension, rendu dans un tampon.
	 *
	 * @param string $name Gabarit (templates/{name}.php).
	 * @param array  $data Données.
	 * @return string
	 */
	private static function view( $name, $data ) {
		ob_start();
		include dirname( __DIR__ ) . '/templates/' . $name . '.php';

		return (string) ob_get_clean();
	}

	// ------------------------------------------------------------------------------------------
	// En-tête de page.
	// ------------------------------------------------------------------------------------------

	/**
	 * En-tête de page.
	 *
	 * @param array $attributes Attributs : type (page, article, liste, tunnel), etape, titre.
	 * @return string
	 */
	public static function render_entete_page( $attributes ) {
		$type = isset( $attributes['type'] ) ? (string) $attributes['type'] : 'page';
		$data = array(
			'type'       => $type,
			'crumbs'     => 'tunnel' === $type ? array() : self::breadcrumb(),
			'kicker'     => '',
			'kicker_url' => '',
			'title'      => '',
			'intro'      => '',
			'meta'       => array(),
			'image'      => '',
			'steps'      => 'tunnel' === $type ? self::steps( (int) ( $attributes['etape'] ?? 0 ) ) : array(),
		);

		if ( 'article' === $type && is_singular() ) {
			$post       = get_queried_object();
			$categories = get_the_category( $post->ID );

			if ( $categories ) {
				$data['kicker']     = $categories[0]->name;
				$data['kicker_url'] = (string) get_category_link( $categories[0] );
			}

			$data['title'] = get_the_title( $post );
			$data['intro'] = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
			$data['meta']  = array(
				array(
					'icon'     => 'calendar',
					/* translators: %s: date de publication. */
					'text'     => sprintf( __( 'Publié le %s', 'icademie-habillage' ), get_the_date( 'j F Y', $post ) ),
					'datetime' => get_the_date( 'c', $post ),
				),
				array(
					'icon' => 'clock',
					'text' => self::reading_time( $post ),
				),
			);
			$data['image'] = has_post_thumbnail( $post ) ? get_the_post_thumbnail(
				$post,
				'large',
				array(
					'class'         => 'icademie-habillage__entete-image',
					'sizes'         => '(max-width: 1023px) 100vw, 960px',
					'fetchpriority' => 'high',
					'loading'       => 'eager',
				)
			) : '';
		} elseif ( 'liste' === $type ) {
			if ( is_search() ) {
				$data['kicker'] = __( 'Recherche', 'icademie-habillage' );
				/* translators: %s: mots cherchés. */
				$data['title'] = '' !== get_search_query( false ) ? sprintf( __( 'Résultats pour « %s »', 'icademie-habillage' ), get_search_query( false ) ) : __( 'Recherche dans le site', 'icademie-habillage' );
			} elseif ( is_home() ) {
				$data['kicker'] = __( 'Le journal', 'icademie-habillage' );
				$data['title']  = get_option( 'page_for_posts' ) ? get_the_title( (int) get_option( 'page_for_posts' ) ) : __( 'Articles', 'icademie-habillage' );
			} else {
				$data['kicker'] = is_category() ? __( 'Articles', 'icademie-habillage' ) : ( is_tag() ? __( 'Étiquette', 'icademie-habillage' ) : ( is_author() ? __( 'Auteur', 'icademie-habillage' ) : __( 'Archives', 'icademie-habillage' ) ) );
				$data['title']  = wp_strip_all_tags( get_the_archive_title() );
				$data['intro']  = wp_strip_all_tags( (string) get_the_archive_description() );
			}

			global $wp_query;
			$count = (int) $wp_query->found_posts;
			/* translators: %s: nombre de résultats ou d'articles. */
			$text         = is_search() ? _n( '%s résultat', '%s résultats', $count, 'icademie-habillage' ) : _n( '%s article', '%s articles', $count, 'icademie-habillage' );
			$data['meta'] = $count > 0 ? array(
				array(
					'icon' => '',
					'text' => sprintf( $text, number_format_i18n( $count ) ),
				),
			) : array();
		} elseif ( is_singular() ) {
			$data['title'] = self::page_title();
			$data['image'] = 'page' === $type && has_post_thumbnail() && ! self::is_service_page() ? get_the_post_thumbnail(
				get_queried_object_id(),
				'large',
				array(
					'class'   => 'icademie-habillage__entete-image',
					'sizes'   => '(max-width: 1023px) 100vw, 960px',
					'loading' => 'eager',
				)
			) : '';
		}

		if ( isset( $attributes['titre'] ) && false === $attributes['titre'] ) {
			$data['title'] = '';
		}

		return self::view( 'entete-page', $data );
	}

	/**
	 * Titre de la page : celui de la page du compte affichée (« Mes locations », « Adresses »…),
	 * sinon celui de la page.
	 *
	 * @return string
	 */
	private static function page_title() {
		if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'WC' ) && WC()->query ) {
			$endpoint = WC()->query->get_current_endpoint();

			if ( '' !== $endpoint ) {
				$title = WC()->query->get_endpoint_title( $endpoint );

				if ( '' !== (string) $title ) {
					return (string) $title;
				}
			}
		}

		return get_the_title( get_queried_object_id() );
	}

	/**
	 * Page de service (compte, panier, validation, pages de WC Vendors) ?
	 *
	 * @return bool
	 */
	private static function is_service_page() {
		return Icademie_Habillage_Templates::is_wide_page();
	}

	/**
	 * Temps de lecture d'un article (« 4 min de lecture »).
	 *
	 * @param WP_Post $post Article.
	 * @return string
	 */
	public static function reading_time( $post ) {
		$text    = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
		$words   = '' === $text ? 0 : count( (array) preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
		$minutes = max( 1, (int) round( $words / self::WORDS_PER_MINUTE ) );

		/* translators: %d: minutes. */
		return sprintf( _n( '%d min de lecture', '%d min de lecture', $minutes, 'icademie-habillage' ), $minutes );
	}

	/**
	 * Fil d'Ariane de WooCommerce (Accueil › Catégorie › Article ; Accueil › Mon compte › …).
	 *
	 * @return array[] Libellé et adresse (vide pour la page affichée).
	 */
	private static function breadcrumb() {
		$items = array();

		if ( class_exists( 'WC_Breadcrumb' ) ) {
			$breadcrumb = new WC_Breadcrumb();
			$breadcrumb->add_crumb( _x( 'Home', 'breadcrumb', 'woocommerce' ), home_url( '/' ) );

			foreach ( $breadcrumb->generate() as $crumb ) {
				$items[] = array(
					'label' => html_entity_decode( wp_strip_all_tags( (string) $crumb[0] ), ENT_QUOTES, 'UTF-8' ),
					'url'   => isset( $crumb[1] ) ? (string) $crumb[1] : '',
				);
			}
		}

		if ( $items ) {
			$items[ count( $items ) - 1 ]['url'] = '';
		}

		return $items;
	}

	/**
	 * Étapes de la réservation.
	 *
	 * @param int $current Étape affichée (1 : panier, 2 : validation, 3 : confirmation).
	 * @return array[]
	 */
	private static function steps( $current ) {
		$steps = array(
			array( __( 'Votre location', 'icademie-habillage' ), function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '' ),
			array( __( 'Coordonnées et paiement', 'icademie-habillage' ), function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '' ),
			array( __( 'Confirmation', 'icademie-habillage' ), '' ),
		);
		$out   = array();

		foreach ( $steps as $index => $step ) {
			$number = $index + 1;
			$state  = $number < $current ? 'done' : ( $number === $current ? 'current' : 'todo' );
			$out[]  = array(
				'number' => $number,
				'label'  => $step[0],
				// Étape passée : lien pour y revenir (pas depuis la confirmation : la commande est passée).
				'url'    => 'done' === $state && $current < 3 ? $step[1] : '',
				'state'  => $state,
			);
		}

		return $out;
	}

	// ------------------------------------------------------------------------------------------
	// Articles.
	// ------------------------------------------------------------------------------------------

	/**
	 * Carte d'un article.
	 *
	 * @param WP_Post $post    Article.
	 * @param int     $heading Niveau du titre.
	 * @return array
	 */
	private static function card( $post, $heading = 2 ) {
		$categories = 'post' === $post->post_type ? get_the_category( $post->ID ) : array();
		$type       = get_post_type_object( $post->post_type );
		$kinds      = array(
			'product' => __( 'Bateau', 'icademie-habillage' ),
			'page'    => __( 'Page', 'icademie-habillage' ),
		);

		return array(
			'url'      => get_permalink( $post ),
			'title'    => get_the_title( $post ),
			'heading'  => $heading,
			'kicker'   => $categories ? $categories[0]->name : ( isset( $kinds[ $post->post_type ] ) ? $kinds[ $post->post_type ] : ( $type && 'post' !== $post->post_type ? $type->labels->singular_name : '' ) ),
			'excerpt'  => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 24, '…' ),
			'date'     => 'post' === $post->post_type ? get_the_date( 'j F Y', $post ) : '',
			'datetime' => get_the_date( 'c', $post ),
			'reading'  => 'post' === $post->post_type ? self::reading_time( $post ) : '',
			'image'    => has_post_thumbnail( $post ) ? get_the_post_thumbnail(
				$post,
				'medium_large',
				array(
					'class'    => 'icademie-habillage__carte-image',
					'alt'      => '',
					'sizes'    => '(max-width: 599px) 100vw, (max-width: 1023px) 50vw, 384px',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			) : '',
		);
	}

	/**
	 * Liste d'articles de la page affichée, avec la pagination.
	 *
	 * @return string
	 */
	public static function render_articles() {
		global $wp_query;

		$cards = array();

		if ( $wp_query instanceof WP_Query ) {
			foreach ( $wp_query->posts as $post ) {
				$cards[] = self::card( $post );
			}
		}

		$pages = array();

		if ( $wp_query instanceof WP_Query && $wp_query->max_num_pages > 1 ) {
			$pages = (array) paginate_links(
				array(
					'type'      => 'array',
					'current'   => max( 1, (int) get_query_var( 'paged' ) ),
					'total'     => (int) $wp_query->max_num_pages,
					'prev_text' => __( 'Précédent', 'icademie-habillage' ),
					'next_text' => __( 'Suivant', 'icademie-habillage' ),
					'mid_size'  => 1,
				)
			);
		}

		return self::view(
			'articles',
			array(
				'cards'  => $cards,
				'pages'  => $pages,
				'search' => is_search(),
			)
		);
	}

	/**
	 * Articles liés : même catégorie, sinon les derniers articles.
	 *
	 * @return string
	 */
	public static function render_articles_lies() {
		if ( ! is_singular( 'post' ) ) {
			return '';
		}

		$post  = get_queried_object();
		$args  = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => self::RELATED,
			'post__not_in'        => array( $post->ID ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$terms = wp_get_post_categories( $post->ID );
		$posts = $terms ? get_posts( $args + array( 'category__in' => $terms ) ) : array();

		if ( count( $posts ) < self::RELATED ) {
			$posts = array_merge( $posts, get_posts( array_merge( $args, array( 'post__not_in' => array_merge( array( $post->ID ), wp_list_pluck( $posts, 'ID' ) ), 'posts_per_page' => self::RELATED - count( $posts ) ) ) ) );
		}

		if ( ! $posts ) {
			return '';
		}

		$cards = array();

		foreach ( $posts as $related ) {
			$cards[] = self::card( $related, 3 );
		}

		return self::view( 'articles-lies', array( 'cards' => $cards ) );
	}

	// ------------------------------------------------------------------------------------------
	// Page introuvable, assistance, suite de la réservation.
	// ------------------------------------------------------------------------------------------

	/**
	 * Page introuvable.
	 *
	 * @return string
	 */
	public static function render_page_introuvable() {
		$contact = method_exists( 'Icademie_Page_Contact', 'find_contact_page' ) ? (int) Icademie_Page_Contact::find_contact_page() : 0;

		return self::view(
			'page-introuvable',
			array(
				'code'    => Icademie_Habillage_Templates::is_forbidden() ? 403 : 404,
				'shop'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
				'home'    => home_url( '/' ),
				'contact' => $contact && 'publish' === get_post_status( $contact ) ? get_permalink( $contact ) : '',
			)
		);
	}

	/**
	 * Ligne d'assistance (Réglages › Page contact).
	 *
	 * @return array « number », « href » ; vides sans réglage.
	 */
	public static function assistance_line() {
		$number = class_exists( 'Icademie_Page_Contact_Settings' ) ? trim( (string) Icademie_Page_Contact_Settings::get( 'assistance' ) ) : '';

		return array(
			'number' => $number,
			'href'   => '' !== $number ? 'tel:' . preg_replace( '/[^0-9+]/', '', $number ) : '',
		);
	}

	/**
	 * Bandeau d'assistance pendant la location.
	 *
	 * @param array $attributes Attributs (« court » : version réduite, sans bouton).
	 * @return string
	 */
	public static function render_assistance( $attributes = array() ) {
		$line    = self::assistance_line();
		$contact = method_exists( 'Icademie_Page_Contact', 'find_contact_page' ) ? (int) Icademie_Page_Contact::find_contact_page() : 0;

		if ( '' === $line['number'] && ! $contact ) {
			return '';
		}

		return self::view(
			'assistance',
			array(
				'number'  => $line['number'],
				'href'    => $line['href'],
				'contact' => $contact && 'publish' === get_post_status( $contact ) ? get_permalink( $contact ) : '',
				'short'   => ! empty( $attributes['court'] ),
			)
		);
	}

	/**
	 * Suite de la réservation (page de confirmation).
	 *
	 * @return string
	 */
	public static function render_suite_reservation() {
		return self::view(
			'suite-reservation',
			array(
				'orders' => is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '',
				'shop'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
			)
		);
	}
}
