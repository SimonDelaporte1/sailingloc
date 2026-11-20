<?php
/**
 * Plugin Name:       Icademie – Page d'accueil
 * Description:       Page d'accueil intégrée d'après les zonings « Accueil » ordinateur (1440 px), tablette (768 px) et smartphone (390 px) : bandeau avec moteur de recherche (destination = catégorie ; type de bateau et passagers = attributs ; dates reprises sur la fiche du bateau), réassurance, catégories de bateaux (attribut « Type de bateau »), bateaux à la une, destinations populaires (catégories mises en avant), comment ça marche, devenir loueur, avis des membres et newsletter (formulaire HubSpot de la page Newsletter). L'en-tête et le pied de page restent ceux du thème. Textes, images et liens : Réglages › Page d'accueil.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       icademie-page-accueil
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-page-accueil-settings.php';
require_once __DIR__ . '/includes/class-icademie-page-accueil-search.php';
require_once __DIR__ . '/includes/class-icademie-page-accueil-terms.php';
require_once __DIR__ . '/includes/class-icademie-page-accueil-newsletter.php';
require_once __DIR__ . '/includes/class-icademie-page-accueil-zones.php';

/**
 * Page d'accueil : bloc, modèle et affichage.
 *
 * Le bloc « Page d'accueil » (icademie/page-accueil) affiche tout le contenu de la page, du
 * bandeau de recherche à la newsletter : zones 2 à 10 des zonings. L'en-tête et le pied de page
 * (zones 1 et 11, communs à tout le site) restent ceux du thème.
 *
 * La page d'accueil du site utilise automatiquement le modèle « Page d'accueil SailingLoc »
 * (en-tête du thème, ce bloc, pied de page du thème), que l'accueil affiche les derniers articles
 * ou une page statique. Désactiver l'extension rend l'accueil précédent.
 */
final class Icademie_Page_Accueil {

	const VERSION = '1.0.0';

	/** Fichier principal de l'extension. */
	const FILE = __FILE__;

	/** Bloc qui affiche la page. */
	const BLOCK = 'icademie/page-accueil';

	/** Modèle : nom complet (« extension//slug ») et slug. */
	const TEMPLATE_NAME = 'icademie-page-accueil//icademie-page-accueil';
	const TEMPLATE_SLUG = 'icademie-page-accueil';

	/** Poignée commune de la feuille de styles et du script. */
	const HANDLE = 'icademie-page-accueil';

	/** Avis affiché une fois après l'activation. */
	const NOTICE_TRANSIENT = 'icademie_page_accueil_activation';

	/**
	 * Branche l'extension sur WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'frontpage_template_hierarchy', array( __CLASS__, 'template_hierarchy' ) );
		add_action( 'wp_head', array( __CLASS__, 'preload_font' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );

		// Cache de la page d'accueil (WP Super Cache) vidé quand son contenu change.
		add_action( 'add_option_' . Icademie_Page_Accueil_Settings::OPTION, array( __CLASS__, 'clear_page_cache' ) );
		add_action( 'update_option_' . Icademie_Page_Accueil_Settings::OPTION, array( __CLASS__, 'clear_page_cache' ) );
		foreach ( array( 'product_cat', 'pa_' . Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE ) as $taxonomy ) {
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'clear_page_cache' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'clear_page_cache' ) );
			add_action( 'delete_' . $taxonomy, array( __CLASS__, 'clear_page_cache' ) );
		}
		foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'clear_cache_on_term_meta' ), 10, 3 );
		}
		add_action( 'wp_update_comment_count', array( __CLASS__, 'clear_cache_on_review' ) );

		Icademie_Page_Accueil_Settings::init();
		Icademie_Page_Accueil_Search::init();
		Icademie_Page_Accueil_Terms::init();
	}

	/**
	 * Enregistre les styles, le script, le bloc et le modèle.
	 */
	public static function register() {
		wp_register_style( self::HANDLE, plugins_url( 'assets/page-accueil.css', __FILE__ ), array(), self::VERSION );
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/page-accueil.js', __FILE__ ),
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
				'title'               => __( 'Page d\'accueil', 'icademie-page-accueil' ),
				'description'         => __( 'Tout le contenu de la page d\'accueil, du bandeau de recherche à la newsletter. Textes, images et liens : Réglages › Page d\'accueil.', 'icademie-page-accueil' ),
				'category'            => 'widgets',
				'icon'                => 'admin-home',
				'keywords'            => array( 'accueil', 'recherche', 'bateaux', 'newsletter' ),
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
				'render_callback'     => array( __CLASS__, 'render_block' ),
				'style_handles'       => array( self::HANDLE ),
				'view_script_handles' => array( self::HANDLE ),
			)
		);

		register_block_template(
			self::TEMPLATE_NAME,
			array(
				'title'       => __( 'Page d\'accueil SailingLoc', 'icademie-page-accueil' ),
				'description' => __( 'Page d\'accueil du site : en-tête et pied de page du thème ; entre les deux, le bloc « Page d\'accueil ».', 'icademie-page-accueil' ),
				'content'     => self::template_content(),
			)
		);
	}

	/**
	 * Contenu du modèle « Page d'accueil SailingLoc ».
	 *
	 * Mêmes en-tête et pied de page que les autres pages du thème ; entre les deux, le bloc, sans
	 * marge ni largeur imposée : il gère sa propre mise en page.
	 *
	 * @return string
	 */
	private static function template_content() {
		return '<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main","metadata":{"name":"Contenu"},"style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><!-- wp:icademie/page-accueil /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->';
	}

	/**
	 * Fait utiliser le modèle de l'extension à la page d'accueil.
	 *
	 * Sert aussi à l'éditeur du site : il affiche ce modèle pour la page d'accueil. Les pages 2, 3…
	 * de la liste des articles (accueil « derniers articles ») gardent le modèle du thème.
	 *
	 * @param string[] $templates Modèles candidats, du plus prioritaire au moins prioritaire
	 *                            (« front-page.php » sur le site, « front-page » dans l'éditeur).
	 * @return string[]
	 */
	public static function template_hierarchy( $templates ) {
		if ( is_paged() ) {
			return $templates;
		}

		$suffix = ( isset( $templates[0] ) && '.php' === substr( $templates[0], -4 ) ) ? '.php' : '';

		array_unshift( $templates, self::TEMPLATE_SLUG . $suffix );

		return $templates;
	}

	/**
	 * Précharge la police des titres sur l'accueil (titre principal visible dès l'arrivée).
	 */
	public static function preload_font() {
		if ( ! is_front_page() || is_paged() ) {
			return;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( plugins_url( 'assets/fonts/space-grotesk-latin-wght-normal.woff2', __FILE__ ) )
		);
	}

	/**
	 * Affiche le bloc « Page d'accueil ».
	 *
	 * @return string
	 */
	public static function render_block() {
		$data          = Icademie_Page_Accueil_Zones::all();
		$data['setup'] = current_user_can( 'manage_options' ) ? Icademie_Page_Accueil_Zones::missing( $data ) : array();

		ob_start();
		include __DIR__ . '/templates/page-accueil.php';
		$html = ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'icademie-accueil' ) ),
			$html
		);
	}

	/**
	 * Vide le cache de la page d'accueil, pour que les visiteurs voient tout de suite le changement.
	 *
	 * Avec WP Super Cache (extension du site), seule l'adresse de l'accueil est vidée. Une autre
	 * extension de cache peut se brancher sur l'action « icademie_page_accueil_vider_cache ».
	 */
	public static function clear_page_cache() {
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( trailingslashit( home_url() ) );
		}

		do_action( 'icademie_page_accueil_vider_cache' );
	}

	/**
	 * Vide le cache de l'accueil quand une destination est cochée ou une image de type changée.
	 *
	 * @param int|int[] $meta_ids  Métadonnées modifiées.
	 * @param int       $object_id Catégorie ou valeur d'attribut.
	 * @param string    $meta_key  Clé de la métadonnée.
	 */
	public static function clear_cache_on_term_meta( $meta_ids, $object_id, $meta_key ) {
		if ( in_array( $meta_key, array( Icademie_Page_Accueil_Terms::FEATURED_META, Icademie_Page_Accueil_Terms::IMAGE_META, 'thumbnail_id' ), true ) ) {
			self::clear_page_cache();
		}
	}

	/**
	 * Vide le cache de l'accueil quand un avis sur un bateau est publié, retiré ou supprimé.
	 *
	 * @param int $post_id Contenu commenté.
	 */
	public static function clear_cache_on_review( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::clear_page_cache();
		}
	}

	/**
	 * Le bloc est-il affiché dans l'aperçu de l'éditeur (rendu par l'API REST) ?
	 *
	 * @return bool
	 */
	public static function is_editor_preview() {
		return function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Active l'extension : avis de bienvenue avec les réglages à faire, et cache de l'accueil vidé
	 * (sinon les visiteurs verraient l'ancienne page d'accueil jusqu'à l'expiration du cache).
	 */
	public static function activate() {
		set_transient( self::NOTICE_TRANSIENT, 1, HOUR_IN_SECONDS );
		self::clear_page_cache();
	}

	/**
	 * Désactive l'extension : cache de l'accueil vidé, l'accueil du thème revient tout de suite.
	 */
	public static function deactivate() {
		self::clear_page_cache();
	}

	/**
	 * Avis affiché une fois, après l'activation.
	 */
	public static function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%1$s</p><p>%2$s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: adresse de la page d'accueil. */
					__( 'La <a href="%s">page d\'accueil</a> utilise maintenant le modèle « Page d\'accueil SailingLoc ». L\'en-tête et le pied de page restent ceux du thème.', 'icademie-page-accueil' ),
					esc_url( home_url( '/' ) )
				),
				array( 'a' => array( 'href' => true ) )
			),
			wp_kses(
				sprintf(
					/* translators: 1: adresse des réglages, 2: adresse des catégories de produits, 3: adresse des produits. */
					__( 'À faire : choisir la photo du bandeau et remplir les adresses des liens dans <a href="%1$s">Réglages › Page d\'accueil</a>, cocher les destinations populaires dans <a href="%2$s">Produits › Catégories</a> et mettre des bateaux à la une (étoile) dans <a href="%3$s">Produits</a>.', 'icademie-page-accueil' ),
					esc_url( Icademie_Page_Accueil_Settings::url() ),
					esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ),
					esc_url( admin_url( 'edit.php?post_type=product' ) )
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	/**
	 * Texte suggéré pour la politique de confidentialité (Réglages › Confidentialité).
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . __( 'La page d\'accueil affiche le formulaire d\'inscription à la newsletter de la page Newsletter. Ce formulaire est fourni par HubSpot : l\'adresse e-mail saisie est envoyée directement à HubSpot, notre outil d\'envoi de la newsletter, et traitée selon sa politique de confidentialité. Vous pouvez vous désinscrire à tout moment grâce au lien présent dans chaque lettre.', 'icademie-page-accueil' ) . '</p>'
			. '<p>' . __( 'Les dates saisies dans le moteur de recherche de la page d\'accueil sont gardées dans votre navigateur (stockage de session, sans cookie) pour pré-remplir la fiche du bateau. Elles ne nous sont pas envoyées et sont effacées à la fermeture de l\'onglet.', 'icademie-page-accueil' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Icademie – Page d\'accueil', 'icademie-page-accueil' ), wp_kses_post( $content ) );
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
			'search'          => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
			'arrow-right'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'chevron-left'    => '<path d="M15 6l-6 6 6 6"/>',
			'chevron-right'   => '<path d="M9 6l6 6-6 6"/>',
			'lock'            => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3M12 14.5v2.5"/>',
			'shield'          => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6z"/><path d="m8.8 12 2.3 2.3 4.2-4.6"/>',
			'user-check'      => '<circle cx="9.5" cy="8" r="3.5"/><path d="M3 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M15.5 11l2 2 4-4"/>',
			'lifebuoy'        => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6"/>',
			'check'           => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8 5.7-5.8"/>',
			'star'            => '<path d="m12 3.2 2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.2 6-.8z" fill="currentColor" stroke="none"/>',
			'mail'            => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
			'type-voile'      => '<path d="M12 3v13M12 4.5 18.5 14H12zM10 7.5 5.5 14H10zM3 17h18l-2.4 3.5H5.4z"/>',
			'type-moteur'     => '<path d="M2.5 15h19l-3 4.5H6.5z"/><path d="M6 15l2.2-4.5H14l2.5 4.5M9.5 10.5V7.5h3.5"/>',
			'type-electrique' => '<path d="M2.5 16h19l-3 4H6.5z"/><path d="M13.5 3 9 10h4l-1.5 4"/>',
			'type-hybride'    => '<path d="M2.5 16h19l-3 4H6.5z"/><path d="M7.5 13C7.5 7.5 11 4 18 4c0 6.5-3.5 9.5-9 9.5"/><path d="m8 13 5.5-5"/>',
			'type-rame'       => '<path d="M4 20 16 8M16.5 7.5l1.5-4.5 3 3-4.5 1.5M20 20 8 8M7.5 7.5 6 3 3 6l4.5 1.5"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="icademie-accueil__icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $name ]
		);
	}

	/**
	 * Illustration du bandeau (mer et voilier), affichée tant qu'aucune photo n'est choisie.
	 *
	 * Le bateau est à gauche et en bas, pour laisser le centre aux textes et au moteur de recherche.
	 *
	 * @return string SVG décoratif.
	 */
	public static function hero_illustration() {
		return '<svg class="icademie-accueil__illustration" viewBox="0 0 1440 560" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
			. '<defs>'
			. '<linearGradient id="icademie-accueil-ciel" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#173f8a"/><stop offset=".62" stop-color="#3f7fe0"/><stop offset="1" stop-color="#8db4ee"/></linearGradient>'
			. '<linearGradient id="icademie-accueil-mer" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1d5fd1"/><stop offset="1" stop-color="#0f2f66"/></linearGradient>'
			. '</defs>'
			. '<rect width="1440" height="560" fill="url(#icademie-accueil-ciel)"/>'
			. '<circle cx="1180" cy="150" r="58" fill="#ffd9a8" opacity=".9"/>'
			. '<path d="M0 360h1440v200H0z" fill="url(#icademie-accueil-mer)"/>'
			. '<path d="M0 392c60-12 120 12 180 0s120-12 180 0 120 12 180 0 120-12 180 0 120 12 180 0 120-12 180 0 120 12 180 0 120-12 180 0" fill="none" stroke="#8db4ee" stroke-width="3" opacity=".5"/>'
			. '<path d="M0 452c60-12 120 12 180 0s120-12 180 0 120 12 180 0 120-12 180 0 120 12 180 0 120-12 180 0 120 12 180 0 120-12 180 0" fill="none" stroke="#8db4ee" stroke-width="3" opacity=".35"/>'
			. '<path d="M196 202v166" stroke="#0f2f66" stroke-width="5" stroke-linecap="round"/>'
			. '<path d="M204 214l84 142h-84z" fill="#f4f8fe"/>'
			. '<path d="M188 236l-62 120h62z" fill="#cfdffa"/>'
			. '<path d="M104 372h196l-26 32H130z" fill="#0f2f66"/>'
			. '</svg>';
	}

	/**
	 * Illustration de l'encart « Devenir loueur » (bateau à quai), tant qu'aucune image n'est choisie.
	 *
	 * @return string SVG décoratif.
	 */
	public static function owner_illustration() {
		return '<svg class="icademie-accueil__illustration" viewBox="0 0 480 260" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
			. '<defs><linearGradient id="icademie-accueil-ciel-clair" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#cfdffa"/><stop offset="1" stop-color="#f4f8fe"/></linearGradient></defs>'
			. '<rect width="480" height="260" fill="url(#icademie-accueil-ciel-clair)"/>'
			. '<circle cx="390" cy="66" r="28" fill="#ffd9a8"/>'
			. '<path d="M0 196h480v64H0z" fill="#1d5fd1"/>'
			. '<path d="M300 150h180v18H300z" fill="#8a6a4a"/>'
			. '<path d="M318 168v60M362 168v60M406 168v60M450 168v60" stroke="#6b5038" stroke-width="8"/>'
			. '<path d="M182 62v124" stroke="#243040" stroke-width="4" stroke-linecap="round"/>'
			. '<path d="M189 72l62 102h-62z" fill="#fff" stroke="#243040" stroke-width="3" stroke-linejoin="round"/>'
			. '<path d="M175 88l-46 86h46z" fill="#1d5fd1" stroke="#173f8a" stroke-width="3" stroke-linejoin="round"/>'
			. '<path d="M112 186h152l-20 26H132z" fill="#243040"/>'
			. '<path d="M264 182c10-8 26-8 36 0" fill="none" stroke="#243040" stroke-width="3" stroke-linecap="round"/>'
			. '<path d="M0 222c40-8 80 8 120 0s80-8 120 0 80 8 120 0 80-8 120 0v38H0z" fill="#173f8a"/>'
			. '</svg>';
	}
}

Icademie_Page_Accueil::init();

register_activation_hook( __FILE__, array( 'Icademie_Page_Accueil', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Icademie_Page_Accueil', 'deactivate' ) );
