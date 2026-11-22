<?php
/**
 * Liste d'articles en cartes, avec la pagination.
 *
 * @package Icademie_Habillage
 *
 * @var array $data « cards », « pages » (liens de paginate_links()), « search ».
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="icademie-habillage__articles">
	<?php if ( $data['cards'] ) : ?>
		<ul class="icademie-habillage__cartes">
			<?php foreach ( $data['cards'] as $card ) : ?>
				<?php include __DIR__ . '/carte.php'; ?>
			<?php endforeach; ?>
		</ul>

		<?php if ( $data['pages'] ) : ?>
			<nav class="icademie-habillage__pagination" aria-label="<?php esc_attr_e( 'Pages de la liste', 'icademie-habillage' ); ?>">
				<ul>
					<?php foreach ( $data['pages'] as $link ) : ?>
						<li><?php echo wp_kses_post( $link ); ?></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<div class="icademie-habillage__vide">
			<p>
				<?php
				echo esc_html(
					$data['search']
						? __( 'Aucun article ne correspond à cette recherche. Essayez d\'autres mots.', 'icademie-habillage' )
						: __( 'Aucun article pour le moment.', 'icademie-habillage' )
				);
				?>
			</p>
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>
</div>
