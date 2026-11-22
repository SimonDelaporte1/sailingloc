<?php
/**
 * Après la commande : liens vers « Mes locations » et vers les bateaux.
 *
 * @package Icademie_Habillage
 *
 * @var array $data « orders » (vide sans compte connecté), « shop ».
 */

defined( 'ABSPATH' ) || exit;

if ( '' === $data['orders'] && '' === $data['shop'] ) {
	return;
}
?>
<div class="icademie-habillage__suite">
	<?php if ( '' !== $data['orders'] ) : ?>
		<p><?php esc_html_e( 'Vous retrouvez cette réservation, ses dates et son récapitulatif dans votre compte.', 'icademie-habillage' ); ?></p>
	<?php endif; ?>
	<div class="icademie-habillage__boutons">
		<?php if ( '' !== $data['orders'] ) : ?>
			<a class="icademie-habillage__bouton" href="<?php echo esc_url( $data['orders'] ); ?>"><?php esc_html_e( 'Voir mes locations', 'icademie-habillage' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $data['shop'] ) : ?>
			<a class="icademie-habillage__bouton icademie-habillage__bouton--secondaire" href="<?php echo esc_url( $data['shop'] ); ?>"><?php esc_html_e( 'Voir d\'autres bateaux', 'icademie-habillage' ); ?></a>
		<?php endif; ?>
	</div>
</div>
