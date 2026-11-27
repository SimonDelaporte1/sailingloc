<?php
/**
 * Formulaire de la page Contact : vérification, envoi par e-mail, accusé de réception.
 *
 * @package Icademie_Page_Contact
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire de contact.
 *
 * Le formulaire est envoyé à la page elle-même. Il est traité avant l'affichage de la page
 * (template_redirect) :
 * - refusé : la page s'affiche de nouveau avec les valeurs saisies et un message sous chaque champ
 *   en erreur ;
 * - accepté : un e-mail part vers l'équipe (pièce jointe comprise, « Répondre » écrit à
 *   l'expéditeur), un accusé de réception part vers l'expéditeur, puis le visiteur est redirigé
 *   vers la page avec un message de confirmation (pas de double envoi en rechargeant la page).
 *
 * Rien n'est enregistré dans la base de données : les messages n'existent que dans les e-mails.
 *
 * Protections contre les robots : jeton de sécurité (nonce), champ piège invisible, délai minimal
 * entre l'affichage et l'envoi, et nombre d'accusés de réception limité par adresse (le formulaire
 * ne peut pas servir à inonder une boîte e-mail).
 */
final class Icademie_Page_Contact_Form {

	/** Champs du formulaire, envoyés dans un tableau : icademie_contact[nom], icademie_contact[email]… */
	const FIELD = 'icademie_contact';

	/** Champ de la pièce jointe. */
	const FILE_FIELD = 'icademie_contact_fichier';

	/** Jeton de sécurité. */
	const NONCE_ACTION = 'icademie_page_contact_envoi';
	const NONCE_FIELD  = 'icademie_contact_nonce';

	/** Paramètre ajouté à l'adresse de la page après un envoi réussi. */
	const SENT_ARG = 'message-envoye';

	/** Ancre du formulaire, pour revenir dessus après l'envoi. */
	const ANCHOR = 'icademie-contact-formulaire';

	/** Délai minimal, en secondes, entre l'affichage du formulaire et son envoi. */
	const MIN_DELAY = 3;

	/** Accusés de réception envoyés au plus, par heure, à une même adresse. */
	const ACK_LIMIT = 3;

	/** Taille maximale de la pièce jointe : 5 Mo, plafonnée par la configuration du serveur. */
	const MAX_FILE_SIZE = 5242880;

	/** Longueurs maximales et minimales. */
	const NAME_MAX        = 100;
	const RESERVATION_MAX = 40;
	const MESSAGE_MIN     = 10;
	const MESSAGE_MAX     = 5000;

	/** Objet des signalements (avis, contenus abusifs). */
	const REPORT_SUBJECT = 'signalement';

	/**
	 * Nom ou prénom : lettres (tous alphabets, accents compris), espaces, apostrophes, traits
	 * d'union et points. Ni chiffres, ni « @ », ni adresse web.
	 */
	const NAME_PATTERN = '/^[\p{L}\p{M}][\p{L}\p{M}\s\'’.-]*$/u';

	/**
	 * Erreurs du dernier envoi refusé : clé du champ (ou « _form » pour tout le formulaire) => message.
	 *
	 * @var string[]
	 */
	private static $errors = array();

	/**
	 * Valeurs saisies lors du dernier envoi refusé, réaffichées dans le formulaire.
	 *
	 * @var array|null
	 */
	private static $values = null;

	/**
	 * Une pièce jointe avait été choisie lors de l'envoi refusé : il faudra la choisir de nouveau.
	 *
	 * @var bool
	 */
	private static $file_dropped = false;

	/**
	 * Branche le traitement du formulaire.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
	}

	/**
	 * Profils proposés (« Vous êtes »).
	 *
	 * @return string[]
	 */
	public static function profiles() {
		return array(
			'locataire'    => __( 'Locataire', 'icademie-page-contact' ),
			'proprietaire' => __( 'Propriétaire', 'icademie-page-contact' ),
			'autre'        => __( 'Autre demande', 'icademie-page-contact' ),
		);
	}

	/**
	 * Objets de demande proposés pour chaque profil.
	 *
	 * Une même clé dans deux profils doit avoir le même sens (« compte », « autre »).
	 *
	 * @return array[] Profil => ( clé => libellé ).
	 */
	public static function subjects() {
		return array(
			'locataire'    => array(
				'reservation'       => __( 'Préparer ou modifier une réservation', 'icademie-page-contact' ),
				'paiement'          => __( 'Paiement et remboursement', 'icademie-page-contact' ),
				'annulation'        => __( 'Annulation', 'icademie-page-contact' ),
				'assurance-caution' => __( 'Assurance et caution', 'icademie-page-contact' ),
				'bateau'            => __( 'Question sur un bateau ou un propriétaire', 'icademie-page-contact' ),
				'compte'            => __( 'Mon compte', 'icademie-page-contact' ),
				'signalement'       => self::report_label(),
				'autre'             => __( 'Autre question', 'icademie-page-contact' ),
			),
			'proprietaire' => array(
				'annonce'                => __( 'Mettre mon bateau en location ou modifier mon annonce', 'icademie-page-contact' ),
				'reservations'           => __( 'Réservations de mon bateau', 'icademie-page-contact' ),
				'versements'             => __( 'Paiements et versements', 'icademie-page-contact' ),
				'assurance-proprietaire' => __( 'Assurance propriétaire', 'icademie-page-contact' ),
				'commission'             => __( 'Tarifs et commission', 'icademie-page-contact' ),
				'compte'                 => __( 'Mon compte', 'icademie-page-contact' ),
				'signalement'            => self::report_label(),
				'autre'                  => __( 'Autre question', 'icademie-page-contact' ),
			),
			'autre'        => array(
				'partenariat' => __( 'Partenariat', 'icademie-page-contact' ),
				'presse'      => __( 'Presse', 'icademie-page-contact' ),
				'site'        => __( 'Signaler un problème sur le site', 'icademie-page-contact' ),
				'donnees'     => __( 'Données personnelles (RGPD)', 'icademie-page-contact' ),
				'signalement' => self::report_label(),
				'autre'       => __( 'Autre demande', 'icademie-page-contact' ),
			),
		);
	}

	/**
	 * Objet « Signalement », proposé à tous les profils (Opquast 11 : signaler un abus).
	 *
	 * @return string
	 */
	private static function report_label() {
		return __( 'Signalement d\'un avis ou d\'un contenu abusif', 'icademie-page-contact' );
	}

	/**
	 * Adresse du formulaire de contact pour signaler un avis : objet « Signalement » choisi et message
	 * prérempli avec l'annonce et l'avis concernés (lien « Signaler cet avis » de la fiche annonce).
	 *
	 * @param WP_Comment|int $comment Avis.
	 * @return string Adresse, ou vide sans page Contact publiée.
	 */
	public static function report_url( $comment ) {
		$comment = get_comment( $comment );
		$page_id = (int) Icademie_Page_Contact::find_contact_page();

		if ( ! $comment instanceof WP_Comment || ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return add_query_arg(
			array(
				'objet' => self::REPORT_SUBJECT,
				'avis'  => (int) $comment->comment_ID,
			),
			get_permalink( $page_id )
		) . '#' . self::ANCHOR;
	}

	/**
	 * Message prérempli d'un signalement : titre de l'annonce, auteur et date de l'avis, adresse.
	 *
	 * Seulement pour un avis publié d'un contenu public : le lien ne révèle rien d'autre.
	 *
	 * @param int $comment_id Avis.
	 * @return string Message, ou vide.
	 */
	private static function report_message( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof WP_Comment || '1' !== (string) $comment->comment_approved ) {
			return '';
		}

		$post = get_post( (int) $comment->comment_post_ID );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) || post_password_required( $post ) ) {
			return '';
		}

		// Prénom, comme sur la fiche : celui du compte, sinon le premier mot du nom de l'avis.
		$author = $comment->user_id ? trim( (string) get_user_meta( (int) $comment->user_id, 'first_name', true ) ) : '';
		$words  = preg_split( '/\s+/u', trim( wp_strip_all_tags( $comment->comment_author ) ), -1, PREG_SPLIT_NO_EMPTY );

		if ( '' === $author ) {
			$author = $words ? $words[0] : __( 'un locataire', 'icademie-page-contact' );
		}
		$date   = wp_date( 'j F Y', (int) strtotime( $comment->comment_date_gmt . ' UTC' ) );
		$title  = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );

		return sprintf(
			/* translators: 1: titre de l'annonce, 2: prénom de l'auteur de l'avis, 3: date de l'avis, 4: adresse de l'avis. */
			__( "Je signale un avis sur l'annonce « %1\$s » : avis de %2\$s du %3\$s (%4\$s).\n\nMotif du signalement : ", 'icademie-page-contact' ),
			$title,
			$author,
			$date,
			get_permalink( $post ) . '#comment-' . (int) $comment->comment_ID
		);
	}

	/**
	 * Libellé du champ « N° de réservation » selon le profil (pas de champ pour « Autre demande »).
	 *
	 * @return string[]
	 */
	private static function reservation_labels() {
		return array(
			'locataire'    => __( 'N° de réservation (facultatif)', 'icademie-page-contact' ),
			'proprietaire' => __( 'N° de réservation ou d\'annonce (facultatif)', 'icademie-page-contact' ),
		);
	}

	/**
	 * Messages d'erreur, communs au serveur et à la vérification dans le navigateur.
	 *
	 * @return string[]
	 */
	private static function messages() {
		$max_size = self::max_file_size_label();

		return array(
			'profil'          => __( 'Indiquez si vous êtes locataire, propriétaire ou si votre demande est d\'un autre ordre.', 'icademie-page-contact' ),
			'nom'             => __( 'Indiquez votre nom.', 'icademie-page-contact' ),
			'nom_invalide'    => __( 'Indiquez votre nom en lettres, sans chiffres ni caractères spéciaux.', 'icademie-page-contact' ),
			'prenom'          => __( 'Indiquez votre prénom.', 'icademie-page-contact' ),
			'prenom_invalide' => __( 'Indiquez votre prénom en lettres, sans chiffres ni caractères spéciaux.', 'icademie-page-contact' ),
			'email'           => __( 'Indiquez votre adresse e-mail.', 'icademie-page-contact' ),
			'email_invalide'  => __( 'Indiquez une adresse e-mail valide, par exemple prenom.nom@exemple.fr.', 'icademie-page-contact' ),
			'telephone'       => __( 'Indiquez un numéro de téléphone valide, par exemple 06 12 34 56 78.', 'icademie-page-contact' ),
			'objet'           => __( 'Choisissez l\'objet de votre demande.', 'icademie-page-contact' ),
			/* translators: %d: nombre maximal de caractères. */
			'reservation'     => sprintf( __( 'Le numéro ne doit pas dépasser %d caractères.', 'icademie-page-contact' ), self::RESERVATION_MAX ),
			'message'         => __( 'Écrivez votre message.', 'icademie-page-contact' ),
			/* translators: %d: nombre minimal de caractères. */
			'message_court'   => sprintf( __( 'Votre message est trop court : précisez votre demande en quelques mots (%d caractères minimum).', 'icademie-page-contact' ), self::MESSAGE_MIN ),
			/* translators: %s: nombre maximal de caractères. */
			'message_long'    => sprintf( __( 'Votre message ne doit pas dépasser %s caractères.', 'icademie-page-contact' ), number_format_i18n( self::MESSAGE_MAX ) ),
			/* translators: %s: taille maximale (« 5 Mo »). */
			'fichier_taille'  => sprintf( __( 'Le fichier dépasse la taille maximale de %s : choisissez un fichier plus léger.', 'icademie-page-contact' ), $max_size ),
			'fichier_format'  => __( 'Format non accepté : joignez une photo (JPG, PNG ou WebP) ou un PDF.', 'icademie-page-contact' ),
			'fichier_erreur'  => __( 'La pièce jointe n\'a pas pu être reçue. Réessayez, ou envoyez votre message sans pièce jointe.', 'icademie-page-contact' ),
			'rgpd'            => __( 'Cochez cette case pour que nous puissions traiter votre demande.', 'icademie-page-contact' ),
			'expire'          => __( 'La page a expiré pendant votre saisie. Vérifiez vos informations, puis cliquez de nouveau sur « Envoyer le message ».', 'icademie-page-contact' ),
			'rapide'          => __( 'Le formulaire a été envoyé trop vite. Patientez quelques secondes, puis cliquez de nouveau sur « Envoyer le message ».', 'icademie-page-contact' ),
			'envoi'           => __( 'Votre message n\'a pas pu être envoyé à la suite d\'un problème technique. Réessayez dans quelques instants, ou contactez-nous par téléphone ou par e-mail.', 'icademie-page-contact' ),
			/* translators: %s: taille maximale (« 5 Mo »). */
			'trop_lourd'      => sprintf( __( 'Votre message n\'a pas pu être envoyé : la pièce jointe est trop lourde (%s maximum). Choisissez un fichier plus léger, puis saisissez de nouveau votre message.', 'icademie-page-contact' ), $max_size ),
		);
	}

	/**
	 * Traite l'envoi du formulaire, avant l'affichage de la page Contact.
	 */
	public static function handle_request() {
		if ( ! Icademie_Page_Contact::is_contact_page() ) {
			return;
		}

		// Page propre à chaque visiteur (jeton de sécurité, valeurs saisies, messages) : jamais mise en
		// cache par WP Super Cache ou une autre extension de cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Le nonce est vérifié dans process(), après la lecture des valeurs à réafficher.
		if ( isset( $_POST[ self::FIELD ] ) ) {
			self::process();
		} elseif ( self::is_post_too_large() ) {
			self::$errors['_form'] = self::messages()['trop_lourd'];
		}
	}

	/**
	 * Vérifie le formulaire, puis envoie les e-mails.
	 */
	private static function process() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Le nonce est vérifié ci-dessous ; les valeurs sont lues avant pour être réaffichées si la page a expiré.
		$raw = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce : comparé tel quel.
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) && is_string( $_POST[ self::NONCE_FIELD ] ) ? wp_unslash( $_POST[ self::NONCE_FIELD ] ) : '';
		// phpcs:enable

		self::$values = self::sanitize_values( $raw );
		$messages     = self::messages();

		// Champ piège rempli : c'est un robot. On fait comme si le message était parti, sans rien envoyer.
		if ( '' !== self::text( $raw, 'site' ) ) {
			self::redirect_after_sending();
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::$errors['_form'] = $messages['expire'];
			self::$file_dropped    = self::has_uploaded_file();
			return;
		}

		$token_error = self::token_error( self::text( $raw, 'jeton' ) );

		if ( '' !== $token_error ) {
			self::$errors['_form'] = $messages[ $token_error ];
			self::$file_dropped    = self::has_uploaded_file();
			return;
		}

		self::$errors = self::validate( self::$values );
		$file         = self::uploaded_file();

		if ( self::$errors ) {
			return;
		}

		$reference = self::reference();

		if ( ! self::send_to_team( self::$values, $file, $reference ) ) {
			self::$errors['_form'] = $messages['envoi'];
			self::$file_dropped    = null !== $file;
			return;
		}

		self::send_acknowledgement( self::$values, $reference );
		self::redirect_after_sending();
	}

	/**
	 * Données du formulaire pour l'affichage.
	 *
	 * @param WP_Post $post Page Contact.
	 * @return array
	 */
	public static function view_data( $post ) {
		$errors = self::$errors;

		return array(
			'action'             => get_permalink( $post ) . '#' . self::ANCHOR,
			'page_url'           => get_permalink( $post ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple affichage du message de confirmation.
			'sent'               => ! $errors && isset( $_GET[ self::SENT_ARG ] ),
			'values'             => null !== self::$values ? self::$values : self::default_values(),
			'errors'             => $errors,
			'file_dropped'       => self::$file_dropped && ! isset( $errors['fichier'] ),
			'profiles'           => self::profiles(),
			'subjects'           => self::subjects(),
			'reservation_labels' => self::reservation_labels(),
			'max_size_label'     => self::max_file_size_label(),
			'accept'             => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
			'privacy_url'        => get_privacy_policy_url(),
			'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
			'token'              => self::token(),
			'config'             => array(
				'messages'          => self::messages(),
				'reservationLabels' => self::reservation_labels(),
				'nameMax'           => self::NAME_MAX,
				'reservationMax'    => self::RESERVATION_MAX,
				'messageMin'        => self::MESSAGE_MIN,
				'messageMax'        => self::MESSAGE_MAX,
				/* translators: %s: nombre de caractères restants. */
				'counterLeft'       => __( 'Il vous reste %s caractères.', 'icademie-page-contact' ),
				'counterOne'        => __( 'Il vous reste 1 caractère.', 'icademie-page-contact' ),
				/* translators: %s: nombre maximal de caractères (« 5 000 »). */
				'counterFull'       => sprintf( __( 'Limite de %s caractères atteinte.', 'icademie-page-contact' ), number_format_i18n( self::MESSAGE_MAX ) ),
				'maxSize'           => self::max_file_size(),
				'extensions'        => array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'pdf' ),
				'summaryOne'        => __( 'Le formulaire contient une erreur :', 'icademie-page-contact' ),
				/* translators: %d: nombre d'erreurs. */
				'summaryMany'       => __( 'Le formulaire contient %d erreurs :', 'icademie-page-contact' ),
				'sending'           => __( 'Envoi en cours…', 'icademie-page-contact' ),
				'addFile'           => __( 'Ajouter une pièce jointe', 'icademie-page-contact' ),
				'changeFile'        => __( 'Changer de pièce jointe', 'icademie-page-contact' ),
				'units'             => array(
					'kb' => __( 'Ko', 'icademie-page-contact' ),
					'mb' => __( 'Mo', 'icademie-page-contact' ),
				),
			),
		);
	}

	/**
	 * Valeurs de départ : profil « Locataire » ; nom, prénom, e-mail et téléphone du compte du
	 * visiteur connecté (profil « Propriétaire » pour un vendeur WC Vendors).
	 *
	 * Un lien peut aussi présélectionner le profil et l'objet : …/contact/?profil=proprietaire&objet=annonce
	 * Signalement d'un avis : …/contact/?objet=signalement&avis=123 (message prérempli).
	 *
	 * @return array
	 */
	private static function default_values() {
		$values = array(
			'profil'      => 'locataire',
			'nom'         => '',
			'prenom'      => '',
			'email'       => '',
			'telephone'   => '',
			'objet'       => '',
			'reservation' => '',
			'message'     => '',
			'rgpd'        => '',
		);

		$user = wp_get_current_user();

		if ( $user->exists() ) {
			$values['nom']    = $user->last_name;
			$values['prenom'] = $user->first_name;
			$values['email']  = $user->user_email;

			$phone = get_user_meta( $user->ID, 'billing_phone', true );

			if ( is_string( $phone ) ) {
				$values['telephone'] = $phone;
			}

			if ( in_array( 'vendor', (array) $user->roles, true ) ) {
				$values['profil'] = 'proprietaire';
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Simple présélection de champs.
		$profile = isset( $_GET['profil'] ) && is_string( $_GET['profil'] ) ? sanitize_key( wp_unslash( $_GET['profil'] ) ) : '';
		$subject = isset( $_GET['objet'] ) && is_string( $_GET['objet'] ) ? sanitize_key( wp_unslash( $_GET['objet'] ) ) : '';
		// phpcs:enable

		if ( isset( self::profiles()[ $profile ] ) ) {
			$values['profil'] = $profile;
		}

		if ( isset( self::subjects()[ $values['profil'] ][ $subject ] ) ) {
			$values['objet'] = $subject;
		}

		// Lien « Signaler cet avis » : message prérempli avec l'annonce et l'avis.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple préremplissage.
		$review = isset( $_GET['avis'] ) && is_string( $_GET['avis'] ) ? absint( $_GET['avis'] ) : 0;

		if ( self::REPORT_SUBJECT === $values['objet'] && $review ) {
			$values['message'] = self::report_message( $review );
		}

		return $values;
	}

	/**
	 * Nettoie les valeurs envoyées.
	 *
	 * Le message est seulement débarrassé des caractères invisibles : il part dans un e-mail en texte
	 * brut et n'est réaffiché qu'échappé, il garde donc les « < » et « % » écrits par le visiteur.
	 *
	 * @param array $raw Valeurs envoyées.
	 * @return array
	 */
	private static function sanitize_values( $raw ) {
		$profile = sanitize_key( self::text( $raw, 'profil' ) );

		return array(
			'profil'      => $profile,
			'nom'         => sanitize_text_field( self::text( $raw, 'nom' ) ),
			'prenom'      => sanitize_text_field( self::text( $raw, 'prenom' ) ),
			'email'       => sanitize_text_field( self::text( $raw, 'email' ) ),
			'telephone'   => sanitize_text_field( self::text( $raw, 'telephone' ) ),
			'objet'       => sanitize_key( self::text( $raw, 'objet' ) ),
			// Pas de numéro de réservation pour « Autre demande » : le champ est masqué.
			'reservation' => 'autre' === $profile ? '' : sanitize_text_field( self::text( $raw, 'reservation' ) ),
			'message'     => self::clean_message( self::text( $raw, 'message' ) ),
			'rgpd'        => '' !== self::text( $raw, 'rgpd' ) ? '1' : '',
		);
	}

	/**
	 * Valeur texte d'un champ envoyé ; vide si absente ou si ce n'est pas du texte.
	 *
	 * @param array  $raw Valeurs envoyées.
	 * @param string $key Clé du champ.
	 * @return string
	 */
	private static function text( $raw, $key ) {
		return isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? $raw[ $key ] : '';
	}

	/**
	 * Message : UTF-8 valide, retours à la ligne normalisés, sans caractères de contrôle.
	 *
	 * @param string $message Message envoyé.
	 * @return string
	 */
	private static function clean_message( $message ) {
		$message = wp_check_invalid_utf8( $message, true );
		$message = str_replace( array( "\r\n", "\r" ), "\n", $message );
		$message = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message );

		return trim( $message );
	}

	/**
	 * Vérifie les champs.
	 *
	 * @param array $values Valeurs nettoyées.
	 * @return string[] Clé du champ => message d'erreur.
	 */
	private static function validate( $values ) {
		$messages = self::messages();
		$subjects = self::subjects();
		$errors   = array();

		if ( ! isset( self::profiles()[ $values['profil'] ] ) ) {
			$errors['profil'] = $messages['profil'];
		}

		foreach ( array( 'nom', 'prenom' ) as $key ) {
			if ( '' === $values[ $key ] ) {
				$errors[ $key ] = $messages[ $key ];
			} elseif ( mb_strlen( $values[ $key ] ) > self::NAME_MAX || ! preg_match( self::NAME_PATTERN, $values[ $key ] ) ) {
				$errors[ $key ] = $messages[ $key . '_invalide' ];
			}
		}

		if ( '' === $values['email'] ) {
			$errors['email'] = $messages['email'];
		} elseif ( ! is_email( $values['email'] ) ) {
			$errors['email'] = $messages['email_invalide'];
		}

		if ( '' !== $values['telephone'] && ! self::is_phone( $values['telephone'] ) ) {
			$errors['telephone'] = $messages['telephone'];
		}

		if ( ! isset( $subjects[ $values['profil'] ][ $values['objet'] ] ) ) {
			$errors['objet'] = $messages['objet'];
		}

		if ( mb_strlen( $values['reservation'] ) > self::RESERVATION_MAX ) {
			$errors['reservation'] = $messages['reservation'];
		}

		$length = mb_strlen( $values['message'] );

		if ( 0 === $length ) {
			$errors['message'] = $messages['message'];
		} elseif ( $length < self::MESSAGE_MIN ) {
			$errors['message'] = $messages['message_court'];
		} elseif ( $length > self::MESSAGE_MAX ) {
			$errors['message'] = $messages['message_long'];
		}

		if ( '1' !== $values['rgpd'] ) {
			$errors['rgpd'] = $messages['rgpd'];
		}

		return $errors;
	}

	/**
	 * Numéro de téléphone plausible : chiffres, espaces, « + », points, tirets, parenthèses, au moins 6 chiffres.
	 *
	 * @param string $phone Numéro saisi.
	 * @return bool
	 */
	private static function is_phone( $phone ) {
		return (bool) preg_match( '/^\+?[0-9\s().-]{6,30}$/', $phone ) && preg_match_all( '/[0-9]/', $phone ) >= 6;
	}

	/**
	 * Une pièce jointe a-t-elle été envoyée ?
	 *
	 * @return bool
	 */
	private static function has_uploaded_file() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Simple test de présence.
		return isset( $_FILES[ self::FILE_FIELD ]['error'] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ self::FILE_FIELD ]['error'];
	}

	/**
	 * Vérifie la pièce jointe (facultative) et ajoute l'erreur éventuelle.
	 *
	 * La pièce jointe n'est jamais enregistrée sur le site : elle est jointe à l'e-mail depuis le
	 * fichier temporaire de PHP, supprimé à la fin de la requête.
	 *
	 * @return array|null Chemin du fichier temporaire, nom et taille ; null sans pièce jointe valable.
	 */
	private static function uploaded_file() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié dans process().
		$file  = isset( $_FILES[ self::FILE_FIELD ] ) && is_array( $_FILES[ self::FILE_FIELD ] ) ? $_FILES[ self::FILE_FIELD ] : array();
		$error = isset( $file['error'] ) && is_int( $file['error'] ) ? $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return null;
		}

		$messages           = self::messages();
		self::$file_dropped = true;

		$too_large = in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
			|| ( UPLOAD_ERR_OK === $error && isset( $file['size'] ) && (int) $file['size'] > self::max_file_size() );

		if ( $too_large ) {
			self::$errors['fichier'] = $messages['fichier_taille'];
			return null;
		}

		if ( UPLOAD_ERR_OK !== $error || ! isset( $file['tmp_name'], $file['name'] ) || ! is_string( $file['tmp_name'] ) || ! is_string( $file['name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::$errors['fichier'] = $messages['fichier_erreur'];
			return null;
		}

		// Vérifie le contenu réel du fichier, pas seulement son extension.
		$name  = sanitize_file_name( wp_basename( $file['name'] ) );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::allowed_mimes() );

		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			self::$errors['fichier'] = $messages['fichier_format'];
			return null;
		}

		if ( ! empty( $check['proper_filename'] ) ) {
			$name = $check['proper_filename'];
		}

		return array(
			'path' => $file['tmp_name'],
			'name' => $name,
			'size' => (int) $file['size'],
		);
	}

	/**
	 * Formats de pièce jointe acceptés.
	 *
	 * @return string[] Extensions => type MIME.
	 */
	private static function allowed_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
		);
	}

	/**
	 * Taille maximale de la pièce jointe, en octets.
	 *
	 * @return int
	 */
	private static function max_file_size() {
		/**
		 * Filtre la taille maximale de la pièce jointe (5 Mo par défaut).
		 *
		 * Elle reste plafonnée par la configuration du serveur (upload_max_filesize, post_max_size).
		 *
		 * @param int $size Taille en octets.
		 */
		$size = (int) apply_filters( 'icademie_page_contact_max_file_size', self::MAX_FILE_SIZE );

		return (int) min( $size, wp_max_upload_size() );
	}

	/**
	 * Taille maximale lisible : « 5 Mo ».
	 *
	 * @return string
	 */
	private static function max_file_size_label() {
		return size_format( self::max_file_size() );
	}

	/**
	 * L'envoi a-t-il dépassé la taille maximale acceptée par PHP (post_max_size) ?
	 *
	 * PHP vide alors $_POST et $_FILES : sans ce contrôle, le formulaire se réafficherait vide, sans
	 * explication.
	 *
	 * @return bool
	 */
	private static function is_post_too_large() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Simple test de présence.
		if ( ! empty( $_POST ) || ! empty( $_FILES ) || empty( $_SERVER['CONTENT_LENGTH'] ) ) {
			return false;
		}

		return (int) $_SERVER['CONTENT_LENGTH'] > wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
	}

	/**
	 * Jeton glissé dans le formulaire : heure d'affichage, signée pour ne pas pouvoir être inventée.
	 *
	 * @return string
	 */
	private static function token() {
		$time = time();

		return $time . '-' . self::token_hash( $time );
	}

	/**
	 * Signature du jeton.
	 *
	 * @param int $time Heure d'affichage du formulaire.
	 * @return string
	 */
	private static function token_hash( $time ) {
		return substr( wp_hash( 'icademie-page-contact|' . $time, 'nonce' ), 0, 16 );
	}

	/**
	 * Vérifie le jeton.
	 *
	 * @param string $token Jeton envoyé.
	 * @return string Vide si le jeton est valable ; sinon « expire » ou « rapide » (clé du message).
	 */
	private static function token_error( $token ) {
		if ( ! preg_match( '/^(\d{1,12})-([a-f0-9]{16})$/', $token, $matches ) || ! hash_equals( self::token_hash( (int) $matches[1] ), $matches[2] ) ) {
			return 'expire';
		}

		$age = time() - (int) $matches[1];

		if ( $age < self::MIN_DELAY ) {
			return 'rapide';
		}

		return $age > DAY_IN_SECONDS ? 'expire' : '';
	}

	/**
	 * Référence de la demande, reprise dans les deux e-mails : « 261120-K7QM ».
	 *
	 * @return string
	 */
	private static function reference() {
		$chars  = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$suffix = '';

		for ( $i = 0; $i < 4; $i++ ) {
			$suffix .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}

		return wp_date( 'ymd' ) . '-' . $suffix;
	}

	/**
	 * Envoie le message à l'équipe.
	 *
	 * @param array      $values    Valeurs vérifiées.
	 * @param array|null $file      Pièce jointe.
	 * @param string     $reference Référence de la demande.
	 * @return bool L'e-mail est-il parti ?
	 */
	private static function send_to_team( $values, $file, $reference ) {
		$site    = self::site_name();
		$subject = self::subjects()[ $values['profil'] ][ $values['objet'] ];
		$name    = trim( $values['prenom'] . ' ' . $values['nom'] );

		$lines = array(
			/* translators: %s: nom du site. */
			sprintf( __( 'Nouveau message envoyé depuis la page Contact de %s.', 'icademie-page-contact' ), $site ),
			'',
			self::line( __( 'Référence', 'icademie-page-contact' ), $reference ),
			self::line( __( 'Profil', 'icademie-page-contact' ), self::profiles()[ $values['profil'] ] ),
			self::line( __( 'Objet', 'icademie-page-contact' ), $subject ),
		);

		if ( '' !== $values['reservation'] ) {
			$lines[] = self::line( preg_replace( '/\s*\(.*\)$/', '', self::reservation_labels()[ $values['profil'] ] ), $values['reservation'] );
		}

		$lines[] = '';
		$lines[] = self::line( __( 'Nom', 'icademie-page-contact' ), $values['nom'] );
		$lines[] = self::line( __( 'Prénom', 'icademie-page-contact' ), $values['prenom'] );
		$lines[] = self::line( __( 'E-mail', 'icademie-page-contact' ), $values['email'] );

		if ( '' !== $values['telephone'] ) {
			$lines[] = self::line( __( 'Téléphone', 'icademie-page-contact' ), $values['telephone'] );
		}

		$lines[] = '';
		$lines[] = __( 'Message :', 'icademie-page-contact' );
		$lines[] = $values['message'];
		$lines[] = '';

		if ( $file ) {
			$lines[] = self::line( __( 'Pièce jointe', 'icademie-page-contact' ), sprintf( '%1$s (%2$s)', $file['name'], size_format( $file['size'], $file['size'] >= MB_IN_BYTES ? 1 : 0 ) ) );
			$lines[] = '';
		}

		$lines[] = '--';
		/* translators: 1: date et heure, 2: adresse de la page Contact. */
		$lines[] = sprintf( __( 'Envoyé le %1$s depuis %2$s', 'icademie-page-contact' ), self::now(), get_permalink( get_queried_object_id() ) );
		/* translators: %s: nom de l'expéditeur. */
		$lines[] = sprintf( __( 'Pour répondre à %s, répondez simplement à cet e-mail.', 'icademie-page-contact' ), $name );

		return self::mail(
			Icademie_Page_Contact_Settings::recipient(),
			/* translators: 1: nom du site, 2: objet de la demande, 3: nom de l'expéditeur. */
			sprintf( __( '[%1$s] %2$s – %3$s', 'icademie-page-contact' ), $site, $subject, $name ),
			implode( "\n", $lines ),
			array( 'Reply-To: ' . self::mailbox( $name, sanitize_email( $values['email'] ) ) ),
			$file ? array( $file['name'] => $file['path'] ) : array()
		);
	}

	/**
	 * Envoie l'accusé de réception à l'expéditeur.
	 *
	 * Au plus ACK_LIMIT par heure pour une même adresse : quelqu'un qui saisirait l'adresse d'un
	 * tiers ne peut pas lui faire envoyer des e-mails en série. Le message de l'expéditeur n'y est
	 * pas recopié, pour la même raison.
	 *
	 * @param array  $values    Valeurs vérifiées.
	 * @param string $reference Référence de la demande.
	 * @return bool L'e-mail est-il parti ?
	 */
	private static function send_acknowledgement( $values, $reference ) {
		$email = sanitize_email( $values['email'] );
		$key   = 'icademie_contact_ar_' . md5( strtolower( $email ) );
		$count = (int) get_transient( $key );

		if ( $count >= self::ACK_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$site       = self::site_name();
		$delay      = Icademie_Page_Contact_Settings::get( 'delai' );
		$assistance = Icademie_Page_Contact_Settings::get( 'assistance' );

		$lines = array(
			/* translators: %s: prénom de l'expéditeur. */
			sprintf( __( 'Bonjour %s,', 'icademie-page-contact' ), $values['prenom'] ),
			'',
			/* translators: %s: nom du site. */
			sprintf( __( 'Nous avons bien reçu votre message et nous vous en remercions. L\'équipe %s vous répondra par e-mail, à cette adresse.', 'icademie-page-contact' ), $site ),
		);

		if ( '' !== $delay ) {
			$lines[] = rtrim( $delay, '. ' ) . '.';
		}

		$lines[] = '';
		$lines[] = __( 'Votre demande', 'icademie-page-contact' );
		$lines[] = self::line( __( 'Référence', 'icademie-page-contact' ), $reference );
		$lines[] = self::line( __( 'Objet', 'icademie-page-contact' ), self::subjects()[ $values['profil'] ][ $values['objet'] ] );
		$lines[] = self::line( __( 'Envoyée le', 'icademie-page-contact' ), self::now() );
		$lines[] = '';
		$lines[] = __( 'Pour compléter votre demande, répondez à cet e-mail en rappelant sa référence.', 'icademie-page-contact' );
		$lines[] = '';

		if ( '' !== $assistance ) {
			/* translators: %s: numéro de la ligne d'assistance. */
			$lines[] = sprintf( __( 'Un problème pendant une location ? Appelez notre ligne d\'assistance au %s.', 'icademie-page-contact' ), $assistance );
		}

		$lines[] = __( 'Urgence en mer : composez le 196 (<abbr title="Centre régional opérationnel de surveillance et de sauvetage">CROSS</abbr>) ou appelez sur le canal 16 de la <abbr title="Very high frequency">VHF</abbr>.', 'icademie-page-contact' );
		$lines[] = '';
		/* translators: %s: nom du site. */
		$lines[] = sprintf( __( 'L\'équipe %s', 'icademie-page-contact' ), $site );
		$lines[] = home_url( '/' );

		return self::mail(
			$email,
			/* translators: %s: nom du site. */
			sprintf( __( '%s – Nous avons bien reçu votre message', 'icademie-page-contact' ), $site ),
			implode( "\n", $lines ),
			array( 'Reply-To: ' . self::mailbox( $site, Icademie_Page_Contact_Settings::recipient() ) )
		);
	}

	/**
	 * Envoie un e-mail en texte brut, avec le nom du site comme nom d'expéditeur (au lieu de
	 * « WordPress ») ; l'adresse d'expédition reste celle du site.
	 *
	 * @param string   $to          Destinataire.
	 * @param string   $subject     Objet.
	 * @param string   $message     Texte.
	 * @param string[] $headers     En-têtes.
	 * @param string[] $attachments Pièces jointes : nom affiché => chemin du fichier.
	 * @return bool
	 */
	private static function mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
		$from_name = static function () {
			return self::site_name();
		};

		add_filter( 'wp_mail_from_name', $from_name );
		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );
		remove_filter( 'wp_mail_from_name', $from_name );

		return (bool) $sent;
	}

	/**
	 * Adresse avec un nom : « Marie Dupont <marie@exemple.fr> ».
	 *
	 * Le nom est réduit aux lettres, chiffres, espaces et ponctuation simple : une virgule ou un
	 * chevron casserait l'en-tête de l'e-mail.
	 *
	 * @param string $name  Nom.
	 * @param string $email Adresse.
	 * @return string
	 */
	private static function mailbox( $name, $email ) {
		$name = trim( (string) preg_replace( '/[^\p{L}\p{M}\p{N} \'’.-]+/u', '', $name ) );

		return '' === $name ? $email : sprintf( '%1$s <%2$s>', $name, $email );
	}

	/**
	 * Ligne « Libellé : valeur » d'un e-mail.
	 *
	 * @param string $label Libellé.
	 * @param string $value Valeur.
	 * @return string
	 */
	private static function line( $label, $value ) {
		/* translators: 1: libellé, 2: valeur. */
		return sprintf( __( '%1$s : %2$s', 'icademie-page-contact' ), $label, $value );
	}

	/**
	 * Date et heure actuelles, aux formats réglés pour le site.
	 *
	 * @return string
	 */
	private static function now() {
		/* translators: 1: date, 2: heure. */
		return sprintf( __( '%1$s à %2$s', 'icademie-page-contact' ), wp_date( get_option( 'date_format' ) ), wp_date( get_option( 'time_format' ) ) );
	}

	/**
	 * Nom du site, sans entités HTML.
	 *
	 * @return string
	 */
	private static function site_name() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Redirige vers la page Contact, qui affiche alors la confirmation d'envoi.
	 */
	private static function redirect_after_sending() {
		wp_safe_redirect( add_query_arg( self::SENT_ARG, '1', get_permalink( get_queried_object_id() ) ) . '#' . self::ANCHOR, 303 );
		exit;
	}
}
