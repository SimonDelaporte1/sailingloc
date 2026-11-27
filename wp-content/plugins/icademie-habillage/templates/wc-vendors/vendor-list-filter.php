<?php
/**
 * Liste des propriétaires (WC Vendors) : recherche et changement d'affichage.
 *
 * Remplace templates/front/vendor-list-filter.php de WC Vendors (version 2.6.5), voir
 * Icademie_Habillage_Vendeurs. Mêmes classes et mêmes paramètres d'adresse (« search »,
 * « display_mode »), pour garder les styles et le fonctionnement de WC Vendors. WC Vendors passe
 * la sortie dans wp_kses() avec sa propre liste d'attributs : pas d'attributs data-* ni aria-*
 * (sauf aria-hidden sur span, ajouté par Icademie_Habillage_Vendeurs).
 *
 * @package Icademie_Habillage
 *
 * @var string $display_mode  Affichage : « grid » (grille) ou « list » (liste).
 * @var string $search_term   Mots cherchés.
 * @var int    $vendors_count Nombre de propriétaires trouvés.
 */

defined( 'ABSPATH' ) || exit;

$icademie_singulier = Icademie_Habillage_Vendeurs::vendor_name( true );
$icademie_pluriel   = Icademie_Habillage_Vendeurs::vendor_name( false );
$icademie_modes     = array(
	'grid' => array( 'dashicons-grid-view', __( 'Affichage en grille', 'icademie-habillage' ) ),
	'list' => array( 'dashicons-menu-alt', __( 'Affichage en liste', 'icademie-habillage' ) ),
);
$icademie_actif     = 'grid' === $display_mode ? 'grid' : 'list';
?>
<div class="wcv-vendor-list-filter icademie-vendeurs__filtre">
	<div class="wcv-vendor-list-search">
		<form action="" method="GET" class="wcv-form">
			<?php /* Opquast 69 : étiquette visible, propre au champ. translators: %s: nom des propriétaires au singulier (« vendeur »). */ ?>
			<label for="wcv-vendor-list-search" class="icademie-vendeurs__etiquette"><?php echo esc_html( sprintf( __( 'Rechercher un %s', 'icademie-habillage' ), $icademie_singulier ) ); ?></label>
			<input type="search" value="<?php echo esc_attr( $search_term ); ?>" name="search" id="wcv-vendor-list-search" class="icademie-vendeurs__champ" />
			<input type="submit" class="wcv-button" value="<?php esc_attr_e( 'Rechercher', 'icademie-habillage' ); ?>" />
		</form>
	</div>
	<div class="wcv-vendor-list-switch">
		<?php foreach ( $icademie_modes as $icademie_mode => $icademie_infos ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'display_mode' => $icademie_mode ) ) ); ?>" class="wcv-vendor-list-switch-item <?php echo $icademie_mode === $icademie_actif ? 'active' : ''; ?>" title="<?php echo esc_attr( $icademie_infos[1] ); ?>">
				<span class="dashicons <?php echo esc_attr( $icademie_infos[0] ); ?>" aria-hidden="true"></span>
				<span class="icademie-habillage__sr"><?php echo esc_html( $icademie_infos[1] . ( $icademie_mode === $icademie_actif ? ' ' . __( '(affichage actuel)', 'icademie-habillage' ) : '' ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php if ( '' !== (string) $search_term ) : ?>
	<div class="vendor-search-result-text">
		<?php
		$icademie_nombre = (int) $vendors_count;

		if ( $icademie_nombre > 0 ) {
			echo esc_html(
				sprintf(
					/* translators: 1: nombre de propriétaires trouvés, 2: nom des propriétaires (« vendeur »), 3: mots cherchés. */
					_n( '%1$s %2$s trouvé pour « %3$s »', '%1$s %2$s trouvés pour « %3$s »', $icademie_nombre, 'icademie-habillage' ),
					number_format_i18n( $icademie_nombre ),
					1 === $icademie_nombre ? $icademie_singulier : $icademie_pluriel,
					$search_term
				)
			);
		} else {
			/* translators: 1: nom des propriétaires au singulier (« vendeur »), 2: mots cherchés. */
			echo esc_html( sprintf( __( 'Aucun %1$s trouvé pour « %2$s »', 'icademie-habillage' ), $icademie_singulier, $search_term ) );
		}
		?>
		<a href="<?php echo esc_url( remove_query_arg( 'search' ) ); ?>" class="vendor-search-clear-button">
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			<span class="icademie-habillage__sr"><?php esc_html_e( 'Effacer la recherche', 'icademie-habillage' ); ?></span>
		</a>
	</div>
<?php endif; ?>
