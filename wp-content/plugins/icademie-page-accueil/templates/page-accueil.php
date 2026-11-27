<?php
/**
 * Contenu de la page d'accueil : zones 2 à 10 des zonings.
 *
 * @package Icademie_Page_Accueil
 *
 * @var array $data Données préparées par Icademie_Page_Accueil::render_block().
 */

defined( 'ABSPATH' ) || exit;

$icon = array( 'Icademie_Page_Accueil', 'icon' );

$hero         = $data['hero'];
$search       = $data['search'];
$types        = $data['types'];
$boats        = $data['boats']['items'];
$destinations = $data['destinations'];
$steps        = $data['steps'];
$owner        = $data['owner'];
$reviews      = $data['reviews'];
$newsletter   = $data['newsletter'];

/* translators: Note moyenne, par exemple « 4,5 ». */
$rating_format = __( '%s sur 5', 'icademie-page-accueil' );
?>
<?php if ( $data['setup'] ) : ?>
	<div class="icademie-accueil__setup" role="note">
		<p><strong><?php esc_html_e( 'Page d\'accueil : à compléter', 'icademie-page-accueil' ); ?></strong> <?php esc_html_e( '(message visible seulement par les administrateurs)', 'icademie-page-accueil' ); ?></p>
		<ul>
			<?php foreach ( $data['setup'] as $item ) : ?>
				<li>
					<?php if ( '' !== $item['url'] ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $item['label'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php /* Zone 2 : bandeau, accroche et moteur de recherche. */ ?>
<section class="icademie-accueil__hero<?php echo '' === $hero['image'] ? ' icademie-accueil__hero--illustration' : ''; ?>" aria-labelledby="icademie-accueil-titre">
	<div class="icademie-accueil__hero-media">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress, ou SVG fixe.
		echo '' !== $hero['image'] ? $hero['image'] : Icademie_Page_Accueil::hero_illustration();
		?>
	</div>

	<div class="icademie-accueil__hero-inner">
		<h1 id="icademie-accueil-titre" class="icademie-accueil__hero-title"><?php echo esc_html( $hero['title'] ); ?></h1>

		<?php if ( '' !== $hero['text'] ) : ?>
			<p class="icademie-accueil__hero-text"><?php echo esc_html( $hero['text'] ); ?></p>
		<?php endif; ?>

		<?php if ( $search ) : ?>
			<form class="icademie-accueil__search" role="search" aria-label="<?php esc_attr_e( 'Rechercher un bateau', 'icademie-page-accueil' ); ?>" method="get" action="<?php echo esc_url( $search['action'] ); ?>" data-search>
				<input type="hidden" name="<?php echo esc_attr( $search['marker'] ); ?>" value="1">

				<div class="icademie-accueil__field icademie-accueil__field--destination">
					<label class="icademie-accueil__label" for="icademie-accueil-destination">
						<span class="icademie-accueil__wide"><?php esc_html_e( 'Destination / port', 'icademie-page-accueil' ); ?></span>
						<span class="icademie-accueil__narrow"><?php esc_html_e( 'Où partez-vous ?', 'icademie-page-accueil' ); ?></span>
					</label>
					<select class="icademie-accueil__control" id="icademie-accueil-destination" name="destination">
						<option value=""><?php esc_html_e( 'Toutes les destinations', 'icademie-page-accueil' ); ?></option>
						<?php foreach ( $search['destinations'] as $option ) : ?>
							<option value="<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( str_repeat( "\u{00A0}\u{00A0}\u{00A0}", $option['depth'] ) . $option['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="icademie-accueil__field icademie-accueil__field--depart">
					<label class="icademie-accueil__label" for="icademie-accueil-depart"><?php esc_html_e( 'Départ', 'icademie-page-accueil' ); ?></label>
					<input class="icademie-accueil__control" type="date" id="icademie-accueil-depart" name="depart" min="<?php echo esc_attr( $search['today'] ); ?>" data-search-depart>
				</div>

				<div class="icademie-accueil__field icademie-accueil__field--retour">
					<label class="icademie-accueil__label" for="icademie-accueil-retour"><?php esc_html_e( 'Retour', 'icademie-page-accueil' ); ?></label>
					<input class="icademie-accueil__control" type="date" id="icademie-accueil-retour" name="retour" min="<?php echo esc_attr( $search['today'] ); ?>" data-search-retour>
				</div>

				<?php if ( $search['types'] ) : ?>
					<div class="icademie-accueil__field icademie-accueil__field--type">
						<label class="icademie-accueil__label" for="icademie-accueil-type"><?php esc_html_e( 'Type de bateau', 'icademie-page-accueil' ); ?></label>
						<select class="icademie-accueil__control" id="icademie-accueil-type" name="type">
							<option value=""><?php esc_html_e( 'Tous les types', 'icademie-page-accueil' ); ?></option>
							<?php foreach ( $search['types'] as $option ) : ?>
								<option value="<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if ( $search['has_passengers'] ) : ?>
					<div class="icademie-accueil__field icademie-accueil__field--passagers">
						<label class="icademie-accueil__label" for="icademie-accueil-passagers"><?php esc_html_e( 'Passagers', 'icademie-page-accueil' ); ?></label>
						<input class="icademie-accueil__control" type="number" id="icademie-accueil-passagers" name="passagers" min="1" max="<?php echo esc_attr( (string) ( $search['passengers_max'] ? $search['passengers_max'] : Icademie_Page_Accueil_Search::PASSENGERS_LIMIT ) ); ?>" step="1" inputmode="numeric" placeholder="2">
					</div>
				<?php endif; ?>

				<button type="submit" class="icademie-accueil__button icademie-accueil__search-submit">
					<?php echo call_user_func( $icon, 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<?php esc_html_e( 'Rechercher', 'icademie-page-accueil' ); ?>
				</button>
			</form>
		<?php endif; ?>
	</div>
</section>

<div class="icademie-accueil__zones">

	<?php /* Zone 3 : réassurance. */ ?>
	<?php if ( $data['reassurance'] ) : ?>
		<section class="icademie-accueil__reassurance" aria-labelledby="icademie-accueil-reassurance-titre">
			<h2 id="icademie-accueil-reassurance-titre" class="icademie-accueil__sr"><?php esc_html_e( 'Louer en toute confiance', 'icademie-page-accueil' ); ?></h2>
			<ul class="icademie-accueil__reassurance-list">
				<?php foreach ( $data['reassurance'] as $item ) : ?>
					<?php $tag = '' !== $item['url'] ? 'a' : 'div'; ?>
					<li>
						<<?php echo tag_escape( $tag ); ?> class="icademie-accueil__reassurance-item"<?php echo '' !== $item['url'] ? ' href="' . esc_url( $item['url'] ) . '"' : ''; ?>>
							<span class="icademie-accueil__reassurance-icon"><?php echo call_user_func( $icon, $item['icon'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
							<span class="icademie-accueil__reassurance-content">
								<span class="icademie-accueil__reassurance-title"><?php echo esc_html( $item['title'] ); ?></span>
								<?php if ( '' !== $item['text'] ) : ?>
									<span class="icademie-accueil__reassurance-text"><?php echo esc_html( $item['text'] ); ?></span>
								<?php endif; ?>
							</span>
						</<?php echo tag_escape( $tag ); ?>>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php /* Zone 4 : catégories de bateaux (attribut « Type de bateau »). */ ?>
	<?php if ( $types ) : ?>
		<section class="icademie-accueil__section icademie-accueil__types" aria-labelledby="icademie-accueil-types-titre">
			<div class="icademie-accueil__section-head">
				<h2 id="icademie-accueil-types-titre" class="icademie-accueil__section-title"><?php esc_html_e( 'Choisissez votre bateau', 'icademie-page-accueil' ); ?></h2>
				<a class="icademie-accueil__section-link" href="<?php echo esc_url( $data['shop_url'] ); ?>">
					<span class="icademie-accueil__wide"><?php esc_html_e( 'Tous les bateaux', 'icademie-page-accueil' ); ?></span>
					<span class="icademie-accueil__narrow"><?php esc_html_e( 'Tous', 'icademie-page-accueil' ); ?><span class="icademie-accueil__sr"> <?php esc_html_e( 'les bateaux', 'icademie-page-accueil' ); ?></span></span>
					<?php echo call_user_func( $icon, 'arrow-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				</a>
			</div>
			<ul class="icademie-accueil__types-list" style="--icademie-accueil-colonnes: <?php echo (int) min( 6, count( $types ) ); ?>">
				<?php foreach ( $types as $type ) : ?>
					<li class="icademie-accueil__types-item">
						<a class="icademie-accueil__type" href="<?php echo esc_url( $type['url'] ); ?>">
							<span class="icademie-accueil__type-media<?php echo '' === $type['image'] ? ' icademie-accueil__type-media--icon' : ''; ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress, ou SVG fixe.
								echo '' !== $type['image'] ? $type['image'] : call_user_func( $icon, $type['icon'], 56 );
								?>
							</span>
							<span class="icademie-accueil__type-name"><?php echo esc_html( $type['name'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php /* Zone 5 : bateaux à la une. */ ?>
	<?php if ( $boats ) : ?>
		<section class="icademie-accueil__section icademie-accueil__boats" aria-labelledby="icademie-accueil-bateaux-titre" data-carousel>
			<div class="icademie-accueil__section-head">
				<h2 id="icademie-accueil-bateaux-titre" class="icademie-accueil__section-title"><?php esc_html_e( 'Bateaux à la une', 'icademie-page-accueil' ); ?></h2>
				<div class="icademie-accueil__carousel-nav" data-carousel-nav hidden>
					<button type="button" class="icademie-accueil__round" aria-controls="icademie-accueil-bateaux" data-carousel-prev>
						<?php echo call_user_func( $icon, 'chevron-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-accueil__sr"><?php esc_html_e( 'Annonces précédentes', 'icademie-page-accueil' ); ?></span>
					</button>
					<button type="button" class="icademie-accueil__round" aria-controls="icademie-accueil-bateaux" data-carousel-next>
						<?php echo call_user_func( $icon, 'chevron-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-accueil__sr"><?php esc_html_e( 'Annonces suivantes', 'icademie-page-accueil' ); ?></span>
					</button>
				</div>
			</div>
			<ul class="icademie-accueil__track icademie-accueil__boats-list" id="icademie-accueil-bateaux" data-carousel-track>
				<?php foreach ( $boats as $boat ) : ?>
					<li class="icademie-accueil__track-item">
						<article class="icademie-accueil__boat">
							<div class="icademie-accueil__boat-media<?php echo '' === $boat['image'] ? ' icademie-accueil__boat-media--icon' : ''; ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress, ou SVG fixe.
								echo '' !== $boat['image'] ? $boat['image'] : call_user_func( $icon, 'type-voile', 48 );
								?>
							</div>
							<div class="icademie-accueil__boat-body">
								<h3 class="icademie-accueil__boat-title"><a href="<?php echo esc_url( $boat['url'] ); ?>"><?php echo esc_html( $boat['title'] ); ?></a></h3>
								<?php if ( '' !== $boat['kicker'] ) : ?>
									<p class="icademie-accueil__boat-kicker"><?php echo esc_html( $boat['kicker'] ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $boat['specs'] ) : ?>
									<p class="icademie-accueil__boat-specs"><?php echo esc_html( $boat['specs'] ); ?></p>
								<?php endif; ?>
								<?php if ( $boat['badges'] ) : ?>
									<?php /* Après le titre pour les lecteurs d'écran, posé sur la photo à l'écran. */ ?>
									<ul class="icademie-accueil__badges">
										<?php foreach ( $boat['badges'] as $badge ) : ?>
											<li class="icademie-accueil__badge icademie-accueil__badge--<?php echo esc_attr( $badge['kind'] ); ?>">
												<?php echo '' !== $badge['icon'] ? call_user_func( $icon, $badge['icon'], 15 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
												<?php echo esc_html( $badge['label'] ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<p class="icademie-accueil__boat-foot">
									<?php if ( $boat['reviews'] > 0 ) : ?>
										<span class="icademie-accueil__rating">
											<?php echo call_user_func( $icon, 'star', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
											<span class="icademie-accueil__sr"><?php esc_html_e( 'Note :', 'icademie-page-accueil' ); ?> <?php echo esc_html( sprintf( $rating_format, number_format_i18n( $boat['rating'], 1 ) ) ); ?></span>
											<span aria-hidden="true"><?php echo esc_html( number_format_i18n( $boat['rating'], 1 ) ); ?></span>
											<span class="icademie-accueil__rating-count">
												<?php
												/* translators: %s: nombre d'avis. */
												echo esc_html( sprintf( _n( '(%s avis)', '(%s avis)', $boat['reviews'], 'icademie-page-accueil' ), number_format_i18n( $boat['reviews'] ) ) );
												?>
											</span>
										</span>
									<?php else : ?>
										<span class="icademie-accueil__rating icademie-accueil__rating--none"><?php esc_html_e( 'Pas encore d\'avis', 'icademie-page-accueil' ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $boat['price'] ) : ?>
										<span class="icademie-accueil__price">
											<?php echo $boat['price']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prix mis en forme par WooCommerce. ?>
											<?php if ( $boat['per_day'] ) : ?>
												<span class="icademie-accueil__price-unit"><?php esc_html_e( '/ jour', 'icademie-page-accueil' ); ?></span>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</p>
							</div>
						</article>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="icademie-accueil__dots" data-carousel-dots aria-hidden="true" hidden></div>
		</section>
	<?php endif; ?>

	<?php /* Zone 6 : destinations populaires (catégories mises en avant). */ ?>
	<?php if ( $destinations ) : ?>
		<section class="icademie-accueil__section icademie-accueil__destinations" aria-labelledby="icademie-accueil-destinations-titre">
			<div class="icademie-accueil__section-head">
				<h2 id="icademie-accueil-destinations-titre" class="icademie-accueil__section-title"><?php esc_html_e( 'Destinations populaires', 'icademie-page-accueil' ); ?></h2>
			</div>
			<ul class="icademie-accueil__destinations-list icademie-accueil__destinations-list--<?php echo (int) count( $destinations ); ?>">
				<?php foreach ( $destinations as $destination ) : ?>
					<li class="icademie-accueil__destinations-item">
						<a class="icademie-accueil__destination<?php echo '' === $destination['image'] ? ' icademie-accueil__destination--empty' : ''; ?>" href="<?php echo esc_url( $destination['url'] ); ?>">
							<?php echo $destination['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?>
							<span class="icademie-accueil__chip"><?php echo esc_html( $destination['name'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php /* Zone 7 : comment ça marche, onglets locataire et propriétaire. */ ?>
	<?php if ( $steps ) : ?>
		<section class="icademie-accueil__section icademie-accueil__steps" aria-labelledby="icademie-accueil-etapes-titre" data-tabs>
			<div class="icademie-accueil__section-head">
				<h2 id="icademie-accueil-etapes-titre" class="icademie-accueil__section-title"><?php esc_html_e( 'Comment ça marche', 'icademie-page-accueil' ); ?></h2>
			</div>

			<?php if ( count( $steps ) > 1 ) : ?>
				<div class="icademie-accueil__tabs" data-tabs-list hidden>
					<?php foreach ( $steps as $key => $group ) : ?>
						<button type="button" class="icademie-accueil__tab" id="icademie-accueil-onglet-<?php echo esc_attr( $key ); ?>" aria-controls="icademie-accueil-etapes-<?php echo esc_attr( $key ); ?>" data-tab>
							<?php if ( 'locataire' === $key ) : ?>
								<span class="icademie-accueil__wide"><?php echo esc_html( $group['label'] ); ?></span>
								<span class="icademie-accueil__narrow"><?php esc_html_e( 'Je loue', 'icademie-page-accueil' ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $group['label'] ); ?>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $steps as $key => $group ) : ?>
				<div class="icademie-accueil__steps-panel" id="icademie-accueil-etapes-<?php echo esc_attr( $key ); ?>" data-tab-panel>
					<h3 class="icademie-accueil__steps-panel-title"><?php echo esc_html( $group['label'] ); ?></h3>
					<ol class="icademie-accueil__steps-list">
						<?php foreach ( $group['steps'] as $index => $step ) : ?>
							<li class="icademie-accueil__step">
								<span class="icademie-accueil__step-number" aria-hidden="true"><?php echo (int) $index + 1; ?></span>
								<span class="icademie-accueil__step-content">
									<?php if ( '' !== $step['title'] ) : ?>
										<span class="icademie-accueil__step-title"><?php echo esc_html( $step['title'] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $step['text'] ) : ?>
										<span class="icademie-accueil__step-text"><?php echo esc_html( $step['text'] ); ?></span>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php /* Zone 8 : devenir loueur. */ ?>
	<?php if ( '' !== $owner['title'] ) : ?>
		<section class="icademie-accueil__owner" aria-labelledby="icademie-accueil-loueur-titre">
			<div class="icademie-accueil__owner-media">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress, ou SVG fixe.
				echo '' !== $owner['image'] ? $owner['image'] : Icademie_Page_Accueil::owner_illustration();
				?>
			</div>
			<div class="icademie-accueil__owner-body">
				<h2 id="icademie-accueil-loueur-titre" class="icademie-accueil__owner-title"><?php echo esc_html( $owner['title'] ); ?></h2>
				<?php /* Surtitre de la maquette : après le titre dans le code, au-dessus à l'écran. */ ?>
				<p class="icademie-accueil__owner-kicker"><?php esc_html_e( 'Propriétaires', 'icademie-page-accueil' ); ?></p>
				<?php if ( '' !== $owner['text'] ) : ?>
					<p class="icademie-accueil__owner-text"><?php echo esc_html( $owner['text'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $owner['propose'] || '' !== $owner['estimate'] ) : ?>
					<div class="icademie-accueil__owner-actions">
						<?php if ( '' !== $owner['propose'] ) : ?>
							<?php
							// Opquast 143 : lien vers l'espace réservé aux propriétaires (« connexion requise »).
							$propose_note = method_exists( 'Icademie_Entete_Pied_View', 'access_note' ) ? Icademie_Entete_Pied_View::access_note( $owner['propose'] ) : '';
							?>
							<a class="icademie-accueil__button" href="<?php echo esc_url( $owner['propose'] ); ?>">
								<?php esc_html_e( 'Proposer mon bateau', 'icademie-page-accueil' ); ?>
								<?php if ( '' !== $propose_note ) : ?>
									<?php echo Icademie_Page_Accueil::icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
									<span class="icademie-accueil__acces"><?php echo esc_html( '(' . $propose_note . ')' ); ?></span>
								<?php endif; ?>
							</a>
						<?php endif; ?>
						<?php if ( '' !== $owner['estimate'] ) : ?>
							<a class="icademie-accueil__button icademie-accueil__button--secondary" href="<?php echo esc_url( $owner['estimate'] ); ?>"><?php esc_html_e( 'Estimer mes revenus', 'icademie-page-accueil' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php /* Zone 9 : avis des membres. */ ?>
	<?php if ( $reviews['items'] ) : ?>
		<section class="icademie-accueil__section icademie-accueil__reviews" aria-labelledby="icademie-accueil-avis-titre" data-carousel>
			<div class="icademie-accueil__section-head">
				<h2 id="icademie-accueil-avis-titre" class="icademie-accueil__section-title"><?php esc_html_e( 'Ils ont largué les amarres', 'icademie-page-accueil' ); ?></h2>
				<?php if ( $reviews['count'] > 0 ) : ?>
					<p class="icademie-accueil__reviews-summary">
						<?php echo call_user_func( $icon, 'star', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-accueil__sr"><?php esc_html_e( 'Note moyenne :', 'icademie-page-accueil' ); ?> <?php echo esc_html( sprintf( $rating_format, number_format_i18n( $reviews['average'], 1 ) ) ); ?>,</span>
						<strong aria-hidden="true"><?php echo esc_html( number_format_i18n( $reviews['average'], 1 ) ); ?>/5</strong>
						<span aria-hidden="true">·</span>
						<?php
						/* translators: %s: nombre d'avis. */
						echo esc_html( sprintf( _n( '%s avis', '%s avis', $reviews['count'], 'icademie-page-accueil' ), number_format_i18n( $reviews['count'] ) ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( '' !== $reviews['url'] ) : ?>
					<a class="icademie-accueil__section-link" href="<?php echo esc_url( $reviews['url'] ); ?>">
						<?php esc_html_e( 'Tous les avis', 'icademie-page-accueil' ); ?>
						<?php echo call_user_func( $icon, 'arrow-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					</a>
				<?php endif; ?>
			</div>
			<?php // Les avis ne contiennent pas de lien : la liste reçoit le focus pour défiler au clavier quand elle déborde. ?>
			<ul class="icademie-accueil__track icademie-accueil__reviews-list" aria-labelledby="icademie-accueil-avis-titre" tabindex="0" data-carousel-track>
				<?php foreach ( $reviews['items'] as $review ) : ?>
					<li class="icademie-accueil__track-item">
						<figure class="icademie-accueil__review">
							<figcaption class="icademie-accueil__review-head">
								<span class="icademie-accueil__review-avatar" aria-hidden="true"><?php echo esc_html( $review['initials'] ); ?></span>
								<span class="icademie-accueil__review-who">
									<span class="icademie-accueil__review-name"><?php echo esc_html( $review['name'] ); ?></span>
									<span class="icademie-accueil__review-meta"><?php echo esc_html( $review['boat'] . ' · ' . $review['date'] ); ?></span>
								</span>
								<span class="icademie-accueil__review-rating">
									<?php echo call_user_func( $icon, 'star', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
									<span class="icademie-accueil__sr"><?php esc_html_e( 'Note :', 'icademie-page-accueil' ); ?> <?php echo esc_html( sprintf( $rating_format, number_format_i18n( $review['rating'] ) ) ); ?></span>
									<span aria-hidden="true"><?php echo esc_html( number_format_i18n( $review['rating'] ) ); ?>/5</span>
								</span>
							</figcaption>
							<blockquote class="icademie-accueil__review-text">
								<p><?php echo esc_html( $review['text'] ); ?></p>
							</blockquote>
							<?php if ( $review['verified'] ) : ?>
								<p class="icademie-accueil__review-verified">
									<?php echo call_user_func( $icon, 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
									<?php esc_html_e( 'Avis vérifié : laissé après une location', 'icademie-page-accueil' ); ?>
								</p>
							<?php endif; ?>
						</figure>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="icademie-accueil__dots" data-carousel-dots aria-hidden="true" hidden></div>
		</section>
	<?php endif; ?>

	<?php /* Zone 10 : newsletter (formulaire HubSpot de la page Newsletter). */ ?>
	<?php if ( '' !== $newsletter['url'] ) : ?>
		<section class="icademie-accueil__newsletter" aria-labelledby="icademie-accueil-newsletter-titre">
			<h2 id="icademie-accueil-newsletter-titre" class="icademie-accueil__newsletter-title"><?php echo esc_html( $newsletter['title'] ? $newsletter['title'] : __( 'Newsletter', 'icademie-page-accueil' ) ); ?></h2>

			<div class="icademie-accueil__newsletter-form">
				<?php if ( $newsletter['form'] ) : ?>
					<?php echo Icademie_Page_Accueil_Newsletter::markup( $newsletter['form'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Conteneur HubSpot aux attributs échappés. ?>
					<noscript>
						<a class="icademie-accueil__button" href="<?php echo esc_url( $newsletter['url'] ); ?>"><?php esc_html_e( 'S\'inscrire sur la page Newsletter', 'icademie-page-accueil' ); ?></a>
					</noscript>
				<?php else : ?>
					<a class="icademie-accueil__button" href="<?php echo esc_url( $newsletter['url'] ); ?>">
						<?php echo call_user_func( $icon, 'mail', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php esc_html_e( 'S\'inscrire à la newsletter', 'icademie-page-accueil' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $newsletter['text'] || '' !== $newsletter['privacy_url'] ) : ?>
				<p class="icademie-accueil__newsletter-text">
					<?php echo esc_html( $newsletter['text'] ); ?>
					<?php if ( '' !== $newsletter['privacy_url'] ) : ?>
						<a href="<?php echo esc_url( $newsletter['privacy_url'] ); ?>"><?php esc_html_e( 'Données personnelles', 'icademie-page-accueil' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

</div>
