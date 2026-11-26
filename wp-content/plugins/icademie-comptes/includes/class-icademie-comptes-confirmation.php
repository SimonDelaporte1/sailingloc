<?php
/**
 * Confirmation de l'adresse e-mail avant la première connexion (Opquast 18).
 *
 * Un compte créé depuis « Mon compte » n'est plus connecté d'office : il est « à confirmer »
 * jusqu'à ce que son titulaire suive un lien reçu à cette adresse.
 *
 * - Mot de passe créé par le site (réglage WooCommerce « Envoyer un lien pour définir le mot de
 *   passe », celui du site en ligne) : le lien de l'e-mail de bienvenue de WooCommerce sert de
 *   confirmation ; choisir son mot de passe active le compte.
 * - Mot de passe choisi à l'inscription : l'extension envoie un e-mail avec un lien d'activation.
 *
 * Dans les deux cas, « Mot de passe perdu ? » renvoie un lien qui active aussi le compte. Les
 * comptes créés avant l'extension, par l'administration ou à la validation d'une commande ne sont
 * pas concernés.
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Confirmation de l'adresse.
 */
final class Icademie_Comptes_Confirmation {

	/** Méta du compte : adresse à confirmer. */
	const META = 'icademie_comptes_a_confirmer';

	/** Méta du compte : jeton d'activation (« date:empreinte »), jamais le jeton lui-même. */
	const TOKEN_META = 'icademie_comptes_jeton';

	/** Durée de validité du lien d'activation. */
	const TOKEN_TTL = DAY_IN_SECONDS;

	/** Paramètres du lien d'activation. */
	const KEY_ARG   = 'icademie-activer';
	const LOGIN_ARG = 'compte';

	/** Paramètre de l'adresse affichée après l'activation (ou un lien périmé). */
	const RESULT_ARG = 'activation';

	/**
	 * Branche les filtres.
	 */
	public static function init() {
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'created' ), 5, 3 );
		// Après la vérification du mot de passe (20) : seul qui connaît le mot de passe l'apprend.
		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 30 );
		add_action( 'password_reset', array( __CLASS__, 'confirm' ) );
		add_action( 'template_redirect', array( __CLASS__, 'activation_link' ), 5 );
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'result_notice' ) );
		add_action( 'woocommerce_account_content', array( __CLASS__, 'result_notice' ), 1 );
	}

	/**
	 * Inscription depuis « Mon compte » : compte à confirmer, e-mail d'activation si le client a
	 * choisi son mot de passe.
	 *
	 * @param int   $customer_id        Compte créé.
	 * @param array $new_customer_data  Données du compte.
	 * @param bool  $password_generated Mot de passe créé par le site ?
	 */
	public static function created( $customer_id, $new_customer_data = array(), $password_generated = false ) {
		// Seulement le formulaire d'inscription (nonce vérifié par WooCommerce avant la création).
		if ( ! isset( $_POST['register'], $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		update_user_meta( $customer_id, self::META, 1 );

		if ( ! $password_generated ) {
			self::send_activation( get_userdata( $customer_id ) );
		}
	}

	/**
	 * Compte à confirmer : connexion refusée, avec la marche à suivre.
	 *
	 * @param WP_User|WP_Error|null $user Résultat de l'authentification.
	 * @return WP_User|WP_Error|null
	 */
	public static function authenticate( $user ) {
		if ( ! $user instanceof WP_User || ! self::is_pending( $user->ID ) ) {
			return $user;
		}

		return new WP_Error(
			'icademie_compte_a_confirmer',
			'<strong>' . esc_html__( 'Erreur :', 'icademie-comptes' ) . '</strong> '
			. esc_html__( 'votre adresse e-mail n\'est pas encore confirmée. Suivez le lien de l\'e-mail reçu à l\'inscription, ou demandez-en un nouveau :', 'icademie-comptes' )
			. ' <a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Mot de passe perdu ?', 'icademie-comptes' ) . '</a>'
		);
	}

	/**
	 * Le compte attend-il la confirmation de son adresse ?
	 *
	 * @param int $user_id Compte.
	 * @return bool
	 */
	public static function is_pending( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META, true );
	}

	/**
	 * Mot de passe choisi depuis un lien reçu par e-mail : l'adresse est confirmée.
	 *
	 * @param WP_User $user Compte.
	 */
	public static function confirm( $user ) {
		if ( $user instanceof WP_User ) {
			delete_user_meta( $user->ID, self::META );
			delete_user_meta( $user->ID, self::TOKEN_META );
		}
	}

	/**
	 * Empreinte d'un jeton (seule l'empreinte est gardée en base).
	 *
	 * @param string $token Jeton.
	 * @return string
	 */
	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * E-mail d'activation (mot de passe choisi à l'inscription). Le lien porte un jeton propre à
	 * l'extension, à usage unique, valable 24 heures. (Pas une clé de réinitialisation de
	 * WordPress : l'e-mail de bienvenue de WooCommerce en crée une, qui remplacerait la nôtre.)
	 *
	 * @param WP_User|false $user Compte.
	 */
	private static function send_activation( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$token = wp_generate_password( 32, false );

		update_user_meta( $user->ID, self::TOKEN_META, time() . ':' . self::token_hash( $token ) );

		$url = add_query_arg(
			array(
				self::KEY_ARG   => rawurlencode( $token ),
				self::LOGIN_ARG => rawurlencode( $user->user_login ),
			),
			wc_get_page_permalink( 'myaccount' )
		);

		$body = '<p>' . esc_html__( 'Bonjour,', 'icademie-comptes' ) . '</p>'
			. '<p>' . esc_html__( 'Merci de votre inscription sur SailingLoc. Pour activer votre compte, confirmez votre adresse e-mail :', 'icademie-comptes' ) . '</p>'
			. '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Activer mon compte', 'icademie-comptes' ) . '</a></p>'
			. '<p>' . esc_html__( 'Ce lien est valable 24 heures. Passé ce délai, utilisez « Mot de passe perdu ? » sur la page Mon compte pour en recevoir un nouveau.', 'icademie-comptes' ) . '</p>'
			. '<p>' . esc_html__( 'Si vous n\'êtes pas à l\'origine de cette inscription, ignorez ce message : le compte ne sera pas activé.', 'icademie-comptes' ) . '</p>';

		Icademie_Comptes::send_mail(
			$user->user_email,
			__( 'Activez votre compte SailingLoc', 'icademie-comptes' ),
			__( 'Activez votre compte', 'icademie-comptes' ),
			$body
		);
	}

	/**
	 * Le jeton est-il celui, encore valable, du compte ?
	 *
	 * @param WP_User $user  Compte.
	 * @param string  $token Jeton reçu.
	 * @return bool
	 */
	private static function token_valid( WP_User $user, $token ) {
		$stored = (string) get_user_meta( $user->ID, self::TOKEN_META, true );

		if ( '' === $token || false === strpos( $stored, ':' ) ) {
			return false;
		}

		list( $time, $hash ) = explode( ':', $stored, 2 );

		return time() - (int) $time <= self::TOKEN_TTL && hash_equals( $hash, self::token_hash( $token ) );
	}

	/**
	 * Lien d'activation : adresse confirmée, jeton effacé, client connecté.
	 */
	public static function activation_link() {
		if ( ! isset( $_GET[ self::KEY_ARG ], $_GET[ self::LOGIN_ARG ] ) || ! is_string( $_GET[ self::KEY_ARG ] ) || ! is_string( $_GET[ self::LOGIN_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Le jeton tient lieu de nonce.
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::KEY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$login = sanitize_user( wp_unslash( $_GET[ self::LOGIN_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user  = get_user_by( 'login', $login );
		$page  = wc_get_page_permalink( 'myaccount' );

		if ( ! $user instanceof WP_User || ! self::token_valid( $user, $token ) ) {
			wp_safe_redirect( add_query_arg( self::RESULT_ARG, 'invalide', $page ) );
			exit;
		}

		self::confirm( $user );

		if ( ! is_user_logged_in() ) {
			wc_set_customer_auth_cookie( $user->ID );
		}

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, 'ok', $page ) );
		exit;
	}

	/**
	 * Message après un lien d'activation : compte activé, ou lien périmé.
	 */
	public static function result_notice() {
		$result = isset( $_GET[ self::RESULT_ARG ] ) && is_string( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( $_GET[ self::RESULT_ARG ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affichage seul.

		if ( 'ok' === $result && is_user_logged_in() ) {
			wc_print_notice( esc_html__( 'Votre adresse e-mail est confirmée : votre compte est activé.', 'icademie-comptes' ), 'success' );
		} elseif ( 'invalide' === $result && ! is_user_logged_in() ) {
			wc_print_notice(
				esc_html__( 'Ce lien d\'activation n\'est plus valable (déjà utilisé, ou plus de 24 heures). Demandez-en un nouveau avec « Mot de passe perdu ? » : choisir votre mot de passe activera votre compte.', 'icademie-comptes' ),
				'error'
			);
		}
	}
}
