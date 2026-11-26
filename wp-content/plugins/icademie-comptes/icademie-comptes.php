<?php
/**
 * Plugin Name:       SailingLoc – Comptes clients
 * Description:       Comptes clients d'après l'audit Opquast : messages neutres qui ne révèlent jamais si une adresse a un compte (connexion, inscription, mot de passe perdu), liste des comptes fermée aux visiteurs (API REST, archives d'auteur) ; adresse e-mail confirmée avant la première connexion ; page « Mes données personnelles » du compte client pour télécharger ses données et supprimer son compte.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       icademie-comptes
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-comptes-messages.php';
require_once __DIR__ . '/includes/class-icademie-comptes-confirmation.php';
require_once __DIR__ . '/includes/class-icademie-comptes-donnees.php';

/**
 * Extension « Comptes clients ».
 */
final class Icademie_Comptes {

	/** Version. */
	const VERSION = '1.0.0';

	/** Option : règles de réécriture à régénérer (nouveau point d'accès du compte). */
	const FLUSH_OPTION = 'icademie_comptes_flush';

	/**
	 * Branche l'extension.
	 */
	public static function init() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		Icademie_Comptes_Messages::init();
		Icademie_Comptes_Confirmation::init();
		Icademie_Comptes_Donnees::init();

		// Après l'ajout des points d'accès de WooCommerce (init, priorité 10).
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * Activation : les règles de réécriture seront régénérées au prochain chargement, une fois le
	 * point d'accès « mes-donnees » déclaré à WooCommerce.
	 */
	public static function activate() {
		update_option( self::FLUSH_OPTION, 1, false );
	}

	/**
	 * Régénère les règles de réécriture une fois après l'activation.
	 */
	public static function maybe_flush() {
		if ( get_option( self::FLUSH_OPTION ) ) {
			delete_option( self::FLUSH_OPTION );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Adresse de la page Contact (bloc de l'extension « Page Contact », sinon page « contact »).
	 *
	 * @return string
	 */
	public static function contact_url() {
		$page_id = method_exists( 'Icademie_Page_Contact', 'find_contact_page' ) ? (int) Icademie_Page_Contact::find_contact_page() : 0;
		$page    = $page_id ? get_post( $page_id ) : get_page_by_path( 'contact' );

		return $page instanceof WP_Post && 'publish' === $page->post_status ? (string) get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * Envoie un e-mail à l'habillage des e-mails de WooCommerce, avec un moyen de contact en pied
	 * (Opquast 111).
	 *
	 * @param string $to      Destinataire.
	 * @param string $subject Objet.
	 * @param string $heading Titre affiché dans l'e-mail.
	 * @param string $body    Corps (HTML, déjà échappé).
	 * @return bool
	 */
	public static function send_mail( $to, $subject, $heading, $body ) {
		$body .= '<p>' . sprintf(
			/* translators: %s: lien vers la page Contact. */
			esc_html__( 'Une question ? Écrivez-nous depuis la page %s.', 'icademie-comptes' ),
			'<a href="' . esc_url( self::contact_url() ) . '">' . esc_html__( 'Contact', 'icademie-comptes' ) . '</a>'
		) . '</p>';

		$mailer  = WC()->mailer();
		$message = $mailer->wrap_message( $heading, $body );

		return (bool) $mailer->send( $to, $subject, $message );
	}
}

register_activation_hook( __FILE__, array( 'Icademie_Comptes', 'activate' ) );
add_action( 'plugins_loaded', array( 'Icademie_Comptes', 'init' ) );
