<?php
/**
 * Plugin Name:       SailingLoc – Page Contact
 * Description:       Page Contact intégrée d'après le zoning « Contact · Ordinateur 1440 px » : fil d'Ariane, aide rapide (recherche et thèmes fréquents), formulaire adapté au profil avec pièce jointe et accusé de réception par e-mail, coordonnées, plan d'accès et bandeau d'assistance pendant la location. Les coordonnées se règlent dans Réglages › Page contact ; à l'activation, la page « Contact » est créée en brouillon. Habillage d'après la charte graphique et les maquettes SailingLoc (couleurs craie, encre et bleu Manche, titres en Fraunces, texte en Atkinson Hyperlegible, polices hébergées avec l'extension).
 * Version:           1.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Text Domain:       icademie-page-contact
 *
 * @package Icademie_Page_Contact
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-page-contact-settings.php';
require_once __DIR__ . '/includes/class-icademie-page-contact-form.php';

/**
 * Page Contact : bloc, modèle de page et affichage.
 *
 * Le bloc « Page contact » (icademie/page-contact) affiche tout le contenu de la page, du fil
 * d'Ariane au bandeau d'assistance : zones 2 à 8 du zoning. L'en-tête et le pied de page (zones 1
 * et 9, communs à tout le site) sont ceux du thème, ou ceux de
 * l'extension « Icademie – En-tête et pied de page » qui les remplace.
 *
 * Une page qui contient ce bloc utilise automatiquement le modèle « Page de contact » : pleine
 * largeur et sans titre de page, puisque le bloc affiche son propre titre. Un autre modèle choisi
 * pour la page dans l'éditeur reste prioritaire.
 */
final class Icademie_Page_Contact {

	const VERSION = '1.1.0';

	/** Bloc qui affiche la page. */
	const BLOCK = 'icademie/page-contact';

	/** Modèle de page : nom complet (« extension//slug ») et slug. */
	const TEMPLATE_NAME = 'icademie-page-contact//icademie-page-contact';
	const TEMPLATE_SLUG = 'icademie-page-contact';

	/** Poignée commune de la feuille de styles et du script. */
	const HANDLE = 'icademie-page-contact';

	/** Avis affiché une fois après l'activation. */
	const NOTICE_TRANSIENT = 'icademie_page_contact_activation';

	/**
	 * Branche le plugin sur WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'page_template_hierarchy', array( __CLASS__, 'template_hierarchy' ) );
		add_action( 'wp_head', array( __CLASS__, 'preload_font' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );

		Icademie_Page_Contact_Settings::init();
		Icademie_Page_Contact_Form::init();
	}

	/**
	 * Enregistre les styles, le script, le bloc et le modèle de page.
	 */
	public static function register() {
		$shared = defined( 'Icademie_Page_Accueil::FONTS_HANDLE' );

		// Polices de la charte : feuille commune de l'extension de la page d'accueil (déclarées une
		// seule fois avec l'en-tête du site), sinon les fichiers de cette extension.
		wp_register_style( self::HANDLE, plugins_url( 'assets/page-contact.css', __FILE__ ), $shared ? array( Icademie_Page_Accueil::FONTS_HANDLE ) : array(), self::VERSION );

		if ( ! $shared ) {
			wp_add_inline_style( self::HANDLE, self::font_face_css() );
		}

		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/page-contact.js', __FILE__ ),
			array(),
			self::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		register_block_type(
			self::BLOCK,
			array(
				'api_version'         => 3,
				'title'               => __( 'Page contact', 'icademie-page-contact' ),
				'description'         => __( 'Tout le contenu de la page Contact : fil d\'Ariane, aide rapide, formulaire, coordonnées, plan d\'accès et bandeau d\'assistance. Les coordonnées se règlent dans Réglages › Page contact.', 'icademie-page-contact' ),
				'category'            => 'widgets',
				'icon'                => 'email-alt',
				'keywords'            => array( 'contact', 'formulaire', 'aide', 'assistance' ),
				'attributes'          => array(
					'align' => array(
						'type'    => 'string',
						'default' => 'full',
					),
				),
				'supports'            => array(
					// Bloc déclaré uniquement en PHP : l'éditeur l'affiche avec un aperçu calculé par le serveur.
					'autoRegister' => true,
					'align'        => array( 'full' ),
					'html'         => false,
					'multiple'     => false,
					'reusable'     => false,
				),
				'uses_context'        => array( 'postId' ),
				'render_callback'     => array( __CLASS__, 'render_block' ),
				'style_handles'       => array( self::HANDLE ),
				'view_script_handles' => array( self::HANDLE ),
			)
		);

		register_block_template(
			self::TEMPLATE_NAME,
			array(
				'title'       => __( 'Page de contact', 'icademie-page-contact' ),
				'description' => __( 'En-tête et pied de page du site, contenu en pleine largeur et sans titre de page : le bloc « Page contact » affiche son propre titre.', 'icademie-page-contact' ),
				'post_types'  => array( 'page' ),
				'content'     => self::template_content(),
			)
		);
	}

	/**
	 * Contenu du modèle « Page de contact ».
	 *
	 * Mêmes en-tête et pied de page que les autres pages du thème ; entre les deux, le contenu de la
	 * page sans son titre, ni marge ni largeur imposée : le bloc gère sa propre mise en page.
	 *
	 * @return string
	 */
	private static function template_content() {
		return '<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main","metadata":{"name":"Contenu"},"style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><!-- wp:post-content {"layout":{"type":"default"}} /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->';
	}

	/**
	 * Fait utiliser le modèle « Page de contact » aux pages qui contiennent le bloc.
	 *
	 * @param string[] $templates Modèles candidats, du plus prioritaire au moins prioritaire.
	 * @return string[]
	 */
	public static function template_hierarchy( $templates ) {
		$page = get_queried_object();

		// Un modèle choisi pour la page dans l'éditeur reste prioritaire.
		if ( $page instanceof WP_Post && '' === get_page_template_slug( $page ) && has_block( self::BLOCK, $page ) ) {
			array_unshift( $templates, self::TEMPLATE_SLUG . '.php' );
		}

		return $templates;
	}

	/**
	 * La page affichée contient-elle le bloc « Page contact » ?
	 *
	 * @return bool
	 */
	public static function is_contact_page() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_queried_object();

		return $post instanceof WP_Post && has_block( self::BLOCK, $post );
	}

	/**
	 * Précharge les polices du titre et du texte sur la page Contact (affichés dès l'arrivée).
	 */
	public static function preload_font() {
		if ( ! self::is_contact_page() ) {
			return;
		}

		if ( method_exists( 'Icademie_Page_Accueil', 'print_font_preloads' ) ) {
			Icademie_Page_Accueil::print_font_preloads();
			return;
		}

		foreach ( array( 'fraunces-latin-600-normal.woff2', 'atkinson-hyperlegible-latin-400-normal.woff2' ) as $file ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( plugins_url( 'assets/fonts/' . $file, __FILE__ ) ) );
		}
	}

	/**
	 * Règles @font-face des fichiers de polices de cette extension (sans l'extension de l'accueil).
	 *
	 * @return string CSS.
	 */
	private static function font_face_css() {
		$range = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
		$css   = '';

		foreach ( array( array( 'Fraunces', '600', 'fraunces-latin-600-normal.woff2' ), array( 'Atkinson Hyperlegible', '400', 'atkinson-hyperlegible-latin-400-normal.woff2' ), array( 'Atkinson Hyperlegible', '700', 'atkinson-hyperlegible-latin-700-normal.woff2' ) ) as $font ) {
			$css .= sprintf( '@font-face{font-family:"%1$s";font-style:normal;font-weight:%2$s;font-display:swap;src:url("%3$s") format("woff2");unicode-range:%4$s}', $font[0], $font[1], esc_url( plugins_url( 'assets/fonts/' . $font[2], __FILE__ ) ), $range );
		}

		return $css;
	}

	/**
	 * Affiche le bloc « Page contact ».
	 *
	 * @param array    $attributes Attributs du bloc.
	 * @param string   $content    Contenu enregistré (vide : bloc dynamique).
	 * @param WP_Block $block      Bloc affiché.
	 * @return string
	 */
	public static function render_block( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$data = array(
			'breadcrumb' => self::breadcrumb( $post ),
			'visual'     => get_the_post_thumbnail( $post, 'medium_large', array( 'sizes' => '240px' ) ),
			'help_url'   => self::help_url( $post ),
			'topics'     => self::topic_links(),
			'form'       => Icademie_Page_Contact_Form::view_data( $post ),
			'contact'    => self::contact_details(),
			'missing'    => current_user_can( 'manage_options' ) ? self::missing_settings() : array(),
		);

		ob_start();
		include __DIR__ . '/templates/page-contact.php';
		$html = ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'icademie-contact' ) ),
			$html
		);
	}

	/**
	 * Fil d'Ariane : accueil, pages parentes (par exemple « Aide »), puis la page elle-même.
	 *
	 * @param WP_Post $post Page Contact.
	 * @return array[] Éléments : libellé et adresse (vide pour la page affichée).
	 */
	private static function breadcrumb( $post ) {
		$items = array(
			array(
				'label' => __( 'Accueil', 'icademie-page-contact' ),
				'url'   => home_url( '/' ),
			),
		);

		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
			$items[] = array(
				'label' => get_the_title( $ancestor_id ),
				'url'   => get_permalink( $ancestor_id ),
			);
		}

		$items[] = array(
			'label' => get_the_title( $post ),
			'url'   => '',
		);

		return $items;
	}

	/**
	 * Adresse du bouton « Centre d'aide complet ».
	 *
	 * @param WP_Post $post Page Contact.
	 * @return string Adresse réglée, sinon celle de la page parente, sinon vide (bouton masqué).
	 */
	private static function help_url( $post ) {
		$url = Icademie_Page_Contact_Settings::get( 'aide_url' );

		if ( '' === $url && $post->post_parent ) {
			$url = (string) get_permalink( $post->post_parent );
		}

		return $url;
	}

	/**
	 * Thèmes fréquents de l'aide rapide.
	 *
	 * @return array[] Clé => libellé, mot recherché quand aucun lien n'est réglé, icône.
	 */
	public static function topics() {
		return array(
			'reservation'  => array(
				'label'  => __( 'Réservation', 'icademie-page-contact' ),
				'search' => __( 'réservation', 'icademie-page-contact' ),
				'icon'   => 'calendar',
			),
			'paiement'     => array(
				'label'  => __( 'Paiement et remboursement', 'icademie-page-contact' ),
				'search' => __( 'paiement', 'icademie-page-contact' ),
				'icon'   => 'card',
			),
			'annulation'   => array(
				'label'  => __( 'Annulation', 'icademie-page-contact' ),
				'search' => __( 'annulation', 'icademie-page-contact' ),
				'icon'   => 'calendar-x',
			),
			'assurance'    => array(
				'label'  => __( 'Assurance et caution', 'icademie-page-contact' ),
				'search' => __( 'assurance', 'icademie-page-contact' ),
				'icon'   => 'shield',
			),
			'proprietaire' => array(
				'label'  => __( 'Espace propriétaire', 'icademie-page-contact' ),
				'search' => __( 'propriétaire', 'icademie-page-contact' ),
				'icon'   => 'boat',
			),
		);
	}

	/**
	 * Thèmes fréquents avec leur lien : celui réglé, sinon une recherche du thème dans le site.
	 *
	 * @return array[]
	 */
	private static function topic_links() {
		$links = array();

		foreach ( self::topics() as $key => $topic ) {
			$url = Icademie_Page_Contact_Settings::get( 'theme_' . $key );

			if ( '' === $url ) {
				$url = add_query_arg( 's', rawurlencode( $topic['search'] ), home_url( '/' ) );
			}

			$links[] = array(
				'label' => $topic['label'],
				'icon'  => $topic['icon'],
				'url'   => $url,
			);
		}

		return $links;
	}

	/**
	 * Réseaux sociaux proposés dans les réglages.
	 *
	 * @return string[] Service (nom utilisé par le bloc « Icônes de réseaux sociaux ») => libellé.
	 */
	public static function social_networks() {
		return array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'youtube'   => 'YouTube',
		);
	}

	/**
	 * Coordonnées affichées, d'après les réglages.
	 *
	 * @return array
	 */
	private static function contact_details() {
		$phone      = Icademie_Page_Contact_Settings::get( 'telephone' );
		$assistance = Icademie_Page_Contact_Settings::get( 'assistance' );
		$address    = Icademie_Page_Contact_Settings::get( 'adresse' );
		$latitude   = Icademie_Page_Contact_Settings::get( 'latitude' );
		$longitude  = Icademie_Page_Contact_Settings::get( 'longitude' );

		$map = null;

		if ( '' !== $latitude && '' !== $longitude ) {
			$map = self::map_urls( (float) $latitude, (float) $longitude );
		} elseif ( '' !== $address ) {
			$map = array(
				'embed' => '',
				'link'  => 'https://www.openstreetmap.org/search?query=' . rawurlencode( preg_replace( '/\s*\n\s*/', ', ', $address ) ),
			);
		}

		$social = array();

		foreach ( self::social_networks() as $service => $label ) {
			$url = Icademie_Page_Contact_Settings::get( $service );

			if ( '' !== $url ) {
				$social[] = array(
					'label' => $label,
					'url'   => $url,
					'icon'  => function_exists( 'block_core_social_link_get_icon' ) ? block_core_social_link_get_icon( $service ) : '',
				);
			}
		}

		return array(
			'phone'           => $phone,
			'phone_href'      => self::tel_href( $phone ),
			'hours'           => Icademie_Page_Contact_Settings::get( 'horaires' ),
			'email'           => Icademie_Page_Contact_Settings::get( 'email' ),
			'delay'           => Icademie_Page_Contact_Settings::get( 'delai' ),
			'chat'            => Icademie_Page_Contact_Settings::get( 'chat' ),
			'address'         => $address,
			'map'             => $map,
			'social'          => $social,
			'assistance'      => $assistance,
			'assistance_href' => self::tel_href( $assistance ),
			'site'            => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	/**
	 * Adresses OpenStreetMap du siège : carte intégrable et lien vers le site d'OpenStreetMap.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return string[]
	 */
	private static function map_urls( $latitude, $longitude ) {
		// Zone affichée autour du siège (en degrés), plus large que haute comme l'encart.
		$lat_span = 0.006;
		$lon_span = 0.012;

		// %F : nombre avec un point décimal, quelle que soit la langue du serveur.
		$bbox   = sprintf( '%F,%F,%F,%F', $longitude - $lon_span, $latitude - $lat_span, $longitude + $lon_span, $latitude + $lat_span );
		$marker = sprintf( '%F,%F', $latitude, $longitude );

		return array(
			'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $marker,
			'link'  => sprintf( 'https://www.openstreetmap.org/?mlat=%1$F&mlon=%2$F#map=16/%1$F/%2$F', $latitude, $longitude ),
		);
	}

	/**
	 * Lien « tel: » d'un numéro saisi librement (« 05 46 00 00 00 », « +33 5 46… »).
	 *
	 * @param string $phone Numéro.
	 * @return string Lien, ou vide sans numéro.
	 */
	private static function tel_href( $phone ) {
		$digits = preg_replace( '/[^0-9+]/', '', $phone );

		return '' === $digits ? '' : 'tel:' . $digits;
	}

	/**
	 * Réglages importants encore vides, signalés aux administrateurs sur la page.
	 *
	 * @return string[] Libellés des réglages manquants.
	 */
	private static function missing_settings() {
		$labels = array(
			'telephone'  => __( 'téléphone du service client', 'icademie-page-contact' ),
			'email'      => __( 'adresse e-mail', 'icademie-page-contact' ),
			'adresse'    => __( 'adresse du siège', 'icademie-page-contact' ),
			'assistance' => __( 'ligne d\'assistance pendant la location', 'icademie-page-contact' ),
		);

		$missing = array();

		foreach ( $labels as $key => $label ) {
			if ( '' === Icademie_Page_Contact_Settings::get( $key ) ) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Première page (hors corbeille) qui contient le bloc « Page contact ».
	 *
	 * @return int ID de la page, 0 si aucune.
	 */
	public static function find_contact_page() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Recherche ponctuelle (activation, page de réglages), pas de cache utile.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND post_content LIKE %s ORDER BY post_status = 'publish' DESC, ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '<!-- wp:' . self::BLOCK ) . '%'
			)
		);
	}

	/**
	 * À l'activation : crée la page « Contact » en brouillon, si elle n'existe pas déjà.
	 *
	 * Elle est rangée sous la page « Aide » quand celle-ci existe (fil d'Ariane Accueil › Aide ›
	 * Contact). Une page existante n'est jamais modifiée.
	 */
	public static function activate() {
		$page_id = self::find_contact_page();

		if ( $page_id ) {
			set_transient( self::NOTICE_TRANSIENT, array( 'state' => 'exists', 'page' => $page_id ), HOUR_IN_SECONDS );
			return;
		}

		$existing = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => 'contact',
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		if ( $existing ) {
			set_transient( self::NOTICE_TRANSIENT, array( 'state' => 'taken', 'page' => (int) $existing[0] ), HOUR_IN_SECONDS );
			return;
		}

		$parent  = get_page_by_path( 'aide' );
		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'draft',
				'post_title'     => __( 'Contact', 'icademie-page-contact' ),
				'post_name'      => 'contact',
				'post_content'   => '<!-- wp:' . self::BLOCK . ' /-->',
				'post_parent'    => $parent instanceof WP_Post ? $parent->ID : 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( ! is_wp_error( $page_id ) ) {
			set_transient( self::NOTICE_TRANSIENT, array( 'state' => 'created', 'page' => (int) $page_id ), HOUR_IN_SECONDS );
		}
	}

	/**
	 * Avis affiché une fois après l'activation : page créée, ou bloc à ajouter.
	 */
	public static function activation_notice() {
		$notice = get_transient( self::NOTICE_TRANSIENT );

		if ( ! is_array( $notice ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		$edit_link     = get_edit_post_link( $notice['page'] );
		$settings_link = admin_url( 'options-general.php?page=' . Icademie_Page_Contact_Settings::PAGE );

		if ( 'created' === $notice['state'] ) {
			$message = __( 'La page « Contact » a été créée en brouillon avec le bloc « Page contact ». Renseignez les coordonnées dans Réglages › Page contact, puis publiez la page.', 'icademie-page-contact' );
		} elseif ( 'taken' === $notice['state'] ) {
			$message = __( 'Une page « contact » existe déjà : elle n\'a pas été modifiée. Pour afficher la nouvelle page Contact, ouvrez-la et remplacez son contenu par le bloc « Page contact » (catégorie Widgets).', 'icademie-page-contact' );
		} else {
			$message = __( 'Le bloc « Page contact » est déjà présent dans une page. Les coordonnées se règlent dans Réglages › Page contact.', 'icademie-page-contact' );
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<p>
				<?php if ( $edit_link ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Ouvrir la page', 'icademie-page-contact' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( $settings_link ); ?>"><?php esc_html_e( 'Réglages de la page Contact', 'icademie-page-contact' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Propose un paragraphe pour la politique de confidentialité (Réglages › Confidentialité).
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . __( 'Lorsque vous utilisez le formulaire de la page Contact, nous recueillons les informations que vous saisissez : profil, nom, prénom, adresse e-mail, objet et texte de votre demande, ainsi que, si vous les indiquez, votre téléphone, un numéro de réservation et une pièce jointe. Elles servent uniquement à traiter votre demande et à vous répondre.', 'icademie-page-contact' ) . '</p>'
			. '<p>' . __( 'Ces informations ne sont pas enregistrées dans la base de données du site : elles sont transmises par e-mail à notre équipe, et un accusé de réception est envoyé à l\'adresse que vous indiquez. Elles sont conservées dans notre messagerie le temps nécessaire au traitement de votre demande.', 'icademie-page-contact' ) . '</p>'
			. '<p>' . __( 'Le plan d\'accès est une carte OpenStreetMap : elle ne se charge, et ne contacte les serveurs d\'OpenStreetMap, que si vous cliquez sur « Afficher la carte ».', 'icademie-page-contact' ) . '</p>';

		wp_add_privacy_policy_content( __( 'SailingLoc – Page Contact', 'icademie-page-contact' ), wp_kses_post( $content ) );
	}

	/**
	 * Icône SVG (tracé au trait, couleur du texte), décorative.
	 *
	 * @param string $name Nom de l'icône.
	 * @param int    $size Taille en pixels.
	 * @return string SVG, ou vide si l'icône n'existe pas.
	 */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'search'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
			'calendar'   => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4m-7 9 2 2 4-4"/>',
			'card'       => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6.5 15h4"/>',
			'calendar-x' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4m-6 7 4 4m0-4-4 4"/>',
			'shield'     => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.8 12 2.3 2.3 4.2-4.6"/>',
			'boat'       => '<path d="M12 3v13M12 4.5 18.5 14H12zM10 7.5 5.5 14H10zM3 17h18l-2.4 3.5H5.4z"/>',
			'phone'      => '<path d="M5.2 3.5h3.1l1.6 4.1-2.2 1.4a11.5 11.5 0 0 0 5.3 5.3l1.4-2.2 4.1 1.6v3.1a2 2 0 0 1-2.1 2A16.2 16.2 0 0 1 3.2 5.6a2 2 0 0 1 2-2.1z"/>',
			'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
			'chat'       => '<path d="M4 5h16v11H9l-5 4z"/>',
			'pin'        => '<path d="M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'warning'    => '<path d="M12 3 22 21H2z"/><path d="M12 10v5M12 18v.5"/>',
			'lifebuoy'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6"/>',
			'paperclip'  => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
			'check'      => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8 5.7-5.8"/>',
			'alert'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.5"/>',
			'external'   => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'close'      => '<path d="M6 6l12 12M18 6 6 18"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="icademie-contact__icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $name ]
		);
	}

	/**
	 * Illustration de l'en-tête (voilier au port), affichée quand la page n'a pas d'image mise en
	 * avant. Aplats aux couleurs de la charte graphique, sans dégradé.
	 *
	 * @return string SVG décoratif.
	 */
	public static function illustration() {
		return '<svg class="icademie-contact__illustration" viewBox="0 0 480 240" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
			. '<rect width="480" height="240" fill="#dce5ea"/>'
			. '<path d="M0 150h480v26H0z" fill="#b9a98c"/>'
			. '<path d="M0 176h480v64H0z" fill="#3f6c87"/>'
			. '<path d="M232 40v124" stroke="#0f2233" stroke-width="4" stroke-linecap="round"/>'
			. '<path d="M239 50l64 106h-64z" fill="#f7f5f0"/>'
			. '<path d="M225 66l-48 90h48z" fill="#e4eef3"/>'
			. '<path d="M160 166h156l-20 26H182z" fill="#0f2233"/>'
			. '</svg>';
	}
}

Icademie_Page_Contact::init();

register_activation_hook( __FILE__, array( 'Icademie_Page_Contact', 'activate' ) );
