<?php
/**
 * Port de départ d'une annonce : catégorie du bateau et sa position sur la carte (encarts 3 et 11).
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le port de départ d'un bateau est sa catégorie la plus précise (« Ajaccio » plutôt que
 * « France »). Sa position (latitude, longitude) se saisit sur la fiche de la catégorie, comme celle
 * du siège sur Réglages › Page contact.
 *
 * Carte de la fiche annonce : même fonctionnement que la page contact. La carte OpenStreetMap ne se
 * charge qu'au clic du visiteur ; sans position, un lien cherche le port sur OpenStreetMap. Une
 * catégorie sans position prend celle de sa catégorie parente, si elle en a une.
 */
final class Icademie_Annonce_Ports {

	/** Métadonnées de la catégorie. */
	const META_LATITUDE  = 'icademie_port_latitude';
	const META_LONGITUDE = 'icademie_port_longitude';

	/** Champ caché du formulaire de la catégorie. */
	const MARKER = 'icademie_port_champ';

	/**
	 * Branche les champs de la catégorie.
	 */
	public static function init() {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_fields' ), 25 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'edit_fields' ), 25 );
		add_action( 'created_product_cat', array( __CLASS__, 'save' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save' ) );
	}

	/**
	 * Port de départ d'un bateau : sa catégorie la plus précise (hors catégorie par défaut).
	 *
	 * @param WC_Product|int $product Bateau.
	 * @return WP_Term|null
	 */
	public static function port( $product ) {
		$id    = $product instanceof WC_Product ? $product->get_id() : (int) $product;
		$terms = get_the_terms( $id, 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$default = (int) get_option( 'default_product_cat' );
		$best    = null;
		$depth   = -1;

		foreach ( $terms as $term ) {
			if ( (int) $term->term_id === $default ) {
				continue;
			}

			$term_depth = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );

			if ( $term_depth > $depth ) {
				$best  = $term;
				$depth = $term_depth;
			}
		}

		return $best;
	}

	/**
	 * Position d'une catégorie, ou de la plus proche de ses catégories parentes qui en a une.
	 *
	 * @param WP_Term $term Catégorie.
	 * @return float[]|null « latitude » et « longitude ».
	 */
	public static function position( $term ) {
		$ids = array_merge( array( (int) $term->term_id ), get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );

		foreach ( $ids as $id ) {
			$latitude  = (string) get_term_meta( $id, self::META_LATITUDE, true );
			$longitude = (string) get_term_meta( $id, self::META_LONGITUDE, true );

			if ( '' !== $latitude && '' !== $longitude ) {
				return array(
					'latitude'  => (float) $latitude,
					'longitude' => (float) $longitude,
					'own'       => (int) $term->term_id === $id,
				);
			}
		}

		return null;
	}

	/**
	 * Carte d'un port : adresse de la carte intégrable (vide sans position) et lien OpenStreetMap.
	 *
	 * @param WP_Term $term Catégorie.
	 * @return array « embed », « link », « name » (port et pays, « Ajaccio, France »).
	 */
	public static function map( $term ) {
		$names = array( $term->name );

		foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );

			if ( $ancestor instanceof WP_Term ) {
				$names[] = $ancestor->name;
			}
		}

		$name     = html_entity_decode( implode( ', ', $names ), ENT_QUOTES, 'UTF-8' );
		$position = self::position( $term );

		if ( ! $position ) {
			return array(
				'embed' => '',
				'link'  => 'https://www.openstreetmap.org/search?query=' . rawurlencode( $name ),
				'name'  => $name,
			);
		}

		// Zone approximative du port (en degrés), plus large que haute comme l'encart ; plus large
		// quand la position est celle d'une catégorie parente (pays, région).
		$lat_span = $position['own'] ? 0.02 : 2.5;
		$lon_span = $position['own'] ? 0.04 : 5;
		$lat      = $position['latitude'];
		$lon      = $position['longitude'];

		// %F : nombre avec un point décimal, quelle que soit la langue du serveur.
		$bbox   = sprintf( '%F,%F,%F,%F', $lon - $lon_span, $lat - $lat_span, $lon + $lon_span, $lat + $lat_span );
		$marker = sprintf( '%F,%F', $lat, $lon );
		$zoom   = $position['own'] ? 14 : 6;

		return array(
			'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik' . ( $position['own'] ? '&marker=' . $marker : '' ),
			'link'  => sprintf( 'https://www.openstreetmap.org/?mlat=%1$F&mlon=%2$F#map=%3$d/%1$F/%2$F', $lat, $lon, $zoom ),
			'name'  => $name,
		);
	}

	/**
	 * Aide des champs.
	 *
	 * @return string
	 */
	private static function help() {
		return __( 'Position du port sur la carte des annonces de cette catégorie. Pour la trouver : sur openstreetmap.org, clic droit sur le port, « Afficher l\'adresse », puis copiez les deux nombres. Sans position, la carte de la catégorie parente est utilisée, sinon un lien vers le port sur OpenStreetMap.', 'icademie-page-annonce' );
	}

	/**
	 * Champs du formulaire d'ajout d'une catégorie.
	 */
	public static function add_fields() {
		?>
		<div class="form-field term-icademie-port-wrap">
			<input type="hidden" name="<?php echo esc_attr( self::MARKER ); ?>" value="1">
			<label for="icademie-port-latitude"><?php esc_html_e( 'Port : latitude et longitude', 'icademie-page-annonce' ); ?></label>
			<input type="text" id="icademie-port-latitude" name="icademie_port_latitude" inputmode="decimal" placeholder="41.9192" style="width:48%" aria-label="<?php esc_attr_e( 'Latitude du port', 'icademie-page-annonce' ); ?>">
			<input type="text" name="icademie_port_longitude" inputmode="decimal" placeholder="8.7386" style="width:48%" aria-label="<?php esc_attr_e( 'Longitude du port', 'icademie-page-annonce' ); ?>">
			<p><?php echo esc_html( self::help() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Champs de la fiche d'une catégorie.
	 *
	 * @param WP_Term $term Catégorie.
	 */
	public static function edit_fields( $term ) {
		?>
		<tr class="form-field term-icademie-port-wrap">
			<th scope="row"><label for="icademie-port-latitude"><?php esc_html_e( 'Port : position sur la carte', 'icademie-page-annonce' ); ?></label></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( self::MARKER ); ?>" value="1">
				<input type="text" id="icademie-port-latitude" name="icademie_port_latitude" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, self::META_LATITUDE, true ) ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( 'Latitude, ex. 41.9192', 'icademie-page-annonce' ); ?>" style="width:12em" aria-label="<?php esc_attr_e( 'Latitude du port', 'icademie-page-annonce' ); ?>">
				<input type="text" name="icademie_port_longitude" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, self::META_LONGITUDE, true ) ); ?>" inputmode="decimal" placeholder="<?php esc_attr_e( 'Longitude, ex. 8.7386', 'icademie-page-annonce' ); ?>" style="width:12em" aria-label="<?php esc_attr_e( 'Longitude du port', 'icademie-page-annonce' ); ?>">
				<p class="description"><?php echo esc_html( self::help() ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Coordonnée saisie avec un point ou une virgule décimale.
	 *
	 * @param mixed $value Valeur.
	 * @param int   $limit 90 (latitude) ou 180 (longitude).
	 * @return string Nombre à point décimal (6 décimales au plus), ou vide si invalide.
	 */
	public static function coordinate( $value, $limit ) {
		$value = is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '';

		if ( '' === $value || ! is_numeric( $value ) || abs( (float) $value ) > $limit ) {
			return '';
		}

		// %F : point décimal quelle que soit la langue du serveur ; 6 décimales, soit environ 10 cm.
		return rtrim( rtrim( sprintf( '%.6F', (float) $value ), '0' ), '.' );
	}

	/**
	 * Enregistre la position du port. Les deux nombres vont ensemble : un seul est ignoré.
	 *
	 * @param int $term_id Catégorie.
	 */
	public static function save( $term_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Jeton du formulaire de catégorie vérifié par WordPress.
		if ( ! isset( $_POST[ self::MARKER ] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$latitude  = self::coordinate( isset( $_POST['icademie_port_latitude'] ) ? wp_unslash( $_POST['icademie_port_latitude'] ) : '', 90 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nombre vérifié.
		$longitude = self::coordinate( isset( $_POST['icademie_port_longitude'] ) ? wp_unslash( $_POST['icademie_port_longitude'] ) : '', 180 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nombre vérifié.
		// phpcs:enable

		if ( '' !== $latitude && '' !== $longitude ) {
			update_term_meta( $term_id, self::META_LATITUDE, $latitude );
			update_term_meta( $term_id, self::META_LONGITUDE, $longitude );
		} else {
			delete_term_meta( $term_id, self::META_LATITUDE );
			delete_term_meta( $term_id, self::META_LONGITUDE );
		}
	}
}
