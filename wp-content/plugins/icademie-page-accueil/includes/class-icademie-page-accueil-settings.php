<?php
/**
 * Réglages de la page d'accueil (Réglages › Page d'accueil).
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Textes, images et adresses des liens de la page d'accueil, réunis dans une seule option.
 *
 * Chaque texte a une valeur par défaut, affichée tant que le réglage n'a pas été enregistré.
 * Les liens sont des adresses saisies à la main (adresse complète, ou chemin d'une page du site) :
 * un lien sans adresse n'est pas affiché, ce qui permet de créer les pages de contenu plus tard.
 */
final class Icademie_Page_Accueil_Settings {

	/** Option qui contient tous les réglages. */
	const OPTION = 'icademie_page_accueil';

	/** Identifiant de la page de réglages (et du groupe de réglages). */
	const PAGE = 'icademie-page-accueil';

	/**
	 * Champs, une fois construits (voir fields()).
	 *
	 * @var array|null
	 */
	private static $fields = null;

	/**
	 * Branche les réglages sur l'administration.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( Icademie_Page_Accueil::FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adresse de la page de réglages.
	 *
	 * @param string $section Section à afficher (ancre), facultative.
	 * @return string
	 */
	public static function url( $section = '' ) {
		return admin_url( 'options-general.php?page=' . self::PAGE ) . ( $section ? '#icademie-accueil-' . $section : '' );
	}

	/**
	 * Sections de la page de réglages, puis lignes de chaque section, puis champs de chaque ligne.
	 *
	 * @return array[]
	 */
	public static function sections() {
		$sections = array(
			'bandeau'     => array(
				'title'       => __( 'Bandeau et recherche (zone 2)', 'icademie-page-accueil' ),
				'description' => __( 'Le moteur de recherche cherche par destination (catégories de produits), par type de bateau (attribut « Type de bateau ») et par nombre de passagers (attribut « Passagers » : bateaux qui acceptent ce nombre de passagers ou plus). Les dates choisies sont reprises sur la fiche du bateau.', 'icademie-page-accueil' ),
				'rows'        => array(
					'bandeau_titre' => array(
						'label'    => __( 'Titre principal (H1)', 'icademie-page-accueil' ),
						'type'     => 'text',
						'default'  => __( 'Louez un bateau entre particuliers', 'icademie-page-accueil' ),
						'required' => true,
					),
					'bandeau_texte' => array(
						'label'   => __( 'Sous-titre', 'icademie-page-accueil' ),
						'type'    => 'textarea',
						'default' => __( 'Choisissez votre bateau, réservez en toute confiance et larguez les amarres.', 'icademie-page-accueil' ),
					),
					'bandeau_image' => array(
						'label'       => __( 'Photo du bandeau', 'icademie-page-accueil' ),
						'type'        => 'image',
						'description' => __( 'Photo plein écran d\'un bateau en mer, 2000 px de large au moins. Le centre de la photo reste visible sur tous les écrans. Sans photo, une illustration est affichée.', 'icademie-page-accueil' ),
					),
				),
			),
			'reassurance' => array(
				'title'       => __( 'Réassurance (zone 3)', 'icademie-page-accueil' ),
				'description' => __( 'Quatre arguments de confiance. Chacun devient un lien dès que son adresse est remplie : les pages de contenu peuvent être créées plus tard. Sur smartphone, seul le titre est affiché.', 'icademie-page-accueil' ),
				'rows'        => self::reassurance_rows(),
			),
			'produits'    => array(
				'title'       => __( 'Catégories de bateaux, bateaux à la une et destinations (zones 4 à 6)', 'icademie-page-accueil' ),
				'description' => '',
				'rows'        => array(),
			),
			'etapes'      => array(
				'title'       => __( 'Comment ça marche (zone 7)', 'icademie-page-accueil' ),
				'description' => __( 'Trois étapes par onglet.', 'icademie-page-accueil' ),
				'rows'        => self::steps_rows(),
			),
			'loueur'      => array(
				'title'       => __( 'Devenir loueur (zone 8)', 'icademie-page-accueil' ),
				'description' => __( 'Les deux boutons mènent aux adresses indiquées (pages d\'aide). Un bouton sans adresse n\'est pas affiché : les pages peuvent être créées plus tard.', 'icademie-page-accueil' ),
				'rows'        => array(
					'loueur_titre'         => array(
						'label'   => __( 'Titre', 'icademie-page-accueil' ),
						'type'    => 'text',
						'default' => __( 'Vous avez un bateau ? Proposez-le à la location', 'icademie-page-accueil' ),
					),
					'loueur_texte'         => array(
						'label'   => __( 'Texte', 'icademie-page-accueil' ),
						'type'    => 'textarea',
						'default' => __( 'Des revenus complémentaires, une assurance pendant la location, et c\'est vous qui choisissez vos dates et vos locataires.', 'icademie-page-accueil' ),
					),
					'loueur_image'         => array(
						'label'       => __( 'Image', 'icademie-page-accueil' ),
						'type'        => 'image',
						'description' => __( 'Sans image, une illustration est affichée.', 'icademie-page-accueil' ),
					),
					'loueur_url_proposer'  => array(
						'label' => __( 'Adresse du bouton « Proposer mon bateau »', 'icademie-page-accueil' ),
						'type'  => 'url',
					),
					'loueur_url_estimer'   => array(
						'label' => __( 'Adresse du bouton « Estimer mes revenus »', 'icademie-page-accueil' ),
						'type'  => 'url',
					),
				),
			),
			'avis'        => array(
				'title'       => __( 'Avis des membres (zone 9)', 'icademie-page-accueil' ),
				'description' => __( 'Note moyenne des avis publiés sur les bateaux et trois derniers avis, les avis vérifiés (laissés après une location) en premier. La zone s\'affiche dès le premier avis publié.', 'icademie-page-accueil' ),
				'rows'        => array(
					'avis_url' => array(
						'label'       => __( 'Adresse du lien « Tous les avis »', 'icademie-page-accueil' ),
						'type'        => 'url',
						'description' => __( 'Par exemple une page qui contient le bloc WooCommerce « Tous les avis ». Sans adresse, le lien n\'est pas affiché.', 'icademie-page-accueil' ),
					),
				),
			),
			'newsletter'  => array(
				'title'       => __( 'Newsletter (zone 10)', 'icademie-page-accueil' ),
				'description' => __( 'Le formulaire affiché est celui de la page Newsletter : si vous changez de formulaire HubSpot sur cette page, l\'accueil suit.', 'icademie-page-accueil' ),
				'rows'        => array(
					'newsletter_url'   => array(
						'label'       => __( 'Adresse de la page Newsletter', 'icademie-page-accueil' ),
						'type'        => 'url',
						'description' => __( 'Vide : la page « newsletter » du site. Le formulaire HubSpot est lu sur la page de cette adresse ; pour une adresse hors du site, l\'accueil affiche un bouton vers elle à la place du formulaire.', 'icademie-page-accueil' ),
					),
					'newsletter_etat'  => array(
						'label' => __( 'Formulaire trouvé', 'icademie-page-accueil' ),
						'type'  => 'newsletter',
					),
					'newsletter_titre' => array(
						'label'   => __( 'Accroche', 'icademie-page-accueil' ),
						'type'    => 'text',
						'default' => __( 'Bons plans et nouveaux bateaux, une fois par mois dans votre boîte mail', 'icademie-page-accueil' ),
					),
					'newsletter_texte' => array(
						'label'   => __( 'Mention sous le formulaire', 'icademie-page-accueil' ),
						'type'    => 'text',
						'default' => __( 'Garanti sans spam. Désinscription possible à tout moment, en un clic.', 'icademie-page-accueil' ),
					),
					'newsletter_desinscription_url' => array(
						'label'       => __( 'Adresse de la page de désinscription (facultatif)', 'icademie-page-accueil' ),
						'type'        => 'url',
						'description' => __( 'Page de gestion des abonnements de votre outil d\'envoi (HubSpot), si elle a une adresse publique. Elle est proposée sous la page Newsletter, à côté du formulaire « Me désinscrire » (demande transmise par e-mail au destinataire des messages de la page Contact). Vide : le formulaire seul.', 'icademie-page-accueil' ),
					),
				),
			),
		);

		return $sections;
	}

	/**
	 * Lignes des quatre arguments de réassurance : titre, texte court, adresse du lien.
	 *
	 * @return array[]
	 */
	private static function reassurance_rows() {
		$defaults = array(
			1 => array( __( 'Paiement sécurisé', 'icademie-page-accueil' ), __( 'Réglez votre location en ligne, en toute sécurité.', 'icademie-page-accueil' ) ),
			2 => array( __( 'Assurance et caution', 'icademie-page-accueil' ), __( 'Garanties et caution expliquées avant de réserver.', 'icademie-page-accueil' ) ),
			3 => array( __( 'Profils vérifiés', 'icademie-page-accueil' ), __( 'Propriétaires et locataires identifiés.', 'icademie-page-accueil' ) ),
			4 => array( __( 'Assistance 7j/7', 'icademie-page-accueil' ), __( 'Une équipe joignable tous les jours.', 'icademie-page-accueil' ) ),
		);

		$rows = array();

		foreach ( $defaults as $n => $texts ) {
			$rows[ 'reassurance_' . $n ] = array(
				/* translators: %d: numéro de l'argument (1 à 4). */
				'label'  => sprintf( __( 'Argument %d', 'icademie-page-accueil' ), $n ),
				'fields' => array(
					'reassurance_' . $n . '_titre' => array(
						'label'   => __( 'Titre', 'icademie-page-accueil' ),
						'type'    => 'text',
						'default' => $texts[0],
					),
					'reassurance_' . $n . '_texte' => array(
						'label'   => __( 'Texte court', 'icademie-page-accueil' ),
						'type'    => 'text',
						'default' => $texts[1],
					),
					'reassurance_' . $n . '_url'   => array(
						'label' => __( 'Adresse du lien', 'icademie-page-accueil' ),
						'type'  => 'url',
					),
				),
			);
		}

		return $rows;
	}

	/**
	 * Étapes par défaut des deux onglets de « Comment ça marche ».
	 *
	 * @return array[] Par onglet : numéro d'étape => array( titre, texte ).
	 */
	public static function steps_defaults() {
		return array(
			'locataire'    => array(
				1 => array( __( 'Recherchez', 'icademie-page-accueil' ), __( 'Destination, dates, type de bateau, avec ou sans skipper.', 'icademie-page-accueil' ) ),
				2 => array( __( 'Réservez', 'icademie-page-accueil' ), __( 'Demande au propriétaire, échanges par messagerie, paiement sécurisé.', 'icademie-page-accueil' ) ),
				3 => array( __( 'Naviguez', 'icademie-page-accueil' ), __( 'Rendez-vous au port, état des lieux, départ en mer.', 'icademie-page-accueil' ) ),
			),
			'proprietaire' => array(
				1 => array( __( 'Publiez votre annonce', 'icademie-page-accueil' ), __( 'Photos, équipements et prix par jour : votre bateau en ligne en quelques minutes.', 'icademie-page-accueil' ) ),
				2 => array( __( 'Choisissez vos locataires', 'icademie-page-accueil' ), __( 'Vous gardez la main sur vos dates et acceptez les demandes qui vous conviennent.', 'icademie-page-accueil' ) ),
				3 => array( __( 'Recevez vos revenus', 'icademie-page-accueil' ), __( 'Paiement sécurisé et assurance pendant la location.', 'icademie-page-accueil' ) ),
			),
		);
	}

	/**
	 * Libellés des deux onglets de « Comment ça marche ».
	 *
	 * @return string[]
	 */
	public static function steps_tabs() {
		return array(
			'locataire'    => __( 'Je loue un bateau', 'icademie-page-accueil' ),
			'proprietaire' => __( 'Je propose mon bateau', 'icademie-page-accueil' ),
		);
	}

	/**
	 * Lignes des étapes : une ligne par étape (titre et texte).
	 *
	 * @return array[]
	 */
	private static function steps_rows() {
		$tabs = self::steps_tabs();
		$rows = array();

		foreach ( self::steps_defaults() as $group => $steps ) {
			foreach ( $steps as $n => $texts ) {
				$rows[ 'etapes_' . $group . '_' . $n ] = array(
					/* translators: 1: nom de l'onglet, 2: numéro de l'étape. */
					'label'  => sprintf( __( '%1$s – étape %2$d', 'icademie-page-accueil' ), $tabs[ $group ], $n ),
					'fields' => array(
						'etapes_' . $group . '_' . $n . '_titre' => array(
							'label'   => __( 'Titre', 'icademie-page-accueil' ),
							'type'    => 'text',
							'default' => $texts[0],
						),
						'etapes_' . $group . '_' . $n . '_texte' => array(
							'label'   => __( 'Texte', 'icademie-page-accueil' ),
							'type'    => 'text',
							'default' => $texts[1],
						),
					),
				);
			}
		}

		return $rows;
	}

	/**
	 * Tous les champs, à plat : clé => définition (libellé, type, valeur par défaut…).
	 *
	 * @return array[]
	 */
	public static function fields() {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$fields = array();

		foreach ( self::sections() as $section ) {
			foreach ( $section['rows'] as $key => $row ) {
				if ( isset( $row['fields'] ) ) {
					foreach ( $row['fields'] as $field_key => $field ) {
						// Nom complet pour les messages d'erreur : « Argument 1 – Adresse du lien ».
						$field['context']      = $row['label'];
						$fields[ $field_key ] = $field;
					}
				} else {
					$fields[ $key ] = $row;
				}
			}
		}

		self::$fields = $fields;

		return $fields;
	}

	/**
	 * Valeur d'un réglage, ou sa valeur par défaut tant qu'il n'a pas été enregistré.
	 *
	 * @param string $key Clé du réglage.
	 * @return string|int Texte ou adresse (vide si aucune), ou ID d'image (0 si aucune).
	 */
	public static function get( $key ) {
		$fields  = self::fields();
		$field   = isset( $fields[ $key ] ) ? $fields[ $key ] : array();
		$is_id   = isset( $field['type'] ) && 'image' === $field['type'];
		$default = isset( $field['default'] ) ? $field['default'] : ( $is_id ? 0 : '' );
		$options = get_option( self::OPTION, array() );

		if ( ! is_array( $options ) || ! array_key_exists( $key, $options ) ) {
			return $default;
		}

		$value = $options[ $key ];

		if ( $is_id ) {
			return absint( $value );
		}

		// Un texte obligatoire vidé reprend sa valeur par défaut.
		if ( '' === $value && ! empty( $field['required'] ) ) {
			return $default;
		}

		return (string) $value;
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
			)
		);

		foreach ( self::sections() as $section_id => $section ) {
			add_settings_section(
				'icademie-accueil-' . $section_id,
				$section['title'],
				array( __CLASS__, 'render_section' ),
				self::PAGE,
				array(
					'description'    => $section['description'],
					// Ancre visée par les liens « à compléter » affichés sur l'accueil aux administrateurs.
					'before_section' => '<div id="icademie-accueil-' . $section_id . '" class="icademie-accueil-settings__section">',
					'after_section'  => '</div>',
				)
			);

			foreach ( $section['rows'] as $key => $row ) {
				$single = ! isset( $row['fields'] );

				add_settings_field(
					$key,
					esc_html( $row['label'] ),
					array( __CLASS__, 'render_row' ),
					self::PAGE,
					'icademie-accueil-' . $section_id,
					array(
						'key'       => $key,
						'row'       => $row,
						// Le libellé de la ligne est cliquable quand la ligne n'a qu'un champ.
						'label_for' => $single && in_array( $row['type'], array( 'text', 'textarea', 'url' ), true ) ? self::field_id( $key ) : '',
					)
				);
			}
		}
	}

	/**
	 * Identifiant HTML d'un champ.
	 *
	 * @param string $key Clé du réglage.
	 * @return string
	 */
	private static function field_id( $key ) {
		return 'icademie-accueil-' . str_replace( '_', '-', $key );
	}

	/**
	 * Description d'une section (et ancre pour les liens « à compléter » de l'accueil).
	 *
	 * @param array $args Arguments de la section.
	 */
	public static function render_section( $args ) {
		if ( 'icademie-accueil-produits' === $args['id'] ) {
			self::render_products_help();
			return;
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p>' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Section « zones 4 à 6 » : ces zones se règlent dans les produits, pas ici.
	 */
	private static function render_products_help() {
		if ( ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			echo '<p>' . esc_html__( 'Ces zones utilisent WooCommerce, qui n\'est pas actif.', 'icademie-page-accueil' ) . '</p>';
			return;
		}

		$type_taxonomy = wc_attribute_taxonomy_name( Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE );
		$items         = array(
			array(
				__( 'Catégories de bateaux : une vignette par valeur de l\'attribut « Type de bateau ». L\'image de chaque type se choisit sur la valeur.', 'icademie-page-accueil' ),
				__( 'Produits › Attributs › Type de bateau', 'icademie-page-accueil' ),
				taxonomy_exists( $type_taxonomy ) ? admin_url( 'edit-tags.php?taxonomy=' . $type_taxonomy . '&post_type=product' ) : admin_url( 'edit.php?post_type=product&page=product_attributes' ),
			),
			array(
				__( 'Bateaux à la une : les produits mis en avant (étoile de la liste des produits). Sans produit mis en avant, les plus récents sont affichés.', 'icademie-page-accueil' ),
				__( 'Produits', 'icademie-page-accueil' ),
				admin_url( 'edit.php?post_type=product' ),
			),
			array(
				__( 'Destinations populaires : les catégories cochées « Destination populaire », dans l\'ordre des catégories (la première a la grande vignette), cinq au plus.', 'icademie-page-accueil' ),
				__( 'Produits › Catégories', 'icademie-page-accueil' ),
				admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ),
			),
		);

		echo '<ul class="ul-disc">';

		foreach ( $items as $item ) {
			printf( '<li>%1$s <a href="%2$s">%3$s</a></li>', esc_html( $item[0] ), esc_url( $item[2] ), esc_html( $item[1] ) );
		}

		echo '</ul>';
	}

	/**
	 * Affiche une ligne de réglages : un champ, ou plusieurs champs avec chacun son libellé.
	 *
	 * @param array $args Arguments de la ligne.
	 */
	public static function render_row( $args ) {
		$row = $args['row'];

		if ( ! isset( $row['fields'] ) ) {
			self::render_control( $args['key'], $row, false );
			return;
		}

		echo '<div class="icademie-accueil-settings__group">';

		foreach ( $row['fields'] as $key => $field ) {
			printf( '<div class="icademie-accueil-settings__subfield icademie-accueil-settings__subfield--%s">', esc_attr( $field['type'] ) );
			self::render_control( $key, $field, true );
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Affiche un champ.
	 *
	 * @param string $key        Clé du réglage.
	 * @param array  $field      Définition du champ.
	 * @param bool   $with_label Afficher le libellé du champ au-dessus (ligne à plusieurs champs).
	 */
	private static function render_control( $key, $field, $with_label ) {
		$id    = self::field_id( $key );
		$name  = self::OPTION . '[' . $key . ']';
		$value = self::get( $key );

		if ( $with_label && 'image' !== $field['type'] ) {
			printf( '<label for="%1$s">%2$s</label><br>', esc_attr( $id ), esc_html( $field['label'] ) );
		}

		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea class="large-text" rows="2" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;

			case 'url':
				printf(
					'<input type="text" class="large-text code" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" inputmode="url" spellcheck="false">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					/* translators: Exemple d'adresse dans un champ vide. */
					esc_attr__( 'https://… ou /chemin-de-la-page/', 'icademie-page-accueil' )
				);
				break;

			case 'image':
				self::media_field( $name, (int) $value, $with_label ? $field['label'] : '' );
				break;

			case 'newsletter':
				self::render_newsletter_status();
				break;

			default:
				printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
	}

	/**
	 * Champ d'image : aperçu, bouton de la médiathèque, bouton pour retirer l'image.
	 *
	 * Partagé avec les écrans des valeurs d'attribut (image d'un type de bateau).
	 *
	 * @param string $name     Nom du champ (ID de la pièce jointe).
	 * @param int    $image_id Image choisie (0 : aucune).
	 * @param string $label    Libellé affiché au-dessus, facultatif.
	 */
	public static function media_field( $name, $image_id, $label = '' ) {
		$preview = $image_id ? wp_get_attachment_image( $image_id, 'medium', false, array( 'style' => 'max-width:200px;height:auto;display:block' ) ) : '';
		?>
		<div class="icademie-accueil-media" data-icademie-media>
			<?php if ( '' !== $label ) : ?>
				<span class="icademie-accueil-media__label"><?php echo esc_html( $label ); ?></span><br>
			<?php endif; ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $image_id ? (string) $image_id : '' ); ?>" data-media-input>
			<div class="icademie-accueil-media__preview" data-media-preview style="margin:4px 0 8px"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?></div>
			<button type="button" class="button" data-media-choose data-title="<?php esc_attr_e( 'Choisir une image', 'icademie-page-accueil' ); ?>" data-button="<?php esc_attr_e( 'Utiliser cette image', 'icademie-page-accueil' ); ?>"><?php esc_html_e( 'Choisir une image', 'icademie-page-accueil' ); ?></button>
			<button type="button" class="button-link button-link-delete" data-media-remove style="margin-left:8px"<?php echo $image_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Retirer l\'image', 'icademie-page-accueil' ); ?></button>
		</div>
		<?php
	}

	/**
	 * État du formulaire de la page Newsletter : trouvé (avec ses identifiants) ou non.
	 */
	private static function render_newsletter_status() {
		$page = Icademie_Page_Accueil_Newsletter::page();

		if ( ! $page ) {
			if ( '' !== self::get( 'newsletter_url' ) ) {
				echo '<p>' . esc_html__( 'Cette adresse ne correspond à aucune page publiée du site : le formulaire ne peut pas y être lu. L\'accueil affiche un bouton « S\'inscrire à la newsletter » vers cette adresse.', 'icademie-page-accueil' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Aucune page « newsletter » publiée : la zone Newsletter n\'est pas affichée. Indiquez l\'adresse de la page qui contient le formulaire.', 'icademie-page-accueil' ) . '</p>';
			}
			return;
		}

		$form = Icademie_Page_Accueil_Newsletter::form( $page );
		$link = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );

		if ( ! $form ) {
			echo '<p>' . wp_kses(
				sprintf(
					/* translators: %s: lien vers la page Newsletter. */
					__( 'Aucun formulaire HubSpot trouvé sur la page %s : l\'accueil affiche un lien vers cette page à la place.', 'icademie-page-accueil' ),
					$link
				),
				array( 'a' => array( 'href' => true ) )
			) . '</p>';
			return;
		}

		echo '<p>' . wp_kses(
			sprintf(
				/* translators: 1: lien vers la page Newsletter, 2: identifiant du formulaire HubSpot, 3: identifiant du compte HubSpot. */
				__( 'Formulaire HubSpot de la page %1$s : formulaire %2$s du compte %3$s.', 'icademie-page-accueil' ),
				$link,
				'<code>' . esc_html( $form['form'] ? $form['form'] : '—' ) . '</code>',
				'<code>' . esc_html( $form['portal'] ? $form['portal'] : '—' ) . '</code>'
			),
			array(
				'a'    => array( 'href' => true ),
				'code' => array(),
			)
		) . '</p>';
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
			if ( 'newsletter' === $field['type'] ) {
				continue;
			}

			$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			$value = is_scalar( $value ) ? (string) $value : '';

			switch ( $field['type'] ) {
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;

				case 'url':
					$clean[ $key ] = self::sanitize_link( $key, $value, $field );
					break;

				case 'image':
					$image_id      = absint( $value );
					$clean[ $key ] = ( $image_id && wp_attachment_is_image( $image_id ) ) ? $image_id : 0;
					break;

				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}

			if ( '' === $clean[ $key ] && ! empty( $field['required'] ) ) {
				$clean[ $key ] = $field['default'];
			}
		}

		return $clean;
	}

	/**
	 * Nettoie l'adresse d'un lien.
	 *
	 * Acceptées : une adresse complète en http ou https (« https://exemple.fr/page/ »), ou le
	 * chemin d'une page du site (« /aide/paiement/ »), complété avec l'adresse du site. Une adresse
	 * sans « http » (« www.exemple.fr ») est complétée par WordPress. Toute autre valeur est refusée,
	 * avec un message en haut de la page de réglages.
	 *
	 * @param string $key   Clé du réglage.
	 * @param string $value Valeur envoyée.
	 * @param array  $field Définition du champ.
	 * @return string Adresse, ou vide.
	 */
	private static function sanitize_link( $key, $value, $field ) {
		$value = trim( wp_strip_all_tags( $value ) );

		if ( '' === $value ) {
			return '';
		}

		// Chemin d'une page du site (mais pas « //exemple.fr », adresse sans protocole).
		if ( '/' === $value[0] && ( ! isset( $value[1] ) || '/' !== $value[1] ) ) {
			$value = home_url( $value );
		}

		$url  = esc_url_raw( $value, array( 'http', 'https' ) );
		$host = '' !== $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';

		// Vrai nom de domaine, adresse IP ou nom du site : « pas une adresse » (dont WordPress ferait
		// « http://pas%20une%20adresse ») est refusé.
		$valid_host = '' !== $host && (
			strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === $host
			|| false !== filter_var( $host, FILTER_VALIDATE_IP )
			|| preg_match( '/^(?:[a-z0-9\\x80-\\xff](?:[a-z0-9\\x80-\\xff-]*[a-z0-9\\x80-\\xff])?\\.)+[a-z\\x80-\\xff][a-z0-9\\x80-\\xff-]*[a-z0-9\\x80-\\xff]$/i', $host )
		);

		if ( $valid_host ) {
			return $url;
		}

		if ( function_exists( 'add_settings_error' ) ) {
			$label = ( isset( $field['context'] ) ? $field['context'] . ' – ' : '' ) . $field['label'];

			add_settings_error(
				self::OPTION,
				'adresse-' . $key,
				sprintf(
					/* translators: 1: nom du champ, 2: valeur refusée. */
					__( '« %1$s » : l\'adresse « %2$s » n\'est pas valide et n\'a pas été enregistrée. Indiquez une adresse complète (https://…) ou le chemin d\'une page du site (/…).', 'icademie-page-accueil' ),
					$label,
					$value
				)
			);
		}

		return '';
	}

	/**
	 * Ajoute Réglages › Page d'accueil.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Page d\'accueil', 'icademie-page-accueil' ),
			__( 'Page d\'accueil', 'icademie-page-accueil' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Médiathèque et script des champs d'image sur la page de réglages.
	 *
	 * @param string $hook_suffix Écran affiché.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		self::enqueue_media_script();

		wp_add_inline_style(
			'common',
			'.icademie-accueil-settings__group{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:flex-start}'
			. '.icademie-accueil-settings__subfield{flex:1 1 220px;min-width:0}'
			// L'adresse d'un lien sur sa propre ligne, en entier : elle doit pouvoir être relue.
			. '.icademie-accueil-settings__subfield--url{flex-basis:100%}'
			. '.icademie-accueil-settings__subfield input[type=text]{width:100%}'
			. '.icademie-accueil-settings__section{margin-top:8px;padding-top:4px;border-top:1px solid #dcdcde}'
		);
	}

	/**
	 * Médiathèque et script des champs d'image (réglages et valeurs d'attribut).
	 */
	public static function enqueue_media_script() {
		wp_enqueue_media();
		wp_enqueue_script(
			'icademie-page-accueil-admin',
			plugins_url( 'assets/admin.js', Icademie_Page_Accueil::FILE ),
			array( 'jquery' ),
			Icademie_Page_Accueil::VERSION,
			true
		);
	}

	/**
	 * Page Réglages › Page d'accueil.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Page d\'accueil', 'icademie-page-accueil' ); ?></h1>
			<p>
				<?php esc_html_e( 'Textes, images et liens de la page d\'accueil. Les champs sont pré-remplis avec les textes affichés par défaut. Pour un lien, indiquez une adresse complète (https://…) ou le chemin d\'une page du site (/aide/paiement/).', 'icademie-page-accueil' ); ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Voir la page d\'accueil', 'icademie-page-accueil' ); ?></a>
			</p>
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
		array_unshift( $links, sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::url() ), esc_html__( 'Réglages', 'icademie-page-accueil' ) ) );

		return $links;
	}
}
