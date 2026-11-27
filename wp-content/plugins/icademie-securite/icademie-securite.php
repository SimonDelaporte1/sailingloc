<?php
/**
 * Plugin Name:       SailingLoc – Sécurité et performances
 * Description:       Corrections de l'audit Opquast côté serveur : versions de WordPress et des extensions retirées du code des pages et des flux (Opquast 213) ; champs anti-spam de WP Armour masqués aux lecteurs d'écran et retirés de la navigation au clavier, avec des identifiants uniques (Opquast 165, 167, 236) ; focus visible sur le bouton de fermeture des fenêtres de Themify Popup (Opquast 165) ; feuilles de styles et scripts des extensions SailingLoc et du thème, et scripts de WP Armour et de WC Vendors, servis minifiés quand leur version « .min » est à jour (Opquast 229, 230). Les en-têtes de sécurité (dont la politique de sécurité du contenu qui limite les scripts tiers aux services utilisés, Opquast 214) et la page d'erreur 403 sont réglés dans le fichier .htaccess à la racine du site.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Text Domain:       icademie-securite
 *
 * @package Icademie_Securite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extension « Sécurité et performances ».
 */
final class Icademie_Securite {

	/** Version. */
	const VERSION = '1.0.0';

	/** Script qui corrige les champs anti-spam. */
	const ANTI_SPAM_HANDLE = 'icademie-securite-anti-spam';

	/** Poignée du script de WP Armour (« honeypot »). */
	const WPA_HANDLE = 'wpascript';

	/** En-tête des fichiers « .min » : empreinte MD5 du fichier source dont ils sont tirés. */
	const MIN_HEADER = '#^/\*! source md5:([0-9a-f]{32}) \*/#';

	/**
	 * Scripts d'extensions tierces servis minifiés (Opquast 230) : fichier source (dans le dossier
	 * des extensions) => version minifiée, dans cette extension (une mise à jour de l'extension
	 * tierce efface son dossier : la copie minifiée est gardée ici, et n'est servie que si elle est
	 * tirée du fichier source actuel).
	 */
	const THIRD_PARTY = array(
		'honeypot/includes/js/wpa.js'                             => 'assets/tiers/honeypot/wpa.min.js',
		'honeypot/includes/js/wpa_vanilla.js'                     => 'assets/tiers/honeypot/wpa_vanilla.min.js',
		'wc-vendors/assets/js/blocks/cart-checkout-integration.js' => 'assets/tiers/wc-vendors/cart-checkout-integration.min.js',
	);

	/** Feuille de styles de Themify Popup (focus du bouton de fermeture). */
	const POPUP_HANDLE = 'themify-popup';

	/**
	 * Branche l'extension.
	 */
	public static function init() {
		// Versions des logiciels.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'googlesitekit_generator', '__return_empty_string' );
		add_filter( 'wpseo_debug_markers', '__return_false' );

		// Champs anti-spam (après le script de WP Armour, chargé en pied de page).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_anti_spam' ), 100 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_anti_spam' ), 100 );

		// Fichiers minifiés.
		add_filter( 'style_loader_src', array( __CLASS__, 'minified_src' ), 20 );
		add_filter( 'script_loader_src', array( __CLASS__, 'minified_src' ), 20 );

		// Fenêtres de Themify Popup : focus visible sur le bouton de fermeture. Themify Popup déclare
		// sa feuille de styles dans le pied de page (wp_footer, priorité 1), imprimée ensuite.
		add_action( 'wp_footer', array( __CLASS__, 'popup_focus' ), 2 );
	}

	// ------------------------------------------------------------------------------------------
	// Fenêtres de Themify Popup.
	// ------------------------------------------------------------------------------------------

	/**
	 * Le bouton « × » des fenêtres de Themify Popup n'a pas d'indicateur de focus (« outline: none »
	 * de sa feuille de styles) : contour blanc et bleu, visible sur tous les fonds (Opquast 165).
	 */
	public static function popup_focus() {
		if ( ! wp_style_is( self::POPUP_HANDLE, 'registered' ) ) {
			return;
		}

		wp_add_inline_style(
			self::POPUP_HANDLE,
			'button.themify-popup-close:focus-visible,.mfp-wrap button.mfp-close:focus-visible{outline:3px solid #fff!important;outline-offset:2px;box-shadow:0 0 0 6px #1d5a80!important}'
		);
	}

	// ------------------------------------------------------------------------------------------
	// Champs anti-spam.
	// ------------------------------------------------------------------------------------------

	/**
	 * Script de correction, seulement là où WP Armour ajoute ses champs (il en dépend : sans WP
	 * Armour, WordPress ne le charge pas).
	 */
	public static function enqueue_anti_spam() {
		if ( ! wp_script_is( self::WPA_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script( self::ANTI_SPAM_HANDLE, plugins_url( 'assets/anti-spam.js', __FILE__ ), array( self::WPA_HANDLE ), self::VERSION, true );

		wp_localize_script(
			self::ANTI_SPAM_HANDLE,
			'icademieAntiSpam',
			array( 'label' => __( 'Ne pas modifier ce champ (protection contre les messages indésirables)', 'icademie-securite' ) )
		);
	}

	// ------------------------------------------------------------------------------------------
	// Fichiers minifiés.
	// ------------------------------------------------------------------------------------------

	/**
	 * Feuille de styles ou script d'une extension SailingLoc (« icademie-… ») ou du thème : la
	 * version « .min » si elle est tirée du fichier source actuel. Sinon (fichier modifié sans
	 * relancer la minification, ou SCRIPT_DEBUG), le fichier source : jamais une version périmée.
	 *
	 * Les fichiers « .min » se fabriquent avec sailingloc-tests/outils/minifier.sh.
	 *
	 * @param string|false $src Adresse du fichier.
	 * @return string|false
	 */
	public static function minified_src( $src ) {
		if ( ! is_string( $src ) || '' === $src || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			return $src;
		}

		$third_party = self::third_party_src( $src );

		if ( '' !== $third_party ) {
			return $third_party;
		}

		$path = self::local_path( $src );

		if ( '' === $path || ! preg_match( '#(?<!\.min)\.(css|js)$#', $path, $match ) ) {
			return $src;
		}

		$min = substr( $path, 0, -strlen( $match[0] ) ) . '.min.' . $match[1];

		if ( ! self::is_fresh( $path, $min ) ) {
			return $src;
		}

		$parts         = explode( '?', $src, 2 );
		$parts[0]      = substr( $parts[0], 0, -strlen( basename( $path ) ) ) . basename( $min );

		return implode( '?', $parts );
	}

	/**
	 * Version minifiée d'un script d'extension tierce (voir THIRD_PARTY), si elle est à jour.
	 *
	 * @param string $src Adresse du fichier.
	 * @return string Adresse de la version minifiée (paramètres de l'adresse d'origine gardés), ou vide.
	 */
	private static function third_party_src( $src ) {
		$host = wp_parse_url( $src, PHP_URL_HOST );

		if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			return '';
		}

		$base     = (string) wp_parse_url( plugins_url(), PHP_URL_PATH ) . '/';
		$url_path = (string) wp_parse_url( $src, PHP_URL_PATH );

		if ( 0 !== strpos( $url_path, $base ) ) {
			return '';
		}

		$relative = substr( $url_path, strlen( $base ) );

		if ( ! isset( self::THIRD_PARTY[ $relative ] ) || ! self::is_fresh( WP_PLUGIN_DIR . '/' . $relative, __DIR__ . '/' . self::THIRD_PARTY[ $relative ] ) ) {
			return '';
		}

		$query = (string) wp_parse_url( $src, PHP_URL_QUERY );

		return plugins_url( self::THIRD_PARTY[ $relative ], __FILE__ ) . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * Chemin du fichier d'une adresse, s'il appartient à une extension SailingLoc ou au thème.
	 *
	 * @param string $src Adresse.
	 * @return string Chemin, ou chaîne vide.
	 */
	private static function local_path( $src ) {
		static $bases = null;

		if ( null === $bases ) {
			$bases = array(
				(string) wp_parse_url( plugins_url(), PHP_URL_PATH ) . '/' => array( WP_PLUGIN_DIR . '/', '#^icademie-[a-z0-9-]+/#' ),
				(string) wp_parse_url( get_template_directory_uri(), PHP_URL_PATH ) . '/' => array( get_template_directory() . '/', '#^#' ),
			);
		}

		$host = wp_parse_url( $src, PHP_URL_HOST );

		if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			return '';
		}

		$url_path = (string) wp_parse_url( $src, PHP_URL_PATH );

		foreach ( $bases as $base => $target ) {
			if ( 0 !== strpos( $url_path, $base ) ) {
				continue;
			}

			$relative = substr( $url_path, strlen( $base ) );

			if ( false !== strpos( $relative, '..' ) || ! preg_match( $target[1], $relative ) ) {
				return '';
			}

			return $target[0] . $relative;
		}

		return '';
	}

	/**
	 * La version « .min » est-elle tirée du fichier source tel qu'il est ?
	 *
	 * @param string $source Fichier source.
	 * @param string $min    Fichier minifié.
	 * @return bool
	 */
	private static function is_fresh( $source, $min ) {
		static $checked = array();

		if ( isset( $checked[ $min ] ) ) {
			return $checked[ $min ];
		}

		$fresh = false;

		if ( is_readable( $source ) && is_readable( $min ) ) {
			$handle = fopen( $min, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Lecture de la première ligne.
			$header = $handle ? (string) fgets( $handle, 128 ) : '';

			if ( $handle ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}

			$fresh = (bool) preg_match( self::MIN_HEADER, $header, $match ) && hash_equals( $match[1], (string) md5_file( $source ) );
		}

		$checked[ $min ] = $fresh;

		return $fresh;
	}
}

add_action( 'plugins_loaded', array( 'Icademie_Securite', 'init' ) );
