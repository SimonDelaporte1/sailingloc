<?php
/**
 * Réglages de la page Contact (Réglages › Page contact).
 *
 * @package Icademie_Page_Contact
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordonnées et liens affichés sur la page Contact, et adresse qui reçoit les messages.
 *
 * Tous les réglages sont enregistrés dans une seule option. Un réglage laissé vide masque
 * l'élément correspondant de la page (ou utilise la valeur par défaut indiquée sous le champ) :
 * le plugin n'affiche jamais de coordonnées inventées.
 */
final class Icademie_Page_Contact_Settings {

	/** Option qui contient tous les réglages. */
	const OPTION = 'icademie_page_contact';

	/** Identifiant de la page de réglages. */
	const PAGE = 'icademie-page-contact';

	/**
	 * Branche la page de réglages.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/icademie-page-contact.php' ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Valeur d'un réglage.
	 *
	 * @param string $key Clé du réglage.
	 * @return string Valeur, ou vide si le réglage n'est pas renseigné.
	 */
	public static function get( $key ) {
		$options = get_option( self::OPTION, array() );

		return is_array( $options ) && isset( $options[ $key ] ) && is_string( $options[ $key ] ) ? $options[ $key ] : '';
	}

	/**
	 * Adresse qui reçoit les messages : celle réglée, sinon l'adresse d'administration du site.
	 *
	 * @return string
	 */
	public static function recipient() {
		$email = self::get( 'destinataire' );

		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Sections et champs de la page de réglages.
	 *
	 * Types : text, textarea, email, url, tel, latitude, longitude.
	 *
	 * @return array[]
	 */
	private static function sections() {
		$topic_fields = array(
			'aide_url' => array(
				'type'        => 'url',
				'label'       => __( 'Lien « Centre d\'aide complet »', 'icademie-page-contact' ),
				'description' => __( 'Vide : page parente de la page Contact (par exemple « Aide »). Sans page parente, le bouton n\'est pas affiché.', 'icademie-page-contact' ),
			),
		);

		foreach ( Icademie_Page_Contact::topics() as $key => $topic ) {
			$topic_fields[ 'theme_' . $key ] = array(
				'type'        => 'url',
				/* translators: %s: nom du thème d'aide (« Réservation »…). */
				'label'       => sprintf( __( 'Lien « %s »', 'icademie-page-contact' ), $topic['label'] ),
				/* translators: %s: mot recherché dans le site. */
				'description' => sprintf( __( 'Vide : recherche « %s » dans le site.', 'icademie-page-contact' ), $topic['search'] ),
			);
		}

		$social_fields = array();

		foreach ( Icademie_Page_Contact::social_networks() as $service => $label ) {
			$social_fields[ $service ] = array(
				'type'        => 'url',
				'label'       => $label,
				'placeholder' => 'https://',
			);
		}

		return array(
			'reception'   => array(
				'title'  => __( 'Réception des messages', 'icademie-page-contact' ),
				'intro'  => __( 'Chaque message envoyé depuis le formulaire arrive par e-mail à cette adresse, avec sa pièce jointe. Répondre à cet e-mail écrit directement à l\'expéditeur, qui reçoit de son côté un accusé de réception.', 'icademie-page-contact' ),
				'fields' => array(
					'destinataire' => array(
						'type'        => 'email',
						'label'       => __( 'Adresse de réception', 'icademie-page-contact' ),
						/* translators: %s: adresse e-mail d'administration du site. */
						'description' => sprintf( __( 'Vide : adresse d\'administration du site (%s).', 'icademie-page-contact' ), get_option( 'admin_email' ) ),
					),
				),
			),
			'coordonnees' => array(
				'title'  => __( 'Coordonnées', 'icademie-page-contact' ),
				'intro'  => __( 'Encart « Coordonnées » de la page. Un élément laissé vide n\'est pas affiché.', 'icademie-page-contact' ),
				'fields' => array(
					'telephone' => array(
						'type'        => 'tel',
						'label'       => __( 'Téléphone du service client', 'icademie-page-contact' ),
						'placeholder' => '01 23 45 67 89',
					),
					'horaires'  => array(
						'type'        => 'text',
						'label'       => __( 'Jours et horaires d\'ouverture', 'icademie-page-contact' ),
						'placeholder' => __( 'Du lundi au vendredi, de 9 h à 18 h', 'icademie-page-contact' ),
					),
					'email'     => array(
						'type'        => 'email',
						'label'       => __( 'Adresse e-mail affichée', 'icademie-page-contact' ),
						'description' => __( 'Adresse publique, affichée sur la page. Elle peut être différente de l\'adresse de réception.', 'icademie-page-contact' ),
					),
					'delai'     => array(
						'type'        => 'text',
						'label'       => __( 'Délai de réponse', 'icademie-page-contact' ),
						'placeholder' => __( 'Réponse sous 24 h ouvrées', 'icademie-page-contact' ),
						'description' => __( 'Affiché sous l\'adresse e-mail, après l\'envoi du formulaire et dans l\'accusé de réception.', 'icademie-page-contact' ),
					),
					'chat'      => array(
						'type'        => 'url',
						'label'       => __( 'Lien du chat en direct', 'icademie-page-contact' ),
						'placeholder' => 'https://',
						'description' => __( 'Le bouton « Ouvrir le chat en direct » n\'apparaît que si ce lien est renseigné.', 'icademie-page-contact' ),
					),
					'adresse'   => array(
						'type'  => 'textarea',
						'label' => __( 'Adresse du siège', 'icademie-page-contact' ),
					),
				),
			),
			'plan'        => array(
				'title'  => __( 'Plan d\'accès', 'icademie-page-contact' ),
				'intro'  => __( 'Position du siège sur la carte OpenStreetMap. Pour la trouver : sur openstreetmap.org, clic droit sur le siège, « Afficher l\'adresse », puis copiez les deux nombres. Sans position, la page propose un lien vers l\'adresse sur OpenStreetMap ; sans position ni adresse, l\'encart n\'est pas affiché.', 'icademie-page-contact' ),
				'fields' => array(
					'latitude'  => array(
						'type'        => 'latitude',
						'label'       => __( 'Latitude', 'icademie-page-contact' ),
						'placeholder' => '46.15913',
					),
					'longitude' => array(
						'type'        => 'longitude',
						'label'       => __( 'Longitude', 'icademie-page-contact' ),
						'placeholder' => '-1.15205',
					),
				),
			),
			'reseaux'     => array(
				'title'  => __( 'Réseaux sociaux', 'icademie-page-contact' ),
				'intro'  => __( 'Seuls les réseaux renseignés sont affichés.', 'icademie-page-contact' ),
				'fields' => $social_fields,
			),
			'aide'        => array(
				'title'  => __( 'Aide rapide', 'icademie-page-contact' ),
				'intro'  => __( 'Liens de l\'encart « Aide rapide », affiché avant le formulaire pour que les visiteurs trouvent leur réponse sans écrire.', 'icademie-page-contact' ),
				'fields' => $topic_fields,
			),
			'assistance'  => array(
				'title'  => __( 'Assistance pendant la location', 'icademie-page-contact' ),
				'fields' => array(
					'assistance' => array(
						'type'        => 'tel',
						'label'       => __( 'Ligne d\'assistance', 'icademie-page-contact' ),
						'placeholder' => '01 23 45 67 89',
						'description' => __( 'Numéro du bandeau en bas de page et du bouton « Appeler l\'assistance ». Vide : le bouton n\'est pas affiché ; les numéros d\'urgence en mer (196, VHF canal 16) restent affichés.', 'icademie-page-contact' ),
					),
				),
			),
		);
	}

	/**
	 * Tous les champs, toutes sections confondues.
	 *
	 * @return array[] Clé => définition du champ.
	 */
	private static function fields() {
		$fields = array();

		foreach ( self::sections() as $section ) {
			$fields += $section['fields'];
		}

		return $fields;
	}

	/**
	 * Déclare l'option, les sections et les champs.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		foreach ( self::sections() as $section_id => $section ) {
			$intro = isset( $section['intro'] ) ? $section['intro'] : '';

			add_settings_section(
				'icademie_page_contact_' . $section_id,
				$section['title'],
				static function () use ( $intro ) {
					if ( '' !== $intro ) {
						echo '<p>' . esc_html( $intro ) . '</p>';
					}
				},
				self::PAGE
			);

			foreach ( $section['fields'] as $key => $field ) {
				add_settings_field(
					'icademie_page_contact_' . $key,
					$field['label'],
					array( __CLASS__, 'render_field' ),
					self::PAGE,
					'icademie_page_contact_' . $section_id,
					array(
						'key'       => $key,
						'field'     => $field,
						'label_for' => 'icademie-page-contact-' . $key,
					)
				);
			}
		}
	}

	/**
	 * Affiche un champ de réglage.
	 *
	 * @param array $args Clé et définition du champ.
	 */
	public static function render_field( $args ) {
		$key         = $args['key'];
		$field       = $args['field'];
		$id          = 'icademie-page-contact-' . $key;
		$name        = self::OPTION . '[' . $key . ']';
		$value       = self::get( $key );
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		$description = isset( $field['description'] ) ? $field['description'] : '';
		$described   = '' !== $description ? $id . '-description' : '';

		if ( 'textarea' === $field['type'] ) {
			?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3" class="regular-text"<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		} else {
			$types = array(
				'email' => 'email',
				'url'   => 'url',
				'tel'   => 'tel',
			);
			$type  = isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : 'text';
			?>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text"<?php echo $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?><?php echo in_array( $field['type'], array( 'latitude', 'longitude' ), true ) ? ' inputmode="decimal"' : ''; ?><?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>>
			<?php
		}

		if ( '' !== $description ) {
			printf( '<p class="description" id="%1$s">%2$s</p>', esc_attr( $described ), esc_html( $description ) );
		}
	}

	/**
	 * Nettoie les réglages envoyés.
	 *
	 * @param mixed $input Valeurs envoyées.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( self::fields() as $key => $field ) {
			$value = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? trim( $input[ $key ] ) : '';

			switch ( $field['type'] ) {
				case 'email':
					if ( '' !== $value && ! is_email( $value ) ) {
						/* translators: %s: nom du réglage. */
						self::add_error( $key, sprintf( __( '« %s » : adresse e-mail invalide, le champ a été vidé.', 'icademie-page-contact' ), $field['label'] ) );
						$value = '';
					}
					$value = sanitize_email( $value );
					break;

				case 'url':
					$value = esc_url_raw( $value, array( 'http', 'https' ) );
					break;

				case 'tel':
					// Chiffres, espaces, « + », points, tirets et parenthèses ; ni balises, ni texte autour
					// (« Tél. 01 23… » donne « 01 23… »).
					$value = preg_replace( array( '/[^0-9+().\s-]/', '/\(\s*\)/' ), '', sanitize_text_field( $value ) );
					$value = preg_replace( '/^[^0-9+(]+|[^0-9)]+$/', '', preg_replace( '/\s+/', ' ', $value ) );
					break;

				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;

				case 'latitude':
				case 'longitude':
					$value = self::sanitize_coordinate( $value, 'latitude' === $field['type'] ? 90 : 180, $key, $field['label'] );
					break;

				default:
					$value = sanitize_text_field( $value );
			}

			$clean[ $key ] = $value;
		}

		if ( ( '' === $clean['latitude'] ) !== ( '' === $clean['longitude'] ) ) {
			self::add_error( 'coordonnees', __( 'Plan d\'accès : renseignez la latitude et la longitude pour afficher la carte.', 'icademie-page-contact' ) );
		}

		return $clean;
	}

	/**
	 * Nettoie une latitude ou une longitude, saisie avec un point ou une virgule décimale.
	 *
	 * @param string $value Valeur saisie.
	 * @param int    $limit Valeur absolue maximale (90 ou 180).
	 * @param string $key   Clé du réglage.
	 * @param string $label Nom du réglage.
	 * @return string Nombre avec un point décimal, ou vide.
	 */
	private static function sanitize_coordinate( $value, $limit, $key, $label ) {
		$value = str_replace( ',', '.', $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! is_numeric( $value ) || abs( (float) $value ) > $limit ) {
			/* translators: 1: nom du réglage, 2: valeur maximale. */
			self::add_error( $key, sprintf( __( '« %1$s » : indiquez un nombre entre -%2$d et %2$d, le champ a été vidé.', 'icademie-page-contact' ), $label, $limit ) );
			return '';
		}

		// %F : point décimal quelle que soit la langue du serveur ; 6 décimales, soit environ 10 cm.
		return rtrim( rtrim( sprintf( '%.6F', (float) $value ), '0' ), '.' );
	}

	/**
	 * Signale une erreur de réglage, une seule fois.
	 *
	 * WordPress peut nettoyer l'option deux fois lors du premier enregistrement. Hors de
	 * l'administration (WP-CLI…), les messages de réglages n'existent pas : rien à signaler.
	 *
	 * @param string $code    Code de l'erreur.
	 * @param string $message Message.
	 */
	private static function add_error( $code, $message ) {
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		foreach ( get_settings_errors( self::OPTION ) as $error ) {
			if ( $error['code'] === $code ) {
				return;
			}
		}

		add_settings_error( self::OPTION, $code, $message );
	}

	/**
	 * Ajoute la page Réglages › Page contact.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Page contact', 'icademie-page-contact' ),
			__( 'Page contact', 'icademie-page-contact' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Affiche la page de réglages.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_id = Icademie_Page_Contact::find_contact_page();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $page_id ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: titre de la page Contact. */
						esc_html__( 'Ces réglages alimentent la page « %s ».', 'icademie-page-contact' ),
						esc_html( get_the_title( $page_id ) )
					);
					?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>"><?php esc_html_e( 'Voir la page', 'icademie-page-contact' ); ?></a>
					<?php $edit_link = get_edit_post_link( $page_id ); ?>
					<?php if ( $edit_link ) : ?>
						· <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Modifier la page', 'icademie-page-contact' ); ?></a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Aucune page ne contient encore le bloc « Page contact » : créez une page « Contact » et insérez-y ce bloc (catégorie Widgets).', 'icademie-page-contact' ); ?></p>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Lien « Réglages » dans la liste des extensions.
	 *
	 * @param string[] $links Liens de l'extension.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Réglages', 'icademie-page-contact' )
			)
		);

		return $links;
	}
}
