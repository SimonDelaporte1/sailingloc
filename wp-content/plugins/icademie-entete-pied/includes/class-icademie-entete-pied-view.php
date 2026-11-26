<?php
/**
 * Données de l'en-tête et du pied de page.
 *
 * @package Icademie_Entete_Pied
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prépare le contenu de l'en-tête (logo, menu, boutons) et du pied de page (colonnes de liens,
 * assistance, réseaux sociaux, liens légaux).
 */
final class Icademie_Entete_Pied_View {

	/** Profondeur des sous-menus lus dans le menu de navigation. */
	const MAX_DEPTH = 3;

	/** Niveaux de catégories du menu des destinations (pays, région, port…). */
	const DESTINATIONS_DEPTH = 3;

	/** Centre d'aide du pied de page : catégorie d'articles (slug). */
	const HELP_CATEGORY = 'aide';

	/** « Assurance propriétaire » du pied de page : article ou page (slug). */
	const INSURANCE_POST = 'aide-assistance-assurance-caution';

	/**
	 * Logo typographique de la charte : « Sailing » et « Loc » de deux couleurs.
	 *
	 * @return array « first », « last » (partie colorée, vide si le nom ne finit pas par « Loc »), « name ».
	 */
	public static function logo() {
		$name = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

		if ( '' === $name ) {
			$name = 'SailingLoc';
		}

		if ( preg_match( '/^(.+?)(Loc)$/u', $name, $m ) ) {
			return array(
				'first' => $m[1],
				'last'  => $m[2],
				'name'  => $name,
			);
		}

		return array(
			'first' => $name,
			'last'  => '',
			'name'  => $name,
		);
	}

	/**
	 * Menu de navigation affiché : celui choisi dans les réglages, sinon celui de l'en-tête du thème
	 * (bloc Navigation de la partie « header », telle que modifiée dans l'éditeur du site), sinon le
	 * plus récent, comme le fait WordPress.
	 *
	 * @return WP_Post|null
	 */
	public static function menu_post() {
		$id = Icademie_Entete_Pied_Settings::menu_id();

		if ( ! $id && function_exists( 'get_block_template' ) ) {
			$part = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
			$id   = $part ? self::navigation_ref( parse_blocks( (string) $part->content ) ) : 0;
		}

		if ( $id ) {
			$post = get_post( $id );

			if ( $post instanceof WP_Post && 'wp_navigation' === $post->post_type && 'publish' === $post->post_status ) {
				return $post;
			}
		}

		$latest = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $latest ? $latest[0] : null;
	}

	/**
	 * Premier menu référencé par un bloc Navigation.
	 *
	 * @param array[] $blocks Blocs.
	 * @return int
	 */
	private static function navigation_ref( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'core/navigation' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				return (int) $block['attrs']['ref'];
			}

			$ref = self::navigation_ref( $block['innerBlocks'] );

			if ( $ref ) {
				return $ref;
			}
		}

		return 0;
	}

	/**
	 * Entrées du menu principal, avec leurs sous-menus.
	 *
	 * @param bool $without_destinations Bouton « Top destinations » affiché : l'entrée du même nom du
	 *                                   menu (tenue à la main) est retirée.
	 * @return array[] « label », « url », « page » (page affichée), « current » (elle ou une de ses
	 *                 sous-pages), « new_tab », « children ».
	 */
	public static function menu( $without_destinations = false ) {
		$post  = self::menu_post();
		$items = $post ? self::menu_items( parse_blocks( (string) $post->post_content ), 1 ) : array();

		if ( ! $items ) {
			// Sans menu : les deux liens essentiels.
			$items = array_values(
				array_filter(
					array(
						function_exists( 'wc_get_page_permalink' ) ? self::item( __( 'Louer un bateau', 'icademie-entete-pied' ), wc_get_page_permalink( 'shop' ) ) : null,
						self::page_item( self::contact_page() ),
					)
				)
			);
		}

		if ( $without_destinations ) {
			$index = self::find_destinations( $items );

			if ( null !== $index ) {
				array_splice( $items, $index, 1 );
			}
		}

		/**
		 * Entrées du menu principal de l'en-tête.
		 *
		 * @param array[] $items « label », « url », « current », « new_tab », « children ».
		 */
		return (array) apply_filters( 'icademie_entete_menu', self::mark_current( $items ) );
	}

	/**
	 * Menu des destinations : l'arborescence des catégories de produits (ports de départ).
	 *
	 * Catégories qui ont au moins un bateau, elles-mêmes ou une de leurs sous-catégories, dans
	 * l'ordre de Produits › Catégories ; la catégorie par défaut (« Non classé ») est exclue.
	 *
	 * @return array|null « label » (bouton « Top destinations ») et « children » (catégories, avec leurs
	 *                    sous-catégories, la page affichée marquée) ; null sans catégorie.
	 */
	public static function destinations() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return null;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return null;
		}

		$by_parent = array();

		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$children = self::destination_items( $by_parent, 0, 1 );

		if ( ! $children ) {
			return null;
		}

		$item = array(
			'label'    => Icademie_Entete_Pied_Settings::get( 'destinations_label', __( 'Top destinations', 'icademie-entete-pied' ) ),
			'children' => self::mark_current( $children ),
		);

		/**
		 * Menu des destinations de l'en-tête.
		 *
		 * @param array $item « label », « children » (catégories, avec leurs sous-catégories).
		 */
		return apply_filters( 'icademie_entete_destinations', $item );
	}

	/**
	 * Catégories d'un niveau de l'arborescence, avec leurs sous-catégories.
	 *
	 * @param array[] $by_parent Catégories, par catégorie parente.
	 * @param int     $parent    Catégorie parente (0 : premier niveau).
	 * @param int     $depth     Niveau.
	 * @return array[]
	 */
	private static function destination_items( $by_parent, $parent, $depth ) {
		$items = array();

		foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $term ) {
			$children = $depth < self::DESTINATIONS_DEPTH ? self::destination_items( $by_parent, (int) $term->term_id, $depth + 1 ) : array();
			$link     = get_term_link( $term );

			// Catégorie sans bateau, ni dans ses sous-catégories : pas de lien vers une liste vide.
			if ( is_wp_error( $link ) || ( (int) $term->count < 1 && ! $children ) ) {
				continue;
			}

			$item             = self::item( $term->name, $link );
			$item['children'] = $children;
			$items[]          = $item;
		}

		return $items;
	}

	/**
	 * Entrée « Destinations » déjà dans le menu (« Top Destinations », « Destinations »…).
	 *
	 * @param array[] $items Entrées.
	 * @return int|null Position.
	 */
	private static function find_destinations( $items ) {
		$normalize = static function ( $label ) {
			$label = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label ) );

			return rtrim( trim( preg_replace( '/\s+/', ' ', $label ) ), 's' );
		};
		$wanted    = array(
			$normalize( Icademie_Entete_Pied_Settings::get( 'destinations_label', __( 'Top destinations', 'icademie-entete-pied' ) ) ),
			'top destination',
			'destination',
			'nos destination',
		);

		foreach ( $items as $index => $item ) {
			if ( in_array( $normalize( $item['label'] ), $wanted, true ) ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Lit les blocs d'un menu de navigation.
	 *
	 * @param array[] $blocks Blocs du menu.
	 * @param int     $depth  Profondeur.
	 * @return array[]
	 */
	private static function menu_items( $blocks, $depth ) {
		$items = array();

		foreach ( $blocks as $block ) {
			$attrs = $block['attrs'];

			switch ( $block['blockName'] ) {
				case 'core/navigation-link':
				case 'core/navigation-submenu':
					$item = self::item( isset( $attrs['label'] ) ? $attrs['label'] : '', self::link_url( $attrs ), ! empty( $attrs['opensInNewTab'] ) );

					if ( 'core/navigation-submenu' === $block['blockName'] && $depth < self::MAX_DEPTH ) {
						$item['children'] = self::menu_items( $block['innerBlocks'], $depth + 1 );
					}

					if ( '' !== $item['label'] && ( '' !== $item['url'] || $item['children'] ) ) {
						$items[] = $item;
					}
					break;

				case 'core/home-link':
					$items[] = self::item( isset( $attrs['label'] ) ? $attrs['label'] : __( 'Accueil', 'icademie-entete-pied' ), home_url( '/' ) );
					break;

				case 'core/page-list':
					foreach ( get_pages(
						array(
							'parent'      => 0,
							'sort_column' => 'menu_order,post_title',
						)
					) as $page ) {
						$items[] = self::page_item( $page );
					}
					break;
			}
		}

		return $items;
	}

	/**
	 * Adresse d'un lien du menu : celle de la page, de l'article ou de la catégorie liée (à jour si son
	 * adresse a changé), sinon l'adresse saisie.
	 *
	 * @param array $attrs Attributs du bloc.
	 * @return string
	 */
	private static function link_url( $attrs ) {
		$id   = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;
		$kind = isset( $attrs['kind'] ) ? $attrs['kind'] : '';

		if ( $id && 'post-type' === $kind ) {
			$url = get_permalink( $id );

			if ( $url ) {
				return $url;
			}
		}

		if ( $id && 'taxonomy' === $kind ) {
			$url = get_term_link( $id );

			if ( ! is_wp_error( $url ) ) {
				return $url;
			}
		}

		return isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
	}

	/**
	 * Entrée de menu.
	 *
	 * @param string $label   Libellé (le HTML est retiré).
	 * @param string $url     Adresse.
	 * @param bool   $new_tab Nouvel onglet.
	 * @return array
	 */
	private static function item( $label, $url, $new_tab = false ) {
		return array(
			'label'    => trim( html_entity_decode( wp_strip_all_tags( (string) $label ), ENT_QUOTES, 'UTF-8' ) ),
			'url'      => (string) $url,
			'current'  => false,
			'page'     => false,
			'new_tab'  => (bool) $new_tab,
			'children' => array(),
		);
	}

	/**
	 * Entrée d'une page.
	 *
	 * @param WP_Post|null $page Page.
	 * @return array|null
	 */
	private static function page_item( $page ) {
		return $page instanceof WP_Post ? self::item( get_the_title( $page ), get_permalink( $page ) ) : null;
	}

	/**
	 * Marque l'entrée de la page affichée, et ses entrées parentes.
	 *
	 * @param array[] $items Entrées.
	 * @return array[]
	 */
	private static function mark_current( $items ) {
		// Adresse demandée (chemin seul : une boutique filtrée reste la boutique).
		$here = self::path( add_query_arg( array() ) );

		foreach ( $items as $index => $item ) {
			$items[ $index ]['children'] = self::mark_current( $item['children'] );
			$child_current               = (bool) array_filter( wp_list_pluck( $items[ $index ]['children'], 'current' ) );
			$items[ $index ]['page']     = '' !== $item['url'] && null !== $here && self::path( $item['url'] ) === $here;
			$items[ $index ]['current']  = $items[ $index ]['page'] || $child_current;
		}

		return $items;
	}

	/**
	 * Chemin d'une adresse du site, sans barre finale ni paramètres (comparaison des pages).
	 *
	 * @param string $url Adresse.
	 * @return string|null Null pour une adresse d'un autre site.
	 */
	private static function path( $url ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$part = wp_parse_url( $url );

		if ( ! $part || ( isset( $part['host'] ) && $part['host'] !== $host ) ) {
			return null;
		}

		return untrailingslashit( isset( $part['path'] ) ? $part['path'] : '' );
	}

	/**
	 * Page Contact (extension de la page contact, sinon la page « contact »).
	 *
	 * @return WP_Post|null
	 */
	private static function contact_page() {
		$id   = method_exists( 'Icademie_Page_Contact', 'find_contact_page' ) ? (int) Icademie_Page_Contact::find_contact_page() : 0;
		$page = $id ? get_post( $id ) : get_page_by_path( 'contact' );

		return $page instanceof WP_Post && 'publish' === $page->post_status ? $page : null;
	}

	/**
	 * Adresse du bouton « Proposer mon bateau ».
	 *
	 * @return string Vide : pas de bouton.
	 */
	public static function propose_url() {
		$url = Icademie_Entete_Pied_Settings::get( 'proposer_url' );

		if ( '' === $url && class_exists( 'Icademie_Page_Accueil_Settings' ) ) {
			$url = (string) Icademie_Page_Accueil_Settings::get( 'loueur_url_proposer' );
		}

		if ( '' === $url && class_exists( 'WCV_Vendor_Dashboard' ) && method_exists( 'WCV_Vendor_Dashboard', 'get_dashboard_page_url' ) ) {
			$url = (string) WCV_Vendor_Dashboard::get_dashboard_page_url();
		}

		return $url;
	}

	/**
	 * Données de l'en-tête.
	 *
	 * @param bool $compact En-tête de la page de commande : logo et compte seulement.
	 * @return array
	 */
	public static function header( $compact = false ) {
		$logged_in    = is_user_logged_in();
		$account      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		$destinations = $compact || ! Icademie_Entete_Pied_Settings::destinations_enabled() ? null : self::destinations();

		return array(
			'compact'       => $compact,
			'logo'          => self::logo(),
			'home'          => home_url( '/' ),
			'menu'          => $compact ? array() : self::menu( null !== $destinations ),
			'lang'          => Icademie_Entete_Pied_Settings::get( 'langue', 'FR · €' ),
			'destinations'  => $destinations,
			'propose'       => $compact ? '' : self::propose_url(),
			'account_url'   => $account,
			'account_label' => $logged_in ? __( 'Mon compte', 'icademie-entete-pied' ) : __( 'Connexion', 'icademie-entete-pied' ),
			'cart_url'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'search'        => ! $compact && Icademie_Entete_Pied_Settings::search_enabled(),
			'search_action' => home_url( '/' ),
			'search_query'  => get_search_query(),
		);
	}

	/**
	 * Colonnes du pied de page : celles enregistrées, sinon proposées d'après le site.
	 *
	 * @return array[] « title » et « links » (« label », « url »).
	 */
	public static function columns() {
		$saved = Icademie_Entete_Pied_Settings::columns();

		if ( null !== $saved ) {
			return $saved;
		}

		$rent = array();

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$rent[] = array(
				'label' => __( 'Tous les bateaux', 'icademie-entete-pied' ),
				'url'   => wc_get_page_permalink( 'shop' ),
			);
		}

		// Catégories principales : les destinations.
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'number'     => 4,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				/* translators: %s: nom de la catégorie (destination). */
				$rent[] = array(
					'label' => sprintf( __( 'Louer en %s', 'icademie-entete-pied' ), $term->name ),
					'url'   => $link,
				);
			}
		}

		$offer   = array();
		$propose = self::propose_url();

		if ( '' !== $propose ) {
			$offer[] = array(
				'label' => __( 'Mettre mon bateau en location', 'icademie-entete-pied' ),
				'url'   => $propose,
			);
		}

		if ( class_exists( 'WCV_Vendor_Dashboard' ) && method_exists( 'WCV_Vendor_Dashboard', 'get_dashboard_page_url' ) ) {
			$dashboard = (string) WCV_Vendor_Dashboard::get_dashboard_page_url();

			if ( '' !== $dashboard && $dashboard !== $propose ) {
				$offer[] = array(
					'label' => __( 'Mon espace propriétaire', 'icademie-entete-pied' ),
					'url'   => $dashboard,
				);
			}
		}

		// Assistance, assurance et caution des propriétaires : l'article d'aide, s'il est publié.
		$insurance = get_page_by_path( self::INSURANCE_POST, OBJECT, array( 'post', 'page' ) );

		if ( $insurance instanceof WP_Post && 'publish' === $insurance->post_status ) {
			$offer[] = array(
				'label' => __( 'Assurance propriétaire', 'icademie-entete-pied' ),
				'url'   => get_permalink( $insurance ),
			);
		}

		$help    = array();
		$contact = self::contact_page();

		if ( $contact ) {
			$help[] = array(
				'label' => __( 'Contact', 'icademie-entete-pied' ),
				'url'   => get_permalink( $contact ),
			);
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$help[] = array(
				'label' => __( 'Mon compte', 'icademie-entete-pied' ),
				'url'   => wc_get_page_permalink( 'myaccount' ),
			);
		}

		// Centre d'aide : la catégorie d'articles « Aide », si elle a des articles.
		$help_category = get_term_by( 'slug', self::HELP_CATEGORY, 'category' );

		if ( $help_category instanceof WP_Term && $help_category->count > 0 ) {
			$link = get_term_link( $help_category );

			if ( ! is_wp_error( $link ) ) {
				$help[] = array(
					'label' => __( 'Centre d\'aide', 'icademie-entete-pied' ),
					'url'   => $link,
				);
			}
		}

		return array(
			array(
				'title' => __( 'Louer', 'icademie-entete-pied' ),
				'links' => $rent,
			),
			array(
				'title' => __( 'Proposer', 'icademie-entete-pied' ),
				'links' => $offer,
			),
			array(
				'title' => __( 'Aide', 'icademie-entete-pied' ),
				'links' => $help,
			),
		);
	}

	/**
	 * Liens légaux : ceux enregistrés, sinon conditions générales et politique de confidentialité.
	 *
	 * @return array[]
	 */
	public static function legal_links() {
		$saved = Icademie_Entete_Pied_Settings::legal();

		if ( null !== $saved ) {
			return $saved;
		}

		$links = array();
		$terms = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;

		if ( $terms && 'publish' === get_post_status( $terms ) ) {
			$links[] = array(
				'label' => __( 'Conditions générales', 'icademie-entete-pied' ),
				'url'   => get_permalink( $terms ),
			);
		}

		$privacy = get_privacy_policy_url();

		if ( '' !== $privacy ) {
			$links[] = array(
				'label' => __( 'Confidentialité', 'icademie-entete-pied' ),
				'url'   => $privacy,
			);
		}

		return $links;
	}

	/**
	 * Numéro d'assistance et réseaux sociaux (réglages de la page contact).
	 *
	 * @return array « phone », « phone_href », « social » (« label », « url », « icon »).
	 */
	private static function contact_details() {
		$details = array(
			'phone'      => '',
			'phone_href' => '',
			'social'     => array(),
		);

		if ( ! class_exists( 'Icademie_Page_Contact_Settings' ) ) {
			return $details;
		}

		$phone = Icademie_Page_Contact_Settings::get( 'assistance' );

		if ( '' === $phone ) {
			$phone = Icademie_Page_Contact_Settings::get( 'telephone' );
		}

		$digits = preg_replace( '/[^0-9+]/', '', $phone );

		$details['phone']      = $phone;
		$details['phone_href'] = '' !== $digits ? 'tel:' . $digits : '';

		$networks = method_exists( 'Icademie_Page_Contact', 'social_networks' ) ? Icademie_Page_Contact::social_networks() : array();

		foreach ( $networks as $service => $label ) {
			$url = Icademie_Page_Contact_Settings::get( $service );

			if ( '' !== $url ) {
				$details['social'][] = array(
					'label' => $label,
					'url'   => $url,
					'icon'  => function_exists( 'block_core_social_link_get_icon' ) ? block_core_social_link_get_icon( $service ) : '',
				);
			}
		}

		return $details;
	}

	/**
	 * Données du pied de page.
	 *
	 * @return array
	 */
	public static function footer() {
		$columns = array_values(
			array_filter(
				self::columns(),
				static function ( $column ) {
					return '' !== $column['title'] && $column['links'];
				}
			)
		);

		return array(
			'logo'     => self::logo(),
			'home'     => home_url( '/' ),
			'baseline' => Icademie_Entete_Pied_Settings::get( 'baseline', (string) get_bloginfo( 'description' ) ),
			'contact'  => self::contact_details(),
			'columns'  => $columns,
			'legal'    => self::legal_links(),
			'lang'     => Icademie_Entete_Pied_Settings::get( 'langue_longue', 'Français · €' ),
			'payments' => Icademie_Entete_Pied_Settings::get( 'paiements' ),
			'year'     => wp_date( 'Y' ),
		);
	}
}
