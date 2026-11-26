<?php
/**
 * Messages neutres : rien n'indique à un visiteur si une adresse e-mail ou un identifiant a un
 * compte (Opquast 26).
 *
 * - Connexion : un seul message, que l'identifiant soit inconnu ou le mot de passe faux.
 * - Inscription : même réponse (redirection et message) que l'adresse soit nouvelle ou déjà
 *   utilisée ; dans le second cas, le titulaire du compte reçoit un e-mail qui le lui rappelle.
 * - Validation de commande avec « Créer un compte » : une adresse déjà utilisée passe la commande
 *   en invité au lieu d'afficher « Un compte est déjà enregistré avec … ».
 * - Mot de passe perdu : même confirmation que l'identifiant existe ou non.
 * - Liste des comptes fermée aux visiteurs : API REST « wp/v2/users », « ?author=N », archives
 *   d'auteur, auteur des contenus intégrés (oEmbed), plan du site des auteurs (Yoast SEO).
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Messages neutres.
 */
final class Icademie_Comptes_Messages {

	/** Paramètre de l'adresse affichée après une demande d'inscription. */
	const REGISTRATION_ARG = 'inscription';

	/** Délai entre deux e-mails « vous avez déjà un compte » au même titulaire. */
	const NOTICE_DELAY = 15 * MINUTE_IN_SECONDS;

	/**
	 * Branche les filtres.
	 */
	public static function init() {
		// Connexion : après la vérification du mot de passe (20) et l'activation du compte (30).
		add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 100 );

		// Inscription depuis « Mon compte ».
		add_filter( 'woocommerce_process_registration_errors', array( __CLASS__, 'registration_errors' ), 100, 4 );
		add_filter( 'woocommerce_registration_auth_new_customer', array( __CLASS__, 'registration_done' ), 100 );
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'registration_notice' ) );

		// Validation de commande (bloc Validation, API Store).
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'checkout_request' ), 10, 3 );

		// Mot de passe perdu (« Mon compte » et wp-login.php).
		add_action( 'lostpassword_post', array( __CLASS__, 'lost_password' ), 100, 2 );
		add_filter( 'woocommerce_lost_password_confirmation_message', array( __CLASS__, 'lost_password_message' ) );
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'woocommerce_texts' ), 10, 2 );

		// Liste des comptes.
		add_filter( 'rest_endpoints', array( __CLASS__, 'rest_endpoints' ) );
		add_filter( 'request', array( __CLASS__, 'author_query' ) );
		add_action( 'template_redirect', array( __CLASS__, 'author_archive' ), 1 );
		add_filter( 'oembed_response_data', array( __CLASS__, 'oembed_data' ) );
		add_filter( 'option_wpseo_titles', array( __CLASS__, 'yoast_titles' ) );
	}

	// ------------------------------------------------------------------------------------------
	// Connexion.
	// ------------------------------------------------------------------------------------------

	/**
	 * Identifiant inconnu, adresse inconnue ou mot de passe faux : un seul message.
	 *
	 * Les autres erreurs (champ vide, compte à activer, protection contre les attaques) restent :
	 * elles ne disent rien de l'existence du compte, ou seulement à qui connaît le mot de passe.
	 *
	 * @param WP_User|WP_Error|null $user Résultat de l'authentification.
	 * @return WP_User|WP_Error|null
	 */
	public static function authenticate( $user ) {
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		$revealing = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' );

		if ( ! array_intersect( $user->get_error_codes(), $revealing ) ) {
			return $user;
		}

		return new WP_Error( 'icademie_identifiants_incorrects', self::login_error() );
	}

	/**
	 * « Identifiant, adresse e-mail ou mot de passe incorrect. Mot de passe oublié ? »
	 *
	 * @return string
	 */
	public static function login_error() {
		return '<strong>' . esc_html__( 'Erreur :', 'icademie-comptes' ) . '</strong> '
			. esc_html__( 'identifiant, adresse e-mail ou mot de passe incorrect.', 'icademie-comptes' )
			. ' <a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Mot de passe oublié ?', 'icademie-comptes' ) . '</a>';
	}

	// ------------------------------------------------------------------------------------------
	// Inscription.
	// ------------------------------------------------------------------------------------------

	/**
	 * Adresse déjà utilisée : au lieu de « Un compte est déjà enregistré avec … », la même réponse
	 * qu'une inscription réussie ; le titulaire du compte reçoit un e-mail.
	 *
	 * @param WP_Error $errors   Erreurs de validation.
	 * @param string   $username Identifiant saisi.
	 * @param string   $password Mot de passe saisi.
	 * @param string   $email    Adresse saisie.
	 * @return WP_Error
	 */
	public static function registration_errors( $errors, $username, $password, $email ) {
		if ( ! $errors instanceof WP_Error || $errors->has_errors() ) {
			return $errors;
		}

		$email = sanitize_email( (string) $email );
		$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

		if ( ! $user instanceof WP_User ) {
			return $errors;
		}

		self::notify_existing_account( $user );
		self::registration_response();

		return $errors; // Jamais atteint : registration_response() redirige.
	}

	/**
	 * Compte créé : pas de connexion automatique (l'adresse doit d'abord être confirmée), et la même
	 * réponse que pour une adresse déjà utilisée.
	 *
	 * Appelé par WooCommerce juste après la création du compte et l'envoi de l'e-mail de bienvenue.
	 *
	 * @param bool $auth Connecter le client ?
	 * @return bool
	 */
	public static function registration_done( $auth ) {
		if ( ! did_action( 'woocommerce_created_customer' ) ) {
			return $auth;
		}

		self::registration_response();

		return false;
	}

	/**
	 * Réponse commune aux demandes d'inscription : retour sur « Mon compte » avec le message.
	 *
	 * Le message passe par l'adresse et non par les avis de WooCommerce : un visiteur n'a pas encore
	 * de session, et les avis ajoutés par WooCommerce ou WC Vendors (« Votre compte a été créé… »,
	 * « Votre demande a été envoyée ») trahiraient un compte nouveau.
	 */
	private static function registration_response() {
		wc_clear_notices();
		wp_safe_redirect( add_query_arg( self::REGISTRATION_ARG, 'demandee', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Message affiché au-dessus des formulaires de connexion et d'inscription après une demande.
	 */
	public static function registration_notice() {
		if ( ! isset( $_GET[ self::REGISTRATION_ARG ] ) || 'demandee' !== $_GET[ self::REGISTRATION_ARG ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affichage seul.
			return;
		}

		wc_print_notice( esc_html( self::registration_message() ), 'success' );
	}

	/**
	 * Texte commun aux deux cas.
	 *
	 * @return string
	 */
	public static function registration_message() {
		return __( 'Merci ! Un e-mail vient d\'être envoyé à l\'adresse indiquée : suivez les instructions qu\'il contient pour finaliser votre inscription. Il peut mettre quelques minutes à arriver ; pensez à regarder dans vos courriers indésirables.', 'icademie-comptes' );
	}

	/**
	 * E-mail au titulaire d'un compte dont l'adresse vient d'être saisie pour une inscription.
	 *
	 * Au plus un e-mail par quart d'heure et par compte, pour qu'on ne puisse pas inonder une boîte.
	 *
	 * @param WP_User $user Compte.
	 */
	private static function notify_existing_account( WP_User $user ) {
		$key = 'icademie_comptes_avis_' . $user->ID;

		if ( get_transient( $key ) ) {
			return;
		}

		set_transient( $key, 1, self::NOTICE_DELAY );

		$body = '<p>' . esc_html__( 'Bonjour,', 'icademie-comptes' ) . '</p>'
			. '<p>' . esc_html__( 'Une demande de création de compte vient d\'être faite sur SailingLoc avec votre adresse e-mail. Vous avez déjà un compte avec cette adresse : inutile d\'en créer un autre.', 'icademie-comptes' ) . '</p>'
			. '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Me connecter à mon compte', 'icademie-comptes' ) . '</a></p>'
			. '<p>' . esc_html__( 'Mot de passe oublié ?', 'icademie-comptes' ) . ' <a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Choisir un nouveau mot de passe', 'icademie-comptes' ) . '</a></p>'
			. '<p>' . esc_html__( 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre compte n\'a pas été modifié.', 'icademie-comptes' ) . '</p>';

		Icademie_Comptes::send_mail(
			$user->user_email,
			__( 'Vous avez déjà un compte SailingLoc', 'icademie-comptes' ),
			__( 'Vous avez déjà un compte', 'icademie-comptes' ),
			$body
		);
	}

	// ------------------------------------------------------------------------------------------
	// Validation de commande.
	// ------------------------------------------------------------------------------------------

	/**
	 * « Créer un compte » coché avec une adresse déjà utilisée : la commande passe en invité (elle
	 * reste rattachée à l'adresse), et le titulaire du compte reçoit l'e-mail de rappel.
	 *
	 * @param mixed           $response Réponse (null tant qu'aucun filtre n'a répondu).
	 * @param array           $handler  Point d'accès.
	 * @param WP_REST_Request $request  Requête.
	 * @return mixed
	 */
	public static function checkout_request( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() || is_user_logged_in() ) {
			return $response;
		}

		if ( ! preg_match( '#^/wc/store(/v\d+)?/checkout$#', $request->get_route() ) || ! filter_var( $request['create_account'], FILTER_VALIDATE_BOOLEAN ) ) {
			return $response;
		}

		$billing = (array) $request['billing_address'];
		$email   = sanitize_email( (string) ( $billing['email'] ?? '' ) );
		$user    = is_email( $email ) ? get_user_by( 'email', $email ) : false;

		if ( $user instanceof WP_User ) {
			$request->set_param( 'create_account', false );
			$request->set_param( 'customer_password', '' );
			self::notify_existing_account( $user );
		}

		return $response;
	}

	// ------------------------------------------------------------------------------------------
	// Mot de passe perdu.
	// ------------------------------------------------------------------------------------------

	/**
	 * Identifiant ou adresse inconnu : la même confirmation qu'une demande aboutie, sans e-mail.
	 *
	 * @param WP_Error      $errors    Erreurs de la demande.
	 * @param WP_User|false $user_data Compte trouvé.
	 */
	public static function lost_password( $errors, $user_data ) {
		if ( $user_data instanceof WP_User || ! $errors instanceof WP_Error ) {
			return;
		}

		$login = isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ? trim( wp_unslash( $_POST['user_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce vérifié par l'appelant ; valeur seulement testée.

		// Champ vide, ou autre erreur que « adresse inconnue » : message d'origine.
		if ( '' === $login || array_diff( $errors->get_error_codes(), array( 'invalid_email', 'invalidcombo' ) ) ) {
			return;
		}

		if ( isset( $_POST['wc_reset_password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_safe_redirect( add_query_arg( 'reset-link-sent', 'true', wc_get_account_endpoint_url( 'lost-password' ) ) );
			exit;
		}

		if ( did_action( 'login_init' ) ) {
			$redirect = ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validé par wp_safe_redirect().
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Confirmation de « Mot de passe perdu » : vraie que le compte existe ou non.
	 *
	 * @return string
	 */
	public static function lost_password_message() {
		return __( 'Si un compte correspond à l\'identifiant ou à l\'adresse e-mail saisi, un e-mail contenant un lien pour choisir un nouveau mot de passe vient de lui être envoyé. Il peut mettre quelques minutes à arriver ; pensez à regarder dans vos courriers indésirables avant de faire une nouvelle demande.', 'icademie-comptes' );
	}

	/**
	 * Avis de WooCommerce au-dessus de la confirmation : « Demande prise en compte. » au lieu de
	 * « L'e-mail de réinitialisation du mot de passe a été envoyé. »
	 *
	 * @param string $translation Traduction.
	 * @param string $text        Texte d'origine (anglais).
	 * @return string
	 */
	public static function woocommerce_texts( $translation, $text ) {
		if ( 'Password reset email has been sent.' !== $text ) {
			return $translation;
		}

		return __( 'Demande prise en compte.', 'icademie-comptes' );
	}

	// ------------------------------------------------------------------------------------------
	// Liste des comptes.
	// ------------------------------------------------------------------------------------------

	/**
	 * API REST : la liste et le détail des comptes (« wp/v2/users ») sont réservés aux personnes qui
	 * rédigent du contenu (l'éditeur en a besoin pour choisir un auteur). « users/me » reste ouvert
	 * aux comptes connectés.
	 *
	 * @param array $endpoints Points d'accès.
	 * @return array
	 */
	public static function rest_endpoints( $endpoints ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(/\(\?P<id>[^)]+\))?$#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * « ?author=N » : page introuvable pour les visiteurs, au lieu d'une redirection vers l'archive
	 * qui donne l'identifiant du compte N.
	 *
	 * @param array $query_vars Variables de la requête.
	 * @return array
	 */
	public static function author_query( $query_vars ) {
		if ( is_admin() || current_user_can( 'edit_posts' ) || ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $query_vars;
		}

		unset( $query_vars['author'], $query_vars['author_name'] );
		$query_vars['error'] = '404';

		return $query_vars;
	}

	/**
	 * Archives d'auteur (« /author/identifiant/ ») : page introuvable pour les visiteurs. Le site
	 * n'y renvoie nulle part ; elles ne servaient qu'à lister les comptes.
	 */
	public static function author_archive() {
		global $wp_query;

		if ( ! is_author() || current_user_can( 'edit_posts' ) ) {
			return;
		}

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Contenus intégrés (oEmbed) : sans le nom ni l'adresse de l'archive de l'auteur.
	 *
	 * @param array $data Données oEmbed.
	 * @return array
	 */
	public static function oembed_data( $data ) {
		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}

	/**
	 * Yoast SEO : archives d'auteur désactivées (« Archives d'auteur » sur Non dans Yoast SEO ›
	 * Réglages), donc plus de plan du site des auteurs.
	 *
	 * @param mixed $titles Réglages « wpseo_titles ».
	 * @return mixed
	 */
	public static function yoast_titles( $titles ) {
		if ( is_array( $titles ) ) {
			$titles['disable-author'] = true;
		}

		return $titles;
	}
}
