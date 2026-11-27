<?php
/**
 * Compte client › Mes données personnelles : téléchargement et suppression du compte.
 *
 * @package Icademie_Comptes
 *
 * @var array $data « url », « errors » (par champ), « blockers » (HTML échappé), « nonces » (action et nom
 *                   du champ de sécurité de chaque formulaire), « field », « size » (taille du fichier).
 */

defined( 'ABSPATH' ) || exit;

$icademie_errors = $data['errors'];
$icademie_fields = array(
	'mot_de_passe' => 'icademie-comptes-mot-de-passe',
	'confirmation' => 'icademie-comptes-confirmation',
);
$icademie_listed = array_intersect_key( $icademie_errors, $icademie_fields );
?>
<div class="icademie-comptes">
	<p><?php esc_html_e( 'Récupérez une copie des données personnelles liées à votre compte, ou supprimez votre compte.', 'icademie-comptes' ); ?></p>

	<section class="icademie-comptes__section" aria-labelledby="icademie-comptes-export-titre">
		<h2 id="icademie-comptes-export-titre" class="icademie-comptes__titre"><?php esc_html_e( 'Télécharger mes données', 'icademie-comptes' ); ?></h2>
		<p><?php esc_html_e( 'Un fichier au format JSON (texte structuré, lisible avec un éditeur de texte) qui contient les informations de votre compte, vos adresses, vos locations, vos avis et, si vous proposez des bateaux, vos annonces.', 'icademie-comptes' ); ?></p>

		<?php if ( isset( $icademie_errors['export'] ) ) : ?>
			<div class="woocommerce-error" role="alert"><?php echo esc_html( $icademie_errors['export'] ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $data['url'] ); ?>">
			<?php wp_nonce_field( $data['nonces']['export'][0], $data['nonces']['export'][1] ); ?>
			<input type="hidden" name="<?php echo esc_attr( $data['field'] ); ?>" value="export">
			<?php /* translators: %s: taille du fichier (« environ 0,6 Ko »). */ ?>
			<button type="submit" class="woocommerce-Button button"><?php echo esc_html( sprintf( __( 'Télécharger mes données (fichier JSON, %s)', 'icademie-comptes' ), $data['size'] ) ); ?></button>
		</form>
	</section>

	<section class="icademie-comptes__section" aria-labelledby="icademie-comptes-suppression-titre">
		<h2 id="icademie-comptes-suppression-titre" class="icademie-comptes__titre"><?php esc_html_e( 'Supprimer mon compte', 'icademie-comptes' ); ?></h2>
		<p><?php esc_html_e( 'La suppression est définitive :', 'icademie-comptes' ); ?></p>
		<ul class="icademie-comptes__liste">
			<li><?php esc_html_e( 'votre compte, vos adresses et vos préférences sont effacés ;', 'icademie-comptes' ); ?></li>
			<li><?php esc_html_e( 'vos avis restent publiés, signés « Ancien membre » ;', 'icademie-comptes' ); ?></li>
			<li><?php esc_html_e( 'vos locations passées sont conservées sans lien avec un compte, pour nos obligations comptables.', 'icademie-comptes' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'Pensez à télécharger vos données avant de supprimer votre compte. Un e-mail vous confirmera la suppression.', 'icademie-comptes' ); ?></p>

		<?php if ( $data['blockers'] ) : ?>
			<div class="woocommerce-info icademie-comptes__blocage" role="status">
				<div>
					<p><strong><?php esc_html_e( 'Votre compte ne peut pas être supprimé pour le moment.', 'icademie-comptes' ); ?></strong></p>
					<?php foreach ( $data['blockers'] as $icademie_blocker ) : ?>
						<p><?php echo $icademie_blocker; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé par Icademie_Comptes_Donnees::blockers(). ?></p>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $data['url'] ); ?>" class="icademie-comptes__formulaire" novalidate>
				<?php if ( isset( $icademie_errors['form'] ) ) : ?>
					<div class="woocommerce-error" role="alert" tabindex="-1" autofocus><?php echo esc_html( $icademie_errors['form'] ); ?></div>
				<?php elseif ( $icademie_listed ) : ?>
					<div class="woocommerce-error icademie-comptes__erreurs" role="alert" tabindex="-1" autofocus>
						<div>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: nombre d'erreurs. */
										_n( 'Le formulaire contient %d erreur :', 'Le formulaire contient %d erreurs :', count( $icademie_listed ), 'icademie-comptes' ),
										count( $icademie_listed )
									)
								);
								?>
							</p>
							<ul>
								<?php foreach ( $icademie_listed as $icademie_field => $icademie_message ) : ?>
									<li><a href="#<?php echo esc_attr( $icademie_fields[ $icademie_field ] ); ?>"><?php echo esc_html( $icademie_message ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				<?php endif; ?>

				<?php wp_nonce_field( $data['nonces']['suppression'][0], $data['nonces']['suppression'][1] ); ?>
				<input type="hidden" name="<?php echo esc_attr( $data['field'] ); ?>" value="suppression">

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide<?php echo isset( $icademie_errors['mot_de_passe'] ) ? ' woocommerce-invalid' : ''; ?>">
					<label for="icademie-comptes-mot-de-passe">
						<?php esc_html_e( 'Mot de passe actuel', 'icademie-comptes' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Obligatoire', 'icademie-comptes' ); ?></span>
					</label>
					<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="mot_de_passe" id="icademie-comptes-mot-de-passe" autocomplete="current-password" required aria-required="true" aria-describedby="icademie-comptes-mot-de-passe-aide<?php echo isset( $icademie_errors['mot_de_passe'] ) ? ' icademie-comptes-mot-de-passe-erreur' : ''; ?>"<?php echo isset( $icademie_errors['mot_de_passe'] ) ? ' aria-invalid="true"' : ''; ?>>
					<span id="icademie-comptes-mot-de-passe-aide" class="icademie-comptes__aide">
						<?php esc_html_e( 'Pour vérifier qu\'il s\'agit bien de vous. Sensible à la casse (majuscules et minuscules).', 'icademie-comptes' ); ?>
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Mot de passe oublié ?', 'icademie-comptes' ); ?></a>
					</span>
					<?php if ( isset( $icademie_errors['mot_de_passe'] ) ) : ?>
						<span id="icademie-comptes-mot-de-passe-erreur" class="icademie-comptes__erreur"><?php echo esc_html( $icademie_errors['mot_de_passe'] ); ?></span>
					<?php endif; ?>
				</p>

				<p class="form-row<?php echo isset( $icademie_errors['confirmation'] ) ? ' woocommerce-invalid' : ''; ?>">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox" for="icademie-comptes-confirmation">
						<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="confirmation" id="icademie-comptes-confirmation" value="1" required aria-required="true"<?php echo isset( $icademie_errors['confirmation'] ) ? ' aria-invalid="true" aria-describedby="icademie-comptes-confirmation-erreur"' : ''; ?>>
						<span><?php esc_html_e( 'Je comprends que la suppression de mon compte est définitive.', 'icademie-comptes' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Obligatoire', 'icademie-comptes' ); ?></span></span>
					</label>
					<?php if ( isset( $icademie_errors['confirmation'] ) ) : ?>
						<span id="icademie-comptes-confirmation-erreur" class="icademie-comptes__erreur"><?php echo esc_html( $icademie_errors['confirmation'] ); ?></span>
					<?php endif; ?>
				</p>

				<p class="icademie-comptes__actions">
					<button type="submit" class="woocommerce-Button button icademie-comptes__supprimer"><?php esc_html_e( 'Supprimer définitivement mon compte', 'icademie-comptes' ); ?></button>
				</p>
				<p class="icademie-comptes__legende"><?php esc_html_e( 'Les champs marqués d\'un astérisque (*) sont obligatoires.', 'icademie-comptes' ); ?></p>
			</form>
		<?php endif; ?>
	</section>
</div>
