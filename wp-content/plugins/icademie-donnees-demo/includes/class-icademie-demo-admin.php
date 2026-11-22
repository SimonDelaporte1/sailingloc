<?php
/**
 * Page Outils › Données de démonstration.
 *
 * @package Icademie_Donnees_Demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page d'administration : lot de photos, génération et suppression (par appels successifs).
 */
final class Icademie_Demo_Admin {

	/** Page. */
	const PAGE = 'icademie-donnees-demo';

	/** Droit nécessaire. */
	const CAPABILITY = 'manage_options';

	/** Jeton des appels AJAX. */
	const NONCE = 'icademie_demo';

	/**
	 * Branche la page et ses actions.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_icademie_demo', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_icademie_demo_photos', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_icademie_demo_retirer_photos', array( __CLASS__, 'remove_photos' ) );
		add_action( 'admin_post_icademie_demo_photos_ftp', array( __CLASS__, 'install_ftp' ) );
		add_filter( 'plugin_action_links_icademie-donnees-demo/icademie-donnees-demo.php', array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Entrée du menu Outils.
	 */
	public static function menu() {
		add_management_page( __( 'Données de démonstration', 'icademie-donnees-demo' ), __( 'Données de démonstration', 'icademie-donnees-demo' ), self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Lien vers la page depuis la liste des extensions.
	 *
	 * @param string[] $links Liens.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Ouvrir', 'icademie-donnees-demo' ) . '</a>' );

		return $links;
	}

	/**
	 * Adresse de la page.
	 *
	 * @param array $args Paramètres.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Script de la page.
	 *
	 * @param string $hook Page affichée.
	 */
	public static function assets( $hook ) {
		if ( 'tools_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_script( 'icademie-donnees-demo', plugins_url( 'assets/admin.js', __DIR__ ), array(), Icademie_Donnees_Demo::VERSION, true );
		wp_localize_script(
			'icademie-donnees-demo',
			'icademieDemo',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'reseau'   => __( 'Le serveur ne répond pas. Réessayez avec « Reprendre ».', 'icademie-donnees-demo' ),
					'erreur'   => __( 'Une erreur est survenue.', 'icademie-donnees-demo' ),
					'termine'  => __( 'Terminé. La page va se recharger.', 'icademie-donnees-demo' ),
					'supprime' => __( 'Données supprimées. La page va se recharger.', 'icademie-donnees-demo' ),
				),
			)
		);
	}

	/**
	 * Appels de la page : démarrer, continuer, supprimer.
	 */
	public static function ajax() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Action non autorisée.', 'icademie-donnees-demo' ) ), 403 );
		}

		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		switch ( $op ) {
			case 'demarrer':
				$settings = isset( $_POST['reglages'] ) && is_array( $_POST['reglages'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['reglages'] ) ) : array();
				$result   = Icademie_Demo_Generateur::start( $settings );
				break;
			case 'continuer':
				$result = Icademie_Demo_Generateur::run();
				break;
			case 'supprimer':
				$result = Icademie_Demo_Generateur::delete();
				break;
			default:
				$result = Icademie_Demo_Generateur::status();
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Message affiché au retour sur la page.
	 *
	 * @param string $type    « success » ou « error ».
	 * @param string $message Message.
	 */
	private static function notice( $type, $message ) {
		set_transient(
			'icademie_demo_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			300
		);
	}

	/**
	 * Dépôt du lot de photos (zip).
	 */
	public static function upload() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'icademie-donnees-demo' ), 403 );
		}

		check_admin_referer( 'icademie_demo_photos' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Fichier envoyé : vérifié ci-dessous.
		$file  = isset( $_FILES['paquet'] ) && is_array( $_FILES['paquet'] ) ? $_FILES['paquet'] : array();
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			/* translators: %s: taille maximale. */
			self::notice( 'error', sprintf( __( 'Fichier trop lourd pour le serveur (au plus %s). Augmentez la taille maximale des envois dans la configuration PHP de l\'hébergement.', 'icademie-donnees-demo' ), size_format( wp_max_upload_size() ) ) );
		} elseif ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::notice( 'error', __( 'Aucun fichier reçu.', 'icademie-donnees-demo' ) );
		} elseif ( ! preg_match( '/\.zip$/i', (string) $file['name'] ) ) {
			self::notice( 'error', __( 'Le lot de photos doit être un fichier .zip.', 'icademie-donnees-demo' ) );
		} else {
			$count = Icademie_Demo_Photos::install( $file['tmp_name'] );

			if ( is_wp_error( $count ) ) {
				self::notice( 'error', $count->get_error_message() );
			} else {
				/* translators: %d: nombre de photos. */
				self::notice( 'success', sprintf( _n( '%d photo prête.', '%d photos prêtes.', $count, 'icademie-donnees-demo' ), $count ) );
			}
		}

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Lot déposé par FTP dans le dossier des médias : installé, puis le zip est effacé.
	 */
	public static function install_ftp() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'icademie-donnees-demo' ), 403 );
		}

		check_admin_referer( 'icademie_demo_photos_ftp' );
		$file  = Icademie_Demo_Photos::ftp_file();
		$count = is_readable( $file ) ? Icademie_Demo_Photos::install( $file ) : new WP_Error( 'ftp', __( 'Fichier introuvable dans le dossier des médias.', 'icademie-donnees-demo' ) );

		if ( is_wp_error( $count ) ) {
			self::notice( 'error', $count->get_error_message() );
		} else {
			wp_delete_file( $file );
			/* translators: %d: nombre de photos. */
			self::notice( 'success', sprintf( _n( '%d photo prête.', '%d photos prêtes.', $count, 'icademie-donnees-demo' ), $count ) );
		}

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Retire le lot de photos déposé.
	 */
	public static function remove_photos() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'icademie-donnees-demo' ), 403 );
		}

		check_admin_referer( 'icademie_demo_retirer_photos' );
		Icademie_Demo_Photos::remove();
		self::notice( 'success', __( 'Lot de photos retiré.', 'icademie-donnees-demo' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Page.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$status   = Icademie_Demo_Generateur::status();
		$photos   = Icademie_Demo_Photos::photos();
		$ready    = Icademie_Demo_Photos::ready();
		$package  = get_option( Icademie_Demo_Photos::OPTION );
		$notice   = get_transient( 'icademie_demo_notice_' . get_current_user_id() );
		$defaults = Icademie_Demo_Generateur::defaults();
		$ports    = Icademie_Demo_Generateur::ports();
		$labels   = array(
			'proprietaires' => __( 'Comptes propriétaires', 'icademie-donnees-demo' ),
			'annonces'      => __( 'Annonces', 'icademie-donnees-demo' ),
			'locataires'    => __( 'Comptes locataires', 'icademie-donnees-demo' ),
			'commandes'     => __( 'Réservations', 'icademie-donnees-demo' ),
			'avis'          => __( 'Avis', 'icademie-donnees-demo' ),
			'photos'        => __( 'Photos dans la médiathèque', 'icademie-donnees-demo' ),
		);
		$empty    = 'vide' === $status['etat'];

		if ( $notice ) {
			delete_transient( 'icademie_demo_notice_' . get_current_user_id() );
		}
		?>
		<div class="wrap icademie-demo" data-icademie-demo>
			<h1><?php esc_html_e( 'Données de démonstration', 'icademie-donnees-demo' ); ?></h1>
			<p><?php esc_html_e( 'Remplit le site de démonstration : propriétaires fictifs et leurs annonces complètes, locataires fictifs, réservations datées et avis des locataires. Tout ce qui est créé est marqué et se supprime en un clic, sans toucher au reste du site.', 'icademie-donnees-demo' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?>" data-demo-notice><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<style>
				.icademie-demo .card { max-width: 820px; }
				.icademie-demo progress { width: 100%; height: 18px; }
				.icademie-demo .icademie-demo__counts td { padding: 4px 16px 4px 0; }
				.icademie-demo .icademie-demo__counts td:last-child { font-weight: 600; text-align: right; }
				.icademie-demo .icademie-demo__danger { margin-top: 16px; padding-top: 12px; border-top: 1px solid #dcdcde; }
			</style>

			<div class="card">
				<h2><?php esc_html_e( 'État', 'icademie-donnees-demo' ); ?></h2>
				<?php if ( $empty ) : ?>
					<p data-demo-state><?php esc_html_e( 'Aucune donnée de démonstration sur le site.', 'icademie-donnees-demo' ); ?></p>
				<?php else : ?>
					<p data-demo-state>
						<?php
						if ( 'termine' === $status['etat'] ) {
							/* translators: 1: date, 2: graine. */
							echo esc_html( sprintf( __( 'Données créées le %1$s (graine %2$d).', 'icademie-donnees-demo' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['date'] ), $status['graine'] ) );
						} elseif ( 'suppression' === $status['etat'] ) {
							esc_html_e( 'Suppression interrompue : relancez-la.', 'icademie-donnees-demo' );
						} else {
							/* translators: %s: étape. */
							echo esc_html( sprintf( __( 'Génération interrompue (%s) : reprenez-la ou supprimez ce qui a été créé.', 'icademie-donnees-demo' ), $status['message'] ) );
						}
						?>
					</p>
					<table class="icademie-demo__counts">
						<?php foreach ( $labels as $key => $label ) : ?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $status['compte'][ $key ] ) ); ?></td></tr>
						<?php endforeach; ?>
					</table>
					<?php if ( 'en_cours' === $status['etat'] ) : ?>
						<p><button type="button" class="button button-primary" data-demo-resume><?php esc_html_e( 'Reprendre la génération', 'icademie-donnees-demo' ); ?></button></p>
					<?php endif; ?>
				<?php endif; ?>

				<div class="icademie-demo__danger"<?php echo $empty ? ' hidden' : ''; ?>>
					<p>
						<label><input type="checkbox" data-demo-confirm> <?php esc_html_e( 'Je confirme la suppression de tous les comptes, annonces, photos, réservations et avis de démonstration.', 'icademie-donnees-demo' ); ?></label>
					</p>
					<p><button type="button" class="button button-link-delete" data-demo-delete disabled><?php esc_html_e( 'Tout supprimer', 'icademie-donnees-demo' ); ?></button></p>
				</div>

				<div data-demo-status hidden>
					<progress max="1" value="0" data-demo-progress></progress>
					<p role="status" aria-live="polite" data-demo-message></p>
				</div>
			</div>

			<div class="card">
				<h2><?php esc_html_e( '1. Lot de photos', 'icademie-donnees-demo' ); ?></h2>
				<?php if ( $ready ) : ?>
					<p>
						<?php
						/* translators: 1: nombre de photos, 2: date. */
						echo esc_html( sprintf( __( '%1$d photos prêtes (déposées le %2$s).', 'icademie-donnees-demo' ), count( $photos ), wp_date( get_option( 'date_format' ), (int) $package['date'] ) ) );
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Aucun lot déposé. Le lot (photos-demo.zip) contient les photos libres de droits des annonces et leur description (photos.json).', 'icademie-donnees-demo' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="icademie_demo_photos">
					<?php wp_nonce_field( 'icademie_demo_photos' ); ?>
					<p>
						<label for="icademie-demo-paquet"><?php echo esc_html( $ready ? __( 'Remplacer le lot', 'icademie-donnees-demo' ) : __( 'Fichier du lot (.zip)', 'icademie-donnees-demo' ) ); ?></label><br>
						<input type="file" id="icademie-demo-paquet" name="paquet" accept=".zip,application/zip" required aria-describedby="icademie-demo-paquet-aide">
					</p>
					<p class="description" id="icademie-demo-paquet-aide">
						<?php
						/* translators: %s: taille maximale. */
						echo esc_html( sprintf( __( 'Taille maximale des envois sur ce serveur : %s.', 'icademie-donnees-demo' ), size_format( wp_max_upload_size() ) ) );
						?>
					</p>
					<p><button type="submit" class="button"><?php esc_html_e( 'Déposer le lot', 'icademie-donnees-demo' ); ?></button></p>
				</form>
				<?php if ( is_readable( Icademie_Demo_Photos::ftp_file() ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="icademie_demo_photos_ftp">
						<?php wp_nonce_field( 'icademie_demo_photos_ftp' ); ?>
						<p><button type="submit" class="button"><?php esc_html_e( 'Utiliser photos-demo.zip, déposé par FTP', 'icademie-donnees-demo' ); ?></button></p>
					</form>
				<?php else : ?>
					<p class="description">
						<?php
						/* translators: %s: dossier des médias. */
						echo esc_html( sprintf( __( 'Fichier trop lourd pour ce formulaire ? Déposez photos-demo.zip par FTP dans %s, puis rechargez cette page.', 'icademie-donnees-demo' ), wp_make_link_relative( content_url( 'uploads/' ) ) ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $photos ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="icademie_demo_retirer_photos">
						<?php wp_nonce_field( 'icademie_demo_retirer_photos' ); ?>
						<p><button type="submit" class="button-link"><?php esc_html_e( 'Retirer le lot déposé', 'icademie-donnees-demo' ); ?></button></p>
					</form>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2><?php esc_html_e( '2. Générer', 'icademie-donnees-demo' ); ?></h2>
				<?php if ( ! $ports ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Aucune catégorie de port : créez d\'abord les catégories d\'annonces (Produits › Catégories).', 'icademie-donnees-demo' ); ?></p></div>
				<?php else : ?>
					<p>
						<?php
						/* translators: %s: ports. */
						echo esc_html( sprintf( __( 'Ports utilisés : %s.', 'icademie-donnees-demo' ), implode( ', ', wp_list_pluck( $ports, 'nom' ) ) ) );
						?>
					</p>
				<?php endif; ?>
				<form data-demo-form>
					<table class="form-table" role="presentation">
						<?php
						$fields = array(
							'proprietaires' => array( __( 'Comptes propriétaires', 'icademie-donnees-demo' ), 1, 30, '' ),
							'annonces_min'  => array( __( 'Annonces par propriétaire, au moins', 'icademie-donnees-demo' ), 1, 20, '' ),
							'annonces_max'  => array( __( 'Annonces par propriétaire, au plus', 'icademie-donnees-demo' ), 1, 20, '' ),
							'commandes'     => array( __( 'Réservations par bateau', 'icademie-donnees-demo' ), 0, 10, __( 'Passées (terminées, quelques annulées) et à venir, sans chevauchement : elles remplissent les calendriers de disponibilité.', 'icademie-donnees-demo' ) ),
							'locataires'    => array( __( 'Comptes locataires', 'icademie-donnees-demo' ), 1, 200, '' ),
							'avis'          => array( __( 'Locations terminées suivies d\'un avis (%)', 'icademie-donnees-demo' ), 0, 100, __( 'Avis du locataire, après la fin de sa location, avec les quatre notes détaillées. La plupart sont positifs, quelques-uns négatifs.', 'icademie-donnees-demo' ) ),
							'graine'        => array( __( 'Graine (facultatif)', 'icademie-donnees-demo' ), 0, 999999999, __( 'Vide : tirage au hasard. La même graine redonne les mêmes données.', 'icademie-donnees-demo' ) ),
						);

						foreach ( $fields as $key => $field ) :
							$id = 'icademie-demo-' . $key;
							?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
								<td>
									<input type="number" class="small-text" id="<?php echo esc_attr( $id ); ?>" name="reglages[<?php echo esc_attr( $key ); ?>]" min="<?php echo (int) $field[1]; ?>" max="<?php echo (int) $field[2]; ?>" value="<?php echo 'graine' === $key ? '' : (int) $defaults[ $key ]; ?>"<?php echo '' !== $field[3] ? ' aria-describedby="' . esc_attr( $id ) . '-aide"' : ''; ?>>
									<?php if ( '' !== $field[3] ) : ?>
										<p class="description" id="<?php echo esc_attr( $id ); ?>-aide"><?php echo esc_html( $field[3] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<p><?php esc_html_e( 'Aucun e-mail n\'est envoyé pendant la génération ; les comptes fictifs ont des adresses en « .invalid », qui ne reçoivent jamais rien, et un mot de passe aléatoire que personne ne connaît.', 'icademie-donnees-demo' ); ?></p>
					<p>
						<button type="submit" class="button button-primary" data-demo-start<?php disabled( ! $empty || ! $ready || ! $ports ); ?>><?php esc_html_e( 'Générer les données', 'icademie-donnees-demo' ); ?></button>
						<?php if ( ! $empty ) : ?>
							<span class="description"><?php esc_html_e( 'Supprimez d\'abord les données existantes.', 'icademie-donnees-demo' ); ?></span>
						<?php elseif ( ! $ready ) : ?>
							<span class="description"><?php esc_html_e( 'Déposez d\'abord le lot de photos.', 'icademie-donnees-demo' ); ?></span>
						<?php endif; ?>
					</p>
				</form>
			</div>
		</div>
		<?php
	}
}
