<?php
/**
 * Page « Mes données personnelles » du compte client : télécharger ses données (Opquast 21) et
 * supprimer son compte (Opquast 20).
 *
 * Les données sont celles rattachées au compte (et non à l'adresse e-mail, que le client peut
 * changer sans vérification) : informations du compte, adresses, locations, avis, annonces.
 *
 * La suppression demande le mot de passe et une confirmation. Elle est refusée, avec la marche à
 * suivre, pour un compte d'administration, un client qui a une location en cours ou à venir, et un
 * propriétaire qui a des annonces (ses réservations concernent d'autres clients). Les avis restent
 * publiés sous « Ancien membre » ; les locations restent dans les commandes de la boutique sans lien
 * avec un compte (obligations comptables), anonymisées si le réglage de WooCommerce « Supprimer les
 * données personnelles des commandes » est actif.
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mes données personnelles.
 */
final class Icademie_Comptes_Donnees {

	/** Point d'accès du compte client. */
	const ENDPOINT = 'mes-donnees';

	/** Champ caché : action demandée. */
	const ACTION_FIELD = 'icademie_comptes_action';

	/** Nonces. */
	const NONCE_EXPORT = 'icademie-comptes-export';
	const NONCE_DELETE = 'icademie-comptes-suppression';

	/** Paramètre de l'adresse affichée après la suppression. */
	const DELETED_ARG = 'compte-supprime';

	/** Essais de mot de passe avant blocage (par quart d'heure). */
	const MAX_ATTEMPTS = 5;

	/** Feuille de styles. */
	const HANDLE = 'icademie-comptes';

	/**
	 * Erreurs du formulaire de suppression, par champ (« form » : tout le formulaire).
	 *
	 * @var string[]
	 */
	private static $errors = array();

	/**
	 * Branche les filtres.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 30 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ), 20 );
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'deleted_notice' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Déclare le point d'accès à WooCommerce (« /mon-compte/mes-donnees/ »).
	 *
	 * @param string[] $vars Points d'accès.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Entrée du menu du compte, juste avant « Se déconnecter ».
	 *
	 * @param string[] $items Entrées.
	 * @return string[]
	 */
	public static function menu_items( $items ) {
		$out = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$out[ self::ENDPOINT ] = self::title();
			}

			$out[ $key ] = $label;
		}

		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out[ self::ENDPOINT ] = self::title();
		}

		return $out;
	}

	/**
	 * Titre de la page.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Mes données personnelles', 'icademie-comptes' );
	}

	/**
	 * Feuille de styles, sur la page seulement.
	 */
	public static function enqueue() {
		if ( self::is_endpoint() ) {
			wp_enqueue_style( self::HANDLE, plugins_url( 'assets/comptes.css', __DIR__ ), array(), Icademie_Comptes::VERSION );
		}
	}

	/**
	 * Sommes-nous sur « Mes données personnelles » ?
	 *
	 * @return bool
	 */
	private static function is_endpoint() {
		return function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT );
	}

	/**
	 * Adresse de la page.
	 *
	 * @return string
	 */
	public static function url() {
		return wc_get_account_endpoint_url( self::ENDPOINT );
	}

	// ------------------------------------------------------------------------------------------
	// Formulaires.
	// ------------------------------------------------------------------------------------------

	/**
	 * Traite les formulaires de la page (téléchargement, suppression).
	 */
	public static function handle_post() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST[ self::ACTION_FIELD ] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Vérifié ci-dessous selon l'action.
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST[ self::ACTION_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user   = wp_get_current_user();

		if ( 'export' === $action ) {
			if ( wp_verify_nonce( $nonce, self::NONCE_EXPORT ) ) {
				self::export( $user );
			}

			self::$errors['export'] = __( 'La page a expiré : cliquez à nouveau sur « Télécharger mes données ».', 'icademie-comptes' );
			return;
		}

		if ( 'suppression' === $action ) {
			if ( ! wp_verify_nonce( $nonce, self::NONCE_DELETE ) ) {
				self::$errors['form'] = __( 'La page a expiré : saisissez à nouveau votre mot de passe.', 'icademie-comptes' );
				return;
			}

			self::delete( $user );
		}
	}

	// ------------------------------------------------------------------------------------------
	// Téléchargement.
	// ------------------------------------------------------------------------------------------

	/**
	 * Envoie le fichier JSON des données du compte, puis s'arrête.
	 *
	 * @param WP_User $user Compte.
	 */
	private static function export( WP_User $user ) {
		$data     = self::collect( $user );
		$filename = 'sailingloc-mes-donnees-' . current_datetime()->format( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fichier JSON.
		exit;
	}

	/**
	 * Données du compte, par rubrique.
	 *
	 * @param WP_User $user Compte.
	 * @return array
	 */
	public static function collect( WP_User $user ) {
		require_once __DIR__ . '/class-icademie-comptes-export-wc.php';

		$data = array(
			'site'          => home_url( '/' ),
			'date_export'   => current_datetime()->format( DATE_ATOM ),
			'compte'        => array(
				__( 'Identifiant', 'icademie-comptes' )      => $user->user_login,
				__( 'Adresse e-mail', 'icademie-comptes' )   => $user->user_email,
				__( 'Prénom', 'icademie-comptes' )           => $user->first_name,
				__( 'Nom', 'icademie-comptes' )              => $user->last_name,
				__( 'Nom affiché', 'icademie-comptes' )      => $user->display_name,
				__( 'Site web', 'icademie-comptes' )         => $user->user_url,
				__( 'Présentation', 'icademie-comptes' )     => $user->description,
				__( 'Date d\'inscription', 'icademie-comptes' ) => mysql2date( DATE_ATOM, $user->user_registered, false ),
			),
			'coordonnees'   => self::pairs( Icademie_Comptes_Export_WC::customer( $user ) ),
			'locations'     => array(),
			'avis'          => array(),
		);

		foreach ( self::orders( $user->ID ) as $order ) {
			$rental = self::pairs( Icademie_Comptes_Export_WC::order( $order ) );

			$rental[ __( 'Statut', 'icademie-comptes' ) ] = wc_get_order_status_name( $order->get_status() );

			foreach ( $order->get_items() as $item ) {
				$start = (string) $item->get_meta( '_icademie_date_debut' );
				$end   = (string) $item->get_meta( '_icademie_date_fin' );

				if ( '' !== $start ) {
					$rental[ __( 'Dates', 'icademie-comptes' ) ][] = array(
						__( 'Bateau', 'icademie-comptes' ) => $item->get_name(),
						__( 'Début', 'icademie-comptes' )  => $start,
						__( 'Fin', 'icademie-comptes' )    => $end,
					);
				}
			}

			$data['locations'][] = $rental;
		}

		foreach ( get_comments( array( 'user_id' => $user->ID, 'status' => 'all', 'type' => 'all' ) ) as $comment ) {
			$review = array(
				__( 'Date', 'icademie-comptes' )   => mysql2date( DATE_ATOM, $comment->comment_date, false ),
				__( 'Page', 'icademie-comptes' )   => get_the_title( (int) $comment->comment_post_ID ),
				__( 'Adresse', 'icademie-comptes' ) => (string) get_permalink( (int) $comment->comment_post_ID ),
				__( 'Texte', 'icademie-comptes' )  => $comment->comment_content,
				__( 'Statut', 'icademie-comptes' ) => '1' === (string) $comment->comment_approved ? __( 'Publié', 'icademie-comptes' ) : __( 'En attente ou refusé', 'icademie-comptes' ),
			);

			foreach ( (array) get_comment_meta( (int) $comment->comment_ID ) as $key => $values ) {
				if ( 'rating' === $key || 0 === strpos( $key, 'icademie' ) ) {
					$review[ $key ] = maybe_unserialize( $values[0] );
				}
			}

			$data['avis'][] = $review;
		}

		$products = self::products( $user->ID, -1 );

		if ( $products ) {
			$data['annonces'] = array();

			foreach ( $products as $product_id ) {
				$data['annonces'][] = array(
					__( 'Bateau', 'icademie-comptes' )  => get_the_title( $product_id ),
					__( 'Statut', 'icademie-comptes' )  => get_post_status( $product_id ),
					__( 'Adresse', 'icademie-comptes' ) => (string) get_permalink( $product_id ),
				);
			}

			$data['page_de_location'] = array(
				__( 'Nom', 'icademie-comptes' )         => (string) get_user_meta( $user->ID, 'pv_shop_name', true ),
				__( 'Description', 'icademie-comptes' ) => wp_strip_all_tags( (string) get_user_meta( $user->ID, 'pv_shop_description', true ) ),
			);
		}

		/**
		 * Données du fichier « Mes données personnelles », par rubrique.
		 *
		 * @param array   $data Données.
		 * @param WP_User $user Compte.
		 */
		return (array) apply_filters( 'icademie_comptes_export', $data, $user );
	}

	/**
	 * Paires « name »/« value » (format des exportateurs de WooCommerce) en tableau associatif, en
	 * texte brut (les adresses formatées ont des <br/>).
	 *
	 * @param array $pairs Paires.
	 * @return array
	 */
	private static function pairs( $pairs ) {
		$out = array();

		foreach ( (array) $pairs as $pair ) {
			if ( isset( $pair['name'], $pair['value'] ) ) {
				$out[ (string) $pair['name'] ] = trim( wp_strip_all_tags( preg_replace( '#<br\s*/?>#i', ', ', (string) $pair['value'] ) ) );
			}
		}

		return $out;
	}

	/**
	 * Commandes du compte.
	 *
	 * @param int   $user_id  Compte.
	 * @param array $statuses Statuts (tous par défaut).
	 * @return WC_Order[]
	 */
	private static function orders( $user_id, $statuses = array() ) {
		$args = array(
			'customer_id' => $user_id,
			'limit'       => -1,
			'type'        => 'shop_order',
		);

		if ( $statuses ) {
			$args['status'] = $statuses;
		}

		return array_filter(
			(array) wc_get_orders( $args ),
			static function ( $order ) {
				return $order instanceof WC_Order;
			}
		);
	}

	/**
	 * Annonces (bateaux) du compte, quel que soit leur statut (hors corbeille).
	 *
	 * @param int $user_id Compte.
	 * @param int $limit   Nombre maximum.
	 * @return int[]
	 */
	private static function products( $user_id, $limit ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'product',
					'author'         => $user_id,
					'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			)
		);
	}

	// ------------------------------------------------------------------------------------------
	// Suppression.
	// ------------------------------------------------------------------------------------------

	/**
	 * Ce qui empêche de supprimer le compte depuis le site (messages, échappés).
	 *
	 * @param WP_User $user Compte.
	 * @return string[]
	 */
	public static function blockers( WP_User $user ) {
		$blockers = array();
		$contact  = '<a href="' . esc_url( Icademie_Comptes::contact_url() ) . '">' . esc_html__( 'contactez-nous', 'icademie-comptes' ) . '</a>';

		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ) {
			$blockers[] = esc_html__( 'Ce compte gère le site : il ne peut pas être supprimé depuis cette page.', 'icademie-comptes' );
		}

		$current = array();
		$today   = current_datetime()->format( 'Y-m-d' );

		foreach ( self::orders( $user->ID, array( 'pending', 'on-hold', 'processing' ) ) as $order ) {
			$ends = array();

			foreach ( $order->get_items() as $item ) {
				$ends[] = (string) $item->get_meta( '_icademie_date_fin' );
			}

			// Sans date de fin connue, la location est considérée comme à venir.
			if ( ! $ends || in_array( '', $ends, true ) || max( $ends ) >= $today ) {
				$current[] = $order->get_order_number();
			}
		}

		if ( $current ) {
			$blockers[] = sprintf(
				/* translators: 1: numéros des locations, 2: lien « Mes locations », 3: lien « contactez-nous ». */
				esc_html__( 'Vous avez une location en cours ou à venir (n°%1$s). Votre compte pourra être supprimé une fois la location terminée ou annulée : une réservation en attente de paiement s\'annule depuis %2$s ; pour une autre, %3$s.', 'icademie-comptes' ),
				esc_html( implode( ', n°', $current ) ),
				'<a href="' . esc_url( wc_get_account_endpoint_url( 'orders' ) ) . '">' . esc_html__( 'Mes locations', 'icademie-comptes' ) . '</a>',
				$contact
			);
		}

		if ( self::products( $user->ID, 1 ) ) {
			$blockers[] = sprintf(
				/* translators: %s: lien « contactez-nous ». */
				esc_html__( 'Vous proposez des bateaux à la location : leurs réservations concernent d\'autres clients. Pour fermer votre espace propriétaire et votre compte, %s ; nous traiterons vos annonces et vos réservations en cours.', 'icademie-comptes' ),
				$contact
			);
		}

		return $blockers;
	}

	/**
	 * Supprime le compte si le mot de passe et la confirmation sont bons ; sinon, note les erreurs.
	 *
	 * @param WP_User $user Compte.
	 */
	private static function delete( WP_User $user ) {
		if ( self::blockers( $user ) ) {
			return; // La page affiche ce qui bloque.
		}

		$attempts_key = 'icademie_comptes_essais_' . $user->ID;
		$attempts     = (int) get_transient( $attempts_key );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			self::$errors['form'] = __( 'Trop d\'essais de mot de passe : réessayez dans un quart d\'heure.', 'icademie-comptes' );
			return;
		}

		$password = isset( $_POST['mot_de_passe'] ) && is_string( $_POST['mot_de_passe'] ) ? wp_unslash( $_POST['mot_de_passe'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce vérifié ; mot de passe comparé tel quel.

		if ( '' === $password ) {
			self::$errors['mot_de_passe'] = __( 'Saisissez votre mot de passe.', 'icademie-comptes' );
		} elseif ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			set_transient( $attempts_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
			self::$errors['mot_de_passe'] = __( 'Mot de passe incorrect. Il est sensible à la casse (majuscules et minuscules).', 'icademie-comptes' );
		}

		if ( empty( $_POST['confirmation'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::$errors['confirmation'] = __( 'Cochez cette case pour confirmer la suppression.', 'icademie-comptes' );
		}

		if ( self::$errors ) {
			return;
		}

		delete_transient( $attempts_key );

		$email = $user->user_email;
		$name  = $user->first_name;

		self::erase( $user );

		require_once ABSPATH . 'wp-admin/includes/user.php';

		wp_logout();
		wp_delete_user( $user->ID );

		self::send_deleted_mail( $email, $name );

		wp_safe_redirect( add_query_arg( self::DELETED_ARG, '1', wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}

	/**
	 * Efface ou anonymise ce qui survit au compte : avis (« Ancien membre »), moyens de paiement
	 * enregistrés, données personnelles des commandes si WooCommerce est réglé pour les effacer.
	 * Le reste (adresses, préférences) disparaît avec le compte.
	 *
	 * @param WP_User $user Compte.
	 */
	private static function erase( WP_User $user ) {
		foreach ( get_comments( array( 'user_id' => $user->ID, 'status' => 'all', 'type' => 'all' ) ) as $comment ) {
			wp_update_comment(
				array(
					'comment_ID'           => (int) $comment->comment_ID,
					'comment_author'       => __( 'Ancien membre', 'icademie-comptes' ),
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_author_IP'    => wp_privacy_anonymize_ip( $comment->comment_author_IP ),
					'comment_agent'        => '',
					'user_id'              => 0,
				)
			);
		}

		if ( class_exists( 'WC_Payment_Tokens' ) ) {
			foreach ( WC_Payment_Tokens::get_customer_tokens( $user->ID ) as $token ) {
				WC_Payment_Tokens::delete( $token->get_id() );
			}
		}

		if ( wc_string_to_bool( get_option( 'woocommerce_erasure_request_removes_order_data', 'no' ) ) && class_exists( 'WC_Privacy_Erasers' ) ) {
			foreach ( self::orders( $user->ID ) as $order ) {
				WC_Privacy_Erasers::remove_order_personal_data( $order );
			}
		}

		/**
		 * Avant la suppression d'un compte par son titulaire.
		 *
		 * @param WP_User $user Compte.
		 */
		do_action( 'icademie_comptes_before_delete', $user );
	}

	/**
	 * E-mail de confirmation de la suppression.
	 *
	 * @param string $email Adresse du compte supprimé.
	 * @param string $name  Prénom.
	 */
	private static function send_deleted_mail( $email, $name ) {
		/* translators: %s: prénom. */
		$hello = '' !== $name ? sprintf( __( 'Bonjour %s,', 'icademie-comptes' ), $name ) : __( 'Bonjour,', 'icademie-comptes' );

		$body = '<p>' . esc_html( $hello ) . '</p>'
			. '<p>' . esc_html__( 'Votre compte SailingLoc a été supprimé à votre demande. Vos informations de compte et vos adresses sont effacées ; vos avis restent publiés sous « Ancien membre » ; vos locations passées sont conservées sans lien avec un compte, pour nos obligations comptables.', 'icademie-comptes' ) . '</p>'
			. '<p>' . esc_html__( 'Si vous n\'êtes pas à l\'origine de cette suppression, contactez-nous rapidement.', 'icademie-comptes' ) . '</p>';

		Icademie_Comptes::send_mail(
			$email,
			__( 'Votre compte SailingLoc a été supprimé', 'icademie-comptes' ),
			__( 'Compte supprimé', 'icademie-comptes' ),
			$body
		);
	}

	/**
	 * Message sur « Mon compte » après la suppression.
	 */
	public static function deleted_notice() {
		if ( isset( $_GET[ self::DELETED_ARG ] ) && ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affichage seul.
			wc_print_notice( esc_html__( 'Votre compte a été supprimé. Un e-mail de confirmation vous a été envoyé.', 'icademie-comptes' ), 'success' );
		}
	}

	// ------------------------------------------------------------------------------------------
	// Affichage.
	// ------------------------------------------------------------------------------------------

	/**
	 * Contenu de la page.
	 */
	public static function render() {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return;
		}

		$data = array(
			'url'      => self::url(),
			'errors'   => self::$errors,
			'blockers' => self::blockers( $user ),
			'nonces'   => array(
				'export'      => self::NONCE_EXPORT,
				'suppression' => self::NONCE_DELETE,
			),
			'field'    => self::ACTION_FIELD,
		);

		include dirname( __DIR__ ) . '/templates/mes-donnees.php';
	}
}
