<?php
/**
 * Plugin Name:       Icademie – Page catégorie
 * Description:       Pages des catégories de produits (liste des bateaux) intégrées d'après les zonings « Catégorie » ordinateur (1440 px), tablette (768 px) et smartphone (390 px) : fil d'Ariane, en-tête de la catégorie, moteur de recherche de la page d'accueil, filtres (prix par jour, attributs des bateaux, note), tri, résultats, pagination, contenu éditorial avec 5 questions fréquentes et maillage interne. La boutique (tous les bateaux, où mène une recherche sans destination) et les résultats de la recherche de produits ont la même présentation. Le contenu éditorial, les questions et les liens se remplissent sur la fiche de chaque catégorie (Produits › Catégories). Le prix des bateaux enregistrés par les vendeurs (WC Vendors) est repris par WooCommerce pour le filtre et le tri par prix. L'en-tête et le pied de page restent ceux du thème.
 * Version:           1.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, icademie-page-accueil
 * Text Domain:       icademie-page-categorie
 *
 * @package Icademie_Page_Categorie
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-page-categorie-filters.php';
require_once __DIR__ . '/includes/class-icademie-page-categorie-prices.php';
require_once __DIR__ . '/includes/class-icademie-page-categorie-content.php';
require_once __DIR__ . '/includes/class-icademie-page-categorie-view.php';

/**
 * Page catégorie : bloc, modèle et affichage.
 *
 * Le bloc « Page catégorie » (icademie/page-categorie) affiche tout le contenu de la page, du fil
 * d'Ariane au maillage interne : zones 2 à 10 des zonings. L'en-tête et le pied de page (zones 1
 * et 11, communs à tout le site) restent ceux du thème.
 *
 * Les pages des catégories de produits, la boutique et les résultats de la recherche de produits
 * utilisent automatiquement le modèle « Liste de bateaux SailingLoc » (en-tête du thème, ce bloc,
 * pied de page du thème). Un modèle créé dans l'éditeur du site pour une catégorie précise reste
 * prioritaire. Désactiver l'extension rend les modèles du thème.
 */
final class Icademie_Page_Categorie {

	const VERSION = '1.1.0';

	/** Fichier principal de l'extension. */
	const FILE = __FILE__;

	/** Bloc qui affiche la page. */
	const BLOCK = 'icademie/page-categorie';

	/** Modèle : nom complet (« extension//slug ») et slug. */
	const TEMPLATE_NAME = 'icademie-page-categorie//icademie-page-categorie';
	const TEMPLATE_SLUG = 'icademie-page-categorie';

	/** Poignée commune de la feuille de styles et du script. */
	const HANDLE = 'icademie-page-categorie';

	/** Bateaux par page : des lignes complètes sur 3, 2 et 1 colonnes. */
	const PER_PAGE = 12;

	/** Avis affiché une fois après l'activation. */
	const NOTICE_TRANSIENT = 'icademie_page_categorie_activation';

	/**
	 * Branche l'extension sur WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// Après WooCommerce (priorité 1), qui ajoute son modèle de repli « archive-product ».
		add_filter( 'taxonomy_template_hierarchy', array( __CLASS__, 'template_hierarchy' ), 20 );
		add_filter( 'archive_template_hierarchy', array( __CLASS__, 'shop_template_hierarchy' ), 20 );
		// Après WooCommerce (priorité 10), qui ajoute « product-search-results ».
		add_filter( 'search_template_hierarchy', array( __CLASS__, 'search_template_hierarchy' ), 20 );
		add_filter( 'loop_shop_per_page', array( __CLASS__, 'per_page' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'preload_font' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );

		// Cache de la page (WP Super Cache) vidé quand la catégorie change.
		add_action( 'edited_product_cat', array( __CLASS__, 'clear_term_cache' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'clear_term_cache' ) );

		Icademie_Page_Categorie_Filters::init();
		Icademie_Page_Categorie_Prices::init();
		Icademie_Page_Categorie_Content::init();
	}

	/**
	 * Enregistre les styles, le script, le bloc et le modèle.
	 */
	public static function register() {
		wp_register_style( self::HANDLE, plugins_url( 'assets/page-categorie.css', __FILE__ ), array(), self::VERSION );

		$font = self::font_url();

		if ( '' !== $font ) {
			wp_add_inline_style(
				self::HANDLE,
				'@font-face{font-family:"Space Grotesk";font-style:normal;font-weight:300 700;font-display:swap;src:url("' . esc_url( $font ) . '") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}'
			);
		}

		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/page-categorie.js', __FILE__ ),
			array(),
			self::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		register_block_type(
			self::BLOCK,
			array(
				'api_version'         => 3,
				'title'               => __( 'Page catégorie', 'icademie-page-categorie' ),
				'description'         => __( 'Liste des bateaux d\'une catégorie de produits, de la boutique ou des résultats de la recherche de produits, du fil d\'Ariane au maillage interne : recherche, filtres, tri, résultats, contenu éditorial et questions fréquentes. Contenu, questions et liens : fiche de la catégorie.', 'icademie-page-categorie' ),
				'category'            => 'woocommerce',
				'icon'                => 'list-view',
				'keywords'            => array( 'catégorie', 'bateaux', 'filtres', 'liste' ),
				'attributes'          => array(
					'align' => array(
						'type'    => 'string',
						'default' => 'full',
					),
				),
				'supports'            => array(
					// Bloc déclaré uniquement en PHP : l'éditeur l'affiche avec un aperçu calculé par le serveur.
					'autoRegister' => true,
					'align'        => array( 'full' ),
					'html'         => false,
					'multiple'     => false,
					'reusable'     => false,
				),
				'render_callback'     => array( __CLASS__, 'render_block' ),
				'style_handles'       => array( self::HANDLE ),
				'view_script_handles' => array( self::HANDLE ),
			)
		);

		register_block_template(
			self::TEMPLATE_NAME,
			array(
				'title'       => __( 'Liste de bateaux SailingLoc', 'icademie-page-categorie' ),
				'description' => __( 'Pages des catégories de produits, boutique et résultats de la recherche de produits : en-tête et pied de page du thème ; entre les deux, le bloc « Page catégorie ».', 'icademie-page-categorie' ),
				'content'     => self::template_content(),
			)
		);
	}

	/**
	 * Contenu du modèle « Catégorie de bateaux SailingLoc ».
	 *
	 * Mêmes en-tête et pied de page que les autres pages du thème ; entre les deux, les messages de
	 * la boutique (ajout au panier…) et le bloc, sans marge ni largeur imposée.
	 *
	 * @return string
	 */
	private static function template_content() {
		return '<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main","metadata":{"name":"Contenu"},"style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><!-- wp:woocommerce/store-notices /-->

<!-- wp:icademie/page-categorie /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->';
	}

	/**
	 * Fait utiliser le modèle de l'extension aux pages des catégories de produits.
	 *
	 * Le modèle passe avant « taxonomy-product_cat » et le modèle de repli de WooCommerce, mais après
	 * un modèle propre à une catégorie (« taxonomy-product_cat-{slug} »). Sert aussi à l'éditeur du
	 * site, qui affiche ce modèle pour les catégories.
	 *
	 * @param string[] $templates Modèles candidats, du plus prioritaire au moins prioritaire
	 *                            (« ….php » sur le site, sans extension dans l'éditeur).
	 * @return string[]
	 */
	public static function template_hierarchy( $templates ) {
		return self::insert_before( $templates, 'taxonomy-product_cat' );
	}

	/**
	 * Fait utiliser le modèle de l'extension à la boutique (tous les bateaux).
	 *
	 * C'est la page des résultats du moteur de recherche quand aucune destination n'est choisie.
	 *
	 * @param string[] $templates Modèles candidats (« archive-product.php », « archive.php »…).
	 * @return string[]
	 */
	public static function shop_template_hierarchy( $templates ) {
		return self::insert_before( $templates, 'archive-product' );
	}

	/**
	 * Fait utiliser le modèle de l'extension aux résultats de la recherche de produits (loupe de
	 * l'en-tête du thème), à la place du modèle « Résultats de recherche de produits » de
	 * WooCommerce. La recherche dans tout le site (articles, pages) garde le modèle du thème.
	 *
	 * @param string[] $templates Modèles candidats (« product-search-results », « search.php »…).
	 * @return string[]
	 */
	public static function search_template_hierarchy( $templates ) {
		return self::insert_before( $templates, 'product-search-results' );
	}

	/**
	 * Place le modèle de l'extension juste avant un modèle de la liste, s'il y est.
	 *
	 * @param string[] $templates Modèles candidats.
	 * @param string   $slug      Modèle à précéder (avec ou sans « .php »).
	 * @return string[]
	 */
	private static function insert_before( $templates, $slug ) {
		foreach ( array( $slug, $slug . '.php' ) as $candidate ) {
			$index = array_search( $candidate, $templates, true );

			if ( false !== $index ) {
				$suffix = '.php' === substr( $candidate, -4 ) ? '.php' : '';

				if ( ! in_array( self::TEMPLATE_SLUG . $suffix, $templates, true ) ) {
					array_splice( $templates, $index, 0, self::TEMPLATE_SLUG . $suffix );
				}

				break;
			}
		}

		return $templates;
	}

	/**
	 * Listes de bateaux qui ont la présentation de l'extension : catégories, boutique et résultats
	 * de la recherche de produits.
	 *
	 * @return bool
	 */
	public static function is_listing() {
		return function_exists( 'is_product_category' ) && ( is_product_category() || is_shop() );
	}

	/**
	 * Bateaux par page sur les catégories, la boutique et les résultats de recherche.
	 *
	 * @param int $per_page Nombre prévu par WooCommerce.
	 * @return int
	 */
	public static function per_page( $per_page ) {
		if ( ! self::is_listing() ) {
			return $per_page;
		}

		/**
		 * Bateaux par page sur les pages des catégories, la boutique et les résultats de recherche.
		 *
		 * @param int $per_page 12 par défaut (4 lignes de 3, 6 de 2 ou 12 de 1).
		 */
		return max( 1, (int) apply_filters( 'icademie_page_categorie_par_page', self::PER_PAGE ) );
	}

	/**
	 * Police des titres, hébergée par l'extension de la page d'accueil.
	 *
	 * @return string Adresse, ou vide sans cette extension.
	 */
	private static function font_url() {
		if ( ! class_exists( 'Icademie_Page_Accueil' ) ) {
			return '';
		}

		return plugins_url( 'assets/fonts/space-grotesk-latin-wght-normal.woff2', Icademie_Page_Accueil::FILE );
	}

	/**
	 * Précharge la police des titres sur les listes de bateaux (titre visible dès l'arrivée).
	 */
	public static function preload_font() {
		$font = self::font_url();

		if ( '' === $font || ! self::is_listing() ) {
			return;
		}

		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $font ) );
	}

	/**
	 * Affiche le bloc « Page catégorie ».
	 *
	 * @return string
	 */
	public static function render_block() {
		$data = Icademie_Page_Categorie_View::data();

		if ( null === $data ) {
			return '';
		}

		ob_start();
		include __DIR__ . '/templates/page-categorie.php';
		$html = ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'icademie-categorie' ) ),
			$html
		);
	}

	/**
	 * Le bloc est-il affiché dans l'aperçu de l'éditeur (rendu par l'API REST) ?
	 *
	 * @return bool
	 */
	public static function is_editor_preview() {
		return function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Vide le cache de la page d'une catégorie et de ses pages suivantes.
	 *
	 * Avec WP Super Cache (extension du site). Une autre extension de cache peut se brancher sur
	 * l'action « icademie_page_categorie_vider_cache ».
	 *
	 * @param int $term_id Catégorie.
	 */
	public static function clear_term_cache( $term_id ) {
		$term = get_term( (int) $term_id, 'product_cat' );
		$link = $term instanceof WP_Term ? get_term_link( $term ) : '';

		if ( $link && ! is_wp_error( $link ) && function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $link );
		}

		/**
		 * Le contenu de la page d'une catégorie a changé.
		 *
		 * @param int $term_id Catégorie.
		 */
		do_action( 'icademie_page_categorie_vider_cache', (int) $term_id );
	}

	/**
	 * Vide le cache des pages de toutes les catégories et de la boutique (activation, désactivation).
	 */
	private static function clear_all_terms_cache() {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

		if ( $shop && function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $shop );
		}

		$ids = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1000,
			)
		);

		foreach ( is_wp_error( $ids ) ? array() : $ids as $term_id ) {
			self::clear_term_cache( (int) $term_id );
		}
	}

	/**
	 * Active l'extension : prix des bateaux recalculés pour le filtre et le tri par prix, avis de
	 * bienvenue avec ce qu'il reste à faire, cache des catégories vidé.
	 */
	public static function activate() {
		Icademie_Page_Categorie_Prices::repair();
		set_transient( self::NOTICE_TRANSIENT, 1, HOUR_IN_SECONDS );
		self::clear_all_terms_cache();
	}

	/**
	 * Désactive l'extension : cache des catégories vidé, le modèle du thème revient tout de suite.
	 */
	public static function deactivate() {
		self::clear_all_terms_cache();
	}

	/**
	 * Avis affiché une fois, après l'activation.
	 */
	public static function activation_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%1$s</p><p>%2$s</p></div>',
			esc_html__( 'Les pages des catégories de produits utilisent maintenant le modèle « Catégorie de bateaux SailingLoc ». L\'en-tête et le pied de page restent ceux du thème.', 'icademie-page-categorie' ),
			wp_kses(
				sprintf(
					/* translators: %s: adresse de la liste des catégories de produits. */
					__( 'À faire pour chaque catégorie, dans <a href="%s">Produits › Catégories</a> : la description (introduction) et la miniature (visuel de l\'en-tête), puis, sur la fiche de la catégorie, le contenu éditorial, les questions fréquentes et les liens du maillage interne.', 'icademie-page-categorie' ),
					esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) )
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	/**
	 * Lien « Catégories » sur la page des extensions.
	 *
	 * @param string[] $links Liens de l'extension.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ),
				esc_html__( 'Catégories', 'icademie-page-categorie' )
			)
		);

		return $links;
	}

	/**
	 * Texte suggéré pour la politique de confidentialité (Réglages › Confidentialité).
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . __( 'Les pages des catégories de bateaux reprennent le moteur de recherche de la page d\'accueil : les dates saisies sont gardées dans votre navigateur (stockage de session, sans cookie) pour pré-remplir la fiche du bateau. Elles ne nous sont pas envoyées et sont effacées à la fermeture de l\'onglet. Les filtres choisis restent dans l\'adresse de la page.', 'icademie-page-categorie' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Icademie – Page catégorie', 'icademie-page-categorie' ), wp_kses_post( $content ) );
	}

	/**
	 * Icône SVG (tracé au trait, couleur du texte), décorative.
	 *
	 * @param string $name Nom de l'icône.
	 * @param int    $size Taille en pixels.
	 * @return string SVG, ou vide si l'icône n'existe pas.
	 */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
			'chevron-left'  => '<path d="M15 6l-6 6 6 6"/>',
			'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
			'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
			'close'         => '<path d="M6 6l12 12M18 6 6 18"/>',
			'filters'       => '<path d="M4 6h16M7 12h10M10 18h4"/>',
			'sort'          => '<path d="M7 4v16M7 20l-3-3M7 20l3-3M17 20V4M17 4l-3 3M17 4l3 3"/>',
			'star'          => '<path d="m12 3.2 2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.2 6-.8z" fill="currentColor" stroke="none"/>',
			'boat'          => '<path d="M12 3v13M12 4.5 18.5 14H12zM10 7.5 5.5 14H10zM3 17h18l-2.4 3.5H5.4z"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="icademie-categorie__icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $name ]
		);
	}
}

Icademie_Page_Categorie::init();

register_activation_hook( __FILE__, array( 'Icademie_Page_Categorie', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Icademie_Page_Categorie', 'deactivate' ) );
