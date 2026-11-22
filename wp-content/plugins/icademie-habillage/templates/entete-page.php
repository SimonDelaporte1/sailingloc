<?php
/**
 * En-tête de page : fil d'Ariane, surtitre, titre, introduction, informations, image ; étapes de
 * la réservation dans le tunnel de commande.
 *
 * @package Icademie_Habillage
 *
 * @var array $data Données préparées par Icademie_Habillage_Blocks::render_entete_page().
 */

defined( 'ABSPATH' ) || exit;

$crumbs = $data['crumbs'];
$parent = null;

for ( $i = count( $crumbs ) - 2; $i >= 0; $i-- ) {
	if ( '' !== $crumbs[ $i ]['url'] ) {
		$parent = $crumbs[ $i ];
		break;
	}
}
?>
<div class="icademie-habillage__entete icademie-habillage__entete--<?php echo esc_attr( $data['type'] ); ?>">
	<div class="icademie-habillage__entete-inner">
		<?php if ( $crumbs ) : ?>
			<nav class="icademie-habillage__ariane" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'icademie-habillage' ); ?>">
				<ol class="icademie-habillage__crumbs">
					<?php foreach ( $crumbs as $crumb ) : ?>
						<li class="icademie-habillage__crumb">
							<?php if ( '' !== $crumb['url'] ) : ?>
								<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
							<?php else : ?>
								<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php if ( $parent ) : ?>
					<a class="icademie-habillage__retour" href="<?php echo esc_url( $parent['url'] ); ?>">
						<?php echo Icademie_Habillage::icon( 'back', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
						<span class="icademie-habillage__sr"><?php esc_html_e( 'Retour à', 'icademie-habillage' ); ?> </span><?php echo esc_html( $parent['label'] ); ?>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $data['steps'] ) : ?>
			<nav class="icademie-habillage__etapes" aria-label="<?php esc_attr_e( 'Étapes de la réservation', 'icademie-habillage' ); ?>">
				<ol>
					<?php foreach ( $data['steps'] as $step ) : ?>
						<li class="icademie-habillage__etape is-<?php echo esc_attr( $step['state'] ); ?>"<?php echo 'current' === $step['state'] ? ' aria-current="step"' : ''; ?>>
							<?php if ( '' !== $step['url'] ) : ?>
								<a href="<?php echo esc_url( $step['url'] ); ?>">
							<?php endif; ?>
							<span class="icademie-habillage__etape-numero" aria-hidden="true">
								<?php echo 'done' === $step['state'] ? Icademie_Habillage::icon( 'check', 16 ) : (int) $step['number']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne ou nombre. ?>
							</span>
							<span class="icademie-habillage__etape-libelle">
								<?php
								if ( 'done' === $step['state'] ) {
									/* translators: %s: étape. */
									echo '<span class="icademie-habillage__sr">' . esc_html__( 'Étape terminée :', 'icademie-habillage' ) . ' </span>';
								}
								echo esc_html( $step['label'] );
								?>
							</span>
							<?php if ( '' !== $step['url'] ) : ?>
								</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
		<?php endif; ?>

		<?php if ( '' !== $data['kicker'] ) : ?>
			<p class="icademie-habillage__surtitre">
				<?php if ( '' !== $data['kicker_url'] ) : ?>
					<a href="<?php echo esc_url( $data['kicker_url'] ); ?>"><?php echo esc_html( $data['kicker'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $data['kicker'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $data['title'] ) : ?>
			<h1 class="icademie-habillage__titre"><?php echo esc_html( $data['title'] ); ?></h1>
		<?php endif; ?>

		<?php if ( '' !== $data['intro'] ) : ?>
			<p class="icademie-habillage__intro"><?php echo esc_html( $data['intro'] ); ?></p>
		<?php endif; ?>

		<?php if ( $data['meta'] ) : ?>
			<ul class="icademie-habillage__meta">
				<?php foreach ( $data['meta'] as $meta ) : ?>
					<li>
						<?php echo '' !== $meta['icon'] ? Icademie_Habillage::icon( $meta['icon'], 18 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
						<?php if ( ! empty( $meta['datetime'] ) ) : ?>
							<time datetime="<?php echo esc_attr( $meta['datetime'] ); ?>"><?php echo esc_html( $meta['text'] ); ?></time>
						<?php else : ?>
							<?php echo esc_html( $meta['text'] ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $data['image'] ) : ?>
			<figure class="icademie-habillage__entete-photo"><?php echo $data['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?></figure>
		<?php endif; ?>
	</div>
</div>
