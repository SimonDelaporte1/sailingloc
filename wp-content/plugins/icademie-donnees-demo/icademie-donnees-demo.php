<?php
/**
 * Plugin Name:       Icademie – Données de démonstration
 * Description:       Remplit le site de démonstration SailingLoc : comptes propriétaires fictifs (vendeurs WC Vendors, avec boutique) et leurs annonces complètes (photos libres de droits, attributs, conditions de location), comptes locataires fictifs, réservations datées (calendriers de disponibilité, commissions) et avis des locataires avec notes détaillées. Tout ce qui est créé est marqué et se supprime en un clic. Aucun e-mail n'est envoyé pendant la génération ni aux comptes fictifs (adresses en « .invalid »). Outils › Données de démonstration.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, wc-vendors
 * Text Domain:       icademie-donnees-demo
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-demo-catalogue.php';
require_once __DIR__ . '/includes/class-icademie-demo-photos.php';
require_once __DIR__ . '/includes/class-icademie-demo-generateur.php';
require_once __DIR__ . '/includes/class-icademie-demo-admin.php';

/**
 * Extension « Données de démonstration ».
 */
final class Icademie_Donnees_Demo {

	/** Version (feuilles et scripts). */
	const VERSION = '1.0.0';

	/** Métadonnée posée sur tout ce que crée l'extension. */
	const META = '_icademie_demo';

	/** Domaine des adresses des comptes fictifs (réservé, jamais livrable : RFC 2606). */
	const EMAIL_DOMAIN = 'sailingloc-demo.invalid';

	/**
	 * Branche l'extension.
	 */
	public static function init() {
		// Aucun e-mail vers les comptes fictifs, même après la génération (changement d'état d'une
		// commande, réinitialisation de mot de passe…).
		add_filter( 'pre_wp_mail', array( __CLASS__, 'block_demo_mail' ), 5, 2 );
		add_filter( 'wp_mail', array( __CLASS__, 'strip_demo_recipients' ), 5 );

		Icademie_Demo_Admin::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/includes/class-icademie-demo-cli.php';
			WP_CLI::add_command( 'icademie-demo', 'Icademie_Demo_CLI' );
		}
	}

	/**
	 * Adresse d'un compte fictif ?
	 *
	 * @param string $recipient Adresse, éventuellement « Nom <adresse> ».
	 * @return bool
	 */
	public static function is_demo_address( $recipient ) {
		$recipient = trim( (string) $recipient );

		if ( preg_match( '/<([^>]+)>/', $recipient, $match ) ) {
			$recipient = $match[1];
		}

		return 1 === preg_match( '/@' . preg_quote( self::EMAIL_DOMAIN, '/' ) . '$/i', trim( $recipient ) );
	}

	/**
	 * Destinataires d'un e-mail.
	 *
	 * @param array $atts Arguments de wp_mail().
	 * @return string[]
	 */
	private static function recipients( $atts ) {
		$to = isset( $atts['to'] ) ? $atts['to'] : array();

		if ( ! is_array( $to ) ) {
			$to = explode( ',', (string) $to );
		}

		return array_values( array_filter( array_map( 'trim', $to ) ) );
	}

	/**
	 * E-mail destiné seulement à des comptes fictifs : pas envoyé.
	 *
	 * @param null|bool $return Résultat déjà décidé.
	 * @param array     $atts   Arguments de wp_mail().
	 * @return null|bool
	 */
	public static function block_demo_mail( $return, $atts ) {
		if ( null !== $return ) {
			return $return;
		}

		$to = self::recipients( $atts );

		return $to && count( array_filter( $to, array( __CLASS__, 'is_demo_address' ) ) ) === count( $to ) ? false : null;
	}

	/**
	 * E-mail à plusieurs destinataires : les comptes fictifs sont retirés.
	 *
	 * @param array $atts Arguments de wp_mail().
	 * @return array
	 */
	public static function strip_demo_recipients( $atts ) {
		$to   = self::recipients( $atts );
		$keep = array_values(
			array_filter(
				$to,
				static function ( $recipient ) {
					return ! self::is_demo_address( $recipient );
				}
			)
		);

		// Tous fictifs : laissés tels quels, « pre_wp_mail » bloque l'envoi.
		if ( $keep && count( $keep ) !== count( $to ) ) {
			$atts['to'] = $keep;
		}

		return $atts;
	}
}

add_action( 'plugins_loaded', array( 'Icademie_Donnees_Demo', 'init' ) );
