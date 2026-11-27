<?php
/**
 * Pied de page SailingLoc.
 *
 * Logo, phrase, assistance pendant la location et réseaux sociaux ; trois colonnes de liens
 * (repliables sur smartphone) ; liens légaux, langue et moyens de paiement.
 *
 * @package Icademie_Entete_Pied
 *
 * @var array    $data Données préparées par Icademie_Entete_Pied_View::footer().
 * @var callable $icon Fonction des icônes.
 */

defined( 'ABSPATH' ) || exit;

$uid     = wp_unique_id( 'icademie-pied-' );
$logo    = $data['logo'];
$contact = $data['contact'];
?>
<footer class="icademie-pied" data-pied>
	<div class="icademie-pied__inner">
		<div class="icademie-pied__top">
			<div class="icademie-pied__brand">
				<a class="icademie-ep__logo icademie-pied__logo" href="<?php echo esc_url( $data['home'] ); ?>">
					<?php echo esc_html( $logo['first'] ); ?><?php if ( '' !== $logo['last'] ) : ?><b><?php echo esc_html( $logo['last'] ); ?></b><?php endif; ?><span class="icademie-ep__sr"><?php esc_html_e( ', accueil', 'icademie-entete-pied' ); ?></span>
				</a>
				<?php if ( '' !== $data['baseline'] ) : ?>
					<p class="icademie-pied__baseline"><?php echo esc_html( $data['baseline'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $contact['phone'] ) : ?>
					<p class="icademie-pied__assist">
						<?php echo call_user_func( $icon, 'lifebuoy', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span>
							<?php esc_html_e( 'Assistance location :', 'icademie-entete-pied' ); ?>
							<?php if ( '' !== $contact['phone_href'] ) : ?>
								<a href="<?php echo esc_url( $contact['phone_href'], array( 'tel' ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
							<?php else : ?>
								<strong><?php echo esc_html( $contact['phone'] ); ?></strong>
							<?php endif; ?>
						</span>
					</p>
				<?php endif; ?>
				<?php if ( $contact['social'] ) : ?>
					<ul class="icademie-pied__social">
						<?php foreach ( $contact['social'] as $network ) : ?>
							<li>
								<a href="<?php echo esc_url( $network['url'] ); ?>" target="_blank" rel="noopener">
									<?php echo $network['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG de WordPress. ?>
									<?php /* translators: %s: réseau social. */ ?>
									<span class="icademie-ep__sr"><?php echo esc_html( sprintf( __( '%s (nouvel onglet)', 'icademie-entete-pied' ), $network['label'] ) ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( $data['columns'] ) : ?>
				<nav class="icademie-pied__nav" aria-label="<?php esc_attr_e( 'Pied de page', 'icademie-entete-pied' ); ?>">
					<?php foreach ( $data['columns'] as $index => $column ) : ?>
						<?php /* Smartphone : le script ajoute un bouton dans le titre et replie la liste. */ ?>
						<div class="icademie-pied__column" data-pied-column>
							<h2 class="icademie-pied__heading" data-pied-heading><?php echo esc_html( $column['title'] ); ?></h2>
							<ul class="icademie-pied__links" id="<?php echo esc_attr( $uid . '-' . $index ); ?>">
								<?php foreach ( $column['links'] as $link ) : ?>
									<li>
										<a href="<?php echo esc_url( $link['url'] ); ?>">
											<?php echo esc_html( $link['label'] ); ?>
											<?php if ( ! empty( $link['note'] ) ) : ?>
												<?php /* Opquast 143 : espace réservé, signalé par un cadenas et le texte. */ ?>
												<span class="icademie-pied__acces"><?php echo call_user_func( $icon, 'lock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?><?php echo esc_html( '(' . $link['note'] . ')' ); ?></span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="icademie-pied__bottom">
			<?php if ( $data['legal'] ) : ?>
				<ul class="icademie-pied__legal" aria-label="<?php esc_attr_e( 'Informations légales', 'icademie-entete-pied' ); ?>">
					<?php foreach ( $data['legal'] as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="icademie-pied__copyright">© <?php echo esc_html( $data['year'] . ' ' . $logo['name'] ); ?></p>
			<?php if ( '' !== $data['lang'] || '' !== $data['payments'] ) : ?>
				<p class="icademie-pied__chips">
					<?php if ( '' !== $data['lang'] ) : ?>
						<span class="icademie-pied__chip"><?php echo esc_html( $data['lang'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $data['payments'] ) : ?>
						<span class="icademie-pied__chip"><?php echo esc_html( $data['payments'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</footer>
