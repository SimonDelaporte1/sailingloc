<?php
/**
 * Réglages ajoutés aux catégories de produits et aux valeurs de l'attribut « Type de bateau ».
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Destinations populaires et images des types de bateau.
 *
 * - Catégories de produits : case « Destination populaire » (fiche de la catégorie) et colonne
 *   « Accueil » dans la liste des catégories, pour la cocher ou la décocher en un clic.
 * - Valeurs de l'attribut « Type de bateau » : image affichée dans la zone « Catégories de
 *   bateaux » de l'accueil.
 */
final class Icademie_Page_Accueil_Terms {

	/** Métadonnée de catégorie : « 1 » quand la catégorie est une destination populaire. */
	const FEATURED_META = 'icademie_destination_populaire';

	/** Métadonnée de valeur d'attribut : ID de l'image. */
	const IMAGE_META = 'icademie_image';

	/** Action de la colonne « Accueil » (cocher ou décocher une destination). */
	const TOGGLE_ACTION = 'icademie_destination_populaire';

	/** Colonne ajoutée aux listes. */
	const COLUMN = 'icademie_accueil';

	/**
	 * Branche les champs, les colonnes et les enregistrements.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Catégories de produits : destination populaire.
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_featured_field' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'edit_featured_field' ), 20 );
		add_action( 'created_product_cat', array( __CLASS__, 'save_featured' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_featured' ) );
		add_filter( 'manage_edit-product_cat_columns', array( __CLASS__, 'add_featured_column' ) );
		add_filter( 'manage_product_cat_custom_column', array( __CLASS__, 'render_featured_column' ), 10, 3 );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, array( __CLASS__, 'toggle_featured' ) );

		// Valeurs de l'attribut « Type de bateau » : image. Le nom de la taxonomie est écrit en
		// entier : WooCommerce n'est pas encore chargé quand cette extension démarre.
		$taxonomy = self::type_taxonomy();

		add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_image_field' ) );
		add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_image_field' ) );
		add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_image' ) );
		add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_image' ) );
		add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'add_image_column' ) );
		add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'render_image_column' ), 10, 3 );
	}

	/**
	 * Taxonomie de l'attribut « Type de bateau ».
	 *
	 * @return string
	 */
	public static function type_taxonomy() {
		return 'pa_' . Icademie_Page_Accueil_Zones::TYPE_ATTRIBUTE;
	}

	/**
	 * Déclare les métadonnées (nettoyage à l'enregistrement).
	 */
	public static function register_meta() {
		register_term_meta(
			'product_cat',
			self::FEATURED_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => static function ( $value ) {
					return $value ? '1' : '';
				},
			)
		);

		if ( taxonomy_exists( self::type_taxonomy() ) ) {
			register_term_meta(
				self::type_taxonomy(),
				self::IMAGE_META,
				array(
					'type'              => 'integer',
					'single'            => true,
					'sanitize_callback' => 'absint',
				)
			);
		}
	}

	/**
	 * La catégorie est-elle une destination populaire ?
	 *
	 * @param int $term_id Catégorie.
	 * @return bool
	 */
	public static function is_featured( $term_id ) {
		return '1' === (string) get_term_meta( (int) $term_id, self::FEATURED_META, true );
	}

	/**
	 * Image choisie pour une valeur de l'attribut « Type de bateau ».
	 *
	 * @param int $term_id Valeur de l'attribut.
	 * @return int ID de l'image, 0 si aucune.
	 */
	public static function image_id( $term_id ) {
		$image_id = absint( get_term_meta( (int) $term_id, self::IMAGE_META, true ) );

		return ( $image_id && wp_attachment_is_image( $image_id ) ) ? $image_id : 0;
	}

	/**
	 * Aide affichée sous la case « Destination populaire ».
	 *
	 * @return string
	 */
	private static function featured_help() {
		return __( 'Affichée dans « Destinations populaires » sur la page d\'accueil : cinq au plus, dans l\'ordre des catégories, la première en grande vignette. Pensez à choisir une miniature.', 'icademie-page-accueil' );
	}

	/**
	 * Case « Destination populaire » du formulaire d'ajout de catégorie.
	 */
	public static function add_featured_field() {
		?>
		<div class="form-field term-icademie-accueil-wrap">
			<input type="hidden" name="icademie_accueil_destination_champ" value="1">
			<label for="icademie-accueil-destination">
				<input type="checkbox" name="icademie_accueil_destination" id="icademie-accueil-destination" value="1">
				<?php esc_html_e( 'Destination populaire', 'icademie-page-accueil' ); ?>
			</label>
			<p><?php echo esc_html( self::featured_help() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Case « Destination populaire » de la fiche d'une catégorie.
	 *
	 * @param WP_Term $term Catégorie.
	 */
	public static function edit_featured_field( $term ) {
		?>
		<tr class="form-field term-icademie-accueil-wrap">
			<th scope="row"><?php esc_html_e( 'Page d\'accueil', 'icademie-page-accueil' ); ?></th>
			<td>
				<input type="hidden" name="icademie_accueil_destination_champ" value="1">
				<label for="icademie-accueil-destination">
					<input type="checkbox" name="icademie_accueil_destination" id="icademie-accueil-destination" value="1"<?php checked( self::is_featured( $term->term_id ) ); ?>>
					<?php esc_html_e( 'Destination populaire', 'icademie-page-accueil' ); ?>
				</label>
				<p class="description"><?php echo esc_html( self::featured_help() ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Enregistre la case « Destination populaire ».
	 *
	 * WordPress a déjà vérifié le jeton du formulaire de catégorie. La modification rapide de la
	 * liste n'envoie pas le champ caché : elle ne change pas la case.
	 *
	 * @param int $term_id Catégorie.
	 */
	public static function save_featured( $term_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Jeton du formulaire de catégorie vérifié par WordPress.
		if ( ! isset( $_POST['icademie_accueil_destination_champ'] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		self::set_featured( $term_id, ! empty( $_POST['icademie_accueil_destination'] ) );
		// phpcs:enable
	}

	/**
	 * Coche ou décoche « Destination populaire ».
	 *
	 * @param int  $term_id  Catégorie.
	 * @param bool $featured Destination populaire.
	 */
	private static function set_featured( $term_id, $featured ) {
		if ( $featured ) {
			update_term_meta( $term_id, self::FEATURED_META, '1' );
		} else {
			delete_term_meta( $term_id, self::FEATURED_META );
		}
	}

	/**
	 * Colonne « Accueil » de la liste des catégories, avant le nombre de produits.
	 *
	 * @param string[] $columns Colonnes.
	 * @return string[]
	 */
	public static function add_featured_column( $columns ) {
		$column = array( self::COLUMN => esc_html__( 'Accueil', 'icademie-page-accueil' ) );
		$index  = array_search( 'posts', array_keys( $columns ), true );

		if ( false === $index ) {
			return $columns + $column;
		}

		return array_slice( $columns, 0, $index, true ) + $column + array_slice( $columns, $index, null, true );
	}

	/**
	 * Étoile de la colonne « Accueil » : un clic coche ou décoche la destination.
	 *
	 * @param string $content Contenu de la cellule.
	 * @param string $column  Colonne.
	 * @param int    $term_id Catégorie.
	 * @return string
	 */
	public static function render_featured_column( $content, $column, $term_id ) {
		if ( self::COLUMN !== $column ) {
			return $content;
		}

		$featured = self::is_featured( $term_id );
		$term     = get_term( $term_id, 'product_cat' );
		$name     = $term instanceof WP_Term ? $term->name : '';
		$star     = $featured ? '★' : '☆';

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return sprintf( '<span class="icademie-accueil-star%1$s" aria-hidden="true">%2$s</span>', $featured ? ' is-featured' : '', $star );
		}

		$label = $featured
			/* translators: %s: nom de la catégorie. */
			? sprintf( __( 'Retirer « %s » des destinations populaires', 'icademie-page-accueil' ), $name )
			/* translators: %s: nom de la catégorie. */
			: sprintf( __( 'Ajouter « %s » aux destinations populaires', 'icademie-page-accueil' ), $name );

		$url = add_query_arg(
			array(
				'action'   => self::TOGGLE_ACTION,
				'term_id'  => (int) $term_id,
				'_wpnonce' => wp_create_nonce( self::TOGGLE_ACTION . '_' . (int) $term_id ),
			),
			admin_url( 'admin-post.php' )
		);

		return sprintf(
			'<a class="icademie-accueil-star%1$s" href="%2$s" title="%3$s"><span aria-hidden="true">%4$s</span><span class="screen-reader-text">%3$s</span></a>',
			$featured ? ' is-featured' : '',
			esc_url( $url ),
			esc_attr( $label ),
			$star
		);
	}

	/**
	 * Clic sur l'étoile de la colonne « Accueil ».
	 */
	public static function toggle_featured() {
		$term_id = isset( $_GET['term_id'] ) ? absint( $_GET['term_id'] ) : 0;

		check_admin_referer( self::TOGGLE_ACTION . '_' . $term_id );

		$term = $term_id ? get_term( $term_id, 'product_cat' ) : null;

		if ( ! $term instanceof WP_Term || ! current_user_can( 'edit_term', $term_id ) ) {
			wp_die( esc_html__( 'Vous ne pouvez pas modifier cette catégorie.', 'icademie-page-accueil' ), 403 );
		}

		self::set_featured( $term_id, ! self::is_featured( $term_id ) );

		$back = wp_get_referer();

		wp_safe_redirect( $back ? $back : admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) );
		exit;
	}

	/**
	 * Champ d'image du formulaire d'ajout d'une valeur « Type de bateau ».
	 */
	public static function add_image_field() {
		?>
		<div class="form-field term-icademie-accueil-image-wrap">
			<input type="hidden" name="icademie_accueil_image_champ" value="1">
			<?php Icademie_Page_Accueil_Settings::media_field( 'icademie_accueil_image', 0, __( 'Image (page d\'accueil)', 'icademie-page-accueil' ) ); ?>
			<p><?php esc_html_e( 'Vignette de ce type dans « Catégories de bateaux » sur la page d\'accueil. Sans image, la photo d\'un bateau de ce type est utilisée, sinon un pictogramme.', 'icademie-page-accueil' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Champ d'image de la fiche d'une valeur « Type de bateau ».
	 *
	 * @param WP_Term $term Valeur de l'attribut.
	 */
	public static function edit_image_field( $term ) {
		?>
		<tr class="form-field term-icademie-accueil-image-wrap">
			<th scope="row"><?php esc_html_e( 'Image (page d\'accueil)', 'icademie-page-accueil' ); ?></th>
			<td>
				<input type="hidden" name="icademie_accueil_image_champ" value="1">
				<?php Icademie_Page_Accueil_Settings::media_field( 'icademie_accueil_image', self::image_id( $term->term_id ) ); ?>
				<p class="description"><?php esc_html_e( 'Vignette de ce type dans « Catégories de bateaux » sur la page d\'accueil. Sans image, la photo d\'un bateau de ce type est utilisée, sinon un pictogramme.', 'icademie-page-accueil' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Enregistre l'image d'une valeur « Type de bateau ».
	 *
	 * @param int $term_id Valeur de l'attribut.
	 */
	public static function save_image( $term_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Jeton du formulaire vérifié par WordPress.
		if ( ! isset( $_POST['icademie_accueil_image_champ'] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$image_id = isset( $_POST['icademie_accueil_image'] ) ? absint( $_POST['icademie_accueil_image'] ) : 0;
		// phpcs:enable

		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			update_term_meta( $term_id, self::IMAGE_META, $image_id );
		} else {
			delete_term_meta( $term_id, self::IMAGE_META );
		}
	}

	/**
	 * Colonne « Image » de la liste des valeurs « Type de bateau ».
	 *
	 * @param string[] $columns Colonnes.
	 * @return string[]
	 */
	public static function add_image_column( $columns ) {
		$image = array( self::COLUMN => esc_html__( 'Image', 'icademie-page-accueil' ) );

		// Après la case à cocher, comme les miniatures des catégories.
		return isset( $columns['cb'] ) ? array_slice( $columns, 0, 1, true ) + $image + array_slice( $columns, 1, null, true ) : $image + $columns;
	}

	/**
	 * Miniature de la colonne « Image ».
	 *
	 * @param string $content Contenu de la cellule.
	 * @param string $column  Colonne.
	 * @param int    $term_id Valeur de l'attribut.
	 * @return string
	 */
	public static function render_image_column( $content, $column, $term_id ) {
		if ( self::COLUMN !== $column ) {
			return $content;
		}

		$image_id = self::image_id( $term_id );

		return $image_id ? wp_get_attachment_image( $image_id, array( 48, 48 ), false, array( 'style' => 'width:48px;height:48px;object-fit:cover' ) ) : '<span aria-hidden="true">–</span>';
	}

	/**
	 * Médiathèque (types de bateau) et styles de la colonne « Accueil » (catégories).
	 *
	 * @param string $hook_suffix Écran affiché.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		$screen   = get_current_screen();
		$taxonomy = $screen ? $screen->taxonomy : '';

		if ( self::type_taxonomy() === $taxonomy ) {
			Icademie_Page_Accueil_Settings::enqueue_media_script();
			wp_add_inline_style( 'common', '.column-' . self::COLUMN . '{width:64px}' );
		}

		if ( 'product_cat' === $taxonomy ) {
			wp_add_inline_style(
				'common',
				'.column-' . self::COLUMN . '{width:72px;text-align:center!important}'
				. '.icademie-accueil-star{font-size:20px;line-height:1;text-decoration:none;color:#8c8f94}'
				. '.icademie-accueil-star.is-featured{color:#dba617}'
			);
		}
	}
}
