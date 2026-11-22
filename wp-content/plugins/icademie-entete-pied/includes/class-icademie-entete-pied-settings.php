<?php
/**
 * Réglages › En-tête et pied de page.
 *
 * @package Icademie_Entete_Pied
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page de réglages : bouton « Proposer mon bateau », menu, colonnes et liens du pied de page.
 *
 * Les liens se saisissent comme des adresses (champs texte). Tant qu'une partie n'a pas été
 * enregistrée, ses valeurs par défaut sont tirées du site (boutique, catégories, pages Contact et
 * Mon compte…) : l'en-tête et le pied de page sont complets dès l'activation.
 */
final class Icademie_Entete_Pied_Settings {

	/** Option. */
	const OPTION = 'icademie_entete_pied';

	/** Page de réglages. */
	const PAGE = 'icademie-entete-pied';

	/** Colonnes du pied de page et liens par colonne. */
	const COLUMNS = 3;
	const COLUMN_LINKS = 6;

	/** Liens légaux. */
	const LEGAL_LINKS = 5;

	/**
	 * Branche la page de réglages.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Réglages enregistrés.
	 *
	 * @return array
	 */
	private static function options() {
		$options = get_option( self::OPTION, array() );

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Valeur texte d'un réglage, ou sa valeur par défaut tant qu'il n'a pas été enregistré.
	 *
	 * @param string $key     Clé.
	 * @param string $default Valeur par défaut.
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		$options = self::options();

		return array_key_exists( $key, $options ) && is_string( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * La recherche de bateaux est-elle dans l'en-tête ?
	 *
	 * @return bool
	 */
	public static function search_enabled() {
		$options = self::options();

		return ! array_key_exists( 'recherche', $options ) || ! empty( $options['recherche'] );
	}

	/**
	 * Bouton « Top destinations » (arborescence des catégories) dans l'en-tête ?
	 *
	 * @return bool
	 */
	public static function destinations_enabled() {
		$options = self::options();

		return ! array_key_exists( 'destinations', $options ) || ! empty( $options['destinations'] );
	}

	/**
	 * Menu de navigation choisi (0 : automatique, celui de l'en-tête du thème).
	 *
	 * @return int
	 */
	public static function menu_id() {
		$options = self::options();

		return isset( $options['menu'] ) ? absint( $options['menu'] ) : 0;
	}

	/**
	 * Colonnes du pied de page enregistrées.
	 *
	 * @return array[]|null « title » et « links » (« label », « url ») ; null tant qu'elles n'ont pas été enregistrées.
	 */
	public static function columns() {
		$options = self::options();

		return isset( $options['colonnes'] ) && is_array( $options['colonnes'] ) ? $options['colonnes'] : null;
	}

	/**
	 * Liens légaux enregistrés.
	 *
	 * @return array[]|null « label » et « url » ; null tant qu'ils n'ont pas été enregistrés.
	 */
	public static function legal() {
		$options = self::options();

		return isset( $options['legal'] ) && is_array( $options['legal'] ) ? $options['legal'] : null;
	}

	/**
	 * Ajoute la page sous Réglages.
	 */
	public static function add_page() {
		add_options_page(
			__( 'En-tête et pied de page', 'icademie-entete-pied' ),
			__( 'En-tête et pied de page', 'icademie-entete-pied' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Déclare l'option.
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
	}

	/**
	 * Nettoie les réglages envoyés.
	 *
	 * @param mixed $input Valeurs envoyées.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array(
			'menu'          => isset( $input['menu'] ) ? absint( $input['menu'] ) : 0,
			'proposer_url'  => isset( $input['proposer_url'] ) ? esc_url_raw( trim( (string) $input['proposer_url'] ) ) : '',
			'langue'        => isset( $input['langue'] ) ? sanitize_text_field( (string) $input['langue'] ) : '',
			'langue_longue' => isset( $input['langue_longue'] ) ? sanitize_text_field( (string) $input['langue_longue'] ) : '',
			'recherche'     => empty( $input['recherche'] ) ? 0 : 1,
			'destinations'  => empty( $input['destinations'] ) ? 0 : 1,
			'destinations_label' => isset( $input['destinations_label'] ) && '' !== trim( (string) $input['destinations_label'] ) ? sanitize_text_field( (string) $input['destinations_label'] ) : __( 'Top destinations', 'icademie-entete-pied' ),
			'baseline'      => isset( $input['baseline'] ) ? sanitize_text_field( (string) $input['baseline'] ) : '',
			'paiements'     => isset( $input['paiements'] ) ? sanitize_text_field( (string) $input['paiements'] ) : '',
			'colonnes'      => array(),
			'legal'         => self::sanitize_links( isset( $input['legal'] ) ? $input['legal'] : array(), self::LEGAL_LINKS ),
		);

		for ( $i = 0; $i < self::COLUMNS; $i++ ) {
			$column = isset( $input['colonnes'][ $i ] ) && is_array( $input['colonnes'][ $i ] ) ? $input['colonnes'][ $i ] : array();

			$clean['colonnes'][] = array(
				'title' => isset( $column['title'] ) ? sanitize_text_field( (string) $column['title'] ) : '',
				'links' => self::sanitize_links( isset( $column['links'] ) ? $column['links'] : array(), self::COLUMN_LINKS ),
			);
		}

		if ( function_exists( 'wpsc_delete_url_cache' ) || function_exists( 'wp_cache_clear_cache' ) ) {
			// L'en-tête et le pied de page sont sur toutes les pages : cache de WP Super Cache vidé.
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
			}
		}

		return $clean;
	}

	/**
	 * Liens complets (libellé et adresse) d'une liste envoyée.
	 *
	 * @param mixed $rows  Lignes envoyées.
	 * @param int   $limit Nombre de lignes.
	 * @return array[]
	 */
	private static function sanitize_links( $rows, $limit ) {
		$links = array();

		foreach ( array_slice( is_array( $rows ) ? $rows : array(), 0, $limit ) as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( (string) $row['url'] ) ) : '';

			if ( '' !== $label && '' !== $url ) {
				$links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		return $links;
	}

	/**
	 * Champ texte de la page.
	 *
	 * @param string $name        Nom du champ (sous l'option).
	 * @param string $value       Valeur.
	 * @param string $type        Type (text, url).
	 * @param string $label       Nom accessible.
	 * @param string $placeholder Exemple.
	 * @param string $id          Identifiant.
	 */
	private static function input( $name, $value, $type, $label, $placeholder = '', $id = '' ) {
		printf(
			'<input type="%1$s" class="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s>',
			esc_attr( $type ),
			'url' === $type ? 'regular-text code' : 'regular-text',
			esc_attr( self::OPTION . $name ),
			esc_attr( $value ),
			'' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			'' !== $id ? ' id="' . esc_attr( $id ) . '"' : ''
		);
	}

	/**
	 * Lignes « libellé, adresse » d'une liste de liens.
	 *
	 * @param string  $name   Nom de la liste (sous l'option).
	 * @param array[] $links  Liens affichés.
	 * @param int     $limit  Nombre de lignes.
	 * @param string  $prefix Début des noms accessibles.
	 */
	private static function link_rows( $name, $links, $limit, $prefix ) {
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>' . esc_html__( 'Libellé', 'icademie-entete-pied' ) . '</th><th>' . esc_html__( 'Adresse (URL)', 'icademie-entete-pied' ) . '</th></tr></thead><tbody>';

		for ( $i = 0; $i < $limit; $i++ ) {
			$link = isset( $links[ $i ] ) ? $links[ $i ] : array(
				'label' => '',
				'url'   => '',
			);

			echo '<tr><td>';
			/* translators: 1: liste, 2: numéro du lien. */
			self::input( $name . '[' . $i . '][label]', $link['label'], 'text', sprintf( __( '%1$s : libellé du lien %2$d', 'icademie-entete-pied' ), $prefix, $i + 1 ) );
			echo '</td><td>';
			/* translators: 1: liste, 2: numéro du lien. */
			self::input( $name . '[' . $i . '][url]', $link['url'], 'url', sprintf( __( '%1$s : adresse du lien %2$d', 'icademie-entete-pied' ), $prefix, $i + 1 ), 'https://' );
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Page de réglages.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$menus   = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$current = Icademie_Entete_Pied_View::menu_post();
		$columns = Icademie_Entete_Pied_View::columns();
		$legal   = Icademie_Entete_Pied_View::legal_links();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'En-tête et pied de page', 'icademie-entete-pied' ); ?></h1>
			<p><?php esc_html_e( 'L\'en-tête et le pied de page de toutes les pages du site, d\'après la charte graphique et les maquettes SailingLoc. Ils remplacent ceux du thème tant que l\'extension est active.', 'icademie-entete-pied' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>

				<h2><?php esc_html_e( 'En-tête', 'icademie-entete-pied' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="icademie-entete-menu"><?php esc_html_e( 'Menu principal', 'icademie-entete-pied' ); ?></label></th>
						<td>
							<select id="icademie-entete-menu" name="<?php echo esc_attr( self::OPTION . '[menu]' ); ?>">
								<option value="0"><?php esc_html_e( 'Automatique : le menu de l\'en-tête du thème', 'icademie-entete-pied' ); ?></option>
								<?php foreach ( $menus as $menu ) : ?>
									<option value="<?php echo (int) $menu->ID; ?>"<?php selected( self::menu_id(), (int) $menu->ID ); ?>><?php echo esc_html( '' !== $menu->post_title ? $menu->post_title : '#' . $menu->ID ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php
								if ( $current ) {
									/* translators: %s: nom du menu. */
									echo esc_html( sprintf( __( 'Menu affiché : « %s ». Ses liens et sous-menus se modifient dans Apparence › Éditeur › Navigation. La charte conseille 4 entrées au plus.', 'icademie-entete-pied' ), '' !== $current->post_title ? $current->post_title : '#' . $current->ID ) );
								} else {
									esc_html_e( 'Aucun menu de navigation : l\'en-tête affiche « Louer un bateau » (boutique) et « Contact ». Créez un menu dans Apparence › Éditeur › Navigation.', 'icademie-entete-pied' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Top destinations', 'icademie-entete-pied' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[destinations]' ); ?>" value="1"<?php checked( self::destinations_enabled() ); ?>> <?php esc_html_e( 'Bouton à gauche de « Proposer mon bateau » : un clic déplie l\'arborescence des catégories de produits', 'icademie-entete-pied' ); ?></label>
							<p>
								<label for="icademie-entete-destinations"><?php esc_html_e( 'Libellé', 'icademie-entete-pied' ); ?></label>
								<?php self::input( '[destinations_label]', self::get( 'destinations_label', __( 'Top destinations', 'icademie-entete-pied' ) ), 'text', '', '', 'icademie-entete-destinations' ); ?>
							</p>
							<p class="description"><?php esc_html_e( 'Catégories qui ont au moins un bateau (elles-mêmes ou une sous-catégorie), dans l\'ordre de Produits › Catégories, « Non classé » exclue. Le bouton remplace l\'entrée du même nom du menu de navigation (« Top Destinations », « Destinations »), qui n\'est plus affichée. Sur smartphone, il est rangé dans le panneau du menu, au-dessus de « Proposer mon bateau ».', 'icademie-entete-pied' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="icademie-entete-proposer"><?php esc_html_e( 'Bouton « Proposer mon bateau »', 'icademie-entete-pied' ); ?></label></th>
						<td>
							<?php self::input( '[proposer_url]', self::get( 'proposer_url' ), 'url', '', 'https://', 'icademie-entete-proposer' ); ?>
							<p class="description"><?php esc_html_e( 'Vide : le lien « Proposer mon bateau » des réglages de la page d\'accueil, sinon le tableau de bord des vendeurs. Sans adresse, le bouton n\'est pas affiché.', 'icademie-entete-pied' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="icademie-entete-langue"><?php esc_html_e( 'Langue et devise', 'icademie-entete-pied' ); ?></label></th>
						<td>
							<?php self::input( '[langue]', self::get( 'langue', 'FR · €' ), 'text', '', '', 'icademie-entete-langue' ); ?>
							<?php self::input( '[langue_longue]', self::get( 'langue_longue', 'Français · €' ), 'text', __( 'Langue et devise, en toutes lettres (pied de page)', 'icademie-entete-pied' ) ); ?>
							<p class="description"><?php esc_html_e( 'Texte court de l\'en-tête, puis texte du pied de page. Vides : non affichés.', 'icademie-entete-pied' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Recherche', 'icademie-entete-pied' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[recherche]' ); ?>" value="1"<?php checked( self::search_enabled() ); ?>> <?php esc_html_e( 'Loupe « Rechercher un bateau » dans l\'en-tête (recherche dans les annonces)', 'icademie-entete-pied' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Pied de page', 'icademie-entete-pied' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="icademie-pied-baseline"><?php esc_html_e( 'Phrase sous le logo', 'icademie-entete-pied' ); ?></label></th>
						<td>
							<?php self::input( '[baseline]', self::get( 'baseline', (string) get_bloginfo( 'description' ) ), 'text', '', '', 'icademie-pied-baseline' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Assistance et réseaux sociaux', 'icademie-entete-pied' ); ?></th>
						<td>
							<p class="description">
								<?php
								if ( class_exists( 'Icademie_Page_Contact_Settings' ) ) {
									printf(
										/* translators: %s: adresse des réglages de la page contact. */
										wp_kses( __( 'Le numéro d\'assistance et les réseaux sociaux sont ceux des <a href="%s">réglages de la page contact</a>.', 'icademie-entete-pied' ), array( 'a' => array( 'href' => true ) ) ),
										esc_url( admin_url( 'options-general.php?page=' . Icademie_Page_Contact_Settings::PAGE ) )
									);
								} else {
									esc_html_e( 'Le numéro d\'assistance et les réseaux sociaux viennent des réglages de l\'extension « Icademie – Page Contact », inactive.', 'icademie-entete-pied' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="icademie-pied-paiements"><?php esc_html_e( 'Moyens de paiement', 'icademie-entete-pied' ); ?></label></th>
						<td>
							<?php self::input( '[paiements]', self::get( 'paiements' ), 'text', '', 'CB · Visa · Mastercard', 'icademie-pied-paiements' ); ?>
							<p class="description"><?php esc_html_e( 'Ceux réellement acceptés à la commande. Vide : non affiché.', 'icademie-entete-pied' ); ?></p>
						</td>
					</tr>
				</table>

				<?php for ( $i = 0; $i < self::COLUMNS; $i++ ) : ?>
					<?php $column = isset( $columns[ $i ] ) ? $columns[ $i ] : array( 'title' => '', 'links' => array() ); ?>
					<?php /* translators: %d: numéro de la colonne. */ ?>
					<h3><?php echo esc_html( sprintf( __( 'Colonne %d', 'icademie-entete-pied' ), $i + 1 ) ); ?></h3>
					<p>
						<label for="icademie-pied-colonne-<?php echo (int) $i; ?>"><?php esc_html_e( 'Titre', 'icademie-entete-pied' ); ?></label>
						<?php self::input( '[colonnes][' . $i . '][title]', $column['title'], 'text', '', '', 'icademie-pied-colonne-' . $i ); ?>
					</p>
					<?php /* translators: %d: numéro de la colonne. */ ?>
					<?php self::link_rows( '[colonnes][' . $i . '][links]', $column['links'], self::COLUMN_LINKS, sprintf( __( 'Colonne %d', 'icademie-entete-pied' ), $i + 1 ) ); ?>
				<?php endfor; ?>

				<h3><?php esc_html_e( 'Liens légaux (bas du pied de page)', 'icademie-entete-pied' ); ?></h3>
				<?php self::link_rows( '[legal]', $legal, self::LEGAL_LINKS, __( 'Liens légaux', 'icademie-entete-pied' ) ); ?>
				<?php if ( null === self::columns() ) : ?>
					<p class="description"><?php esc_html_e( 'Colonnes et liens proposés d\'après le site (boutique, catégories, pages Contact et Mon compte…) tant qu\'ils n\'ont pas été enregistrés. Une ligne sans libellé ou sans adresse n\'est pas affichée.', 'icademie-entete-pied' ); ?></p>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
