<?php
/**
 * Fiche d'un bateau : zones 2 à 13 des zonings « Produit ».
 *
 * Ordre du code (lecteurs d'écran, clavier) : fil d'Ariane, en-tête, photos, réservation, puis le
 * détail de l'annonce et les bateaux similaires. Sur ordinateur, la réservation s'affiche à droite
 * et reste visible pendant le défilement ; sur smartphone, les photos passent au-dessus du titre et
 * la réservation s'ouvre en plein écran depuis la barre du bas.
 *
 * @package Icademie_Page_Annonce
 *
 * @var array    $data Données préparées par Icademie_Annonce_View::data().
 * @var callable $icon Fonction des icônes.
 */

defined( 'ABSPATH' ) || exit;

$uid          = 'icademie-annonce-' . (int) $data['id'];
$booking      = $data['booking'];
$reviews      = $data['reviews'];
$photo_count  = count( $data['photos'] );
$equip_count  = count( $data['equipments'] );
$review_count = count( $reviews['items'] );
$decimals     = $booking['price_format']['decimals'];

// Zones de la colonne principale, à côté du module de réservation (ordinateur).
$main_zones = 2 + count( array_filter( array( $data['specs'], '' !== $data['description'], $data['equipments'], $booking['rental'], $data['conditions'], $data['map'] ) ) );

// Montants affichés avant le script : dates renvoyées après un refus d'ajout au panier.
$days = 0;

if ( '' !== $booking['start'] && '' !== $booking['end'] && $booking['end'] >= $booking['start'] ) {
	$days = (int) ( new DateTimeImmutable( $booking['start'] ) )->diff( new DateTimeImmutable( $booking['end'] ) )->days + 1;
}

$rental = $days ? round( $booking['price'] * $days, $decimals ) : 0;
$fee    = $days ? round( $rental * $booking['fee_rate'], $decimals ) : 0;
$taxes  = Icademie_Annonce_Taxes::split( $rental + $fee );
$dash   = '—';

/* translators: %s: prix par jour. */
$rental_label_empty = sprintf( __( '%s × nombre de jours', 'icademie-page-annonce' ), wp_strip_all_tags( $booking['price_html'] ) );
/* translators: 1: prix par jour, 2: nombre de jours. */
$rental_label_format = __( '%1$s × %2$s jours', 'icademie-page-annonce' );
/* translators: 1: prix par jour. */
$rental_label_one = __( '%1$s × 1 jour', 'icademie-page-annonce' );
$rental_label     = $days ? sprintf( 1 === $days ? $rental_label_one : $rental_label_format, wp_strip_all_tags( $booking['price_html'] ), number_format_i18n( $days ) ) : $rental_label_empty;
/* translators: %s: taux (« 10 »). */
$fee_label = sprintf( __( 'Frais de service (%s %%)', 'icademie-page-annonce' ), number_format_i18n( $booking['fee_rate'] * 100 ) );
$star      = call_user_func( $icon, 'star', 16 );
?>
<div class="icademie-annonce__inner">

	<?php if ( $data['setup'] ) : ?>
		<div class="icademie-annonce__setup">
			<p><strong><?php esc_html_e( 'À compléter (visible seulement par les personnes qui peuvent modifier ce bateau) :', 'icademie-page-annonce' ); ?></strong></p>
			<ul>
				<?php foreach ( $data['setup'] as $item ) : ?>
					<li><?php if ( '' !== $item['url'] ) : ?><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a><?php else : ?><?php echo esc_html( $item['label'] ); ?><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php /* Zone 2 : fil d'Ariane (smartphone : retour au port de départ). */ ?>
	<?php if ( $data['breadcrumb']['items'] ) : ?>
		<nav class="icademie-annonce__breadcrumb" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'icademie-page-annonce' ); ?>">
			<ol class="icademie-annonce__crumbs">
				<?php foreach ( $data['breadcrumb']['items'] as $crumb ) : ?>
					<li>
						<?php if ( '' !== $crumb['url'] ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $data['breadcrumb']['parent'] ) : ?>
				<a class="icademie-annonce__back" href="<?php echo esc_url( $data['breadcrumb']['parent']['url'] ); ?>">
					<?php echo call_user_func( $icon, 'chevron-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<?php echo esc_html( $data['breadcrumb']['parent']['label'] ); ?>
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<div class="icademie-annonce__layout" style="--ic-main-rows: <?php echo (int) $main_zones; ?>">

		<?php /* Zone 3 : en-tête de l'annonce. */ ?>
		<header class="icademie-annonce__header">
			<div class="icademie-annonce__heading">
				<h1 class="icademie-annonce__title"><?php echo esc_html( $data['title'] ); ?></h1>
				<p class="icademie-annonce__meta">
					<?php if ( '' !== $data['header']['type'] ) : ?>
						<span><?php echo esc_html( $data['header']['type'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $data['header']['port'] ) : ?>
						<span>
							<span class="icademie-annonce__sr"><?php esc_html_e( 'Port de départ :', 'icademie-page-annonce' ); ?></span>
							<?php if ( '' !== $data['header']['port_url'] ) : ?>
								<a href="<?php echo esc_url( $data['header']['port_url'] ); ?>"><?php echo esc_html( $data['header']['port'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $data['header']['port'] ); ?>
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<span>
						<?php if ( $data['header']['reviews'] > 0 ) : ?>
							<a class="icademie-annonce__meta-rating" href="#<?php echo esc_attr( $uid ); ?>-avis">
								<?php echo $star; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
								<span class="icademie-annonce__sr"><?php esc_html_e( 'Note :', 'icademie-page-annonce' ); ?></span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: note sur 5, 2: nombre d'avis. */
										_n( '%1$s (%2$s avis)', '%1$s (%2$s avis)', $data['header']['reviews'], 'icademie-page-annonce' ),
										number_format_i18n( $data['header']['rating'], 1 ),
										number_format_i18n( $data['header']['reviews'] )
									)
								);
								?>
							</a>
						<?php else : ?>
							<span class="icademie-annonce__muted"><?php esc_html_e( 'Pas encore d\'avis', 'icademie-page-annonce' ); ?></span>
						<?php endif; ?>
					</span>
				</p>
				<?php if ( $data['header']['badges'] ) : ?>
					<ul class="icademie-annonce__badges">
						<?php foreach ( $data['header']['badges'] as $badge ) : ?>
							<li class="icademie-annonce__badge icademie-annonce__badge--<?php echo esc_attr( $badge['kind'] ); ?>">
								<?php echo '' !== $badge['icon'] ? call_user_func( $icon, $badge['icon'], 15 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
								<?php echo esc_html( $badge['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( ! $data['preview'] ) : ?>
				<button type="button" class="icademie-annonce__share icademie-annonce__share--header" aria-haspopup="dialog" data-share-open hidden>
					<?php echo call_user_func( $icon, 'share', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__share-label"><?php esc_html_e( 'Partager', 'icademie-page-annonce' ); ?></span>
				</button>
			<?php endif; ?>
		</header>

		<?php /* Zone 4 : galerie photos. */ ?>
		<section class="icademie-annonce__gallery icademie-annonce__gallery--n<?php echo (int) min( $photo_count, 5 ); ?>" aria-label="<?php esc_attr_e( 'Photos du bateau', 'icademie-page-annonce' ); ?>" data-photos>
			<?php if ( $photo_count ) : ?>
				<ul class="icademie-annonce__gallery-list" data-photos-track>
					<?php foreach ( $data['photos'] as $index => $photo ) : ?>
						<li class="icademie-annonce__gallery-item">
							<?php
							$photo_name = '' !== $photo['label']
								/* translators: 1: numéro de la photo, 2: nombre de photos, 3: description de la photo (« Intérieur du bateau »). */
								? sprintf( __( 'Agrandir la photo %1$s sur %2$s : %3$s', 'icademie-page-annonce' ), number_format_i18n( $index + 1 ), number_format_i18n( $photo_count ), $photo['label'] )
								/* translators: 1: numéro de la photo, 2: nombre de photos. */
								: sprintf( __( 'Agrandir la photo %1$s sur %2$s', 'icademie-page-annonce' ), number_format_i18n( $index + 1 ), number_format_i18n( $photo_count ) );
							?>
							<a class="icademie-annonce__gallery-link" href="<?php echo esc_url( $photo['full'] ); ?>" data-photo="<?php echo (int) $index; ?>" aria-label="<?php echo esc_attr( $photo_name ); ?>">
								<?php echo $photo['grid']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $photo_count > 1 ) : ?>
					<a class="icademie-annonce__gallery-all" href="<?php echo esc_url( $data['photos'][0]['full'] ); ?>" data-photo="0">
						<?php echo call_user_func( $icon, 'expand', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php
						/* translators: %s: nombre de photos. */
						echo esc_html( sprintf( __( 'Voir les %s photos', 'icademie-page-annonce' ), number_format_i18n( $photo_count ) ) );
						?>
					</a>
					<p class="icademie-annonce__gallery-counter" aria-hidden="true"><span data-photos-current>1</span> / <?php echo esc_html( number_format_i18n( $photo_count ) ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<div class="icademie-annonce__gallery-empty">
					<?php echo call_user_func( $icon, 'boat', 56 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<p><?php esc_html_e( 'Pas encore de photo', 'icademie-page-annonce' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! $data['preview'] ) : ?>
				<button type="button" class="icademie-annonce__share icademie-annonce__share--gallery" aria-haspopup="dialog" data-share-open hidden>
					<?php echo call_user_func( $icon, 'share', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__sr"><?php esc_html_e( 'Partager l\'annonce', 'icademie-page-annonce' ); ?></span>
				</button>
			<?php endif; ?>
		</section>

		<?php /* Zone 5 : module de réservation. */ ?>
		<section class="icademie-annonce__booking<?php echo $booking['available'] && ! $data['preview'] ? ' icademie-annonce__booking--form' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>-reservation" aria-labelledby="<?php echo esc_attr( $uid ); ?>-reservation-titre">
			<div class="icademie-annonce__booking-card" data-booking-card>
				<h2 class="icademie-annonce__sr" id="<?php echo esc_attr( $uid ); ?>-reservation-titre"><?php esc_html_e( 'Réservation', 'icademie-page-annonce' ); ?></h2>
				<?php
				ob_start();
				?>
				<p class="icademie-annonce__price">
					<span class="icademie-annonce__price-amount"><?php echo wp_kses_post( $booking['price_html'] ); ?></span>
					<span class="icademie-annonce__price-unit"><?php echo $booking['per_day'] ? esc_html__( 'TTC / jour', 'icademie-page-annonce' ) : esc_html__( 'TTC', 'icademie-page-annonce' ); ?></span>
				</p>
				<?php
				$price_html = (string) ob_get_clean();
				?>

				<?php if ( $booking['available'] && ! $data['preview'] ) : ?>
					<form class="cart icademie-annonce__booking-form" action="<?php echo esc_url( $data['url'] ); ?>" method="post" enctype="multipart/form-data" data-booking-form
						data-price="<?php echo esc_attr( wc_format_decimal( $booking['price'] ) ); ?>"
						data-fee-rate="<?php echo esc_attr( (string) $booking['fee_rate'] ); ?>"
						data-vat-rate="<?php echo esc_attr( (string) Icademie_Annonce_Taxes::vat_rate() ); ?>"
						data-format="<?php echo esc_attr( wp_json_encode( $booking['price_format'] ) ); ?>"
						data-label-empty="<?php echo esc_attr( $rental_label_empty ); ?>"
						data-label-one="<?php echo esc_attr( sprintf( $rental_label_one, wp_strip_all_tags( $booking['price_html'] ) ) ); ?>"
						data-label-many="<?php echo esc_attr( sprintf( $rental_label_format, wp_strip_all_tags( $booking['price_html'] ), '%s' ) ); ?>"
						<?php /* translators: 1: montant total, 2: nombre de jours. */ ?>
						data-announce="<?php echo esc_attr__( 'Total TTC : %1$s pour %2$s jours.', 'icademie-page-annonce' ); ?>"
						<?php /* translators: %s: montant total. */ ?>
						data-announce-one="<?php echo esc_attr__( 'Total TTC : %s pour 1 jour.', 'icademie-page-annonce' ); ?>"
						data-error-booked="<?php echo esc_attr__( 'Ces dates comprennent des jours déjà réservés. Choisissez d\'autres dates dans le calendrier des disponibilités.', 'icademie-page-annonce' ); ?>"
						data-error-order="<?php echo esc_attr__( 'La date de retour doit être le jour du départ ou après.', 'icademie-page-annonce' ); ?>">
						<div class="icademie-annonce__booking-main">
						<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prix mis en forme par WooCommerce, échappé ci-dessus. ?>
						<?php if ( $booking['rental'] ) : ?>
							<div class="icademie-annonce__dates">
								<p class="icademie-annonce__field">
									<label for="<?php echo esc_attr( $uid ); ?>-depart"><?php esc_html_e( 'Départ', 'icademie-page-annonce' ); ?></label>
									<input class="icademie-annonce__input" type="date" id="<?php echo esc_attr( $uid ); ?>-depart" name="icademie_date_debut" value="<?php echo esc_attr( $booking['start'] ); ?>" min="<?php echo esc_attr( $booking['today'] ); ?>" required aria-describedby="<?php echo esc_attr( $uid ); ?>-dates-erreur" data-date-start>
								</p>
								<p class="icademie-annonce__field">
									<label for="<?php echo esc_attr( $uid ); ?>-retour"><?php esc_html_e( 'Retour', 'icademie-page-annonce' ); ?></label>
									<input class="icademie-annonce__input" type="date" id="<?php echo esc_attr( $uid ); ?>-retour" name="icademie_date_fin" value="<?php echo esc_attr( $booking['end'] ); ?>" min="<?php echo esc_attr( '' !== $booking['start'] ? $booking['start'] : $booking['today'] ); ?>" required aria-describedby="<?php echo esc_attr( $uid ); ?>-dates-erreur" data-date-end>
								</p>
							</div>
							<p class="icademie-annonce__error" id="<?php echo esc_attr( $uid ); ?>-dates-erreur" data-dates-error hidden></p>

							<?php if ( $booking['max'] > 0 ) : ?>
								<p class="icademie-annonce__field">
									<label for="<?php echo esc_attr( $uid ); ?>-passagers"><?php esc_html_e( 'Passagers', 'icademie-page-annonce' ); ?></label>
									<select class="icademie-annonce__input" id="<?php echo esc_attr( $uid ); ?>-passagers" name="<?php echo esc_attr( Icademie_Annonce_Reservation::FIELD_PASSENGERS ); ?>">
										<?php for ( $i = 1; $i <= $booking['max']; $i++ ) : ?>
											<?php /* translators: %s: nombre de passagers. */ ?>
											<option value="<?php echo (int) $i; ?>"<?php selected( $booking['passengers'], $i ); ?>><?php echo esc_html( sprintf( _n( '%s passager', '%s passagers', $i, 'icademie-page-annonce' ), number_format_i18n( $i ) ) ); ?></option>
										<?php endfor; ?>
									</select>
								</p>
							<?php endif; ?>
						<?php endif; ?>
						</div>

						<div class="icademie-annonce__booking-side">
						<?php if ( $booking['rental'] ) : ?>
							<dl class="icademie-annonce__lines">
								<div class="icademie-annonce__line">
									<dt data-line-label><?php echo esc_html( $rental_label ); ?></dt>
									<dd data-line-rental><?php echo $days ? wp_kses_post( wc_price( $rental ) ) : esc_html( $dash ); ?></dd>
								</div>
								<div class="icademie-annonce__line">
									<dt><?php echo esc_html( $fee_label ); ?></dt>
									<dd data-line-fee><?php echo $days ? wp_kses_post( wc_price( $fee ) ) : esc_html( $dash ); ?></dd>
								</div>
								<?php if ( null !== $booking['deposit'] ) : ?>
									<div class="icademie-annonce__line">
										<dt><?php esc_html_e( 'Caution (empreinte, non débitée)', 'icademie-page-annonce' ); ?></dt>
										<dd><?php echo esc_html( $booking['deposit_text'] ); ?></dd>
									</div>
								<?php endif; ?>
								<?php $tax_labels = Icademie_Annonce_Taxes::labels(); ?>
								<div class="icademie-annonce__line icademie-annonce__line--total">
									<dt><?php echo esc_html( $tax_labels['total'] ); ?></dt>
									<dd data-line-total><?php echo $days ? wp_kses_post( wc_price( $rental + $fee ) ) : esc_html( $dash ); ?></dd>
								</div>
								<div class="icademie-annonce__line icademie-annonce__line--tax">
									<dt><?php echo esc_html( $tax_labels['tax'] ); ?></dt>
									<dd data-line-tax><?php echo $days ? wp_kses_post( wc_price( $taxes['tax'] ) ) : esc_html( $dash ); ?></dd>
								</div>
								<div class="icademie-annonce__line icademie-annonce__line--tax">
									<dt><?php echo esc_html( $tax_labels['net'] ); ?></dt>
									<dd data-line-net><?php echo $days ? wp_kses_post( wc_price( $taxes['net'] ) ) : esc_html( $dash ); ?></dd>
								</div>
							</dl>
							<p class="icademie-annonce__hint" data-lines-hint<?php echo $days ? ' hidden' : ''; ?>><?php esc_html_e( 'Choisissez vos dates : le total se calcule selon le nombre de jours.', 'icademie-page-annonce' ); ?></p>
							<p class="icademie-annonce__sr" role="status" data-booking-status></p>
							<input type="hidden" name="<?php echo esc_attr( Icademie_Annonce_Reservation::MARKER ); ?>" value="1">
						<?php endif; ?>

						<button type="submit" class="icademie-annonce__button icademie-annonce__submit" name="add-to-cart" value="<?php echo (int) $data['id']; ?>">
							<?php echo $booking['rental'] ? esc_html__( 'Demander à réserver', 'icademie-page-annonce' ) : esc_html__( 'Ajouter au panier', 'icademie-page-annonce' ); ?>
						</button>
						<?php if ( $booking['rental'] ) : ?>
							<p class="icademie-annonce__note"><?php esc_html_e( 'Récapitulatif et paiement à l\'étape suivante.', 'icademie-page-annonce' ); ?></p>
							<?php /* Opquast 86 : informations demandées pendant la réservation, annoncées avant de commencer. */ ?>
							<div class="icademie-annonce__prepare">
								<p class="icademie-annonce__prepare-title"><?php esc_html_e( 'À préparer pour réserver :', 'icademie-page-annonce' ); ?></p>
								<ul class="icademie-annonce__prepare-list">
									<li><?php esc_html_e( 'vos coordonnées (nom, e-mail, téléphone) et votre adresse de facturation ;', 'icademie-page-annonce' ); ?></li>
									<li><?php echo $booking['deposit'] ? esc_html__( 'une carte bancaire, pour le paiement et l\'empreinte de la caution ;', 'icademie-page-annonce' ) : esc_html__( 'une carte bancaire, pour le paiement ;', 'icademie-page-annonce' ); ?></li>
									<?php if ( '' !== $booking['licence'] ) : ?>
										<?php /* translators: %s: condition de permis (« Permis côtier requis »). */ ?>
										<li><?php echo esc_html( sprintf( __( 'pour ce bateau : %s.', 'icademie-page-annonce' ), $booking['licence'] ) ); ?></li>
									<?php endif; ?>
								</ul>
							</div>
						<?php endif; ?>
						</div>
					</form>
				<?php else : ?>
					<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prix mis en forme par WooCommerce, échappé ci-dessus. ?>
					<?php if ( ! $data['preview'] ) : ?>
						<p class="icademie-annonce__unavailable"><?php esc_html_e( 'Ce bateau ne peut pas être réservé pour le moment.', 'icademie-page-annonce' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</section>

		<?php /* Zone 6 : caractéristiques clés. */ ?>
		<?php if ( $data['specs'] ) : ?>
			<section class="icademie-annonce__zone icademie-annonce__specs-zone" aria-labelledby="<?php echo esc_attr( $uid ); ?>-caracteristiques">
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-caracteristiques"><?php esc_html_e( 'Caractéristiques', 'icademie-page-annonce' ); ?></h2>
				<dl class="icademie-annonce__specs">
					<?php foreach ( $data['specs'] as $spec ) : ?>
						<div class="icademie-annonce__spec">
							<dt><span class="icademie-annonce__spec-icon"><?php echo call_user_func( $icon, $spec['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span><?php echo esc_html( $spec['label'] ); ?></dt>
							<dd><?php echo Icademie_Page_Annonce::with_abbreviations( $spec['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Texte échappé, abréviations ajoutées. ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>

		<?php /* Zone 7 : description. */ ?>
		<?php if ( '' !== $data['description'] ) : ?>
			<section class="icademie-annonce__zone icademie-annonce__description" aria-labelledby="<?php echo esc_attr( $uid ); ?>-description" data-read-more>
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-description"><?php esc_html_e( 'Le mot du propriétaire', 'icademie-page-annonce' ); ?></h2>
				<div class="icademie-annonce__description-text" id="<?php echo esc_attr( $uid ); ?>-description-texte" data-read-more-text>
					<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contenu filtré par « the_content ». ?>
				</div>
				<button type="button" class="icademie-annonce__link-button" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-description-texte" data-read-more-toggle data-label-less="<?php esc_attr_e( 'Réduire', 'icademie-page-annonce' ); ?>" hidden><?php esc_html_e( 'Lire la suite', 'icademie-page-annonce' ); ?></button>
			</section>
		<?php endif; ?>

		<?php /* Zone 8 : équipements. */ ?>
		<?php if ( $equip_count ) : ?>
			<?php
			$equip_classes = 'icademie-annonce__zone icademie-annonce__equipments';

			foreach ( Icademie_Annonce_View::EQUIPMENTS_VISIBLE as $visible ) {
				$equip_classes .= $equip_count > $visible ? ' is-over-' . (int) $visible : '';
			}
			?>
			<section class="<?php echo esc_attr( $equip_classes ); ?>" aria-labelledby="<?php echo esc_attr( $uid ); ?>-equipements" data-expand>
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-equipements"><?php esc_html_e( 'Équipements', 'icademie-page-annonce' ); ?></h2>
				<ul class="icademie-annonce__equipment-list" id="<?php echo esc_attr( $uid ); ?>-equipements-liste">
					<?php foreach ( $data['equipments'] as $equipment ) : ?>
						<li class="icademie-annonce__equipment<?php echo Icademie_Page_Annonce::is_eco_equipment( $equipment ) ? ' icademie-annonce__equipment--eco' : ''; ?>">
							<span class="icademie-annonce__equipment-icon"><?php echo call_user_func( $icon, Icademie_Page_Annonce::equipment_icon( $equipment ), 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
							<?php echo Icademie_Page_Annonce::with_abbreviations( $equipment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Texte échappé, abréviations ajoutées. ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php /* translators: %s: nombre d'équipements. */ ?>
				<button type="button" class="icademie-annonce__expand" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-equipements-liste" data-expand-toggle data-label-less="<?php esc_attr_e( 'Réduire la liste', 'icademie-page-annonce' ); ?>" hidden><?php echo esc_html( sprintf( __( 'Voir les %s équipements', 'icademie-page-annonce' ), number_format_i18n( $equip_count ) ) ); ?></button>
			</section>
		<?php endif; ?>

		<?php /* Zone 9 : disponibilités. */ ?>
		<?php if ( $booking['rental'] ) : ?>
			<section class="icademie-annonce__zone icademie-annonce__availability" aria-labelledby="<?php echo esc_attr( $uid ); ?>-disponibilites">
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-disponibilites"><?php esc_html_e( 'Disponibilités', 'icademie-page-annonce' ); ?></h2>
				<div class="icademie-annonce__calendar" data-calendar
					data-booked="<?php echo esc_attr( wp_json_encode( $data['calendar']['booked'] ) ); ?>"
					data-today="<?php echo esc_attr( $data['calendar']['today'] ); ?>"
					data-label-prev="<?php esc_attr_e( 'Mois précédent', 'icademie-page-annonce' ); ?>"
					data-label-next="<?php esc_attr_e( 'Mois suivant', 'icademie-page-annonce' ); ?>"
					data-label-booked="<?php esc_attr_e( 'déjà réservé', 'icademie-page-annonce' ); ?>"
					data-label-past="<?php esc_attr_e( 'passé', 'icademie-page-annonce' ); ?>"
					data-label-start="<?php esc_attr_e( 'départ', 'icademie-page-annonce' ); ?>"
					data-label-end="<?php esc_attr_e( 'retour', 'icademie-page-annonce' ); ?>"
					<?php /* translators: %s: date de départ. */ ?>
					data-status-start="<?php echo esc_attr__( 'Départ le %s. Choisissez le jour du retour.', 'icademie-page-annonce' ); ?>"
					<?php /* translators: 1: départ, 2: retour, 3: nombre de jours. */ ?>
					data-status-range="<?php echo esc_attr__( 'Du %1$s au %2$s : %3$s jours.', 'icademie-page-annonce' ); ?>"
					<?php /* translators: %s: date. */ ?>
					data-status-one="<?php echo esc_attr__( 'Le %s : 1 jour.', 'icademie-page-annonce' ); ?>"
					data-status-booked="<?php echo esc_attr__( 'Cette période comprend des jours déjà réservés. Choisissez un autre jour de retour, ou un autre départ.', 'icademie-page-annonce' ); ?>">
				</div>
				<p class="icademie-annonce__sr" role="status" data-calendar-status></p>
				<ul class="icademie-annonce__legend" aria-hidden="true">
					<li><span class="icademie-annonce__swatch"></span><?php esc_html_e( 'Disponible', 'icademie-page-annonce' ); ?></li>
					<li><span class="icademie-annonce__swatch icademie-annonce__swatch--booked"></span><?php esc_html_e( 'Déjà réservé', 'icademie-page-annonce' ); ?></li>
					<li><span class="icademie-annonce__swatch icademie-annonce__swatch--selected"></span><?php esc_html_e( 'Dates sélectionnées', 'icademie-page-annonce' ); ?></li>
				</ul>
				<div class="icademie-annonce__periods" data-calendar-periods>
					<?php if ( $data['calendar']['periods'] ) : ?>
						<p><?php esc_html_e( 'Dates déjà réservées :', 'icademie-page-annonce' ); ?></p>
						<ul>
							<?php foreach ( $data['calendar']['periods'] as $period ) : ?>
								<li><?php echo esc_html( $period ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( 'Aucune date réservée pour le moment : le bateau est disponible tous les jours.', 'icademie-page-annonce' ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php /* Zone 10 : conditions de location. */ ?>
		<?php if ( $data['conditions'] ) : ?>
			<section class="icademie-annonce__zone icademie-annonce__conditions-zone" aria-labelledby="<?php echo esc_attr( $uid ); ?>-conditions">
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-conditions"><?php esc_html_e( 'Conditions de location', 'icademie-page-annonce' ); ?></h2>
				<dl class="icademie-annonce__conditions">
					<?php foreach ( $data['conditions'] as $condition ) : ?>
						<div class="icademie-annonce__condition">
							<dt><?php echo esc_html( $condition['label'] ); ?></dt>
							<dd><?php echo esc_html( $condition['text'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endif; ?>

		<?php /* Zone 11 : localisation du port de départ. */ ?>
		<?php if ( $data['map'] ) : ?>
			<?php
			$map_attributes = '';

			if ( '' !== $data['map']['embed'] ) {
				/* translators: %s: port de départ. */
				$map_title      = sprintf( __( 'Carte : port de départ, %s', 'icademie-page-annonce' ), $data['map']['name'] );
				$map_attributes = ' data-embed="' . esc_url( $data['map']['embed'] ) . '" data-title="' . esc_attr( $map_title ) . '"';
			}
			?>
			<section class="icademie-annonce__zone icademie-annonce__location" aria-labelledby="<?php echo esc_attr( $uid ); ?>-localisation">
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-localisation"><?php esc_html_e( 'Localisation', 'icademie-page-annonce' ); ?></h2>
				<div class="icademie-annonce__map"<?php echo $map_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Valeurs échappées ci-dessus. ?>>
					<div class="icademie-annonce__map-placeholder">
						<span class="icademie-annonce__map-pin"><?php echo call_user_func( $icon, 'pin', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
						<span class="icademie-annonce__chip"><?php echo '' !== $data['map']['embed'] ? esc_html__( 'Carte · zone approximative du port', 'icademie-page-annonce' ) : esc_html( $data['map']['name'] ); ?></span>
						<?php if ( '' !== $data['map']['embed'] ) : ?>
							<button type="button" class="icademie-annonce__button icademie-annonce__button--secondary icademie-annonce__map-button" data-map-load hidden><?php esc_html_e( 'Afficher la carte', 'icademie-page-annonce' ); ?></button>
							<p class="icademie-annonce__map-note"><?php esc_html_e( 'Carte fournie par OpenStreetMap, chargée seulement à votre demande.', 'icademie-page-annonce' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<p class="icademie-annonce__map-caption">
					<span><?php esc_html_e( 'Port de départ :', 'icademie-page-annonce' ); ?> <strong><?php echo esc_html( $data['map']['name'] ); ?></strong></span>
					<a href="<?php echo esc_url( $data['map']['link'] ); ?>">
						<?php echo '' !== $data['map']['embed'] ? esc_html__( 'Voir sur OpenStreetMap', 'icademie-page-annonce' ) : esc_html__( 'Chercher le port sur OpenStreetMap', 'icademie-page-annonce' ); ?>
						<?php echo call_user_func( $icon, 'external', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					</a>
				</p>
			</section>
		<?php endif; ?>

		<?php /* Zone 12 : avis des locataires. */ ?>
		<?php
		$review_classes = 'icademie-annonce__zone icademie-annonce__reviews';

		foreach ( array( 1, Icademie_Annonce_View::REVIEWS_VISIBLE ) as $visible ) {
			$review_classes .= $review_count > $visible ? ' is-over-' . (int) $visible : '';
		}
		?>
		<section class="<?php echo esc_attr( $review_classes ); ?>" id="<?php echo esc_attr( $uid ); ?>-avis" aria-labelledby="<?php echo esc_attr( $uid ); ?>-avis-titre" data-expand>
			<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-avis-titre"><?php esc_html_e( 'Avis des locataires', 'icademie-page-annonce' ); ?></h2>

			<?php if ( $reviews['count'] > 0 || $review_count ) : ?>
				<div class="icademie-annonce__reviews-summary">
					<p class="icademie-annonce__reviews-average">
						<?php echo call_user_func( $icon, 'star', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-annonce__sr"><?php esc_html_e( 'Note moyenne :', 'icademie-page-annonce' ); ?></span>
						<span class="icademie-annonce__reviews-average-value"><?php echo esc_html( number_format_i18n( $reviews['average'], 1 ) ); ?></span>
						<span class="icademie-annonce__reviews-average-max"><?php esc_html_e( '/ 5', 'icademie-page-annonce' ); ?></span>
					</p>
					<?php /* translators: %s: nombre d'avis. */ ?>
					<p class="icademie-annonce__reviews-count"><?php echo esc_html( sprintf( _n( '%s avis', '%s avis', $reviews['count'], 'icademie-page-annonce' ), number_format_i18n( $reviews['count'] ) ) ); ?></p>
					<?php if ( $reviews['criteria'] ) : ?>
						<dl class="icademie-annonce__subnotes" aria-label="<?php esc_attr_e( 'Notes détaillées', 'icademie-page-annonce' ); ?>">
							<?php foreach ( $reviews['criteria'] as $criterion ) : ?>
								<div class="icademie-annonce__subnote">
									<dt><?php echo esc_html( $criterion['label'] ); ?></dt>
									<dd>
										<span class="icademie-annonce__bar" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) round( $criterion['average'] / 5 * 100, 1 ) ); ?>%"></span></span>
										<?php /* translators: %s: moyenne sur 5. */ ?>
										<span class="icademie-annonce__subnote-value"><?php echo esc_html( number_format_i18n( $criterion['average'], 1 ) ); ?><span class="icademie-annonce__sr"> <?php esc_html_e( 'sur 5', 'icademie-page-annonce' ); ?></span></span>
									</dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $review_count ) : ?>
				<ol class="icademie-annonce__review-list" id="<?php echo esc_attr( $uid ); ?>-avis-liste">
					<?php foreach ( $reviews['items'] as $review ) : ?>
						<li class="icademie-annonce__review" id="comment-<?php echo (int) $review['id']; ?>">
							<article aria-labelledby="<?php echo esc_attr( $uid ); ?>-avis-<?php echo (int) $review['id']; ?>">
								<div class="icademie-annonce__review-head">
									<span class="icademie-annonce__avatar" aria-hidden="true"><?php echo esc_html( $review['initials'] ); ?></span>
									<p class="icademie-annonce__review-author">
										<span class="icademie-annonce__review-name" id="<?php echo esc_attr( $uid ); ?>-avis-<?php echo (int) $review['id']; ?>"><?php echo esc_html( $review['name'] ); ?></span>
										<?php /* translators: %s: date de dépôt de l'avis. */ ?>
										<time class="icademie-annonce__review-date" datetime="<?php echo esc_attr( $review['datetime'] ); ?>"><?php echo esc_html( sprintf( __( 'Avis du %s', 'icademie-page-annonce' ), $review['date'] ) ); ?></time>
									</p>
									<?php if ( $review['rating'] > 0 ) : ?>
										<p class="icademie-annonce__review-rating">
											<?php echo $star; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
											<span class="icademie-annonce__sr"><?php esc_html_e( 'Note :', 'icademie-page-annonce' ); ?></span>
											<?php /* translators: %d: note sur 5. */ ?>
											<?php echo esc_html( sprintf( __( '%d / 5', 'icademie-page-annonce' ), $review['rating'] ) ); ?>
										</p>
									<?php endif; ?>
								</div>
								<?php if ( $review['pending'] ) : ?>
									<p class="icademie-annonce__review-pending"><?php esc_html_e( 'Votre avis est en attente de validation.', 'icademie-page-annonce' ); ?></p>
								<?php endif; ?>
								<div class="icademie-annonce__review-text"><?php echo wp_kses_post( $review['text'] ); ?></div>
								<?php echo $review['notes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notes détaillées échappées par l'extension des avis. ?>
								<?php if ( '' !== $review['report_url'] && ! $review['pending'] ) : ?>
									<?php /* Opquast 11 : signaler un abus, depuis le formulaire de contact (objet et message préremplis). */ ?>
									<p class="icademie-annonce__review-report">
										<a href="<?php echo esc_url( $review['report_url'] ); ?>" rel="nofollow">
											<?php esc_html_e( 'Signaler cet avis', 'icademie-page-annonce' ); ?>
											<?php /* translators: %s: prénom de l'auteur de l'avis. */ ?>
											<span class="icademie-annonce__sr"><?php echo esc_html( sprintf( __( 'de %s', 'icademie-page-annonce' ), $review['name'] ) ); ?></span>
										</a>
									</p>
								<?php endif; ?>
							</article>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php /* translators: %s: nombre d'avis. */ ?>
				<button type="button" class="icademie-annonce__expand" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-avis-liste" data-expand-toggle data-label-less="<?php esc_attr_e( 'Réduire les avis', 'icademie-page-annonce' ); ?>" hidden><?php echo esc_html( sprintf( _n( 'Voir les %s avis', 'Voir les %s avis', $review_count, 'icademie-page-annonce' ), number_format_i18n( $review_count ) ) ); ?></button>
			<?php elseif ( ! $data['preview'] ) : ?>
				<p class="icademie-annonce__reviews-empty"><?php esc_html_e( 'Pas encore d\'avis sur ce bateau.', 'icademie-page-annonce' ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== trim( $reviews['form'] ) ) : ?>
				<div class="icademie-annonce__review-form" data-review-form>
					<button type="button" class="icademie-annonce__button icademie-annonce__button--secondary icademie-annonce__review-form-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-avis-formulaire" data-review-form-toggle hidden><?php esc_html_e( 'Donner mon avis', 'icademie-page-annonce' ); ?></button>
					<div class="icademie-annonce__review-form-body" id="<?php echo esc_attr( $uid ); ?>-avis-formulaire" data-review-form-body>
						<?php echo $reviews['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formulaire produit par le bloc de WooCommerce. ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</div>

	<?php /* Zone 13 : bateaux similaires. */ ?>
	<?php if ( $data['similar'] ) : ?>
		<section class="icademie-annonce__similar" aria-labelledby="<?php echo esc_attr( $uid ); ?>-similaires" data-similar>
			<div class="icademie-annonce__similar-head">
				<h2 class="icademie-annonce__zone-title" id="<?php echo esc_attr( $uid ); ?>-similaires"><?php esc_html_e( 'Bateaux similaires', 'icademie-page-annonce' ); ?></h2>
				<div class="icademie-annonce__similar-nav" data-similar-nav hidden>
					<button type="button" class="icademie-annonce__round" data-similar-prev>
						<?php echo call_user_func( $icon, 'chevron-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-annonce__sr"><?php esc_html_e( 'Bateaux précédents', 'icademie-page-annonce' ); ?></span>
					</button>
					<button type="button" class="icademie-annonce__round" data-similar-next>
						<?php echo call_user_func( $icon, 'chevron-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-annonce__sr"><?php esc_html_e( 'Bateaux suivants', 'icademie-page-annonce' ); ?></span>
					</button>
				</div>
			</div>
			<div class="icademie-categorie icademie-annonce__similar-cards">
				<ul class="icademie-annonce__similar-list" data-similar-track>
					<?php
					foreach ( $data['similar'] as $card ) {
						echo Icademie_Page_Categorie_View::render_card( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Carte échappée par son modèle.
					}
					?>
				</ul>
			</div>
			<p class="icademie-annonce__dots" aria-hidden="true" data-similar-dots></p>
		</section>
	<?php endif; ?>
</div>

<?php if ( ! $data['preview'] ) : ?>
	<?php /* Fenêtre de partage sur les réseaux sociaux. */ ?>
	<dialog class="icademie-annonce__dialog icademie-annonce__dialog--share" aria-labelledby="<?php echo esc_attr( $uid ); ?>-partage-titre" data-dialog="share">
		<div class="icademie-annonce__dialog-head">
			<h2 class="icademie-annonce__dialog-title" id="<?php echo esc_attr( $uid ); ?>-partage-titre"><?php esc_html_e( 'Partager l\'annonce', 'icademie-page-annonce' ); ?></h2>
			<button type="button" class="icademie-annonce__close" data-dialog-close>
				<?php echo call_user_func( $icon, 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				<span class="icademie-annonce__sr"><?php esc_html_e( 'Fermer', 'icademie-page-annonce' ); ?></span>
			</button>
		</div>
		<p class="icademie-annonce__share-title"><?php echo esc_html( $data['share']['title'] ); ?></p>
		<ul class="icademie-annonce__share-list">
			<?php foreach ( $data['share']['links'] as $link ) : ?>
				<li>
					<a class="icademie-annonce__share-link icademie-annonce__share-link--<?php echo esc_attr( $link['network'] ); ?>" href="<?php echo esc_url( $link['url'], array( 'https', 'mailto' ) ); ?>"<?php echo 'mail' === $link['network'] ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>>
						<span class="icademie-annonce__share-icon" aria-hidden="true"><?php echo '' !== $link['icon'] ? $link['icon'] : call_user_func( $icon, 'mail', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG de WordPress ou de l'extension. ?></span>
						<?php echo esc_html( $link['label'] ); ?>
						<?php if ( 'mail' !== $link['network'] ) : ?>
							<span class="icademie-annonce__sr"><?php esc_html_e( '(nouvel onglet)', 'icademie-page-annonce' ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
			<li>
				<button type="button" class="icademie-annonce__share-link" data-share-copy="<?php echo esc_url( $data['share']['url'] ); ?>" data-label-done="<?php esc_attr_e( 'Lien copié dans le presse-papiers.', 'icademie-page-annonce' ); ?>" data-label-fail="<?php esc_attr_e( 'Copie impossible : sélectionnez l\'adresse de la page pour la copier.', 'icademie-page-annonce' ); ?>">
					<span class="icademie-annonce__share-icon" aria-hidden="true"><?php echo call_user_func( $icon, 'link', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
					<?php esc_html_e( 'Copier le lien', 'icademie-page-annonce' ); ?>
				</button>
			</li>
			<li data-share-native hidden>
				<button type="button" class="icademie-annonce__share-link" data-share-native-button data-title="<?php echo esc_attr( $data['share']['title'] ); ?>" data-text="<?php echo esc_attr( $data['share']['text'] ); ?>" data-url="<?php echo esc_url( $data['share']['url'] ); ?>">
					<span class="icademie-annonce__share-icon" aria-hidden="true"><?php echo call_user_func( $icon, 'share', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
					<?php esc_html_e( 'Autres applications', 'icademie-page-annonce' ); ?>
				</button>
			</li>
		</ul>
		<p class="icademie-annonce__share-status" role="status" data-share-status></p>
	</dialog>

	<?php if ( $photo_count ) : ?>
		<?php /* Visionneuse des photos en plein écran. */ ?>
		<?php /* translators: %s: nom du bateau. */ ?>
		<dialog class="icademie-annonce__dialog icademie-annonce__dialog--lightbox" aria-label="<?php echo esc_attr( sprintf( __( 'Photos de « %s »', 'icademie-page-annonce' ), $data['title'] ) ); ?>" data-dialog="lightbox">
			<div class="icademie-annonce__lightbox-head">
				<p class="icademie-annonce__lightbox-count" aria-live="polite"><span data-lightbox-current>1</span> / <?php echo esc_html( number_format_i18n( $photo_count ) ); ?></p>
				<button type="button" class="icademie-annonce__close icademie-annonce__close--light" data-dialog-close>
					<?php echo call_user_func( $icon, 'close', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__sr"><?php esc_html_e( 'Fermer les photos', 'icademie-page-annonce' ); ?></span>
				</button>
			</div>
			<ul class="icademie-annonce__lightbox-track" data-lightbox-track>
				<?php foreach ( $data['photos'] as $photo ) : ?>
					<li class="icademie-annonce__lightbox-item"><?php echo $photo['large']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $photo_count > 1 ) : ?>
				<button type="button" class="icademie-annonce__lightbox-nav icademie-annonce__lightbox-nav--prev" data-lightbox-prev>
					<?php echo call_user_func( $icon, 'chevron-left', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__sr"><?php esc_html_e( 'Photo précédente', 'icademie-page-annonce' ); ?></span>
				</button>
				<button type="button" class="icademie-annonce__lightbox-nav icademie-annonce__lightbox-nav--next" data-lightbox-next>
					<?php echo call_user_func( $icon, 'chevron-right', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__sr"><?php esc_html_e( 'Photo suivante', 'icademie-page-annonce' ); ?></span>
				</button>
			<?php endif; ?>
		</dialog>
	<?php endif; ?>

	<?php if ( $booking['available'] ) : ?>
		<?php /* Smartphone : module de réservation en plein écran. */ ?>
		<dialog class="icademie-annonce__dialog icademie-annonce__dialog--booking" aria-labelledby="<?php echo esc_attr( $uid ); ?>-reserver-titre" data-dialog="booking">
			<div class="icademie-annonce__dialog-head">
				<h2 class="icademie-annonce__dialog-title" id="<?php echo esc_attr( $uid ); ?>-reserver-titre"><?php esc_html_e( 'Réserver ce bateau', 'icademie-page-annonce' ); ?></h2>
				<button type="button" class="icademie-annonce__close" data-dialog-close>
					<?php echo call_user_func( $icon, 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-annonce__sr"><?php esc_html_e( 'Fermer', 'icademie-page-annonce' ); ?></span>
				</button>
			</div>
			<div class="icademie-annonce__dialog-body" data-dialog-body></div>
		</dialog>
	<?php endif; ?>
<?php endif; ?>
