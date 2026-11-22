<?php
/**
 * Bandeau d'assistance pendant la location (charte : fond balise-soft, bouée et titre balise).
 *
 * @package Icademie_Habillage
 *
 * @var array $data « number », « href », « contact », « short ».
 */

defined( 'ABSPATH' ) || exit;

$uid = wp_unique_id( 'icademie-habillage-assistance-' );
?>
<section class="icademie-habillage__assistance<?php echo $data['short'] ? ' is-short' : ''; ?>" aria-labelledby="<?php echo esc_attr( $uid ); ?>">
	<span class="icademie-habillage__assistance-icone"><?php echo Icademie_Habillage::icon( 'lifebuoy', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
	<div class="icademie-habillage__assistance-texte">
		<h2 class="icademie-habillage__assistance-titre" id="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Assistance pendant la location', 'icademie-habillage' ); ?></h2>
		<p>
			<?php if ( '' !== $data['number'] ) : ?>
				<?php esc_html_e( 'Un problème à bord ou au port : appelez la ligne d\'assistance au', 'icademie-habillage' ); ?>
				<a href="<?php echo esc_url( $data['href'], array( 'tel' ) ); ?>"><?php echo esc_html( $data['number'] ); ?></a>.
			<?php else : ?>
				<?php esc_html_e( 'Un problème à bord ou au port :', 'icademie-habillage' ); ?>
				<a href="<?php echo esc_url( $data['contact'] ); ?>"><?php esc_html_e( 'contactez l\'équipe', 'icademie-habillage' ); ?></a>.
			<?php endif; ?>
			<strong><?php esc_html_e( 'Urgence en mer : 196 (CROSS) ou VHF canal 16.', 'icademie-habillage' ); ?></strong>
		</p>
	</div>
	<?php if ( '' !== $data['number'] && ! $data['short'] ) : ?>
		<a class="icademie-habillage__bouton icademie-habillage__bouton--assistance" href="<?php echo esc_url( $data['href'], array( 'tel' ) ); ?>">
			<?php echo Icademie_Habillage::icon( 'phone', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
			<?php esc_html_e( 'Appeler l\'assistance', 'icademie-habillage' ); ?>
		</a>
	<?php endif; ?>
</section>
