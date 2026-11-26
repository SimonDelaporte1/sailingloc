<?php
/**
 * Plugin Name:       SailingLoc – En-tête et pied de page
 * Description:       En-tête et pied de page de tout le site d'après la charte graphique et les maquettes SailingLoc : fond encre, logo typographique, menu principal (le menu de navigation du site, avec ses sous-menus), bouton « Top destinations » (un clic déplie l'arborescence des catégories d'annonces), « Proposer mon bateau », connexion, recherche de bateaux ; pied de page avec assistance, réseaux sociaux, trois colonnes de liens et liens légaux. Remplace l'en-tête et le pied de page du thème tant que l'extension est active. Réglages › En-tête et pied de page.
 * Version:           1.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Requires Plugins:  icademie-page-accueil
 * Text Domain:       icademie-entete-pied
 *
 * @package Icademie_Entete_Pied
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-entete-pied-settings.php';
require_once __DIR__ . '/includes/class-icademie-entete-pied-view.php';

/**
 * En-tête et pied de page : blocs, remplacement de ceux du thème, styles.
 *
 * Les blocs « En-tête SailingLoc » (icademie/entete) et « Pied de page SailingLoc »
 * (icademie/pied-de-page) s'affichent à la place des parties « header » et « footer » du thème
 * (et de leurs variantes), sur toutes les pages : le thème Purple reste intact et retrouve son
 * en-tête si l'extension est désactivée. La page de commande garde un en-tête réduit (logo et
 * compte), sans menu, comme celui du thème.
 */
final class Icademie_Entete_Pied {

	const VERSION = '1.1.0';

	/** Fichier principal. */
	const FILE = __FILE__;

	/** Blocs. */
	const BLOCK_HEADER = 'icademie/entete';
	const BLOCK_FOOTER = 'icademie/pied-de-page';

	/** Poignée de la feuille de styles et du script. */
	const HANDLE = 'icademie-entete-pied';

	/** Parties du thème remplacées. */
	const HEADER_PARTS = array( 'header', 'header-alternative' );
	const FOOTER_PARTS = array( 'footer', 'footer-alternative' );
	const CHECKOUT_PART = 'checkout-header';

	/** Avis affiché une fois après l'activation. */
	const NOTICE_TRANSIENT = 'icademie_entete_pied_activation';

	/**
	 * Branche l'extension.
	 */
	public static function init() {
		// Après les polices de l'extension de la page d'accueil (init, priorité 10).
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'pre_render_block', array( __CLASS__, 'replace_template_part' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'preload_fonts' ), 2 );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );

		// « Top destinations » : les catégories changent, le cache des pages (WP Super Cache) est vidé.
		foreach ( array( 'created_product_cat', 'edited_product_cat', 'delete_product_cat' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'clear_cache' ) );
		}

		Icademie_Entete_Pied_Settings::init();
	}

	/**
	 * Enregistre la feuille de styles, le script et les blocs.
	 */
	public static function register() {
		// Polices de la charte : feuille commune de l'extension de la page d'accueil.
		wp_register_style( self::HANDLE, plugins_url( 'assets/entete-pied.css', __FILE__ ), defined( 'Icademie_Page_Accueil::FONTS_HANDLE' ) ? array( Icademie_Page_Accueil::FONTS_HANDLE ) : array(), self::VERSION );

		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/entete-pied.js', __FILE__ ),
			array(),
			self::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$common = array(
			'api_version'         => 3,
			'category'            => 'theme',
			'supports'            => array(
				'autoRegister' => true,
				'html'         => false,
				'reusable'     => false,
			),
			'style_handles'       => array( self::HANDLE ),
			'view_script_handles' => array( self::HANDLE ),
		);

		register_block_type(
			self::BLOCK_HEADER,
			array_merge(
				$common,
				array(
					'title'           => __( 'En-tête SailingLoc', 'icademie-entete-pied' ),
					'description'     => __( 'Logo, menu principal, « Proposer mon bateau », connexion et recherche. Réglages › En-tête et pied de page.', 'icademie-entete-pied' ),
					'icon'            => 'table-row-before',
					'attributes'      => array(
						'compact' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'render_callback' => array( __CLASS__, 'render_header' ),
				)
			)
		);

		register_block_type(
			self::BLOCK_FOOTER,
			array_merge(
				$common,
				array(
					'title'           => __( 'Pied de page SailingLoc', 'icademie-entete-pied' ),
					'description'     => __( 'Logo, assistance, réseaux sociaux, colonnes de liens et liens légaux. Réglages › En-tête et pied de page.', 'icademie-entete-pied' ),
					'icon'            => 'table-row-after',
					'render_callback' => array( __CLASS__, 'render_footer' ),
				)
			)
		);
	}

	/**
	 * Remplace l'en-tête et le pied de page du thème par ceux de l'extension.
	 *
	 * @param string|null $pre   Rendu déjà fourni (null : rendu normal).
	 * @param array       $block Bloc analysé.
	 * @return string|null
	 */
	public static function replace_template_part( $pre, $block ) {
		if ( null !== $pre || 'core/template-part' !== $block['blockName'] || empty( $block['attrs']['slug'] ) ) {
			return $pre;
		}

		$slug = (string) $block['attrs']['slug'];

		/**
		 * Remplacer les parties « en-tête » et « pied de page » du thème.
		 *
		 * @param bool   $replace Oui par défaut.
		 * @param string $slug    Partie du thème.
		 */
		if ( ! apply_filters( 'icademie_entete_pied_remplacer', true, $slug ) ) {
			return $pre;
		}

		if ( in_array( $slug, self::HEADER_PARTS, true ) ) {
			return self::render_block_markup( '<!-- wp:' . self::BLOCK_HEADER . ' /-->' );
		}

		if ( self::CHECKOUT_PART === $slug ) {
			return self::render_block_markup( '<!-- wp:' . self::BLOCK_HEADER . ' {"compact":true} /-->' );
		}

		if ( in_array( $slug, self::FOOTER_PARTS, true ) ) {
			return self::render_block_markup( '<!-- wp:' . self::BLOCK_FOOTER . ' /-->' );
		}

		return $pre;
	}

	/**
	 * Rendu d'un bloc (feuille de styles et script ajoutés par WordPress).
	 *
	 * @param string $markup Bloc sérialisé.
	 * @return string
	 */
	private static function render_block_markup( $markup ) {
		$parsed = parse_blocks( $markup );

		return ( new WP_Block( $parsed[0] ) )->render();
	}

	/**
	 * Affiche l'en-tête.
	 *
	 * @param array $attributes Attributs du bloc.
	 * @return string
	 */
	public static function render_header( $attributes = array() ) {
		$data = Icademie_Entete_Pied_View::header( ! empty( $attributes['compact'] ) );
		$icon = array( __CLASS__, 'icon' );

		ob_start();
		include __DIR__ . '/templates/entete.php';
		return (string) ob_get_clean();
	}

	/**
	 * Affiche le pied de page.
	 *
	 * @return string
	 */
	public static function render_footer() {
		$data = Icademie_Entete_Pied_View::footer();
		$icon = array( __CLASS__, 'icon' );

		ob_start();
		include __DIR__ . '/templates/pied-de-page.php';
		return (string) ob_get_clean();
	}

	/**
	 * Précharge les polices de la charte sur les pages sans extension de page (les pages accueil,
	 * catégorie, annonce et contact les préchargent déjà).
	 */
	public static function preload_fonts() {
		if ( ! method_exists( 'Icademie_Page_Accueil', 'print_font_preloads' ) ) {
			return;
		}

		$own = is_front_page()
			|| ( function_exists( 'is_product' ) && ( is_product() || is_shop() || is_product_taxonomy() || is_search() ) )
			|| ( method_exists( 'Icademie_Page_Contact', 'is_contact_page' ) && Icademie_Page_Contact::is_contact_page() );

		if ( ! $own ) {
			Icademie_Page_Accueil::print_font_preloads();
		}
	}

	/**
	 * Vide le cache des pages : l'en-tête est sur toutes les pages.
	 */
	public static function clear_cache() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Active l'extension : avis, cache des pages vidé.
	 */
	public static function activate() {
		set_transient( self::NOTICE_TRANSIENT, 1, HOUR_IN_SECONDS );

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Désactive l'extension : l'en-tête du thème revient (cache vidé).
	 */
	public static function deactivate() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Avis affiché une fois, après l'activation.
	 */
	public static function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: adresse des réglages. */
					__( 'L\'en-tête et le pied de page SailingLoc remplacent ceux du thème. Le menu principal est celui de l\'en-tête du site (Apparence › Éditeur › Navigation) ; le bouton « Proposer mon bateau » et les colonnes du pied de page se règlent dans <a href="%s">Réglages › En-tête et pied de page</a>.', 'icademie-entete-pied' ),
					esc_url( admin_url( 'options-general.php?page=' . Icademie_Entete_Pied_Settings::PAGE ) )
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	/**
	 * Lien « Réglages » sur la page des extensions.
	 *
	 * @param string[] $links Liens.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'options-general.php?page=' . Icademie_Entete_Pied_Settings::PAGE ) ), esc_html__( 'Réglages', 'icademie-entete-pied' ) )
		);

		return $links;
	}

	/**
	 * Icône SVG (tracé au trait de 1,75 px, couleur du texte), décorative.
	 *
	 * @param string $name Nom.
	 * @param int    $size Taille en pixels.
	 * @return string
	 */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'user'         => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
			'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
			'close'        => '<path d="M6 6l12 12M18 6 6 18"/>',
			'search'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
			'cart'         => '<path d="M3 4h2.5l2.2 11h10.6l2.2-8H7"/><circle cx="9.5" cy="19.5" r="1.5"/><circle cx="17.5" cy="19.5" r="1.5"/>',
			'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
			'lifebuoy'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6"/>',
			'external'     => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="icademie-ep__icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $name ]
		);
	}
}

Icademie_Entete_Pied::init();

register_activation_hook( __FILE__, array( 'Icademie_Entete_Pied', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Icademie_Entete_Pied', 'deactivate' ) );
