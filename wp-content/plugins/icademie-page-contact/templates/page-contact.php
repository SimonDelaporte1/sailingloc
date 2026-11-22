<?php
/**
 * Contenu de la page Contact (bloc « Page contact »).
 *
 * Zones 2 à 8 du zoning « Contact · Ordinateur 1440 px » : fil d'Ariane, en-tête, aide rapide,
 * formulaire, coordonnées, plan d'accès et bandeau d'assistance. L'en-tête du site (zone 1) et le
 * pied de page (zone 9) sont ceux du thème.
 *
 * @package Icademie_Page_Contact
 *
 * @var array $data Données préparées par Icademie_Page_Contact::render_block().
 */

defined( 'ABSPATH' ) || exit;

$form     = $data['form'];
$values   = $form['values'];
$errors   = $form['errors'];
$contact  = $data['contact'];
$profile  = $values['profil'];
$is_other = 'autre' === $profile;

// Encadré « Coordonnées » seulement s'il a quelque chose à afficher ; sans coordonnées ni plan,
// pas de colonne : le formulaire prend toute la largeur.
$has_details = '' !== $contact['phone'] || '' !== $contact['email'] || '' !== $contact['chat'] || '' !== $contact['address'] || $contact['social'];
$has_sidebar = $has_details || $contact['map'];

// Erreurs de champ (hors erreur générale du formulaire), dans l'ordre du formulaire.
$field_errors = array_diff_key( $errors, array( '_form' => true ) );

// Élément qui reçoit le focus depuis le résumé des erreurs.
$error_targets = array(
	'profil'  => 'icademie-contact-profil-' . ( isset( $form['profiles'][ $profile ] ) ? $profile : 'locataire' ),
	'fichier' => 'icademie-contact-fichier',
);

/**
 * Attributs d'un champ : état invalide et descriptions (aide, puis erreur).
 *
 * @param string $key     Clé du champ.
 * @param string $hint_id ID du texte d'aide du champ.
 * @return string Attributs échappés.
 */
$field_aria = static function ( $key, $hint_id = '' ) use ( $errors ) {
	$ids  = array();
	$html = '';

	if ( '' !== $hint_id ) {
		$ids[] = $hint_id;
	}

	if ( isset( $errors[ $key ] ) ) {
		$ids[] = 'icademie-contact-' . $key . '-erreur';
		$html .= ' aria-invalid="true"';
	}

	if ( $ids ) {
		$html .= ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"';
	}

	return $html;
};

/**
 * Message d'erreur d'un champ, sous le champ (vide et masqué sans erreur : le script le remplit).
 *
 * @param string $key Clé du champ.
 */
$field_error = static function ( $key ) use ( $errors ) {
	$message = isset( $errors[ $key ] ) ? $errors[ $key ] : '';

	printf(
		'<p class="icademie-contact__error" id="%1$s"%2$s>%3$s<span>%4$s</span></p>',
		esc_attr( 'icademie-contact-' . $key . '-erreur' ),
		'' === $message ? ' hidden' : '',
		Icademie_Page_Contact::icon( 'alert', 16 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne.
		esc_html( $message )
	);
};

$required = '<span class="icademie-contact__required" aria-hidden="true">*</span>';
?>
<div class="icademie-contact__inner">

	<?php if ( $data['missing'] ) : ?>
		<p class="icademie-contact__setup">
			<?php
			printf(
				/* translators: 1: liste des réglages manquants, 2: lien vers les réglages. */
				esc_html__( 'Visible uniquement par les administrateurs : à compléter dans %2$s : %1$s.', 'icademie-page-contact' ),
				esc_html( implode( ', ', $data['missing'] ) ),
				'<a href="' . esc_url( admin_url( 'options-general.php?page=' . Icademie_Page_Contact_Settings::PAGE ) ) . '">' . esc_html__( 'Réglages › Page contact', 'icademie-page-contact' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php /* Zone 2 : fil d'Ariane. */ ?>
	<nav class="icademie-contact__breadcrumb" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'icademie-page-contact' ); ?>">
		<ol>
			<?php foreach ( $data['breadcrumb'] as $index => $item ) : ?>
				<li>
					<?php if ( $index > 0 ) : ?>
						<span class="icademie-contact__breadcrumb-sep" aria-hidden="true">›</span>
					<?php endif; ?>
					<?php if ( '' !== $item['url'] ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<?php else : ?>
						<span aria-current="page"><?php echo esc_html( $item['label'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php /* Zone 3 : en-tête. */ ?>
	<header class="icademie-contact__header">
		<div class="icademie-contact__header-text">
			<h1 class="icademie-contact__title"><?php esc_html_e( 'Contactez-nous', 'icademie-page-contact' ); ?></h1>
			<p class="icademie-contact__intro"><?php esc_html_e( 'Une question sur une réservation, votre annonce ou votre compte ? L\'équipe vous répond.', 'icademie-page-contact' ); ?></p>
		</div>
		<div class="icademie-contact__header-visual">
			<?php
			// Image mise en avant de la page, sinon illustration.
			echo $data['visual'] ? $data['visual'] : Icademie_Page_Contact::illustration(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise <img> de WordPress ou SVG interne.
			?>
		</div>
	</header>

	<?php /* Zone 4 : aide rapide. */ ?>
	<section class="icademie-contact__panel icademie-contact__help" aria-labelledby="icademie-contact-aide-titre">
		<div class="icademie-contact__panel-head">
			<h2 class="icademie-contact__panel-title" id="icademie-contact-aide-titre"><?php esc_html_e( 'Aide rapide', 'icademie-page-contact' ); ?></h2>
			<p class="icademie-contact__panel-desc"><?php esc_html_e( 'Avant de nous écrire : votre réponse se trouve peut-être déjà dans le centre d\'aide.', 'icademie-page-contact' ); ?></p>
		</div>

		<div class="icademie-contact__search-row">
			<form class="icademie-contact__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="icademie-contact__sr" for="icademie-contact-recherche"><?php esc_html_e( 'Rechercher dans le centre d\'aide', 'icademie-page-contact' ); ?></label>
				<input class="icademie-contact__input" type="search" id="icademie-contact-recherche" name="s" placeholder="<?php esc_attr_e( 'Rechercher une réponse : caution, annulation, assurance…', 'icademie-page-contact' ); ?>">
				<button class="icademie-contact__search-button" type="submit">
					<?php echo Icademie_Page_Contact::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
					<span class="icademie-contact__sr"><?php esc_html_e( 'Rechercher', 'icademie-page-contact' ); ?></span>
				</button>
			</form>
			<?php if ( '' !== $data['help_url'] ) : ?>
				<a class="icademie-contact__button icademie-contact__button--secondary" href="<?php echo esc_url( $data['help_url'] ); ?>"><?php esc_html_e( 'Centre d\'aide complet', 'icademie-page-contact' ); ?></a>
			<?php endif; ?>
		</div>

		<ul class="icademie-contact__topics">
			<?php foreach ( $data['topics'] as $topic ) : ?>
				<li>
					<a class="icademie-contact__topic" href="<?php echo esc_url( $topic['url'] ); ?>">
						<span class="icademie-contact__topic-icon"><?php echo Icademie_Page_Contact::icon( $topic['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
						<span class="icademie-contact__topic-title"><?php echo esc_html( $topic['label'] ); ?></span>
						<span class="icademie-contact__topic-meta"><?php esc_html_e( 'Questions fréquentes', 'icademie-page-contact' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<div class="icademie-contact__columns">

		<?php /* Zone 5 : formulaire de contact. */ ?>
		<div class="icademie-contact__panel icademie-contact__panel--form" id="<?php echo esc_attr( Icademie_Page_Contact_Form::ANCHOR ); ?>">
			<div class="icademie-contact__panel-head">
				<h2 class="icademie-contact__panel-title" id="icademie-contact-formulaire-titre"><?php esc_html_e( 'Formulaire de contact', 'icademie-page-contact' ); ?></h2>
				<p class="icademie-contact__panel-desc"><?php esc_html_e( 'Un accusé de réception vous est envoyé par e-mail dès l\'envoi.', 'icademie-page-contact' ); ?></p>
			</div>

			<?php if ( $form['sent'] ) : ?>

				<div class="icademie-contact__notice icademie-contact__notice--success" role="status" tabindex="-1" data-focus>
					<?php echo Icademie_Page_Contact::icon( 'check', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
					<div class="icademie-contact__notice-body">
						<p class="icademie-contact__notice-title"><?php esc_html_e( 'Merci, votre message a bien été envoyé.', 'icademie-page-contact' ); ?></p>
						<p>
							<?php esc_html_e( 'Un accusé de réception vient de vous être adressé par e-mail. Pensez à vérifier vos courriers indésirables si vous ne le voyez pas.', 'icademie-page-contact' ); ?>
							<?php if ( '' !== $contact['delay'] ) : ?>
								<?php echo esc_html( rtrim( $contact['delay'], '. ' ) . '.' ); ?>
							<?php endif; ?>
						</p>
						<p><a href="<?php echo esc_url( $form['page_url'] . '#' . Icademie_Page_Contact_Form::ANCHOR ); ?>"><?php esc_html_e( 'Envoyer un autre message', 'icademie-page-contact' ); ?></a></p>
					</div>
				</div>

			<?php else : ?>

				<?php if ( isset( $errors['_form'] ) ) : ?>
					<div class="icademie-contact__notice icademie-contact__notice--error" role="alert" tabindex="-1" data-focus>
						<?php echo Icademie_Page_Contact::icon( 'alert', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
						<div class="icademie-contact__notice-body">
							<p><?php echo esc_html( $errors['_form'] ); ?></p>
						</div>
					</div>
				<?php endif; ?>

				<div class="icademie-contact__notice icademie-contact__notice--error" role="alert" tabindex="-1" data-summary<?php echo $field_errors ? ' data-focus' : ' hidden'; ?>>
					<?php echo Icademie_Page_Contact::icon( 'alert', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
					<div class="icademie-contact__notice-body">
						<p class="icademie-contact__notice-title" data-summary-title>
							<?php
							$count = count( $field_errors );
							/* translators: %d: nombre d'erreurs. */
							echo esc_html( sprintf( _n( 'Le formulaire contient une erreur :', 'Le formulaire contient %d erreurs :', max( 1, $count ), 'icademie-page-contact' ), $count ) );
							?>
						</p>
						<ul>
							<?php foreach ( $field_errors as $key => $message ) : ?>
								<li><a href="#<?php echo esc_attr( isset( $error_targets[ $key ] ) ? $error_targets[ $key ] : 'icademie-contact-' . $key ); ?>"><?php echo esc_html( $message ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>

				<form class="icademie-contact__form" method="post" action="<?php echo esc_url( $form['action'] ); ?>" enctype="multipart/form-data" aria-labelledby="icademie-contact-formulaire-titre" data-config="<?php echo esc_attr( wp_json_encode( $form['config'] ) ); ?>">

					<fieldset class="icademie-contact__field icademie-contact__fieldset" data-field="profil">
						<legend class="icademie-contact__label"><?php esc_html_e( 'Vous êtes', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></legend>
						<div class="icademie-contact__choices">
							<?php foreach ( $form['profiles'] as $key => $label ) : ?>
								<label class="icademie-contact__choice">
									<input type="radio" id="<?php echo esc_attr( 'icademie-contact-profil-' . $key ); ?>" name="icademie_contact[profil]" value="<?php echo esc_attr( $key ); ?>"<?php checked( $profile, $key ); ?> required<?php echo $field_aria( 'profil' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<?php $field_error( 'profil' ); ?>
					</fieldset>

					<div class="icademie-contact__grid">
						<div class="icademie-contact__field" data-field="nom">
							<label class="icademie-contact__label" for="icademie-contact-nom"><?php esc_html_e( 'Nom', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></label>
							<input class="icademie-contact__input" type="text" id="icademie-contact-nom" name="icademie_contact[nom]" value="<?php echo esc_attr( $values['nom'] ); ?>" autocomplete="family-name" maxlength="<?php echo esc_attr( Icademie_Page_Contact_Form::NAME_MAX ); ?>" required<?php echo $field_aria( 'nom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<?php $field_error( 'nom' ); ?>
						</div>

						<div class="icademie-contact__field" data-field="prenom">
							<label class="icademie-contact__label" for="icademie-contact-prenom"><?php esc_html_e( 'Prénom', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></label>
							<input class="icademie-contact__input" type="text" id="icademie-contact-prenom" name="icademie_contact[prenom]" value="<?php echo esc_attr( $values['prenom'] ); ?>" autocomplete="given-name" maxlength="<?php echo esc_attr( Icademie_Page_Contact_Form::NAME_MAX ); ?>" required<?php echo $field_aria( 'prenom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<?php $field_error( 'prenom' ); ?>
						</div>

						<div class="icademie-contact__field" data-field="email">
							<label class="icademie-contact__label" for="icademie-contact-email"><?php esc_html_e( 'E-mail', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></label>
							<input class="icademie-contact__input" type="email" id="icademie-contact-email" name="icademie_contact[email]" value="<?php echo esc_attr( $values['email'] ); ?>" autocomplete="email" spellcheck="false" maxlength="254" required<?php echo $field_aria( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<?php $field_error( 'email' ); ?>
						</div>

						<div class="icademie-contact__field" data-field="telephone">
							<label class="icademie-contact__label" for="icademie-contact-telephone"><?php esc_html_e( 'Téléphone (facultatif)', 'icademie-page-contact' ); ?></label>
							<input class="icademie-contact__input" type="tel" id="icademie-contact-telephone" name="icademie_contact[telephone]" value="<?php echo esc_attr( $values['telephone'] ); ?>" autocomplete="tel" maxlength="30"<?php echo $field_aria( 'telephone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<?php $field_error( 'telephone' ); ?>
						</div>

						<div class="icademie-contact__field" data-field="objet">
							<label class="icademie-contact__label" for="icademie-contact-objet"><?php esc_html_e( 'Objet de la demande', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></label>
							<select class="icademie-contact__input icademie-contact__select" id="icademie-contact-objet" name="icademie_contact[objet]" required<?php echo $field_aria( 'objet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
								<option value=""><?php esc_html_e( 'Choisir un sujet', 'icademie-page-contact' ); ?></option>
								<?php
								// Sans JavaScript, tous les objets sont proposés, regroupés par profil ; le script ne garde que ceux du profil choisi.
								foreach ( $form['subjects'] as $subject_profile => $subjects ) :
									?>
									<optgroup label="<?php echo esc_attr( $form['profiles'][ $subject_profile ] ); ?>" data-profil="<?php echo esc_attr( $subject_profile ); ?>">
										<?php foreach ( $subjects as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $subject_profile === $profile && $key === $values['objet'] ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<?php $field_error( 'objet' ); ?>
						</div>

						<div class="icademie-contact__field" data-field="reservation"<?php echo $is_other ? ' hidden' : ''; ?>>
							<label class="icademie-contact__label" for="icademie-contact-reservation"><?php echo esc_html( $form['reservation_labels'][ $is_other || ! isset( $form['reservation_labels'][ $profile ] ) ? 'locataire' : $profile ] ); ?></label>
							<input class="icademie-contact__input" type="text" id="icademie-contact-reservation" name="icademie_contact[reservation]" value="<?php echo esc_attr( $values['reservation'] ); ?>" autocomplete="off" maxlength="<?php echo esc_attr( Icademie_Page_Contact_Form::RESERVATION_MAX ); ?>"<?php echo $is_other ? ' disabled' : ''; ?><?php echo $field_aria( 'reservation' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<?php $field_error( 'reservation' ); ?>
						</div>
					</div>

					<div class="icademie-contact__field" data-field="message">
						<label class="icademie-contact__label" for="icademie-contact-message"><?php esc_html_e( 'Message', 'icademie-page-contact' ); ?> <?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></label>
						<textarea class="icademie-contact__input icademie-contact__textarea" id="icademie-contact-message" name="icademie_contact[message]" rows="6" maxlength="<?php echo esc_attr( Icademie_Page_Contact_Form::MESSAGE_MAX ); ?>" placeholder="<?php esc_attr_e( 'Décrivez votre demande', 'icademie-page-contact' ); ?>" required<?php echo $field_aria( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>><?php echo esc_textarea( $values['message'] ); ?></textarea>
						<?php $field_error( 'message' ); ?>
					</div>

					<div class="icademie-contact__field" data-field="fichier">
						<div class="icademie-contact__file">
							<input class="icademie-contact__file-input" type="file" id="icademie-contact-fichier" name="<?php echo esc_attr( Icademie_Page_Contact_Form::FILE_FIELD ); ?>" accept="<?php echo esc_attr( $form['accept'] ); ?>"<?php echo $field_aria( 'fichier', 'icademie-contact-fichier-aide' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<label class="icademie-contact__button icademie-contact__button--secondary icademie-contact__file-button" for="icademie-contact-fichier">
								<?php echo Icademie_Page_Contact::icon( 'paperclip', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
								<span data-file-label><?php esc_html_e( 'Ajouter une pièce jointe', 'icademie-page-contact' ); ?></span>
							</label>
							<span class="icademie-contact__hint" id="icademie-contact-fichier-aide">
								<?php
								/* translators: %s: taille maximale (« 5 Mo »). */
								echo esc_html( sprintf( __( 'Facultatif · photo (JPG, PNG, WebP) ou PDF · %s maximum', 'icademie-page-contact' ), $form['max_size_label'] ) );
								?>
							</span>
							<span class="icademie-contact__file-chosen" data-file-chosen hidden>
								<span data-file-name></span>
								<button type="button" class="icademie-contact__file-remove" data-file-remove>
									<?php echo Icademie_Page_Contact::icon( 'close', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
									<span class="icademie-contact__sr"><?php esc_html_e( 'Retirer la pièce jointe', 'icademie-page-contact' ); ?></span>
								</button>
							</span>
						</div>
						<?php if ( $form['file_dropped'] ) : ?>
							<p class="icademie-contact__hint icademie-contact__hint--warning"><?php esc_html_e( 'Par sécurité, la pièce jointe n\'est pas conservée quand le formulaire est refusé : sélectionnez-la de nouveau.', 'icademie-page-contact' ); ?></p>
						<?php endif; ?>
						<?php $field_error( 'fichier' ); ?>
					</div>

					<div class="icademie-contact__field" data-field="rgpd">
						<div class="icademie-contact__consent">
							<input type="checkbox" id="icademie-contact-rgpd" name="icademie_contact[rgpd]" value="1"<?php checked( $values['rgpd'], '1' ); ?> required<?php echo $field_aria( 'rgpd', 'icademie-contact-rgpd-aide' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributs échappés. ?>>
							<label for="icademie-contact-rgpd">
								<?php
								/* translators: %s: nom du site. */
								echo esc_html( sprintf( __( 'J\'accepte que mes données soient utilisées par %s pour traiter ma demande et y répondre.', 'icademie-page-contact' ), $contact['site'] ) );
								?>
								<?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?>
							</label>
						</div>
						<p class="icademie-contact__hint icademie-contact__consent-note" id="icademie-contact-rgpd-aide">
							<?php esc_html_e( 'Vos données ne sont pas enregistrées sur le site : elles nous parviennent par e-mail et ne servent qu\'à vous répondre.', 'icademie-page-contact' ); ?>
							<?php if ( $form['privacy_url'] ) : ?>
								<a href="<?php echo esc_url( $form['privacy_url'] ); ?>"><?php esc_html_e( 'Politique de confidentialité', 'icademie-page-contact' ); ?></a>
							<?php endif; ?>
						</p>
						<?php $field_error( 'rgpd' ); ?>
					</div>

					<div class="icademie-contact__actions">
						<button type="submit" class="icademie-contact__button icademie-contact__button--large"><?php esc_html_e( 'Envoyer le message', 'icademie-page-contact' ); ?></button>
						<p class="icademie-contact__hint"><?php esc_html_e( '* Champs obligatoires', 'icademie-page-contact' ); ?></p>
					</div>

					<input type="hidden" name="<?php echo esc_attr( Icademie_Page_Contact_Form::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $form['nonce'] ); ?>">
					<input type="hidden" name="icademie_contact[jeton]" value="<?php echo esc_attr( $form['token'] ); ?>">

					<?php // Champ piège : invisible pour les visiteurs, rempli par les robots. ?>
					<div class="icademie-contact__trap" aria-hidden="true">
						<label for="icademie-contact-site"><?php esc_html_e( 'Laissez ce champ vide', 'icademie-page-contact' ); ?></label>
						<input type="text" id="icademie-contact-site" name="icademie_contact[site]" value="" tabindex="-1" autocomplete="off">
					</div>
				</form>

			<?php endif; ?>
		</div>

		<?php if ( $has_sidebar ) : ?>
			<aside class="icademie-contact__sidebar" aria-label="<?php esc_attr_e( 'Coordonnées', 'icademie-page-contact' ); ?>">

				<?php /* Zone 6 : coordonnées. */ ?>
				<?php if ( $has_details ) : ?>
					<section class="icademie-contact__panel icademie-contact__panel--details" aria-labelledby="icademie-contact-coordonnees-titre">
						<div class="icademie-contact__panel-head">
							<h2 class="icademie-contact__panel-title" id="icademie-contact-coordonnees-titre"><?php esc_html_e( 'Coordonnées', 'icademie-page-contact' ); ?></h2>
							<p class="icademie-contact__panel-desc"><?php esc_html_e( 'Autres moyens de nous joindre', 'icademie-page-contact' ); ?></p>
						</div>

						<?php if ( '' !== $contact['phone'] || '' !== $contact['email'] ) : ?>
							<ul class="icademie-contact__channels">
								<?php if ( '' !== $contact['phone'] ) : ?>
									<li class="icademie-contact__channel">
										<span class="icademie-contact__channel-icon"><?php echo Icademie_Page_Contact::icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
										<span class="icademie-contact__channel-text">
											<a class="icademie-contact__channel-title" href="<?php echo esc_url( $contact['phone_href'], array( 'tel' ) ); ?>"><span class="icademie-contact__sr"><?php esc_html_e( 'Téléphone du service client :', 'icademie-page-contact' ); ?> </span><?php echo esc_html( $contact['phone'] ); ?></a>
											<?php if ( '' !== $contact['hours'] ) : ?>
												<span class="icademie-contact__channel-meta"><?php echo esc_html( $contact['hours'] ); ?></span>
											<?php endif; ?>
										</span>
									</li>
								<?php endif; ?>
								<?php if ( '' !== $contact['email'] ) : ?>
									<li class="icademie-contact__channel">
										<span class="icademie-contact__channel-icon"><?php echo Icademie_Page_Contact::icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
										<span class="icademie-contact__channel-text">
											<a class="icademie-contact__channel-title" href="<?php echo esc_url( 'mailto:' . antispambot( $contact['email'] ), array( 'mailto' ) ); ?>"><span class="icademie-contact__sr"><?php esc_html_e( 'E-mail :', 'icademie-page-contact' ); ?> </span><?php echo esc_html( antispambot( $contact['email'] ) ); ?></a>
											<?php if ( '' !== $contact['delay'] ) : ?>
												<span class="icademie-contact__channel-meta"><?php echo esc_html( $contact['delay'] ); ?></span>
											<?php endif; ?>
										</span>
									</li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>

						<?php if ( '' !== $contact['chat'] ) : ?>
							<a class="icademie-contact__button icademie-contact__chat" href="<?php echo esc_url( $contact['chat'] ); ?>">
								<?php echo Icademie_Page_Contact::icon( 'chat', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
								<?php esc_html_e( 'Ouvrir le chat en direct', 'icademie-page-contact' ); ?>
							</a>
						<?php endif; ?>

						<?php if ( '' !== $contact['address'] ) : ?>
							<div class="icademie-contact__channel">
								<span class="icademie-contact__channel-icon"><?php echo Icademie_Page_Contact::icon( 'pin', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
								<span class="icademie-contact__channel-text">
									<span class="icademie-contact__channel-title">
										<?php
										/* translators: %s: nom du site. */
										echo esc_html( sprintf( __( 'Siège de %s', 'icademie-page-contact' ), $contact['site'] ) );
										?>
									</span>
									<address class="icademie-contact__channel-meta"><?php echo nl2br( esc_html( $contact['address'] ), false ); ?></address>
								</span>
							</div>
						<?php endif; ?>

						<?php if ( $contact['social'] ) : ?>
							<div class="icademie-contact__social">
								<span class="icademie-contact__social-label" id="icademie-contact-reseaux"><?php esc_html_e( 'Réseaux sociaux', 'icademie-page-contact' ); ?></span>
								<ul aria-labelledby="icademie-contact-reseaux">
									<?php foreach ( $contact['social'] as $network ) : ?>
										<li>
											<a href="<?php echo esc_url( $network['url'] ); ?>">
												<?php echo $network['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG du bloc « Icônes de réseaux sociaux » de WordPress. ?>
												<span class="icademie-contact__sr"><?php echo esc_html( $network['label'] ); ?></span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php /* Zone 7 : plan d'accès. */ ?>
				<?php if ( $contact['map'] ) : ?>
					<section class="icademie-contact__panel icademie-contact__panel--map" aria-labelledby="icademie-contact-plan-titre">
						<div class="icademie-contact__panel-head">
							<h2 class="icademie-contact__panel-title" id="icademie-contact-plan-titre"><?php esc_html_e( 'Plan d\'accès', 'icademie-page-contact' ); ?></h2>
							<p class="icademie-contact__panel-desc">
								<?php
								/* translators: %s: nom du site. */
								echo esc_html( sprintf( __( 'Siège de %s', 'icademie-page-contact' ), $contact['site'] ) );
								?>
							</p>
						</div>
						<?php
						// Carte chargée au clic par le script (data-embed), titre lu par les lecteurs d'écran.
						$map_attributes = '';

						if ( '' !== $contact['map']['embed'] ) {
							/* translators: %s: nom du site. */
							$map_title      = sprintf( __( 'Carte : siège de %s', 'icademie-page-contact' ), $contact['site'] );
							$map_attributes = ' data-embed="' . esc_url( $contact['map']['embed'] ) . '" data-title="' . esc_attr( $map_title ) . '"';
						}
						?>
						<div class="icademie-contact__map"<?php echo $map_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Valeurs échappées ci-dessus. ?>>
							<div class="icademie-contact__map-placeholder">
								<span class="icademie-contact__map-pin"><?php echo Icademie_Page_Contact::icon( 'pin', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
								<span class="icademie-contact__chip"><?php esc_html_e( 'Carte interactive', 'icademie-page-contact' ); ?></span>
								<?php if ( '' !== $contact['map']['embed'] ) : ?>
									<button type="button" class="icademie-contact__button icademie-contact__button--secondary icademie-contact__map-button" data-map-load hidden><?php esc_html_e( 'Afficher la carte', 'icademie-page-contact' ); ?></button>
									<p class="icademie-contact__map-note"><?php esc_html_e( 'Carte fournie par OpenStreetMap, chargée seulement à votre demande.', 'icademie-page-contact' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<a class="icademie-contact__map-link" href="<?php echo esc_url( $contact['map']['link'] ); ?>">
							<?php esc_html_e( 'Voir le plan sur OpenStreetMap', 'icademie-page-contact' ); ?>
							<?php echo Icademie_Page_Contact::icon( 'external', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
						</a>
					</section>
				<?php endif; ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php /* Zone 8 : assistance pendant la location, toujours visible en bas de l'écran. */ ?>
	<section class="icademie-contact__assistance" aria-labelledby="icademie-contact-assistance-titre">
		<span class="icademie-contact__assistance-icon"><?php echo Icademie_Page_Contact::icon( 'lifebuoy', 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?></span>
		<div class="icademie-contact__assistance-text">
			<h2 class="icademie-contact__assistance-title" id="icademie-contact-assistance-titre"><?php esc_html_e( 'Assistance pendant la location', 'icademie-page-contact' ); ?></h2>
			<p>
				<?php if ( '' !== $contact['assistance'] ) : ?>
					<?php esc_html_e( 'Problème pendant une location : ligne d\'assistance dédiée au', 'icademie-page-contact' ); ?>
					<a href="<?php echo esc_url( $contact['assistance_href'], array( 'tel' ) ); ?>"><?php echo esc_html( $contact['assistance'] ); ?></a>.
				<?php endif; ?>
				<strong>
					<?php esc_html_e( 'Urgence en mer :', 'icademie-page-contact' ); ?>
					<a href="tel:196">196</a>
					<?php esc_html_e( '(CROSS) ou VHF canal 16.', 'icademie-page-contact' ); ?>
				</strong>
			</p>
		</div>
		<?php if ( '' !== $contact['assistance'] ) : ?>
			<a class="icademie-contact__button icademie-contact__button--alert" href="<?php echo esc_url( $contact['assistance_href'], array( 'tel' ) ); ?>">
				<?php echo Icademie_Page_Contact::icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG interne. ?>
				<?php esc_html_e( 'Appeler l\'assistance', 'icademie-page-contact' ); ?>
			</a>
		<?php endif; ?>
	</section>
</div>
