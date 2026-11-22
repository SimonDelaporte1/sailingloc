<?php
/**
 * Carte d'un article (liste, articles liés).
 *
 * @package Icademie_Habillage
 *
 * @var array $card Carte préparée par Icademie_Habillage_Blocks::card().
 */

defined( 'ABSPATH' ) || exit;

$tag = 'h' . max( 2, min( 4, (int) $card['heading'] ) );
?>
<li class="icademie-habillage__carte">
	<article class="icademie-habillage__carte-inner">
		<div class="icademie-habillage__carte-media<?php echo '' === $card['image'] ? ' is-empty' : ''; ?>">
			<?php if ( '' !== $card['image'] ) : ?>
				<?php echo $card['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?>
			<?php else : ?>
				<?php echo Icademie_Habillage::icon( 'anchor', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
			<?php endif; ?>
		</div>
		<div class="icademie-habillage__carte-corps">
			<?php if ( '' !== $card['kicker'] ) : ?>
				<p class="icademie-habillage__carte-surtitre"><?php echo esc_html( $card['kicker'] ); ?></p>
			<?php endif; ?>
			<<?php echo esc_html( $tag ); ?> class="icademie-habillage__carte-titre"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></<?php echo esc_html( $tag ); ?>>
			<?php if ( '' !== $card['excerpt'] ) : ?>
				<p class="icademie-habillage__carte-extrait"><?php echo esc_html( $card['excerpt'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $card['date'] || '' !== $card['reading'] ) : ?>
				<p class="icademie-habillage__carte-meta">
					<?php if ( '' !== $card['date'] ) : ?>
						<time datetime="<?php echo esc_attr( $card['datetime'] ); ?>"><?php echo esc_html( $card['date'] ); ?></time>
					<?php endif; ?>
					<?php if ( '' !== $card['date'] && '' !== $card['reading'] ) : ?>
						<span aria-hidden="true">·</span>
					<?php endif; ?>
					<?php echo esc_html( $card['reading'] ); ?>
				</p>
			<?php endif; ?>
		</div>
	</article>
</li>
