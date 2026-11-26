<?php
/**
 * Plugin Name:       SailingLoc – Habillage des pages
 * Description:       Habillage d'après la charte graphique SailingLoc des pages qui n'ont pas de maquette : pages de contenu et articles (fil d'Ariane, titre, typographie de lecture, tableaux, citations, formulaires), listes d'articles, page introuvable et accès refusé (erreur 403), compte client (« Mes locations » avec le bandeau d'assistance, adresses, détails du compte, connexion), tableau de bord et liste des propriétaires (WC Vendors), et tunnel de réservation (panier, validation, confirmation, avec les étapes). Modèles de page propres à l'extension, entre l'en-tête et le pied de page du site.
 * Version:           1.2.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, icademie-page-accueil
 * Text Domain:       icademie-habillage
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-habillage-templates.php';
require_once __DIR__ . '/includes/class-icademie-habillage-blocks.php';
require_once __DIR__ . '/includes/class-icademie-habillage-compte.php';
require_once __DIR__ . '/includes/class-icademie-habillage-textes.php';

/**
 * Extension « Habillage des pages ».
 */
final class Icademie_Habillage {

	/** Version (feuille de styles). */
	const VERSION = '1.2.0';

	/** Feuille de styles. */
	const HANDLE = 'icademie-habillage';

	/**
	 * Branche l'extension.
	 */
	public static function init() {
		Icademie_Habillage_Templates::init();
		Icademie_Habillage_Blocks::init();
		Icademie_Habillage_Compte::init();
		Icademie_Habillage_Textes::init();

		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		// Tard : après les feuilles de WooCommerce, de WC Vendors et du thème.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'preload_fonts' ), 2 );
		add_action( 'wp_footer', array( __CLASS__, 'print_icon_filter' ) );
	}

	/**
	 * Filtre SVG qui repeint en manche les icônes de WC Vendors posées sur un fond clair.
	 *
	 * Le fichier d'icônes de WC Vendors les dessine en blanc (pour son menu gris foncé) : le CSS ne
	 * peut pas changer la couleur d'une icône tirée de ce fichier, un filtre SVG le peut.
	 */
	public static function print_icon_filter() {
		if ( ! self::is_active() || ! Icademie_Habillage_Templates::is_vendor_page() ) {
			return;
		}

		echo '<svg class="icademie-habillage__filtres" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden"><filter id="icademie-habillage-manche" color-interpolation-filters="sRGB"><feFlood flood-color="#1d5a80"/><feComposite in2="SourceAlpha" operator="in"/></filter></svg>' . "\n";
	}

	/**
	 * Feuille de styles (après les polices de la charte, partagées avec les autres extensions).
	 */
	public static function register_assets() {
		$fonts = defined( 'Icademie_Page_Accueil::FONTS_HANDLE' ) ? array( Icademie_Page_Accueil::FONTS_HANDLE ) : array();

		wp_register_style( self::HANDLE, plugins_url( 'assets/habillage.css', __FILE__ ), $fonts, self::VERSION );
	}

	/**
	 * La page affichée utilise-t-elle un modèle de l'extension ?
	 *
	 * Sur un thème par blocs, le modèle est choisi avant l'en-tête HTML : son identifiant est connu
	 * au moment de charger les styles et les classes du corps de la page. WordPress le note
	 * « {thème}//{slug} », aussi pour un modèle d'extension (et pour sa version modifiée dans
	 * l'éditeur du site).
	 *
	 * @return bool
	 */
	public static function is_active() {
		global $_wp_current_template_id;

		if ( ! is_string( $_wp_current_template_id ) || false === strpos( $_wp_current_template_id, '//' ) ) {
			return false;
		}

		$slug = substr( $_wp_current_template_id, strpos( $_wp_current_template_id, '//' ) + 2 );

		return 0 === strpos( $slug, 'icademie-habillage-' );
	}

	/**
	 * Charge la feuille de styles sur les pages habillées, après celles de WooCommerce, de WC Vendors
	 * et du thème déjà prévues : à spécificité égale, la charte l'emporte. Seules des feuilles déjà
	 * chargées sur la page deviennent des dépendances (aucune feuille de plus).
	 */
	public static function enqueue() {
		if ( ! self::is_active() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE );

		$styles = wp_styles();
		$self   = isset( $styles->registered[ self::HANDLE ] ) ? $styles->registered[ self::HANDLE ] : null;

		if ( ! $self ) {
			return;
		}

		foreach ( (array) $styles->queue as $handle ) {
			$style = isset( $styles->registered[ $handle ] ) ? $styles->registered[ $handle ] : null;
			$src   = $style && is_string( $style->src ) ? $style->src : '';

			if ( self::HANDLE !== $handle && preg_match( '#/(plugins/woocommerce|plugins/wc-vendors|themes)/#', $src ) && ! in_array( $handle, $self->deps, true ) ) {
				$self->deps[] = $handle;
			}
		}
	}

	/**
	 * Classes du corps de la page : fond craie sous le contenu, largeur des pages de service.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::is_active() ) {
			$classes[] = 'icademie-habillage-page';

			if ( Icademie_Habillage_Templates::is_wide_page() ) {
				$classes[] = 'icademie-habillage-large';
			}
		}

		return $classes;
	}

	/**
	 * Précharge les polices de la charte (même liste que les autres pages).
	 */
	public static function preload_fonts() {
		if ( self::is_active() && method_exists( 'Icademie_Page_Accueil', 'print_font_preloads' ) ) {
			Icademie_Page_Accueil::print_font_preloads();
		}
	}

	/**
	 * Icône au trait (charte : 24px, trait 1,75px, extrémités arrondies, couleur du texte).
	 *
	 * @param string $name Nom.
	 * @param int    $size Taille.
	 * @return string
	 */
	public static function icon( $name, $size = 24 ) {
		$paths = array(
			'lifebuoy' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6M14.8 14.8l3.6 3.6M18.4 5.6l-3.6 3.6M9.2 14.8l-3.6 3.6"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'check'    => '<path d="m5 12 5 5L20 7"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4.2-4.2"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'back'     => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'anchor'   => '<circle cx="12" cy="5" r="2"/><path d="M12 7v14M5 12H3a9 9 0 0 0 18 0h-2M8 11h8"/>',
			'boat'     => '<path d="M12 3v12M12 4l6 9h-6M4 16h16l-2 4H6z"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="icademie-habillage__icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}

add_action( 'plugins_loaded', array( 'Icademie_Habillage', 'init' ) );
