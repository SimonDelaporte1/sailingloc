<?php
/**
 * Page introuvable : message, recherche et liens utiles.
 *
 * @package Icademie_Habillage
 *
 * @var array $data « shop », « home », « contact ».
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="icademie-habillage__introuvable">
	<p class="icademie-habillage__surtitre"><?php esc_html_e( 'Erreur 404', 'icademie-habillage' ); ?></p>
	<h1 class="icademie-habillage__titre"><?php esc_html_e( 'Cette page est introuvable', 'icademie-habillage' ); ?></h1>
	<p class="icademie-habillage__intro"><?php esc_html_e( 'L\'adresse est peut-être mal saisie, ou la page a été déplacée. Cherchez un bateau, ou repartez de l\'accueil.', 'icademie-habillage' ); ?></p>
	<div class="icademie-habillage__boutons">
		<?php if ( '' !== $data['shop'] ) : ?>
			<a class="icademie-habillage__bouton" href="<?php echo esc_url( $data['shop'] ); ?>"><?php esc_html_e( 'Voir les bateaux', 'icademie-habillage' ); ?></a>
		<?php endif; ?>
		<a class="icademie-habillage__bouton icademie-habillage__bouton--secondaire" href="<?php echo esc_url( $data['home'] ); ?>"><?php esc_html_e( 'Revenir à l\'accueil', 'icademie-habillage' ); ?></a>
	</div>
	<div class="icademie-habillage__introuvable-recherche">
		<h2 class="icademie-habillage__section-titre"><?php esc_html_e( 'Rechercher dans le site', 'icademie-habillage' ); ?></h2>
		<?php get_search_form(); ?>
		<?php if ( '' !== $data['contact'] ) : ?>
			<p>
				<?php esc_html_e( 'Besoin d\'aide ?', 'icademie-habillage' ); ?>
				<a href="<?php echo esc_url( $data['contact'] ); ?>"><?php esc_html_e( 'Contacter l\'équipe', 'icademie-habillage' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>
