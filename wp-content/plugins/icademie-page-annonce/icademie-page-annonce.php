<?php
/**
 * Plugin Name:       Icademie – Page annonce
 * Description:       Fiche d'un bateau (page produit) intégrée d'après les zonings « Produit » ordinateur (1440 px), tablette (768 px) et smartphone (390 px) : fil d'Ariane, en-tête (type de bateau, port de départ, note, partage sur les réseaux sociaux), galerie photos, module de réservation (dates, passagers, prix, frais de service de 10 %, caution, total mis à jour selon le nombre de jours), caractéristiques, description, équipements, calendrier des disponibilités (jours des commandes déjà passées), conditions de location, carte du port de départ, avis et bateaux similaires. Les six conditions de location (caution, annulation, embarquement, débarquement, permis, carburant) se choisissent dans le formulaire du vendeur et sur la fiche du produit ; la position de chaque port, sur la fiche de sa catégorie. L'en-tête et le pied de page restent ceux du thème.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, icademie-page-accueil, icademie-page-categorie, icademie-dates-produit
 * Text Domain:       icademie-page-annonce
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-annonce-conditions.php';
require_once __DIR__ . '/includes/class-icademie-annonce-reservation.php';
require_once __DIR__ . '/includes/class-icademie-annonce-ports.php';
require_once __DIR__ . '/includes/class-icademie-annonce-view.php';

/**
 * Page annonce : bloc, modèle et affichage.
 *
 * Le bloc « Page annonce » (icademie/page-annonce) affiche tout le contenu de la fiche, du fil
 * d'Ariane aux bateaux similaires : zones 2 à 13 des zonings. L'en-tête et le pied de page (zones 1
 * et 14 ou 15, communs à tout le site) restent ceux du thème.
 *
 * Les fiches des bateaux utilisent automatiquement le modèle « Annonce de bateau SailingLoc »
 * (en-tête du thème, messages de la boutique, ce bloc, pied de page du thème). Un modèle créé dans
 * l'éditeur du site pour un bateau précis reste prioritaire. Désactiver l'extension rend le modèle
 * « Produit unique » de WooCommerce.
 */
final class Icademie_Page_Annonce {

	const VERSION = '1.0.0';

	/** Fichier principal de l'extension. */
	const FILE = __FILE__;

	/** Bloc qui affiche la fiche. */
	const BLOCK = 'icademie/page-annonce';

	/** Modèle : nom complet (« extension//slug ») et slug. */
	const TEMPLATE_NAME = 'icademie-page-annonce//icademie-page-annonce';
	const TEMPLATE_SLUG = 'icademie-page-annonce';

	/** Poignée commune de la feuille de styles et du script. */
	const HANDLE = 'icademie-page-annonce';

	/** Avis affiché une fois après l'activation. */
	const NOTICE_TRANSIENT = 'icademie_page_annonce_activation';

	/**
	 * Branche l'extension sur WordPress.
	 */
	public static function init() {
		// Après l'extension des pages catégorie (priorité 10), dont la feuille de styles est reprise.
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		// Après WooCommerce, qui ajoute ses modèles de produit.
		add_filter( 'single_template_hierarchy', array( __CLASS__, 'template_hierarchy' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'preload_font' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );

		// Cache de la fiche (WP Super Cache) vidé quand une commande change les jours réservés.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'clear_order_cache' ) );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'clear_order_cache' ) );

		Icademie_Annonce_Conditions::init();
		Icademie_Annonce_Reservation::init();
		Icademie_Annonce_Ports::init();
	}

	/**
	 * Enregistre les styles, le script, le bloc et le modèle.
	 */
	public static function register() {
		// Cartes des bateaux similaires : celles des pages catégorie.
		$dependencies = class_exists( 'Icademie_Page_Categorie' ) && wp_style_is( Icademie_Page_Categorie::HANDLE, 'registered' ) ? array( Icademie_Page_Categorie::HANDLE ) : array();

		wp_register_style( self::HANDLE, plugins_url( 'assets/page-annonce.css', __FILE__ ), $dependencies, self::VERSION );

		$font = self::font_url();

		// Police déjà déclarée par la feuille des pages catégorie, chargée avant celle-ci.
		if ( '' !== $font && ! $dependencies ) {
			wp_add_inline_style(
				self::HANDLE,
				'@font-face{font-family:"Space Grotesk";font-style:normal;font-weight:300 700;font-display:swap;src:url("' . esc_url( $font ) . '") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}'
			);
		}

		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/page-annonce.js', __FILE__ ),
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
				'title'               => __( 'Page annonce', 'icademie-page-annonce' ),
				'description'         => __( 'Fiche d\'un bateau, du fil d\'Ariane aux bateaux similaires : galerie, réservation, caractéristiques, équipements, disponibilités, conditions, carte du port et avis. Conditions de location : fiche du produit ; position du port : fiche de la catégorie.', 'icademie-page-annonce' ),
				'category'            => 'woocommerce',
				'icon'                => 'products',
				'keywords'            => array( 'annonce', 'bateau', 'produit', 'réservation' ),
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
				'title'       => __( 'Annonce de bateau SailingLoc', 'icademie-page-annonce' ),
				'description' => __( 'Fiches des bateaux : en-tête et pied de page du thème ; entre les deux, le bloc « Page annonce ».', 'icademie-page-annonce' ),
				'content'     => self::template_content(),
				'post_types'  => array( 'product' ),
			)
		);
	}

	/**
	 * Contenu du modèle « Annonce de bateau SailingLoc ».
	 *
	 * Mêmes en-tête et pied de page que les autres pages du thème ; entre les deux, les messages de
	 * la boutique (réservation refusée…) et le bloc, sans marge ni largeur imposée.
	 *
	 * @return string
	 */
	private static function template_content() {
		return '<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main","metadata":{"name":"Contenu"},"style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><!-- wp:woocommerce/store-notices /-->

<!-- wp:icademie/page-annonce /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->';
	}

	/**
	 * Fait utiliser le modèle de l'extension aux fiches des bateaux.
	 *
	 * Le modèle passe avant « single-product » (WooCommerce), mais après un modèle propre à un bateau
	 * (« single-product-{slug} »).
	 *
	 * @param string[] $templates Modèles candidats (« ….php » sur le site, sans extension dans l'éditeur).
	 * @return string[]
	 */
	public static function template_hierarchy( $templates ) {
		foreach ( array( 'single-product', 'single-product.php' ) as $candidate ) {
			$index = array_search( $candidate, $templates, true );

			if ( false !== $index ) {
				$suffix = '.php' === substr( $candidate, -4 ) ? '.php' : '';

				if ( ! in_array( self::TEMPLATE_SLUG . $suffix, $templates, true ) ) {
					array_splice( $templates, $index, 0, self::TEMPLATE_SLUG . $suffix );
				}

				break;
			}
		}

		return $templates;
	}

	/**
	 * Police des titres, hébergée par l'extension de la page d'accueil.
	 *
	 * @return string Adresse, ou vide sans cette extension.
	 */
	private static function font_url() {
		if ( ! class_exists( 'Icademie_Page_Accueil' ) ) {
			return '';
		}

		return plugins_url( 'assets/fonts/space-grotesk-latin-wght-normal.woff2', Icademie_Page_Accueil::FILE );
	}

	/**
	 * Précharge la police des titres sur les fiches des bateaux (titre visible dès l'arrivée).
	 */
	public static function preload_font() {
		$font = self::font_url();

		if ( '' === $font || ! is_singular( 'product' ) ) {
			return;
		}

		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $font ) );
	}

	/**
	 * Affiche le bloc « Page annonce ».
	 *
	 * @return string
	 */
	public static function render_block() {
		$data = Icademie_Annonce_View::data();

		if ( null === $data ) {
			return '';
		}

		// Données structurées du produit (prix, note) : WooCommerce les écrit en pied de page.
		if ( ! $data['preview'] && function_exists( 'WC' ) && isset( WC()->structured_data ) ) {
			$product = wc_get_product( $data['id'] );

			if ( $product ) {
				WC()->structured_data->generate_product_data( $product );
			}
		}

		$icon = array( __CLASS__, 'icon' );

		ob_start();
		include __DIR__ . '/templates/page-annonce.php';
		$html = ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>%3$s',
			get_block_wrapper_attributes( array( 'class' => 'icademie-annonce' ) ),
			$html,
			self::booking_bar( $data )
		);
	}

	/**
	 * Smartphone : barre fixe en bas de l'écran (prix par jour, « Réserver »).
	 *
	 * Hors du bloc : sa mise en page (requêtes de conteneur) retiendrait un élément fixe. Affichée
	 * par le script, qui ouvre le module de réservation en plein écran.
	 *
	 * @param array $data Données de la fiche.
	 * @return string
	 */
	private static function booking_bar( $data ) {
		if ( $data['preview'] || ! $data['booking']['available'] ) {
			return '';
		}

		ob_start();
		?>
		<div class="icademie-annonce-bar" data-booking-bar hidden>
			<p class="icademie-annonce-bar__price">
				<span class="icademie-annonce-bar__amount"><?php echo wp_kses_post( $data['booking']['price_html'] ); ?></span>
				<?php if ( $data['booking']['per_day'] ) : ?>
					<span class="icademie-annonce-bar__unit"><?php esc_html_e( '/ jour', 'icademie-page-annonce' ); ?></span>
				<?php endif; ?>
				<span class="icademie-annonce-bar__dates" data-bar-dates></span>
			</p>
			<button type="button" class="icademie-annonce-bar__button" aria-haspopup="dialog" data-booking-open><?php esc_html_e( 'Réserver', 'icademie-page-annonce' ); ?></button>
		</div>
		<?php
		return (string) ob_get_clean();
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
	 * Vide le cache des fiches des bateaux d'une commande (calendrier des disponibilités).
	 *
	 * Avec WP Super Cache (extension du site). Une autre extension de cache peut se brancher sur
	 * l'action « icademie_page_annonce_vider_cache ».
	 *
	 * @param int $order_id Commande.
	 */
	public static function clear_order_cache( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item instanceof WC_Order_Item_Product ? (int) $item->get_product_id() : 0;

			if ( ! $product_id ) {
				continue;
			}

			if ( function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $product_id );
			}

			/**
			 * Les jours réservés d'un bateau ont changé.
			 *
			 * @param int $product_id Bateau.
			 */
			do_action( 'icademie_page_annonce_vider_cache', $product_id );
		}
	}

	/**
	 * Active l'extension : avis de bienvenue avec ce qu'il reste à faire.
	 */
	public static function activate() {
		set_transient( self::NOTICE_TRANSIENT, 1, HOUR_IN_SECONDS );
		update_option( Icademie_Annonce_Reservation::VERSION_OPTION, (string) microtime( true ), false );

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Désactive l'extension : le modèle de WooCommerce revient (cache vidé).
	 */
	public static function deactivate() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Avis affiché une fois, après l'activation.
	 */
	public static function activation_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%1$s</p><p>%2$s</p></div>',
			esc_html__( 'Les fiches des bateaux utilisent maintenant le modèle « Annonce de bateau SailingLoc ». L\'en-tête et le pied de page restent ceux du thème.', 'icademie-page-annonce' ),
			wp_kses(
				sprintf(
					/* translators: %s: adresse de la liste des catégories de produits. */
					__( 'À faire : la position (latitude, longitude) de chaque port, sur la fiche de sa catégorie dans <a href="%s">Produits › Catégories</a>, et les conditions de location de chaque bateau (onglet « Conditions de location » de la fiche produit, ou formulaire du vendeur).', 'icademie-page-annonce' ),
					esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) )
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

		$content = '<p>' . __( 'Sur la fiche d\'un bateau, la carte du port de départ est fournie par OpenStreetMap : elle n\'est chargée, et OpenStreetMap n\'est contacté, que si vous cliquez sur « Afficher la carte ». Les boutons de partage ouvrent le site du réseau choisi (Facebook, X, WhatsApp, LinkedIn, Pinterest) seulement quand vous cliquez dessus ; aucun de leurs scripts n\'est chargé sur nos pages. Les dates et le nombre de passagers choisis pour une réservation sont gardés dans votre panier, puis dans votre commande.', 'icademie-page-annonce' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Icademie – Page annonce', 'icademie-page-annonce' ), wp_kses_post( $content ) );
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
			'share'         => '<path d="M12 3v13M7 8l5-5 5 5M5 14v6h14v-6"/>',
			'chevron-left'  => '<path d="M15 6l-6 6 6 6"/>',
			'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
			'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'close'         => '<path d="M6 6l12 12M18 6 6 18"/>',
			'star'          => '<path d="m12 3.2 2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6-4.4-4.2 6-.8z" fill="currentColor" stroke="none"/>',
			'boat'          => '<path d="M12 3v13M12 4.5 18.5 14H12zM10 7.5 5.5 14H10zM3 17h18l-2.4 3.5H5.4z"/>',
			'type'          => '<path d="M12 3v13M12 4.5 18.5 14H12zM10 7.5 5.5 14H10zM3 17h18l-2.4 3.5H5.4z"/>',
			'calendar'      => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
			'ruler'         => '<path d="M3 16 16 3l5 5L8 21z"/><path d="m7.5 11.5 2 2M10.5 8.5l2 2M13.5 5.5l2 2"/>',
			'users'         => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3c2 .8 3.5 2.9 3.5 5.7"/>',
			'door'          => '<path d="M5 21V4.5A1.5 1.5 0 0 1 6.5 3h11A1.5 1.5 0 0 1 19 4.5V21M3 21h18"/><circle cx="15" cy="12" r="1" fill="currentColor"/>',
			'bed'           => '<path d="M3 19V6M3 15h18v4M21 15v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="11" r="2"/>',
			'engine'        => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'licence'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.5 1.8-2.3 3.2-2.3s2.6.8 3.2 2.3M14.5 10h4M14.5 13.5h3"/>',
			'check'         => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'compass'       => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
			'snow'          => '<path d="M12 2v20M4.2 7l15.6 10M4.2 17 19.8 7M9 4l3 2 3-2M9 20l3-2 3 2"/>',
			'drop'          => '<path d="M12 3s6.5 7 6.5 11.5a6.5 6.5 0 0 1-13 0C5.5 10 12 3 12 3z"/>',
			'lifebuoy'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6M14.8 14.8l3.6 3.6M18.4 5.6l-3.6 3.6M9.2 14.8l-3.6 3.6"/>',
			'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'plug'          => '<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
			'fish'          => '<path d="M2.5 12c3-4.5 8-6 12.5-3.5L21 5v14l-6-3.5C10.5 18 5.5 16.5 2.5 12z"/><circle cx="8" cy="11" r="1" fill="currentColor"/>',
			'wheel'         => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.5"/><path d="M12 2v7.5M12 14.5V22M2 12h7.5M14.5 12H22"/>',
			'music'         => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
			'wifi'          => '<path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.8 16a4.7 4.7 0 0 1 6.4 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor"/>',
			'anchor'        => '<circle cx="12" cy="5" r="2.5"/><path d="M12 7.5V21M8 11h8M4 13a8 8 0 0 0 16 0"/>',
			'flame'         => '<path d="M12 22a7 7 0 0 0 7-7c0-4-3-6.5-4-10-2 1.5-3 3.5-3 5.5-1-1-1.5-2-1.5-3.5C8 9 5 11.5 5 15a7 7 0 0 0 7 7z"/>',
			'pin'           => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'external'      => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'link'          => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
			'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
			'expand'        => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="icademie-annonce__icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $name ]
		);
	}

	/**
	 * Icône d'un équipement, d'après son nom.
	 *
	 * @param string $name Équipement (« Réfrigérateur », « GPS / traceur »…).
	 * @return string Nom de l'icône.
	 */
	public static function equipment_icon( $name ) {
		$name = remove_accents( function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ) );

		$keywords = array(
			'compass'  => array( 'gps', 'traceur', 'sondeur', 'boussole', 'compas', 'radar', 'carte', 'vhf', 'radio' ),
			'snow'     => array( 'frigo', 'refrigerateur', 'glaciere', 'congelateur', 'climatisation' ),
			'drop'     => array( 'douche', 'eau', 'dessalinisateur', 'toilette', 'wc', 'evier' ),
			'lifebuoy' => array( 'gilet', 'bouee', 'securite', 'radeau', 'survie', 'extincteur', 'trousse' ),
			'sun'      => array( 'bimini', 'taud', 'soleil', 'parasol', 'solarium', 'bain de soleil' ),
			'plug'     => array( 'usb', 'prise', '220', '12v', 'electrique', 'panneau', 'chargeur', 'onduleur', 'generateur' ),
			'fish'     => array( 'peche', 'canne' ),
			'wheel'    => array( 'pilote', 'barre', 'propulseur', 'guindeau', 'winch' ),
			'music'    => array( 'audio', 'hifi', 'enceinte', 'bluetooth', 'musique', 'chaine' ),
			'wifi'     => array( 'wifi', 'wi-fi', 'internet', '4g', '5g' ),
			'boat'     => array( 'annexe', 'paddle', 'kayak', 'voile', 'spi', 'gennaker' ),
			'anchor'   => array( 'ancre', 'mouillage', 'echelle', 'plateforme', 'masque', 'tuba', 'plongee', 'ski' ),
			'flame'    => array( 'four', 'plancha', 'barbecue', 'cuisine', 'gaz', 'rechaud', 'cafetiere', 'plaque' ),
		);

		// Début de mot : « eau » ne reconnaît pas « panneaux ».
		foreach ( $keywords as $icon => $words ) {
			foreach ( $words as $word ) {
				if ( preg_match( '/(?<![a-z0-9])' . preg_quote( $word, '/' ) . '/', $name ) ) {
					return $icon;
				}
			}
		}

		return 'check';
	}
}

Icademie_Page_Annonce::init();

register_activation_hook( __FILE__, array( 'Icademie_Page_Annonce', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Icademie_Page_Annonce', 'deactivate' ) );
