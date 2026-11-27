<?php
/**
 * Plan du site (Opquast 171) : page « Plan du site », liée depuis le pied de page de toutes les
 * pages, mise à jour en un clic depuis les réglages.
 *
 * @package Icademie_Entete_Pied
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page « Plan du site ».
 *
 * Le bouton « Créer le plan du site » / « Mettre à jour le plan du site » (Réglages › En-tête et
 * pied de page) écrit dans la page, en blocs de listes ordinaires :
 * - les pages publiées (hiérarchie des pages), sauf le panier, la commande, l'espace des
 *   propriétaires, les pages protégées par un mot de passe et celles exclues des moteurs de
 *   recherche (Yoast SEO) ;
 * - la location : tous les bateaux, puis les catégories (destinations, ports) qui ont des bateaux ;
 * - les articles, par catégorie.
 *
 * Le contenu reste modifiable dans l'éditeur, mais le bouton le remplace. Le lien « Plan du site »
 * s'ajoute aux liens légaux du pied de page tant que la page est publiée.
 */
final class Icademie_Entete_Pied_Plan {

	/** Option : page du plan et date de la dernière mise à jour. */
	const OPTION = 'icademie_entete_pied_plan';

	/** Action du formulaire (admin-post.php) et du jeton de sécurité. */
	const ACTION = 'icademie_plan_du_site';

	/** Adresse de la page. */
	const SLUG = 'plan-du-site';

	/** Paramètre de l'adresse des réglages après la mise à jour. */
	const DONE_ARG = 'plan-du-site';

	/** Articles listés par catégorie, au plus. */
	const POSTS_PER_CATEGORY = 100;

	/**
	 * Branche le plan du site.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
	}

	/**
	 * Page du plan, si elle est publiée.
	 *
	 * @return int 0 sans page publiée.
	 */
	public static function page_id() {
		$option  = get_option( self::OPTION, array() );
		$page_id = is_array( $option ) && isset( $option['page'] ) ? (int) $option['page'] : 0;

		return $page_id && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ? $page_id : 0;
	}

	/**
	 * Adresse de la page du plan.
	 *
	 * @return string Vide sans page publiée.
	 */
	public static function url() {
		$page_id = self::page_id();

		return $page_id ? (string) get_permalink( $page_id ) : '';
	}

	/**
	 * Date de la dernière mise à jour.
	 *
	 * @return int Horodatage, 0 si jamais.
	 */
	public static function updated() {
		$option = get_option( self::OPTION, array() );

		return is_array( $option ) && isset( $option['updated'] ) ? (int) $option['updated'] : 0;
	}

	/**
	 * Bouton des réglages : crée ou met à jour la page, puis revient aux réglages.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas l\'autorisation de modifier le plan du site.', 'icademie-entete-pied' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$result = self::refresh();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => Icademie_Entete_Pied_Settings::PAGE,
					self::DONE_ARG => is_wp_error( $result ) ? 'erreur' : 'ok',
				),
				admin_url( 'options-general.php' )
			) . '#icademie-plan-du-site'
		);
		exit;
	}

	/**
	 * Écrit le plan dans sa page (créée au besoin, publiée) et vide le cache des pages : le lien du
	 * pied de page apparaît sur toutes les pages.
	 *
	 * @return int|WP_Error Page, ou erreur.
	 */
	public static function refresh() {
		$option  = get_option( self::OPTION, array() );
		$page_id = is_array( $option ) && isset( $option['page'] ) ? (int) $option['page'] : 0;

		// Page supprimée ou mise à la corbeille : une autre page « plan-du-site », sinon une nouvelle.
		if ( ! $page_id || 'page' !== get_post_type( $page_id ) || in_array( get_post_status( $page_id ), array( false, 'trash' ), true ) ) {
			$existing = get_page_by_path( self::SLUG );
			$page_id  = $existing instanceof WP_Post && 'trash' !== $existing->post_status ? (int) $existing->ID : 0;
		}

		$content = self::content( $page_id );

		if ( $page_id ) {
			$result = wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => $content,
					'post_status'  => 'publish',
				),
				true
			);
		} else {
			$result = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_title'     => __( 'Plan du site', 'icademie-entete-pied' ),
					'post_name'      => self::SLUG,
					'post_status'    => 'publish',
					'post_content'   => $content,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_option(
			self::OPTION,
			array(
				'page'    => (int) $result,
				'updated' => time(),
			),
			false
		);

		Icademie_Entete_Pied::clear_cache();

		return (int) $result;
	}

	/**
	 * Contenu de la page : introduction, puis une liste par rubrique.
	 *
	 * @param int $plan_id Page du plan (exclue de la liste), 0 si elle n'existe pas encore.
	 * @return string Blocs.
	 */
	public static function content( $plan_id = 0 ) {
		$sections = array(
			__( 'Pages', 'icademie-entete-pied' )             => self::pages( $plan_id ),
			__( 'Location de bateaux', 'icademie-entete-pied' ) => self::rentals(),
			__( 'Articles', 'icademie-entete-pied' )          => self::articles(),
		);

		$blocks = array(
			self::paragraph(
				sprintf(
					/* translators: %s: date de la mise à jour. */
					__( 'Toutes les pages du site, par rubrique. Plan mis à jour le %s.', 'icademie-entete-pied' ),
					wp_date( 'j F Y' )
				)
			),
		);

		foreach ( $sections as $title => $items ) {
			if ( $items ) {
				$blocks[] = '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">' . esc_html( $title ) . '</h2>' . "\n" . '<!-- /wp:heading -->';
				$blocks[] = self::list_markup( $items );
			}
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Bloc paragraphe.
	 *
	 * @param string $text Texte brut.
	 * @return string
	 */
	private static function paragraph( $text ) {
		return '<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( $text ) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
	}

	/**
	 * Bloc liste (listes imbriquées pour les sous-pages et sous-catégories).
	 *
	 * @param array[] $items « label », « url », « children ».
	 * @return string
	 */
	private static function list_markup( $items ) {
		$html = '<!-- wp:list -->' . "\n" . '<ul class="wp-block-list">';

		foreach ( $items as $item ) {
			$html .= '<!-- wp:list-item -->' . "\n" . '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';

			if ( ! empty( $item['children'] ) ) {
				$html .= self::list_markup( $item['children'] );
			}

			$html .= '</li>' . "\n" . '<!-- /wp:list-item -->';
		}

		return $html . '</ul>' . "\n" . '<!-- /wp:list -->';
	}

	/**
	 * Texte d'un titre (entités décodées, sans balises).
	 *
	 * @param string $title Titre.
	 * @return string
	 */
	private static function text( $title ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Pages publiées, en arborescence, l'accueil en premier.
	 *
	 * @param int $plan_id Page du plan.
	 * @return array[]
	 */
	private static function pages( $plan_id ) {
		$excluded = array( (int) $plan_id, (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) );

		foreach ( array( 'cart', 'checkout', 'shop' ) as $woo_page ) {
			$excluded[] = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $woo_page ) : 0;
		}

		// Espace des propriétaires (WC Vendors) : réservé, pas dans le plan.
		foreach ( (array) get_option( 'wcvendors_vendor_dashboard_page_id', array() ) as $dashboard ) {
			$excluded[] = (int) $dashboard;
		}

		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
			)
		);

		$kept = array();

		foreach ( (array) $pages as $page ) {
			if ( in_array( (int) $page->ID, $excluded, true ) || '' !== $page->post_password || '1' === (string) get_post_meta( $page->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
				continue;
			}

			$kept[ (int) $page->ID ] = $page;
		}

		/**
		 * Pages du plan du site, avant la mise en arborescence.
		 *
		 * @param WP_Post[] $kept Pages, par identifiant.
		 */
		$kept = (array) apply_filters( 'icademie_plan_du_site_pages', $kept );

		$children = array();

		foreach ( $kept as $page ) {
			// Parent absent du plan : la page remonte au premier niveau.
			$parent                = isset( $kept[ (int) $page->post_parent ] ) ? (int) $page->post_parent : 0;
			$children[ $parent ][] = $page;
		}

		$tree = static function ( $parent ) use ( &$tree, $children ) {
			$items = array();

			foreach ( isset( $children[ $parent ] ) ? $children[ $parent ] : array() as $page ) {
				$items[] = array(
					'label'    => self::text( get_the_title( $page ) ),
					'url'      => (string) get_permalink( $page ),
					'children' => $tree( (int) $page->ID ),
				);
			}

			return $items;
		};

		return array_merge(
			array(
				array(
					'label'    => __( 'Accueil', 'icademie-entete-pied' ),
					'url'      => home_url( '/' ),
					'children' => array(),
				),
			),
			$tree( 0 )
		);
	}

	/**
	 * Location : tous les bateaux, puis les catégories qui en ont (destinations, ports).
	 *
	 * @return array[]
	 */
	private static function rentals() {
		$items = array();
		$shop  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		if ( $shop > 0 && 'publish' === get_post_status( $shop ) ) {
			$items[] = array(
				'label'    => __( 'Tous les bateaux', 'icademie-entete-pied' ),
				'url'      => (string) get_permalink( $shop ),
				'children' => array(),
			);
		}

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $items;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);

		return array_merge( $items, self::term_tree( is_wp_error( $terms ) ? array() : $terms ) );
	}

	/**
	 * Catégories en arborescence, sans les branches vides (aucun bateau dans la catégorie ni ses
	 * sous-catégories).
	 *
	 * @param WP_Term[] $terms Catégories.
	 * @return array[]
	 */
	private static function term_tree( $terms ) {
		$children = array();
		$ids      = array();

		foreach ( $terms as $term ) {
			$ids[ (int) $term->term_id ] = true;
		}

		foreach ( $terms as $term ) {
			$parent                = isset( $ids[ (int) $term->parent ] ) ? (int) $term->parent : 0;
			$children[ $parent ][] = $term;
		}

		$tree = static function ( $parent ) use ( &$tree, $children ) {
			$items = array();

			foreach ( isset( $children[ $parent ] ) ? $children[ $parent ] : array() as $term ) {
				$sub  = $tree( (int) $term->term_id );
				$link = get_term_link( $term );

				if ( is_wp_error( $link ) || ( (int) $term->count < 1 && ! $sub ) ) {
					continue;
				}

				$items[] = array(
					'label'    => self::text( $term->name ),
					'url'      => (string) $link,
					'children' => $sub,
				);
			}

			return $items;
		};

		return $tree( 0 );
	}

	/**
	 * Articles : la page des articles, puis chaque catégorie et ses articles.
	 *
	 * @return array[]
	 */
	private static function articles() {
		$items   = array();
		$blog_id = (int) get_option( 'page_for_posts' );

		if ( $blog_id && 'publish' === get_post_status( $blog_id ) ) {
			$items[] = array(
				'label'    => self::text( get_the_title( $blog_id ) ),
				'url'      => (string) get_permalink( $blog_id ),
				'children' => array(),
			);
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);

		foreach ( is_wp_error( $categories ) ? array() : $categories as $category ) {
			$link  = get_term_link( $category );
			$posts = get_posts(
				array(
					'post_type'        => 'post',
					'post_status'      => 'publish',
					'has_password'     => false,
					'posts_per_page'   => self::POSTS_PER_CATEGORY,
					'category__in'     => array( (int) $category->term_id ),
					'orderby'          => 'date',
					'order'            => 'DESC',
					'suppress_filters' => false,
				)
			);

			if ( is_wp_error( $link ) || ! $posts ) {
				continue;
			}

			$items[] = array(
				'label'    => self::text( $category->name ),
				'url'      => (string) $link,
				'children' => array_map(
					static function ( $post ) {
						return array(
							'label'    => self::text( get_the_title( $post ) ),
							'url'      => (string) get_permalink( $post ),
							'children' => array(),
						);
					},
					$posts
				),
			);
		}

		return $items;
	}

	/**
	 * Mention « Plan du site » dans la liste des pages de l'administration.
	 *
	 * @param string[] $states États.
	 * @param WP_Post  $post   Page.
	 * @return string[]
	 */
	public static function post_state( $states, $post ) {
		if ( $post instanceof WP_Post && (int) $post->ID === self::page_id() ) {
			$states['icademie_plan_du_site'] = __( 'Plan du site (mis à jour depuis Réglages › En-tête et pied de page)', 'icademie-entete-pied' );
		}

		return $states;
	}

	/**
	 * Encart des réglages : état de la page et bouton de mise à jour.
	 */
	public static function render_box() {
		$page_id = self::page_id();
		$updated = self::updated();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple message après la redirection.
		$done = isset( $_GET[ self::DONE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::DONE_ARG ] ) ) : '';
		?>
		<h2 id="icademie-plan-du-site"><?php esc_html_e( 'Plan du site', 'icademie-entete-pied' ); ?></h2>
		<?php if ( 'ok' === $done ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Le plan du site a été mis à jour.', 'icademie-entete-pied' ); ?></p></div>
		<?php elseif ( 'erreur' === $done ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Le plan du site n\'a pas pu être enregistré. Réessayez.', 'icademie-entete-pied' ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Page « Plan du site », liée en bas du pied de page de toutes les pages, à la suite des liens légaux : les pages, les destinations et les articles du site, en listes. Le bouton recalcule son contenu : cliquez-le après avoir ajouté, renommé ou supprimé une page, une catégorie ou un article.', 'icademie-entete-pied' ); ?></p>
		<p>
			<?php if ( $page_id ) : ?>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: lien vers la page, 2: date de la mise à jour. */
						__( 'Page publiée : %1$s, mise à jour le %2$s.', 'icademie-entete-pied' ),
						'<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html( self::text( get_the_title( $page_id ) ) ) . '</a>',
						esc_html( $updated ? wp_date( 'j F Y à H:i', $updated ) : '—' )
					),
					array( 'a' => array( 'href' => true ) )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'La page n\'existe pas encore : le bouton la crée et la publie.', 'icademie-entete-pied' ); ?>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::ACTION ); ?>
			<?php submit_button( $page_id ? __( 'Mettre à jour le plan du site', 'icademie-entete-pied' ) : __( 'Créer le plan du site', 'icademie-entete-pied' ), 'secondary', 'icademie-plan-du-site', false ); ?>
		</form>
		<?php
	}
}
