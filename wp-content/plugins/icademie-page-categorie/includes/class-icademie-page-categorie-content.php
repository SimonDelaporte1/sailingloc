<?php
/**
 * Contenu éditorial, questions fréquentes et maillage interne de chaque catégorie.
 *
 * @package Icademie_Page_Categorie
 */

defined( 'ABSPATH' ) || exit;

/**
 * Champs ajoutés à la fiche des catégories de produits (Produits › Catégories › Modifier).
 *
 * - Zone 9 : un titre et un bloc de contenu (éditeur de texte), puis 5 questions avec leurs réponses.
 * - Zone 10 : les liens du maillage interne (libellé et adresse), dans l'ordre choisi.
 *
 * Les adresses sont saisies à la main, comme sur Réglages › Page d'accueil : adresse complète, ou
 * chemin d'une page du site. Une zone vide n'est pas affichée sur le site.
 */
final class Icademie_Page_Categorie_Content {

	/** Métadonnées de la catégorie. */
	const META_TITLE   = 'icademie_categorie_titre';
	const META_CONTENT = 'icademie_categorie_contenu';
	const META_FAQ     = 'icademie_categorie_faq';
	const META_LINKS   = 'icademie_categorie_maillage';

	/** Nombre de questions. */
	const FAQ_COUNT = 5;

	/** Nombre maximal de liens du maillage. */
	const LINKS_LIMIT = 12;

	/** Nom des champs envoyés. */
	const FIELD = 'icademie_categorie';

	/** Identifiant de l'éditeur du contenu (lettres minuscules et soulignés seulement). */
	const EDITOR_ID = 'icademie_categorie_contenu';

	/** Erreurs d'enregistrement affichées après la redirection (suivi de l'ID de l'utilisateur). */
	const NOTICE_TRANSIENT = 'icademie_page_categorie_erreurs_';

	/**
	 * Branche les champs et les enregistrements.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_form_note' ), 30 );
		add_action( 'product_cat_edit_form', array( __CLASS__, 'render_fields' ), 20 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_errors' ) );
	}

	/**
	 * Déclare les métadonnées des catégories.
	 *
	 * Le nettoyage est fait à l'enregistrement du formulaire (voir save()).
	 */
	public static function register_meta() {
		register_term_meta(
			'product_cat',
			self::META_TITLE,
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
		register_term_meta(
			'product_cat',
			self::META_CONTENT,
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
		register_term_meta(
			'product_cat',
			self::META_FAQ,
			array(
				'type'   => 'array',
				'single' => true,
			)
		);
		register_term_meta(
			'product_cat',
			self::META_LINKS,
			array(
				'type'   => 'array',
				'single' => true,
			)
		);
	}

	/**
	 * Titre et texte du contenu éditorial.
	 *
	 * @param int $term_id Catégorie.
	 * @return string[] « title » et « content » (HTML enregistré, pas encore mis en forme).
	 */
	public static function editorial( $term_id ) {
		return array(
			'title'   => trim( (string) get_term_meta( (int) $term_id, self::META_TITLE, true ) ),
			'content' => trim( (string) get_term_meta( (int) $term_id, self::META_CONTENT, true ) ),
		);
	}

	/**
	 * Les cinq questions enregistrées (vides comprises), dans l'ordre.
	 *
	 * @param int $term_id Catégorie.
	 * @return array[] « question » et « answer ».
	 */
	public static function faq_slots( $term_id ) {
		$saved = get_term_meta( (int) $term_id, self::META_FAQ, true );
		$saved = is_array( $saved ) ? array_values( $saved ) : array();
		$slots = array();

		for ( $i = 0; $i < self::FAQ_COUNT; $i++ ) {
			$item    = isset( $saved[ $i ] ) && is_array( $saved[ $i ] ) ? $saved[ $i ] : array();
			$slots[] = array(
				'question' => isset( $item['question'] ) ? (string) $item['question'] : '',
				'answer'   => isset( $item['answer'] ) ? (string) $item['answer'] : '',
			);
		}

		return $slots;
	}

	/**
	 * Questions à afficher : celles qui ont une question et une réponse.
	 *
	 * @param int $term_id Catégorie.
	 * @return array[] « question » et « answer ».
	 */
	public static function faq( $term_id ) {
		return array_values(
			array_filter(
				self::faq_slots( $term_id ),
				static function ( $item ) {
					return '' !== trim( $item['question'] ) && '' !== trim( wp_strip_all_tags( $item['answer'] ) );
				}
			)
		);
	}

	/**
	 * Liens du maillage interne, dans l'ordre.
	 *
	 * @param int $term_id Catégorie.
	 * @return array[] « label » et « url ».
	 */
	public static function links( $term_id ) {
		$saved = get_term_meta( (int) $term_id, self::META_LINKS, true );
		$links = array();

		foreach ( is_array( $saved ) ? $saved : array() as $item ) {
			if ( is_array( $item ) && ! empty( $item['label'] ) && ! empty( $item['url'] ) ) {
				$links[] = array(
					'label' => (string) $item['label'],
					'url'   => (string) $item['url'],
				);
			}
		}

		return array_slice( $links, 0, self::LINKS_LIMIT );
	}

	/**
	 * Mise en forme d'un texte enregistré : paragraphes, typographie, codes courts et images
	 * adaptées à l'écran, comme le contenu d'une page.
	 *
	 * @param string $html Texte enregistré.
	 * @return string
	 */
	public static function format( $html ) {
		$html = shortcode_unautop( wpautop( wptexturize( convert_chars( $html ) ) ) );

		return wp_filter_content_tags( do_shortcode( $html ), 'icademie_page_categorie' );
	}

	/**
	 * Rappel dans le formulaire d'ajout d'une catégorie.
	 */
	public static function add_form_note() {
		?>
		<div class="form-field term-icademie-categorie-wrap">
			<p><?php esc_html_e( 'Contenu éditorial, questions fréquentes et maillage interne de la page de la catégorie : à remplir sur la fiche de la catégorie, une fois ajoutée (lien « Modifier » de la liste).', 'icademie-page-categorie' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Champs de la fiche d'une catégorie, sous les réglages de WooCommerce.
	 *
	 * @param WP_Term $term Catégorie.
	 */
	public static function render_fields( $term ) {
		$editorial = self::editorial( $term->term_id );
		$faq       = self::faq_slots( $term->term_id );
		$links     = self::links( $term->term_id );
		$field     = self::FIELD;

		// Une ligne vide à remplir, sous les liens existants.
		$rows = $links;

		if ( count( $rows ) < self::LINKS_LIMIT ) {
			$rows[] = array(
				'label' => '',
				'url'   => '',
			);
		}
		?>
		<div class="icademie-categorie-admin" id="icademie-categorie-page">
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>[champ]" value="1">

			<h2><?php esc_html_e( 'Page de la catégorie : contenu éditorial (zone 9)', 'icademie-page-categorie' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Affiché sous la liste des bateaux, sur la première page de la catégorie : le texte de présentation, puis les questions fréquentes en accordéon. Une partie vide n\'est pas affichée.', 'icademie-page-categorie' ); ?></p>

			<table class="form-table" role="presentation">
				<tr class="form-field">
					<th scope="row"><label for="icademie-categorie-titre"><?php esc_html_e( 'Titre du contenu', 'icademie-page-categorie' ); ?></label></th>
					<td>
						<input type="text" class="large-text" id="icademie-categorie-titre" name="<?php echo esc_attr( $field ); ?>[titre]" value="<?php echo esc_attr( $editorial['title'] ); ?>">
						<?php /* translators: %s: nom de la catégorie. */ ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Titre (H2) du texte, par exemple « Louer un bateau à %s entre particuliers ».', 'icademie-page-categorie' ), $term->name ) ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row"><label for="<?php echo esc_attr( self::EDITOR_ID ); ?>"><?php esc_html_e( 'Contenu', 'icademie-page-categorie' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							$editorial['content'],
							self::EDITOR_ID,
							array(
								'textarea_name' => $field . '[contenu]',
								'textarea_rows' => 12,
								'media_buttons' => true,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Texte de présentation de la destination, utile au référencement : sur téléphone, il est replié après quelques lignes (« Lire la suite »).', 'icademie-page-categorie' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Questions fréquentes', 'icademie-page-categorie' ); ?></h2>
			<?php /* translators: %d: nombre de questions. */ ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Jusqu\'à %d questions, affichées dans cet ordre. Une question sans réponse n\'est pas affichée.', 'icademie-page-categorie' ), self::FAQ_COUNT ) ); ?></p>

			<table class="form-table" role="presentation">
				<?php foreach ( $faq as $index => $item ) : ?>
					<?php $number = $index + 1; ?>
					<tr class="form-field">
						<?php /* translators: %d: numéro de la question. */ ?>
						<th scope="row"><label for="icademie-categorie-question-<?php echo (int) $number; ?>"><?php echo esc_html( sprintf( __( 'Question %d', 'icademie-page-categorie' ), $number ) ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="icademie-categorie-question-<?php echo (int) $number; ?>" name="<?php echo esc_attr( $field ); ?>[faq][<?php echo (int) $index; ?>][question]" value="<?php echo esc_attr( $item['question'] ); ?>">
							<?php /* translators: %d: numéro de la question. */ ?>
							<p><label for="icademie_categorie_reponse_<?php echo (int) $number; ?>"><?php echo esc_html( sprintf( __( 'Réponse %d', 'icademie-page-categorie' ), $number ) ); ?></label></p>
							<?php
							wp_editor(
								$item['answer'],
								'icademie_categorie_reponse_' . $number,
								array(
									'textarea_name' => $field . '[faq][' . $index . '][reponse]',
									'textarea_rows' => 4,
									'media_buttons' => false,
									'tinymce'       => false,
									'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li' ),
								)
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Page de la catégorie : maillage interne (zone 10)', 'icademie-page-categorie' ); ?></h2>
			<?php /* translators: %d: nombre maximal de liens. */ ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Liens vers les catégories, destinations ou pages voisines, affichés en bas de la page de la catégorie, dans cet ordre (%d au plus). Adresse complète (https://…) ou chemin d\'une page du site (/…). Les adresses des catégories sont proposées pendant la saisie.', 'icademie-page-categorie' ), self::LINKS_LIMIT ) ); ?></p>

			<table class="widefat striped icademie-categorie-links" data-icademie-links data-limit="<?php echo (int) self::LINKS_LIMIT; ?>">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Libellé', 'icademie-page-categorie' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Adresse', 'icademie-page-categorie' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Ordre et suppression', 'icademie-page-categorie' ); ?></span></th>
					</tr>
				</thead>
				<tbody data-links-body>
					<?php foreach ( $rows as $index => $link ) : ?>
						<?php self::render_link_row( (string) $index, $link['label'], $link['url'] ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" data-link-add hidden><?php esc_html_e( 'Ajouter un lien', 'icademie-page-categorie' ); ?></button>
			</p>

			<template data-link-template>
				<?php self::render_link_row( '__INDEX__', '', '' ); ?>
			</template>

			<datalist id="icademie-categorie-adresses">
				<?php foreach ( self::category_suggestions( $term ) as $suggestion ) : ?>
					<option value="<?php echo esc_attr( $suggestion['url'] ); ?>" label="<?php echo esc_attr( $suggestion['label'] ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
		</div>
		<?php
	}

	/**
	 * Ligne d'un lien du maillage.
	 *
	 * @param string $index Position (ou « __INDEX__ » dans le modèle).
	 * @param string $label Libellé.
	 * @param string $url   Adresse.
	 */
	private static function render_link_row( $index, $label, $url ) {
		$name = self::FIELD . '[liens][' . $index . ']';
		?>
		<tr data-link-row>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[libelle]" value="<?php echo esc_attr( $label ); ?>" aria-label="<?php esc_attr_e( 'Libellé du lien', 'icademie-page-categorie' ); ?>" data-link-label></td>
			<td><input type="text" class="widefat code" name="<?php echo esc_attr( $name ); ?>[adresse]" value="<?php echo esc_attr( $url ); ?>" aria-label="<?php esc_attr_e( 'Adresse du lien', 'icademie-page-categorie' ); ?>" list="icademie-categorie-adresses" inputmode="url" spellcheck="false" autocomplete="off" placeholder="https://…" data-link-url></td>
			<td class="icademie-categorie-links__actions">
				<button type="button" class="button-link" data-link-up hidden><?php esc_html_e( 'Monter', 'icademie-page-categorie' ); ?></button>
				<button type="button" class="button-link" data-link-down hidden><?php esc_html_e( 'Descendre', 'icademie-page-categorie' ); ?></button>
				<button type="button" class="button-link button-link-delete" data-link-remove hidden><?php esc_html_e( 'Retirer', 'icademie-page-categorie' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Adresses proposées pendant la saisie : les autres catégories de produits.
	 *
	 * @param WP_Term $current Catégorie modifiée.
	 * @return array[] « url » et « label ».
	 */
	private static function category_suggestions( $current ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'exclude'    => array( (int) $current->term_id, (int) get_option( 'default_product_cat' ) ),
				'number'     => 200,
			)
		);

		$suggestions = array();

		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				$suggestions[] = array(
					'url'   => $link,
					'label' => $term->name,
				);
			}
		}

		return $suggestions;
	}

	/**
	 * Enregistre les champs de la fiche d'une catégorie.
	 *
	 * WordPress a déjà vérifié le jeton du formulaire. La modification rapide de la liste des
	 * catégories n'envoie pas ces champs : elle ne les change pas.
	 *
	 * @param int $term_id Catégorie.
	 */
	public static function save( $term_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Jeton du formulaire de catégorie vérifié par WordPress.
		if ( ! isset( $_POST[ self::FIELD ]['champ'] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé champ par champ ci-dessous.
		$input = wp_unslash( $_POST[ self::FIELD ] );
		// phpcs:enable

		$input = is_array( $input ) ? $input : array();

		self::update( $term_id, self::META_TITLE, isset( $input['titre'] ) && is_string( $input['titre'] ) ? sanitize_text_field( $input['titre'] ) : '' );
		self::update( $term_id, self::META_CONTENT, isset( $input['contenu'] ) && is_string( $input['contenu'] ) ? self::clean_html( $input['contenu'] ) : '' );

		// Questions : cinq emplacements, gardés dans l'ordre.
		$faq   = array();
		$items = isset( $input['faq'] ) && is_array( $input['faq'] ) ? $input['faq'] : array();

		for ( $i = 0; $i < self::FAQ_COUNT; $i++ ) {
			$item  = isset( $items[ $i ] ) && is_array( $items[ $i ] ) ? $items[ $i ] : array();
			$faq[] = array(
				'question' => isset( $item['question'] ) && is_string( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '',
				'answer'   => isset( $item['reponse'] ) && is_string( $item['reponse'] ) ? self::clean_html( $item['reponse'] ) : '',
			);
		}

		$has_faq = array_filter(
			$faq,
			static function ( $item ) {
				return '' !== $item['question'] || '' !== $item['answer'];
			}
		);

		self::update( $term_id, self::META_FAQ, $has_faq ? $faq : '' );

		// Maillage : lignes complètes, dans l'ordre du formulaire.
		$links  = array();
		$errors = array();
		$rows   = isset( $input['liens'] ) && is_array( $input['liens'] ) ? array_values( $input['liens'] ) : array();

		foreach ( $rows as $position => $row ) {
			$row   = is_array( $row ) ? $row : array();
			$label = isset( $row['libelle'] ) && is_string( $row['libelle'] ) ? sanitize_text_field( $row['libelle'] ) : '';
			$value = isset( $row['adresse'] ) && is_string( $row['adresse'] ) ? trim( wp_strip_all_tags( $row['adresse'] ) ) : '';

			if ( '' === $label && '' === $value ) {
				continue;
			}

			$url = self::clean_link( $value );

			if ( '' === $label || '' === $url ) {
				$errors[] = '' === $label
					/* translators: %d: numéro de la ligne. */
					? sprintf( __( 'Maillage, ligne %d : libellé manquant, le lien n\'a pas été enregistré.', 'icademie-page-categorie' ), $position + 1 )
					: sprintf(
						/* translators: 1: numéro de la ligne, 2: adresse refusée. */
						__( 'Maillage, ligne %1$d : l\'adresse « %2$s » n\'est pas valide, le lien n\'a pas été enregistré. Indiquez une adresse complète (https://…) ou le chemin d\'une page du site (/…).', 'icademie-page-categorie' ),
						$position + 1,
						$value
					);
				continue;
			}

			if ( count( $links ) >= self::LINKS_LIMIT ) {
				/* translators: %d: nombre maximal de liens. */
				$errors[] = sprintf( __( 'Maillage : %d liens au plus, les suivants n\'ont pas été enregistrés.', 'icademie-page-categorie' ), self::LINKS_LIMIT );
				break;
			}

			$links[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		self::update( $term_id, self::META_LINKS, $links ? $links : '' );

		if ( $errors ) {
			set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $errors, 5 * MINUTE_IN_SECONDS );
		}

		Icademie_Page_Categorie::clear_term_cache( $term_id );
	}

	/**
	 * Enregistre une métadonnée, ou la supprime si elle est vide.
	 *
	 * @param int          $term_id Catégorie.
	 * @param string       $key     Clé.
	 * @param string|array $value   Valeur.
	 */
	private static function update( $term_id, $key, $value ) {
		if ( '' === $value || array() === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}

	/**
	 * Texte mis en forme : balises autorisées dans les articles, sauf pour les utilisateurs qui
	 * peuvent publier du HTML sans filtre (administrateurs), comme pour le contenu d'une page.
	 *
	 * @param string $html Texte envoyé.
	 * @return string
	 */
	private static function clean_html( $html ) {
		$html = trim( $html );

		return current_user_can( 'unfiltered_html' ) ? $html : wp_kses_post( $html );
	}

	/**
	 * Nettoie l'adresse d'un lien (même règle que Réglages › Page d'accueil).
	 *
	 * Acceptées : une adresse complète en http ou https, ou le chemin d'une page du site
	 * (« /aide/paiement/ »), complété avec l'adresse du site. Une adresse sans « http »
	 * (« www.exemple.fr ») est complétée par WordPress. Le nom du site doit être un vrai nom de
	 * domaine : « pas une adresse » est refusé (WordPress en ferait « http://pas%20une%20adresse »).
	 *
	 * @param string $value Adresse saisie.
	 * @return string Adresse, ou vide si elle n'est pas valide.
	 */
	public static function clean_link( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		// Chemin d'une page du site (mais pas « //exemple.fr », adresse sans protocole).
		if ( '/' === $value[0] && ( ! isset( $value[1] ) || '/' !== $value[1] ) ) {
			$value = home_url( $value );
		}

		$url  = esc_url_raw( $value, array( 'http', 'https' ) );
		$host = '' !== $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : '';

		return self::is_host( $host ) ? $url : '';
	}

	/**
	 * Nom de site valide : nom de domaine (« exemple.fr »), adresse IP, ou nom du site lui-même.
	 *
	 * @param string $host Nom lu dans l'adresse.
	 * @return bool
	 */
	private static function is_host( $host ) {
		if ( '' === $host ) {
			return false;
		}

		if ( strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		// Lettres accentuées acceptées (noms de domaine internationaux).
		return (bool) preg_match( '/^(?:[a-z0-9\\x80-\\xff](?:[a-z0-9\\x80-\\xff-]*[a-z0-9\\x80-\\xff])?\\.)+[a-z\\x80-\\xff][a-z0-9\\x80-\\xff-]*[a-z0-9\\x80-\\xff]$/i', $host );
	}

	/**
	 * Erreurs du dernier enregistrement (liens refusés), en haut de la fiche de la catégorie.
	 */
	public static function render_errors() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
			return;
		}

		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$errors = get_transient( $key );

		if ( ! $errors || ! is_array( $errors ) ) {
			return;
		}

		delete_transient( $key );

		echo '<div class="notice notice-warning is-dismissible">';

		foreach ( $errors as $error ) {
			echo '<p>' . esc_html( $error ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Script des liens du maillage (ajouter, monter, descendre, retirer) sur la fiche des catégories.
	 *
	 * @param string $hook_suffix Écran affiché.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'term.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
			return;
		}

		wp_enqueue_script(
			'icademie-page-categorie-admin',
			plugins_url( 'assets/admin.js', Icademie_Page_Categorie::FILE ),
			array(),
			Icademie_Page_Categorie::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_style(
			'common',
			'.icademie-categorie-admin{max-width:1100px;margin-top:32px}'
			. '.icademie-categorie-admin h2{margin-top:32px}'
			. '.icademie-categorie-admin .wp-editor-wrap{max-width:100%}'
			. '.icademie-categorie-links{max-width:960px}'
			. '.icademie-categorie-links td{vertical-align:middle}'
			. '.icademie-categorie-links th:first-child{width:32%}'
			. '.icademie-categorie-links th:last-child{width:190px}'
			. '.icademie-categorie-links__actions{white-space:nowrap}'
			. '.icademie-categorie-links__actions .button-link{margin-right:10px}'
		);
	}
}
