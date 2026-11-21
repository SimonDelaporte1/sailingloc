<?php
/**
 * Carte d'un bateau (zone 7) : photos, type et port, titre, capacité, note, prix par jour.
 *
 * Toute la carte mène à la fiche du bateau : les photos sont des liens (hors tabulation) et le
 * lien du titre couvre le texte de la carte.
 *
 * @package Icademie_Page_Categorie
 *
 * @var array $card          Carte préparée par Icademie_Page_Categorie_View::card().
 * @var array $icon          Fonction des icônes.
 * @var string $rating_format Format de la note (« %s sur 5 »).
 */

defined( 'ABSPATH' ) || exit;

$photo_count = count( $card['photos'] );
$title_id    = 'icademie-categorie-bateau-' . (int) $card['id'];
?>
<li class="icademie-categorie__item">
	<article class="icademie-categorie__card" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="icademie-categorie__card-media<?php echo $photo_count ? '' : ' is-empty'; ?>"<?php echo $photo_count > 1 ? ' data-gallery' : ''; ?>>
			<?php if ( $photo_count ) : ?>
				<ul class="icademie-categorie__photos" data-gallery-track>
					<?php foreach ( $card['photos'] as $photo ) : ?>
						<li class="icademie-categorie__photo">
							<a href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true"><?php echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $photo_count > 1 ) : ?>
					<button type="button" class="icademie-categorie__photo-nav icademie-categorie__photo-nav--prev" data-gallery-prev hidden>
						<?php echo call_user_func( $icon, 'chevron-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php /* translators: %s: nom du bateau. */ ?>
						<span class="icademie-categorie__sr"><?php echo esc_html( sprintf( __( 'Photo précédente de « %s »', 'icademie-page-categorie' ), $card['title'] ) ); ?></span>
					</button>
					<button type="button" class="icademie-categorie__photo-nav icademie-categorie__photo-nav--next" data-gallery-next hidden>
						<?php echo call_user_func( $icon, 'chevron-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php /* translators: %s: nom du bateau. */ ?>
						<span class="icademie-categorie__sr"><?php echo esc_html( sprintf( __( 'Photo suivante de « %s »', 'icademie-page-categorie' ), $card['title'] ) ); ?></span>
					</button>
					<span class="icademie-categorie__photo-dots" aria-hidden="true" data-gallery-dots>
						<?php for ( $i = 0; $i < $photo_count; $i++ ) : ?>
							<span<?php echo 0 === $i ? ' class="is-active"' : ''; ?>></span>
						<?php endfor; ?>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<a class="icademie-categorie__photo-empty" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo call_user_func( $icon, 'boat', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="icademie-categorie__card-body">
			<h3 class="icademie-categorie__card-title" id="<?php echo esc_attr( $title_id ); ?>"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
			<?php if ( '' !== $card['kicker'] ) : ?>
				<p class="icademie-categorie__card-kicker"><?php echo esc_html( $card['kicker'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $card['specs'] ) : ?>
				<p class="icademie-categorie__card-specs"><?php echo esc_html( $card['specs'] ); ?></p>
			<?php endif; ?>
			<p class="icademie-categorie__card-foot">
				<?php if ( $card['reviews'] > 0 ) : ?>
					<span class="icademie-categorie__rating">
						<?php echo call_user_func( $icon, 'star', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-categorie__sr"><?php esc_html_e( 'Note :', 'icademie-page-categorie' ); ?> <?php echo esc_html( sprintf( $rating_format, number_format_i18n( $card['rating'], 1 ) ) ); ?></span>
						<span aria-hidden="true"><?php echo esc_html( number_format_i18n( $card['rating'], 1 ) ); ?></span>
						<?php
						/* translators: %s: nombre d'avis. */
						echo esc_html( sprintf( _n( '(%s avis)', '(%s avis)', $card['reviews'], 'icademie-page-categorie' ), number_format_i18n( $card['reviews'] ) ) );
						?>
					</span>
				<?php else : ?>
					<span class="icademie-categorie__rating icademie-categorie__rating--none"><?php esc_html_e( 'Pas encore d\'avis', 'icademie-page-categorie' ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $card['price'] ) : ?>
					<span class="icademie-categorie__price-tag">
						<?php echo $card['price']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prix mis en forme par WooCommerce. ?>
						<?php if ( $card['per_day'] ) : ?>
							<span class="icademie-categorie__price-day"><?php esc_html_e( '/ jour', 'icademie-page-categorie' ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</p>
		</div>
	</article>
</li>
