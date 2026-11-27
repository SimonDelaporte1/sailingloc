<?php
/**
 * Formulaires du compte : mots de passe sensibles à la casse (Opquast 73) et connexion sécurisée
 * signalée sous les boutons (Opquast 27).
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aides des formulaires de connexion, d'inscription, de réinitialisation du mot de passe, des
 * détails du compte et de la validation de la commande.
 *
 * - Mot de passe : « Sensible à la casse (majuscules et minuscules). » sous le premier champ mot de
 *   passe de chaque formulaire du compte ; chaque champ mot de passe du formulaire y renvoie
 *   (aria-describedby). Les modèles de WooCommerce n'ont pas de point d'accroche à côté des
 *   champs : le formulaire est lu entre ses actions de début et de fin, puis complété.
 * - Connexion sécurisée : un cadenas et « Connexion sécurisée (HTTPS) » sous le bouton des mêmes
 *   formulaires et sous le bloc de validation de la commande (bouton « Commander »), seulement si
 *   la page est réellement servie en HTTPS.
 * - Page de connexion de WordPress (wp-login.php) : mêmes aides sous le champ mot de passe.
 */
final class Icademie_Comptes_Saisie {

	/** Formulaires lus : action de début => action de fin. */
	const FORMS = array(
		'connexion'        => array( 'woocommerce_login_form_start', 'woocommerce_login_form_end' ),
		'inscription'      => array( 'woocommerce_register_form_start', 'woocommerce_register_form_end' ),
		'reinitialisation' => array( 'woocommerce_before_reset_password_form', 'woocommerce_after_reset_password_form' ),
		'compte'           => array( 'woocommerce_edit_account_form_start', 'woocommerce_edit_account_form_end' ),
	);

	/**
	 * Niveaux de mise en mémoire tampon ouverts, par formulaire.
	 *
	 * @var int[]
	 */
	private static $buffers = array();

	/**
	 * Branche les formulaires.
	 */
	public static function init() {
		foreach ( self::FORMS as $form => $hooks ) {
			add_action(
				$hooks[0],
				static function () use ( $form ) {
					self::start( $form );
				},
				1
			);
			add_action(
				$hooks[1],
				static function () use ( $form ) {
					self::end( $form );
				},
				PHP_INT_MAX
			);
		}

		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'checkout_block' ) );
		add_action( 'woocommerce_review_order_after_submit', array( __CLASS__, 'print_secure_notice' ) );
		add_action( 'login_form', array( __CLASS__, 'wp_login_hint' ) );
		add_action( 'resetpass_form', array( __CLASS__, 'wp_login_hint' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_style' ) );
	}

	/**
	 * Feuille de styles : compte client et validation de la commande.
	 */
	public static function enqueue() {
		if ( ( function_exists( 'is_account_page' ) && is_account_page() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			wp_enqueue_style( Icademie_Comptes_Donnees::HANDLE, plugins_url( 'assets/comptes.css', __DIR__ ), array(), Icademie_Comptes::VERSION );
		}
	}

	/**
	 * La page est-elle servie en HTTPS ?
	 *
	 * @return bool
	 */
	public static function is_secure() {
		/**
		 * La connexion est-elle chiffrée (mention « Connexion sécurisée (HTTPS) ») ?
		 *
		 * @param bool $secure Page servie en HTTPS (is_ssl()).
		 */
		return (bool) apply_filters( 'icademie_comptes_connexion_securisee', is_ssl() );
	}

	/**
	 * Texte de l'aide « sensible à la casse ».
	 *
	 * @return string
	 */
	public static function case_text() {
		return __( 'Mot de passe sensible à la casse (majuscules et minuscules).', 'icademie-comptes' );
	}

	/**
	 * Mention « Connexion sécurisée (HTTPS) », avec un cadenas.
	 *
	 * @return string HTML, vide si la page n'est pas servie en HTTPS.
	 */
	public static function secure_notice() {
		if ( ! self::is_secure() ) {
			return '';
		}

		$lock = '<svg class="icademie-comptes-securise__icone" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4.5" y="10.5" width="15" height="10.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>';

		return '<p class="icademie-comptes-securise">' . $lock . '<span>' . esc_html__( 'Connexion sécurisée (HTTPS) : vos informations sont chiffrées pendant leur envoi.', 'icademie-comptes' ) . '</span></p>';
	}

	/**
	 * Affiche la mention (commande classique, sous le bouton « Commander »).
	 */
	public static function print_secure_notice() {
		echo self::secure_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Texte échappé, SVG fixe.
	}

	/**
	 * Bloc de validation de la commande : la mention sous le bloc, donc sous le bouton « Commander »
	 * (le contenu du bloc est remplacé par son script : rien ne peut y être ajouté côté serveur).
	 *
	 * @param string $content Bloc.
	 * @return string
	 */
	public static function checkout_block( $content ) {
		$notice = self::secure_notice();

		return '' === $notice ? $content : $content . '<div class="icademie-comptes-securise-commande alignwide">' . $notice . '</div>';
	}

	/**
	 * Début d'un formulaire : sa sortie est mise de côté.
	 *
	 * @param string $form Formulaire.
	 */
	private static function start( $form ) {
		if ( isset( self::$buffers[ $form ] ) ) {
			return;
		}

		ob_start();
		self::$buffers[ $form ] = ob_get_level();
	}

	/**
	 * Fin d'un formulaire : aides ajoutées, puis affichage.
	 *
	 * @param string $form Formulaire.
	 */
	private static function end( $form ) {
		if ( ! isset( self::$buffers[ $form ] ) ) {
			return;
		}

		$level = self::$buffers[ $form ];
		unset( self::$buffers[ $form ] );

		// Tampon ouvert entre-temps par un autre code et pas refermé : on n'y touche pas.
		if ( ob_get_level() !== $level ) {
			return;
		}

		$html = (string) ob_get_clean();

		echo self::complete( $html, $form ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formulaire de WooCommerce, aides échappées.
	}

	/**
	 * Ajoute l'aide « sensible à la casse » et la mention de connexion sécurisée à un formulaire.
	 *
	 * @param string $html Code du formulaire.
	 * @param string $form Formulaire.
	 * @return string
	 */
	public static function complete( $html, $form ) {
		$hint_id = 'icademie-comptes-casse-' . sanitize_key( $form );
		$tags    = new WP_HTML_Tag_Processor( $html );
		$found   = false;

		while ( $tags->next_tag( 'input' ) ) {
			if ( 'password' !== strtolower( (string) $tags->get_attribute( 'type' ) ) ) {
				continue;
			}

			$described = trim( (string) $tags->get_attribute( 'aria-describedby' ) );
			$ids       = array_filter( explode( ' ', $described ) );

			if ( ! in_array( $hint_id, $ids, true ) ) {
				$ids[] = $hint_id;
			}

			$tags->set_attribute( 'aria-describedby', implode( ' ', $ids ) );

			// Aide visible une fois, sous le premier champ mot de passe.
			if ( ! $found ) {
				$tags->set_attribute( 'data-icademie-casse', '1' );
				$found = true;
			}
		}

		$html = $tags->get_updated_html();

		if ( $found ) {
			$hint = '<span class="icademie-comptes__aide icademie-comptes__casse" id="' . esc_attr( $hint_id ) . '">' . esc_html( self::case_text() ) . '</span>';
			$html = (string) preg_replace( '/(<input\b[^>]*?)\s+data-icademie-casse="1"([^>]*>)/i', '$1$2' . $hint, $html, 1 );
		}

		$notice = self::secure_notice();

		if ( '' === $notice ) {
			return $html;
		}

		// Sous le bouton : à la fin du formulaire (avant « </form> » si le code lu le contient).
		$position = strripos( $html, '</form>' );

		return false === $position ? $html . $notice : substr( $html, 0, $position ) . $notice . substr( $html, $position );
	}

	/**
	 * Page de connexion de WordPress : aides sous le champ mot de passe.
	 */
	public static function wp_login_hint() {
		echo '<p class="icademie-comptes-wp-login">' . esc_html( self::case_text() ) . '</p>';
		echo self::secure_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Texte échappé, SVG fixe.
	}

	/**
	 * Styles de la page de connexion de WordPress.
	 */
	public static function login_style() {
		wp_register_style( 'icademie-comptes-login', false, array(), Icademie_Comptes::VERSION );
		wp_enqueue_style( 'icademie-comptes-login' );
		wp_add_inline_style( 'icademie-comptes-login', '.icademie-comptes-wp-login,.icademie-comptes-securise{margin:-8px 0 16px;color:#50575e;font-size:13px}.icademie-comptes-securise{display:flex;gap:6px;align-items:center;margin-top:0}.icademie-comptes-securise svg{flex:none}' );
	}
}
