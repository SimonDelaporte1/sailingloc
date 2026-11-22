<?php
/**
 * Modèles de page de l'extension et leur place dans la hiérarchie des modèles.
 *
 * Chaque modèle reprend l'en-tête et le pied de page du site (parties « header » et « footer »,
 * remplacées par l'extension « En-tête et pied de page ») ; entre les deux, les blocs de
 * l'extension et le contenu de la page. Un modèle propre à une page (choisi dans l'éditeur, ou
 * « page-{slug} » du thème) reste prioritaire.
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modèles.
 */
final class Icademie_Habillage_Templates {

	/** Espace de noms des modèles (« icademie-habillage//… »). */
	const THEME = 'icademie-habillage';

	/**
	 * Modèles : slug → titre, description.
	 *
	 * @return array[]
	 */
	public static function templates() {
		return array(
			'icademie-habillage-page'         => array( __( 'Page de contenu SailingLoc', 'icademie-habillage' ), __( 'Pages du site (à propos, compte client, propriétaires…) : fil d\'Ariane, titre et contenu habillés d\'après la charte.', 'icademie-habillage' ) ),
			'icademie-habillage-article'      => array( __( 'Article SailingLoc', 'icademie-habillage' ), __( 'Articles (aide, blog) : fil d\'Ariane, catégorie, titre, date et temps de lecture, image, contenu, articles liés et commentaires.', 'icademie-habillage' ) ),
			'icademie-habillage-articles'     => array( __( 'Liste d\'articles SailingLoc', 'icademie-habillage' ), __( 'Catégories, étiquettes, auteurs, dates et recherche dans le site : les articles en cartes, avec la pagination.', 'icademie-habillage' ) ),
			'icademie-habillage-404'          => array( __( 'Page introuvable SailingLoc', 'icademie-habillage' ), __( 'Adresse inconnue : message, recherche et liens utiles.', 'icademie-habillage' ) ),
			'icademie-habillage-panier'       => array( __( 'Panier SailingLoc', 'icademie-habillage' ), __( 'Panier : étapes de la réservation et bloc Panier de WooCommerce habillé.', 'icademie-habillage' ) ),
			'icademie-habillage-commande'     => array( __( 'Validation de la commande SailingLoc', 'icademie-habillage' ), __( 'Validation de la commande : en-tête réduit, étapes de la réservation et bloc Validation de WooCommerce habillé.', 'icademie-habillage' ) ),
			'icademie-habillage-confirmation' => array( __( 'Confirmation de commande SailingLoc', 'icademie-habillage' ), __( 'Commande reçue : étapes de la réservation, récapitulatif, détails de la location, adresse et assistance.', 'icademie-habillage' ) ),
		);
	}

	/**
	 * Branche les modèles.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'page_template_hierarchy', array( __CLASS__, 'page_hierarchy' ), 20 );
		add_filter( 'single_template_hierarchy', array( __CLASS__, 'single_hierarchy' ), 20 );
		add_filter( 'home_template_hierarchy', array( __CLASS__, 'list_hierarchy' ), 20 );
		add_filter( 'archive_template_hierarchy', array( __CLASS__, 'archive_hierarchy' ), 20 );
		add_filter( 'search_template_hierarchy', array( __CLASS__, 'search_hierarchy' ), 20 );
		add_filter( '404_template_hierarchy', array( __CLASS__, 'not_found_hierarchy' ), 20 );

		foreach ( array( 'category', 'tag', 'author', 'date' ) as $type ) {
			add_filter( $type . '_template_hierarchy', array( __CLASS__, 'list_hierarchy' ), 20 );
		}
	}

	/**
	 * Enregistre les modèles (éditeur du site : Apparence › Éditeur › Modèles).
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		foreach ( self::templates() as $slug => $template ) {
			register_block_template(
				self::THEME . '//' . $slug,
				array(
					'title'       => $template[0],
					'description' => $template[1],
					'content'     => self::content( $slug ),
				)
			);
		}
	}

	/**
	 * Contenu (blocs) d'un modèle.
	 *
	 * @param string $slug Modèle.
	 * @return string
	 */
	private static function content( $slug ) {
		$header  = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
		$footer  = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		$notices = '<!-- wp:woocommerce/store-notices /-->';
		$content = '<!-- wp:post-content {"className":"icademie-habillage__contenu","layout":{"type":"constrained"}} /-->';
		$main    = static function ( $modifier, $inner ) {
			return '<!-- wp:group {"tagName":"main","className":"icademie-habillage icademie-habillage--' . $modifier . '","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->'
				. "\n" . '<main class="wp-block-group icademie-habillage icademie-habillage--' . $modifier . '" style="margin-top:0;margin-bottom:0">' . "\n" . $inner . "\n</main>\n<!-- /wp:group -->";
		};

		switch ( $slug ) {
			case 'icademie-habillage-article':
				return $header . "\n\n" . $main(
					'article',
					'<!-- wp:icademie/entete-page {"type":"article"} /-->' . "\n\n" . $content . "\n\n" . self::comments() . "\n\n" . '<!-- wp:icademie/articles-lies /-->'
				) . "\n\n" . $footer;

			case 'icademie-habillage-articles':
				return $header . "\n\n" . $main( 'articles', '<!-- wp:icademie/entete-page {"type":"liste"} /-->' . "\n\n" . '<!-- wp:icademie/articles /-->' ) . "\n\n" . $footer;

			case 'icademie-habillage-404':
				return $header . "\n\n" . $main( 'introuvable', '<!-- wp:icademie/page-introuvable /-->' ) . "\n\n" . $footer;

			case 'icademie-habillage-panier':
				return $header . "\n\n" . '<!-- wp:woocommerce/page-content-wrapper {"page":"cart"} -->' . "\n" . $main( 'tunnel icademie-habillage--panier', '<!-- wp:icademie/entete-page {"type":"tunnel","etape":1} /-->' . "\n" . $notices . "\n" . $content ) . "\n" . '<!-- /wp:woocommerce/page-content-wrapper -->' . "\n\n" . $footer;

			case 'icademie-habillage-commande':
				// Comme le modèle de WooCommerce : en-tête réduit, sans pied de page (moins de distractions).
				return '<!-- wp:template-part {"slug":"checkout-header","tagName":"header"} /-->' . "\n\n" . '<!-- wp:woocommerce/page-content-wrapper {"page":"checkout"} -->' . "\n" . $main( 'tunnel icademie-habillage--commande', '<!-- wp:icademie/entete-page {"type":"tunnel","etape":2} /-->' . "\n" . $notices . "\n" . $content ) . "\n" . '<!-- /wp:woocommerce/page-content-wrapper -->';

			case 'icademie-habillage-confirmation':
				return $header . "\n\n" . $main( 'tunnel icademie-habillage--confirmation', self::confirmation() ) . "\n\n" . $footer;
		}

		return $header . "\n\n" . $main( 'page', '<!-- wp:icademie/entete-page {"type":"page"} /-->' . "\n" . $notices . "\n" . $content ) . "\n\n" . $footer;
	}

	/**
	 * Commentaires d'un article (s'ils sont ouverts ou s'il y en a).
	 *
	 * @return string
	 */
	private static function comments() {
		return '<!-- wp:comments {"className":"icademie-habillage__commentaires"} -->
<div class="wp-block-comments icademie-habillage__commentaires"><!-- wp:comments-title {"level":2} /-->

<!-- wp:comment-template -->
<!-- wp:group {"className":"icademie-habillage__commentaire","layout":{"type":"default"}} -->
<div class="wp-block-group icademie-habillage__commentaire"><!-- wp:group {"className":"icademie-habillage__commentaire-tete","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group icademie-habillage__commentaire-tete"><!-- wp:avatar {"size":40} /-->

<!-- wp:comment-author-name /-->

<!-- wp:comment-date /--></div>
<!-- /wp:group -->

<!-- wp:comment-content /-->

<!-- wp:comment-reply-link /--></div>
<!-- /wp:group -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-numbers /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /--></div>
<!-- /wp:comments -->';
	}

	/**
	 * Commande reçue : les blocs de WooCommerce, avec des titres en français.
	 *
	 * @return string
	 */
	private static function confirmation() {
		$heading = static function ( $text ) {
			return '<!-- wp:heading {"level":2,"className":"icademie-habillage__bloc-titre"} -->' . "\n" . '<h2 class="wp-block-heading icademie-habillage__bloc-titre">' . esc_html( $text ) . '</h2>' . "\n" . '<!-- /wp:heading -->';
		};

		return '<!-- wp:icademie/entete-page {"type":"tunnel","etape":3,"titre":false} /-->

<!-- wp:group {"className":"icademie-habillage__confirmation","layout":{"type":"default"}} -->
<div class="wp-block-group icademie-habillage__confirmation"><!-- wp:woocommerce/order-confirmation-status /-->

<!-- wp:woocommerce/order-confirmation-summary /-->

<!-- wp:icademie/suite-reservation /-->

<!-- wp:woocommerce/order-confirmation-totals-wrapper -->
' . $heading( __( 'Détails de la location', 'icademie-habillage' ) ) . '

<!-- wp:woocommerce/order-confirmation-totals {"lock":{"remove":true}} /-->
<!-- /wp:woocommerce/order-confirmation-totals-wrapper -->

<!-- wp:woocommerce/order-confirmation-downloads-wrapper -->
' . $heading( __( 'Téléchargements', 'icademie-habillage' ) ) . '

<!-- wp:woocommerce/order-confirmation-downloads {"lock":{"remove":true}} /-->
<!-- /wp:woocommerce/order-confirmation-downloads-wrapper -->

<!-- wp:woocommerce/order-confirmation-billing-wrapper -->
' . $heading( __( 'Adresse de facturation', 'icademie-habillage' ) ) . '

<!-- wp:woocommerce/order-confirmation-billing-address {"lock":{"remove":true}} /-->
<!-- /wp:woocommerce/order-confirmation-billing-wrapper -->

<!-- wp:woocommerce/order-confirmation-additional-fields-wrapper -->
' . $heading( __( 'Informations complémentaires', 'icademie-habillage' ) ) . '

<!-- wp:woocommerce/order-confirmation-additional-fields /-->
<!-- /wp:woocommerce/order-confirmation-additional-fields-wrapper -->

<!-- wp:woocommerce/order-confirmation-additional-information /--></div>
<!-- /wp:group -->

<!-- wp:icademie/assistance /-->';
	}

	// ------------------------------------------------------------------------------------------
	// Hiérarchie des modèles.
	// ------------------------------------------------------------------------------------------

	/**
	 * Place un modèle de l'extension juste avant un modèle de la liste (avec ou sans « .php »).
	 *
	 * @param string[] $templates Modèles candidats, du plus prioritaire au moins prioritaire.
	 * @param string   $before    Modèle à précéder.
	 * @param string   $slug      Modèle de l'extension.
	 * @return string[]
	 */
	private static function insert_before( $templates, $before, $slug ) {
		foreach ( array( $before, $before . '.php' ) as $candidate ) {
			$index = array_search( $candidate, $templates, true );

			if ( false !== $index ) {
				$suffix = '.php' === substr( $candidate, -4 ) ? '.php' : '';

				if ( ! in_array( $slug . $suffix, $templates, true ) ) {
					array_splice( $templates, $index, 0, $slug . $suffix );
				}

				return $templates;
			}
		}

		return $templates;
	}

	/**
	 * Le modèle est-il dans la liste (avec ou sans « .php ») ?
	 *
	 * @param string[] $templates Modèles candidats.
	 * @param string   $slug      Modèle.
	 * @return bool
	 */
	private static function has( $templates, $slug ) {
		return in_array( $slug, $templates, true ) || in_array( $slug . '.php', $templates, true );
	}

	/**
	 * Pages : panier, validation et confirmation de commande (modèles de WooCommerce), sinon page de
	 * contenu (après « page-{slug} » et « page-{id} » du thème).
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function page_hierarchy( $templates ) {
		if ( self::has( $templates, 'order-confirmation' ) ) {
			return self::insert_before( $templates, 'order-confirmation', 'icademie-habillage-confirmation' );
		}

		if ( self::has( $templates, 'page-checkout' ) ) {
			return self::insert_before( $templates, self::has( $templates, 'checkout' ) ? 'checkout' : 'page-checkout', 'icademie-habillage-commande' );
		}

		if ( self::has( $templates, 'page-cart' ) ) {
			return self::insert_before( $templates, self::has( $templates, 'cart' ) ? 'cart' : 'page-cart', 'icademie-habillage-panier' );
		}

		return self::insert_before( $templates, 'page', 'icademie-habillage-page' );
	}

	/**
	 * Articles (pas les autres types de contenu : bateaux, etc.).
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function single_hierarchy( $templates ) {
		return is_singular( 'post' ) ? self::insert_before( $templates, 'single', 'icademie-habillage-article' ) : $templates;
	}

	/**
	 * Listes d'articles : blog, catégories, étiquettes, auteurs, dates (en dernier recours : un modèle
	 * plus précis du thème reste prioritaire).
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function list_hierarchy( $templates ) {
		if ( self::has( $templates, 'icademie-habillage-articles' ) ) {
			return $templates;
		}

		foreach ( array( 'home', 'archive', 'index' ) as $generic ) {
			if ( self::has( $templates, $generic ) ) {
				return self::insert_before( $templates, $generic, 'icademie-habillage-articles' );
			}
		}

		$templates[] = 'icademie-habillage-articles';

		return $templates;
	}

	/**
	 * Archives : celles des articles seulement (les bateaux ont la page catégorie).
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function archive_hierarchy( $templates ) {
		if ( is_post_type_archive() || self::has( $templates, 'archive-product' ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) {
			return $templates;
		}

		return self::insert_before( $templates, 'archive', 'icademie-habillage-articles' );
	}

	/**
	 * Recherche dans le site (la recherche de bateaux a la page catégorie).
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function search_hierarchy( $templates ) {
		if ( self::has( $templates, 'product-search-results' ) || self::has( $templates, 'icademie-page-categorie' ) ) {
			return $templates;
		}

		return self::insert_before( $templates, 'search', 'icademie-habillage-articles' );
	}

	/**
	 * Page introuvable.
	 *
	 * @param string[] $templates Modèles candidats.
	 * @return string[]
	 */
	public static function not_found_hierarchy( $templates ) {
		return self::insert_before( $templates, '404', 'icademie-habillage-404' );
	}

	/**
	 * Page de service, sur toute la largeur du contenu (1200px) : compte client, panier,
	 * validation, pages de WC Vendors (tableau de bord, liste des propriétaires).
	 *
	 * @return bool
	 */
	public static function is_wide_page() {
		if ( ( function_exists( 'is_account_page' ) && is_account_page() ) || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return true;
		}

		$page = is_page() ? get_queried_object() : null;

		if ( ! $page instanceof WP_Post ) {
			return false;
		}

		foreach ( array( 'wcv_vendor_dashboard', 'wcv_vendorslist', 'wcv_orders', 'wcv_shop_settings', 'wcv_pro_dashboard' ) as $shortcode ) {
			if ( has_shortcode( $page->post_content, $shortcode ) ) {
				return true;
			}
		}

		return (int) get_option( 'wcvendors_vendor_dashboard_page_id' ) === (int) $page->ID;
	}
}
