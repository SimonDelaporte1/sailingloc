<?php
/**
 * Page Newsletter : désinscription depuis le site (Opquast 176) et puces émojis masquées aux
 * lecteurs d'écran (Opquast 189).
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sous le contenu de la page Newsletter (celle des réglages de la page d'accueil) :
 * - « Se désinscrire de la newsletter » : le lien de la page de gestion des abonnements, s'il est
 *   réglé (Réglages › Page d'accueil, zone Newsletter), et un formulaire « adresse e-mail » qui
 *   transmet la demande par e-mail à l'équipe (destinataire des messages de la page Contact, sinon
 *   l'administrateur du site). Les envois de HubSpot contiennent aussi leur lien de désinscription.
 *   Protection : jeton de sécurité, champ piège, cinq demandes par heure et par adresse IP ;
 *   l'adresse saisie ne reçoit aucun e-mail (le formulaire ne peut pas servir à écrire à un tiers).
 * - Les émojis du contenu de la page (puces « 📍 », « 🛟 »…, « ⛵ » du titre) sont décoratifs :
 *   ils sont entourés de <span aria-hidden="true">, pour que les lecteurs d'écran ne les lisent pas.
 */
final class Icademie_Page_Accueil_Desinscription {

	/** Champs du formulaire. */
	const FIELD = 'icademie_desinscription';

	/** Jeton de sécurité. */
	const NONCE_ACTION = 'icademie_newsletter_desinscription';
	const NONCE_FIELD  = 'icademie_desinscription_jeton';

	/** Paramètre de l'adresse après l'envoi. */
	const SENT_ARG = 'desinscription';

	/** Ancre de la section. */
	const ANCHOR = 'newsletter-desinscription';

	/** Demandes par heure et par adresse IP. */
	const LIMIT = 5;

	/** Poignée de la feuille de styles. */
	const HANDLE = 'icademie-page-accueil-desinscription';

	/**
	 * Erreur du dernier envoi.
	 *
	 * @var string
	 */
	private static $error = '';

	/**
	 * Adresse saisie, réaffichée après une erreur.
	 *
	 * @var string
	 */
	private static $email = '';

	/**
	 * Branche la page Newsletter.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	/**
	 * La page affichée est-elle la page Newsletter ?
	 *
	 * @return WP_Post|null
	 */
	private static function current_page() {
		if ( ! is_singular() ) {
			return null;
		}

		$page = Icademie_Page_Accueil_Newsletter::page();

		return $page && (int) get_queried_object_id() === (int) $page->ID ? $page : null;
	}

	/**
	 * Feuille de styles de la section.
	 */
	public static function enqueue() {
		if ( self::current_page() ) {
			wp_enqueue_style( self::HANDLE, plugins_url( 'assets/desinscription.css', Icademie_Page_Accueil::FILE ), array(), Icademie_Page_Accueil::VERSION );
		}
	}

	/**
	 * Traite le formulaire, avant l'affichage de la page.
	 */
	public static function handle_request() {
		if ( ! self::current_page() ) {
			return;
		}

		// Jeton de sécurité et messages propres à chaque visiteur : page jamais mise en cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Vérifié ci-dessous.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST[ self::FIELD ] ) || ! is_array( $_POST[ self::FIELD ] ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Vérifié ci-dessous, après la lecture de l'adresse à réafficher.
		$raw   = wp_unslash( $_POST[ self::FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé champ par champ.
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) && is_string( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		// phpcs:enable

		self::$email = isset( $raw['email'] ) && is_string( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '';

		// Champ piège rempli : un robot. Même réponse qu'une demande transmise, sans rien envoyer.
		if ( isset( $raw['site'] ) && is_string( $raw['site'] ) && '' !== $raw['site'] ) {
			self::redirect_after_sending();
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::$error = __( 'La page a expiré : cliquez de nouveau sur « Me désinscrire ».', 'icademie-page-accueil' );
			return;
		}

		if ( '' === self::$email || ! is_email( self::$email ) ) {
			self::$error = __( 'Indiquez l\'adresse e-mail abonnée à la newsletter, par exemple prenom.nom@exemple.fr.', 'icademie-page-accueil' );
			return;
		}

		if ( ! self::within_limit() ) {
			self::$error = __( 'Trop de demandes ont été envoyées depuis votre connexion. Réessayez dans une heure, ou écrivez-nous depuis la page Contact.', 'icademie-page-accueil' );
			return;
		}

		if ( ! self::send( self::$email ) ) {
			self::$error = __( 'Votre demande n\'a pas pu être transmise à la suite d\'un problème technique. Réessayez dans quelques instants, ou écrivez-nous depuis la page Contact.', 'icademie-page-accueil' );
			return;
		}

		self::redirect_after_sending();
	}

	/**
	 * Cinq demandes par heure et par adresse IP au plus.
	 *
	 * @return bool
	 */
	private static function within_limit() {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'icademie_desinscription_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Transmet la demande à l'équipe.
	 *
	 * @param string $email Adresse à désinscrire.
	 * @return bool
	 */
	private static function send( $email ) {
		$to   = method_exists( 'Icademie_Page_Contact_Settings', 'recipient' ) ? Icademie_Page_Contact_Settings::recipient() : (string) get_option( 'admin_email' );
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		$lines = array(
			__( 'Demande de désinscription de la newsletter, envoyée depuis la page Newsletter du site.', 'icademie-page-accueil' ),
			'',
			/* translators: %s: adresse e-mail. */
			sprintf( __( 'Adresse à retirer de la liste d\'envoi : %s', 'icademie-page-accueil' ), $email ),
			/* translators: %s: date et heure. */
			sprintf( __( 'Date de la demande : %s', 'icademie-page-accueil' ), wp_date( 'j F Y à H:i' ) ),
			'',
			__( 'À faire : retirer cette adresse des contacts abonnés dans HubSpot (désabonnement de toutes les communications marketing).', 'icademie-page-accueil' ),
		);

		/**
		 * Demande de désinscription de la newsletter, avant l'envoi de l'e-mail à l'équipe.
		 *
		 * @param string $email Adresse à désinscrire.
		 */
		do_action( 'icademie_newsletter_desinscription', $email );

		return (bool) wp_mail(
			$to,
			/* translators: %s: nom du site. */
			sprintf( __( '[%s] Demande de désinscription de la newsletter', 'icademie-page-accueil' ), $site ),
			implode( "\n", $lines ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Demande transmise : retour sur la page avec le message de confirmation (pas de nouvel envoi
	 * si la page est rechargée).
	 */
	private static function redirect_after_sending() {
		$page = self::current_page();

		wp_safe_redirect( add_query_arg( self::SENT_ARG, 'envoyee', $page ? get_permalink( $page ) : home_url( '/' ) ) . '#' . self::ANCHOR, 303 );
		exit;
	}

	/**
	 * Contenu de la page Newsletter : émojis masqués aux lecteurs d'écran, section de désinscription
	 * à la fin.
	 *
	 * @param string $content Contenu.
	 * @return string
	 */
	public static function filter_content( $content ) {
		$page = self::current_page();

		if ( ! $page || (int) get_the_ID() !== (int) $page->ID || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return self::hide_emojis( (string) $content ) . self::section();
	}

	/**
	 * Entoure chaque émoji du texte d'un <span aria-hidden="true"> (texte seulement : balises,
	 * attributs, scripts et styles ne sont pas modifiés).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function hide_emojis( $html ) {
		$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $parts ) {
			return $html;
		}

		$skip = '';

		foreach ( $parts as $index => $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( '<' === $part[0] ) {
				if ( '' === $skip && preg_match( '#^<(script|style|textarea)\b#i', $part, $match ) ) {
					$skip = strtolower( $match[1] );
				} elseif ( '' !== $skip && preg_match( '#^</' . $skip . '\s*>#i', $part ) ) {
					$skip = '';
				}
				continue;
			}

			if ( '' !== $skip ) {
				continue;
			}

			// Pictogrammes (symboles divers, dingbats, émojis), avec leur sélecteur de variante,
			// leur couleur de peau et leurs assemblages (ZWJ).
			$replaced = preg_replace(
				'/(?:[\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{1F000}-\x{1FAFF}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]?(?:\x{200D}[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}][\x{FE0F}]?)*)+/u',
				'<span aria-hidden="true">$0</span>',
				$part
			);

			if ( is_string( $replaced ) ) {
				$parts[ $index ] = $replaced;
			}
		}

		return implode( '', $parts );
	}

	/**
	 * Section « Se désinscrire de la newsletter ».
	 *
	 * @return string
	 */
	private static function section() {
		$manage = (string) Icademie_Page_Accueil_Settings::get( 'newsletter_desinscription_url' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple message après la redirection.
		$sent = '' === self::$error && isset( $_GET[ self::SENT_ARG ] );

		ob_start();
		?>
		<section class="icademie-desinscription" id="<?php echo esc_attr( self::ANCHOR ); ?>" aria-labelledby="<?php echo esc_attr( self::ANCHOR ); ?>-titre">
			<h2 class="wp-block-heading" id="<?php echo esc_attr( self::ANCHOR ); ?>-titre"><?php esc_html_e( 'Se désinscrire de la newsletter', 'icademie-page-accueil' ); ?></h2>
			<p><?php esc_html_e( 'Chaque newsletter contient un lien de désinscription, en bas du message. Vous pouvez aussi demander votre désinscription ici : indiquez l\'adresse e-mail abonnée, nous la retirons de la liste d\'envoi.', 'icademie-page-accueil' ); ?></p>
			<?php if ( '' !== $manage ) : ?>
				<p><a href="<?php echo esc_url( $manage ); ?>"><?php esc_html_e( 'Gérer mes abonnements ou me désinscrire en ligne', 'icademie-page-accueil' ); ?></a></p>
			<?php endif; ?>

			<?php if ( $sent ) : ?>
				<p class="icademie-desinscription__message icademie-desinscription__message--ok" role="status"><?php esc_html_e( 'Votre demande de désinscription a bien été transmise. Votre adresse sera retirée de la liste d\'envoi.', 'icademie-page-accueil' ); ?></p>
			<?php endif; ?>

			<form class="icademie-desinscription__form" method="post" action="<?php echo esc_url( get_permalink() . '#' . self::ANCHOR ); ?>" novalidate>
				<?php if ( '' !== self::$error ) : ?>
					<p class="icademie-desinscription__message icademie-desinscription__message--erreur" id="<?php echo esc_attr( self::ANCHOR ); ?>-erreur" role="alert"><?php echo esc_html( self::$error ); ?></p>
				<?php endif; ?>
				<p class="icademie-desinscription__champ">
					<label for="<?php echo esc_attr( self::ANCHOR ); ?>-email"><?php esc_html_e( 'Adresse e-mail abonnée', 'icademie-page-accueil' ); ?> <span aria-hidden="true">*</span></label>
					<input type="email" id="<?php echo esc_attr( self::ANCHOR ); ?>-email" name="<?php echo esc_attr( self::FIELD . '[email]' ); ?>" value="<?php echo esc_attr( self::$email ); ?>" autocomplete="email" spellcheck="false" maxlength="254" required aria-required="true"<?php echo '' !== self::$error ? ' aria-invalid="true" aria-describedby="' . esc_attr( self::ANCHOR ) . '-erreur"' : ''; ?>>
				</p>
				<input type="hidden" name="<?php echo esc_attr( self::NONCE_FIELD ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
				<?php // Champ piège : invisible pour les visiteurs, rempli par les robots. ?>
				<div class="icademie-desinscription__piege" aria-hidden="true">
					<label for="<?php echo esc_attr( self::ANCHOR ); ?>-site"><?php esc_html_e( 'Laissez ce champ vide', 'icademie-page-accueil' ); ?></label>
					<input type="text" id="<?php echo esc_attr( self::ANCHOR ); ?>-site" name="<?php echo esc_attr( self::FIELD . '[site]' ); ?>" value="" tabindex="-1" autocomplete="off">
				</div>
				<p class="icademie-desinscription__actions">
					<button type="submit"><?php esc_html_e( 'Me désinscrire', 'icademie-page-accueil' ); ?></button>
				</p>
				<p class="icademie-desinscription__legende"><?php esc_html_e( '* Champ obligatoire. Votre adresse sert seulement à traiter cette demande.', 'icademie-page-accueil' ); ?></p>
			</form>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
