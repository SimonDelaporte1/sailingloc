<?php
/**
 * Notes détaillées des avis : conformité de l'annonce, propreté, communication, rapport qualité-prix.
 *
 * @package Icademie_Avis_Produits
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quatre notes de 0 à 5, en complément de la note générale de WooCommerce (étoiles de 1 à 5).
 *
 * - Aperçu de l'avis avant sa publication (Opquast 10) : le premier envoi affiche un récapitulatif
 *   (note, notes détaillées, texte, nom affiché) avec « Modifier mon avis » et « Publier mon avis ».
 * - Formulaire d'avis de la fiche produit : une ligne par critère, choix de 0 à 5. Les notes sont
 *   obligatoires quand la note générale l'est (WooCommerce › Réglages › Produits : « Les évaluations
 *   par étoiles doivent être obligatoires, et non facultatives »). Elles ne sont demandées que si les
 *   évaluations par étoiles sont activées, et seulement pour un avis, pas pour une réponse.
 * - Chaque avis affiche ses notes sous son texte ; la section des avis affiche la moyenne de chaque
 *   critère (avis approuvés).
 * - Administration : notes affichées sous le texte de l'avis (Produits › Avis) et modifiables sur la
 *   fiche de l'avis (encadré « Notes détaillées »).
 *
 * Chaque note est une métadonnée de l'avis (« icademie_note_{critère} », de 0 à 5). Un avis créé
 * autrement que par le formulaire (import, API) n'a pas de notes détaillées et n'est pas refusé.
 */
final class Icademie_Avis_Criteres {

	/** Préfixe des métadonnées des avis. */
	const META_PREFIX = 'icademie_note_';

	/** Nom des champs du formulaire. */
	const FIELD = 'icademie_criteres';

	/** Champ caché : l'avis vient du formulaire qui contient les critères. */
	const MARKER = 'icademie_criteres_formulaire';

	/** Note la plus haute. */
	const MAX = 5;

	/** Poignée de la feuille de styles et du script. */
	const HANDLE = 'icademie-avis-criteres';

	/** Jeton de l'encadré de l'administration. */
	const NONCE = 'icademie_criteres_admin';

	/**
	 * Résumés déjà affichés (un par produit et par page).
	 *
	 * @var int[]
	 */
	private static $summaries = array();

	/**
	 * Branche le formulaire, l'enregistrement et l'affichage.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );

		// Formulaire (thème en blocs et modèle classique de WooCommerce : même filtre).
		add_filter( 'woocommerce_product_review_comment_form_args', array( __CLASS__, 'add_fields' ) );
		add_filter( 'preprocess_comment', array( __CLASS__, 'validate' ), 5 );
		add_action( 'comment_post', array( __CLASS__, 'save' ), 10, 3 );

		// Fiche produit : notes de chaque avis et moyennes.
		add_filter( 'render_block_woocommerce/product-review-content', array( __CLASS__, 'render_review_block' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-reviews-title', array( __CLASS__, 'render_summary_after_title' ), 10, 3 );
		add_filter( 'render_block_woocommerce/product-reviews', array( __CLASS__, 'render_summary_in_reviews' ), 10, 3 );
		add_action( 'woocommerce_review_after_comment_text', array( __CLASS__, 'render_classic_review' ) );

		// Administration.
		add_filter( 'comment_text', array( __CLASS__, 'admin_comment_text' ), 20, 2 );
		add_action( 'add_meta_boxes_comment', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'edit_comment', array( __CLASS__, 'save_admin' ), 10, 2 );
	}

	/**
	 * Critères, dans l'ordre d'affichage.
	 *
	 * @return string[] Libellés, par clé.
	 */
	public static function criteria() {
		$criteria = array(
			'conformite'    => __( 'Conformité de l\'annonce', 'icademie-avis-produits' ),
			'proprete'      => __( 'Propreté', 'icademie-avis-produits' ),
			'communication' => __( 'Communication', 'icademie-avis-produits' ),
			'qualite_prix'  => __( 'Rapport qualité-prix', 'icademie-avis-produits' ),
		);

		/**
		 * Critères des notes détaillées des avis.
		 *
		 * @param string[] $criteria Libellés, par clé (lettres minuscules, chiffres et « _ »).
		 */
		return (array) apply_filters( 'icademie_avis_criteres', $criteria );
	}

	/**
	 * Déclare les métadonnées, la feuille de styles et le script.
	 */
	public static function register() {
		foreach ( array_keys( self::criteria() ) as $key ) {
			register_meta(
				'comment',
				self::META_PREFIX . $key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_note' ),
				)
			);
		}

		$file = Icademie_Avis_Produits::FILE;

		wp_register_style( self::HANDLE, plugins_url( 'assets/criteres.css', $file ), array(), Icademie_Avis_Produits::VERSION );
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/criteres.js', $file ),
			array(),
			Icademie_Avis_Produits::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Note valide : entier de 0 à 5.
	 *
	 * @param mixed $value Valeur.
	 * @return int|null
	 */
	public static function note( $value ) {
		if ( ! is_scalar( $value ) || '' === (string) $value || ! ctype_digit( (string) $value ) ) {
			return null;
		}

		$note = (int) $value;

		return ( $note >= 0 && $note <= self::MAX ) ? $note : null;
	}

	/**
	 * Nettoyage des métadonnées (enregistrement direct).
	 *
	 * @param mixed $value Valeur.
	 * @return int
	 */
	public static function sanitize_note( $value ) {
		$note = self::note( $value );

		return null === $note ? 0 : $note;
	}

	/**
	 * Les notes détaillées sont-elles demandées ? Avec les évaluations par étoiles seulement.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled();
	}

	/**
	 * Sont-elles obligatoires ? Comme la note générale.
	 *
	 * @return bool
	 */
	public static function required() {
		return function_exists( 'wc_review_ratings_required' ) && wc_review_ratings_required();
	}

	/**
	 * Notes enregistrées d'un avis.
	 *
	 * @param int $comment_id Avis.
	 * @return int[] Notes, par clé de critère (critères notés seulement).
	 */
	public static function notes( $comment_id ) {
		$notes = array();

		foreach ( array_keys( self::criteria() ) as $key ) {
			$value = get_comment_meta( (int) $comment_id, self::META_PREFIX . $key, true );
			$note  = self::note( $value );

			if ( null !== $note ) {
				$notes[ $key ] = $note;
			}
		}

		return $notes;
	}

	/**
	 * Ajoute les critères au formulaire d'avis, entre la note générale et le texte de l'avis.
	 *
	 * @param array $form Réglages du formulaire (comment_form()).
	 * @return array
	 */
	public static function add_fields( $form ) {
		if ( ! isset( $form['comment_field'] ) ) {
			return $form;
		}

		// Aperçu avant publication (Opquast 10) : avec ou sans notes détaillées.
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		wp_localize_script( self::HANDLE, 'icademieAvisApercu', self::preview_texts() );

		if ( ! self::enabled() ) {
			return $form;
		}

		$fields = self::fields_html();
		$anchor = '<p class="comment-form-comment">';
		$field  = (string) $form['comment_field'];

		$form['comment_field'] = false !== strpos( $field, $anchor ) ? str_replace( $anchor, $fields . $anchor, $field ) : $field . $fields;

		return $form;
	}

	/**
	 * Textes de l'aperçu de l'avis, affiché par le script avant la publication (Opquast 10).
	 *
	 * @return array
	 */
	private static function preview_texts() {
		return array(
			'check'      => __( 'Vérifier mon avis', 'icademie-avis-produits' ),
			'title'      => __( 'Vérifiez votre avis avant de le publier', 'icademie-avis-produits' ),
			'intro'      => __( 'Voici votre avis tel qu\'il sera publié sur la fiche du bateau. Votre adresse e-mail ne sera pas publiée.', 'icademie-avis-produits' ),
			'moderation' => get_option( 'comment_moderation' ) ? __( 'Il sera visible après sa validation par notre équipe.', 'icademie-avis-produits' ) : '',
			'rating'     => __( 'Note générale', 'icademie-avis-produits' ),
			'criteria'   => __( 'Notes détaillées', 'icademie-avis-produits' ),
			'text'       => __( 'Votre avis', 'icademie-avis-produits' ),
			'name'       => __( 'Nom affiché', 'icademie-avis-produits' ),
			/* translators: %s: note de 0 à 5. */
			'outOf'      => __( '%s sur 5', 'icademie-avis-produits' ),
			'edit'       => __( 'Modifier mon avis', 'icademie-avis-produits' ),
			'publish'    => __( 'Publier mon avis', 'icademie-avis-produits' ),
		);
	}

	/**
	 * Champs des critères.
	 *
	 * @return string
	 */
	private static function fields_html() {
		$required = self::required();
		$mark     = $required ? '&nbsp;<span class="required">*</span>' : '';

		ob_start();
		?>
		<div class="icademie-avis-criteres" data-icademie-criteres<?php echo $required ? ' data-required' : ''; ?>>
			<input type="hidden" name="<?php echo esc_attr( self::MARKER ); ?>" value="1">
			<p class="icademie-avis-criteres__titre"><?php esc_html_e( 'Notes détaillées', 'icademie-avis-produits' ); ?></p>
			<p class="icademie-avis-criteres__aide" id="icademie-avis-criteres-aide"><?php esc_html_e( 'De 0 (très décevant) à 5 (excellent).', 'icademie-avis-produits' ); ?></p>
			<?php foreach ( self::criteria() as $key => $label ) : ?>
				<?php $id = 'icademie-avis-critere-' . sanitize_html_class( $key ); ?>
				<fieldset class="icademie-avis-critere" aria-describedby="icademie-avis-criteres-aide <?php echo esc_attr( $id ); ?>-erreur" data-critere>
					<legend class="icademie-avis-critere__nom"><?php echo esc_html( $label ); ?><?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Balise fixe. ?></legend>
					<span class="icademie-avis-critere__choix">
						<?php for ( $note = 0; $note <= self::MAX; $note++ ) : ?>
							<label class="icademie-avis-critere__note">
								<input type="radio" name="<?php echo esc_attr( self::FIELD . '[' . $key . ']' ); ?>" value="<?php echo (int) $note; ?>"<?php echo $required ? ' required' : ''; ?>>
								<span aria-hidden="true"><?php echo (int) $note; ?></span>
								<?php /* translators: %d: note de 0 à 5. */ ?>
								<span class="icademie-avis-sr"><?php echo esc_html( sprintf( __( '%d sur 5', 'icademie-avis-produits' ), $note ) ); ?></span>
							</label>
						<?php endfor; ?>
					</span>
					<?php /* translators: %s: nom du critère. */ ?>
					<small class="icademie-avis-critere__erreur" id="<?php echo esc_attr( $id ); ?>-erreur" role="alert" hidden data-erreur="<?php echo esc_attr( sprintf( __( 'Choisissez une note de 0 à 5 pour « %s ».', 'icademie-avis-produits' ), $label ) ); ?>"></small>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * L'avis envoyé vient-il du formulaire des critères (avis sur un produit, pas une réponse) ?
	 *
	 * @param array $commentdata Avis envoyé.
	 * @return bool
	 */
	private static function from_form( $commentdata ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Formulaire d'avis public de WordPress.
		return ! is_admin()
			&& ! empty( $_POST[ self::MARKER ] )
			&& self::enabled()
			&& ! empty( $commentdata['comment_post_ID'] )
			&& 'product' === get_post_type( (int) $commentdata['comment_post_ID'] )
			&& empty( $commentdata['comment_parent'] );
		// phpcs:enable
	}

	/**
	 * Notes envoyées par le formulaire.
	 *
	 * @return array Notes valides (« notes »), critères oubliés (« missing »), notes invalides (« invalid »).
	 */
	private static function posted() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé ci-dessous (entiers de 0 à 5).
		$input  = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();
		$result = array(
			'notes'   => array(),
			'missing' => array(),
			'invalid' => array(),
		);

		foreach ( self::criteria() as $key => $label ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				$result['missing'][ $key ] = $label;
				continue;
			}

			$note = self::note( $input[ $key ] );

			if ( null === $note ) {
				$result['invalid'][ $key ] = $label;
			} else {
				$result['notes'][ $key ] = $note;
			}
		}

		return $result;
	}

	/**
	 * Refuse un avis sans ses notes détaillées (si elles sont obligatoires) ou avec une note invalide.
	 *
	 * Le script du formulaire prévient avant l'envoi ; ce contrôle vaut aussi sans script.
	 *
	 * @param array $commentdata Avis envoyé.
	 * @return array
	 */
	public static function validate( $commentdata ) {
		if ( ! self::from_form( $commentdata ) ) {
			return $commentdata;
		}

		$posted = self::posted();
		$labels = $posted['invalid'] + ( self::required() ? $posted['missing'] : array() );

		if ( $labels ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: noms des critères. */
						_n( 'Choisissez une note de 0 à 5 pour : %s.', 'Choisissez une note de 0 à 5 pour chacun de ces points : %s.', count( $labels ), 'icademie-avis-produits' ),
						implode( ', ', $labels )
					)
				),
				esc_html__( 'Notes détaillées manquantes', 'icademie-avis-produits' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		return $commentdata;
	}

	/**
	 * Enregistre les notes d'un avis publié avec le formulaire.
	 *
	 * @param int        $comment_id  Avis.
	 * @param int|string $approved    État de l'avis.
	 * @param array      $commentdata Avis.
	 */
	public static function save( $comment_id, $approved, $commentdata ) {
		if ( 'spam' === $approved || ! self::from_form( $commentdata ) ) {
			return;
		}

		foreach ( self::posted()['notes'] as $key => $note ) {
			update_comment_meta( $comment_id, self::META_PREFIX . $key, $note );
		}
	}

	/**
	 * Notes d'un avis, sous son texte (bloc « Contenu de l'avis » de la fiche produit).
	 *
	 * @param string   $content  Contenu du bloc.
	 * @param array    $block    Bloc.
	 * @param WP_Block $instance Bloc et son contexte (avis affiché).
	 * @return string
	 */
	public static function render_review_block( $content, $block, $instance ) {
		$comment_id = isset( $instance->context['commentId'] ) ? (int) $instance->context['commentId'] : 0;

		return $comment_id ? $content . self::review_html( $comment_id ) : $content;
	}

	/**
	 * Notes d'un avis, sous son texte (modèle classique des avis de WooCommerce).
	 *
	 * @param WP_Comment $comment Avis.
	 */
	public static function render_classic_review( $comment ) {
		if ( $comment instanceof WP_Comment ) {
			echo self::review_html( (int) $comment->comment_ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé dans review_html().
		}
	}

	/**
	 * Liste des notes d'un avis.
	 *
	 * @param int $comment_id Avis.
	 * @return string Vide si l'avis n'a pas de notes détaillées.
	 */
	public static function review_html( $comment_id ) {
		$notes = self::notes( $comment_id );

		if ( ! $notes ) {
			return '';
		}

		wp_enqueue_style( self::HANDLE );

		$criteria = self::criteria();
		$items    = '';

		foreach ( $notes as $key => $note ) {
			$items .= sprintf(
				'<div class="icademie-avis-notes__item"><dt>%1$s</dt><dd><span aria-hidden="true">%2$d/5</span><span class="icademie-avis-sr">%3$s</span></dd></div>',
				esc_html( $criteria[ $key ] ),
				(int) $note,
				/* translators: %d: note de 0 à 5. */
				esc_html( sprintf( __( '%d sur 5', 'icademie-avis-produits' ), $note ) )
			);
		}

		return sprintf(
			'<dl class="icademie-avis-notes" aria-label="%1$s">%2$s</dl>',
			esc_attr__( 'Notes détaillées', 'icademie-avis-produits' ),
			$items
		);
	}

	/**
	 * Moyennes des critères sous le titre de la section des avis.
	 *
	 * @param string   $content  Titre.
	 * @param array    $block    Bloc.
	 * @param WP_Block $instance Bloc et son contexte (produit).
	 * @return string
	 */
	public static function render_summary_after_title( $content, $block, $instance ) {
		$product_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

		return $product_id ? $content . self::summary_once( $product_id ) : $content;
	}

	/**
	 * Moyennes des critères au début de la section des avis, si elle n'a pas de titre.
	 *
	 * @param string   $content  Section des avis.
	 * @param array    $block    Bloc.
	 * @param WP_Block $instance Bloc et son contexte (produit).
	 * @return string
	 */
	public static function render_summary_in_reviews( $content, $block, $instance ) {
		$product_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

		if ( ! $product_id || in_array( $product_id, self::$summaries, true ) ) {
			return $content;
		}

		$summary = self::summary_once( $product_id );

		if ( '' === $summary ) {
			return $content;
		}

		// Juste après la balise d'ouverture de la section.
		$position = strpos( $content, '>' );

		return false === $position ? $summary . $content : substr( $content, 0, $position + 1 ) . $summary . substr( $content, $position + 1 );
	}

	/**
	 * Moyennes d'un produit, une seule fois par page.
	 *
	 * @param int $product_id Produit.
	 * @return string
	 */
	private static function summary_once( $product_id ) {
		if ( in_array( $product_id, self::$summaries, true ) ) {
			return '';
		}

		self::$summaries[] = $product_id;

		return self::summary_html( $product_id );
	}

	/**
	 * Moyenne de chaque critère sur les avis approuvés d'un produit.
	 *
	 * @param int $product_id Produit.
	 * @return array[] Par clé de critère : « average » (0 à 5, une décimale) et « count ».
	 */
	public static function averages( $product_id ) {
		global $wpdb;

		$keys  = array();
		$names = array();

		foreach ( array_keys( self::criteria() ) as $key ) {
			$keys[ self::META_PREFIX . $key ] = $key;
			$names[]                          = self::META_PREFIX . $key;
		}

		$cache_key = 'moyennes:' . (int) $product_id . ':' . md5( implode( ',', $names ) ) . ':' . wp_cache_get_last_changed( 'comment' );
		$rows      = wp_cache_get( $cache_key, 'icademie_avis' );

		if ( false === $rows ) {
			$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Agrégat sans fonction WordPress équivalente, mis en cache.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT m.meta_key, AVG( CAST( m.meta_value AS UNSIGNED ) ) AS average, COUNT(*) AS total
					FROM {$wpdb->commentmeta} m
					INNER JOIN {$wpdb->comments} c ON c.comment_ID = m.comment_id
					WHERE c.comment_post_ID = %d AND c.comment_approved = '1' AND c.comment_parent = 0
					AND m.meta_key IN ( {$placeholders} )
					GROUP BY m.meta_key",
					array_merge( array( (int) $product_id ), $names )
				)
			);

			wp_cache_set( $cache_key, $rows, 'icademie_avis', DAY_IN_SECONDS );
		}

		$averages = array();

		foreach ( self::criteria() as $key => $label ) {
			foreach ( (array) $rows as $row ) {
				if ( isset( $keys[ $row->meta_key ] ) && $keys[ $row->meta_key ] === $key && (int) $row->total > 0 ) {
					$averages[ $key ] = array(
						'average' => round( (float) $row->average, 1 ),
						'count'   => (int) $row->total,
					);
				}
			}
		}

		return $averages;
	}

	/**
	 * Résumé des moyennes : une barre par critère.
	 *
	 * @param int $product_id Produit.
	 * @return string Vide sans avis approuvé avec des notes détaillées.
	 */
	public static function summary_html( $product_id ) {
		$averages = self::averages( $product_id );

		if ( ! $averages ) {
			return '';
		}

		wp_enqueue_style( self::HANDLE );

		$criteria = self::criteria();
		$items    = '';

		foreach ( $averages as $key => $data ) {
			$value = number_format_i18n( $data['average'], 1 );

			$items .= sprintf(
				'<li class="icademie-avis-moyennes__item"><span class="icademie-avis-moyennes__nom">%1$s</span><span class="icademie-avis-moyennes__barre" aria-hidden="true"><span style="width:%2$s%%"></span></span><span class="icademie-avis-moyennes__valeur"><span aria-hidden="true">%3$s</span><span class="icademie-avis-sr">%4$s</span></span></li>',
				esc_html( $criteria[ $key ] ),
				esc_attr( (string) round( 100 * $data['average'] / self::MAX, 1 ) ),
				esc_html( $value ),
				/* translators: 1: moyenne (« 4,3 »), 2: nombre d'avis. */
				esc_html( sprintf( _n( '%1$s sur 5, %2$s avis', '%1$s sur 5, %2$s avis', $data['count'], 'icademie-avis-produits' ), $value, number_format_i18n( $data['count'] ) ) )
			);
		}

		return sprintf(
			'<div class="icademie-avis-moyennes"><p class="icademie-avis-moyennes__titre" id="icademie-avis-moyennes-%1$d">%2$s</p><ul class="icademie-avis-moyennes__liste" aria-labelledby="icademie-avis-moyennes-%1$d">%3$s</ul></div>',
			(int) $product_id,
			esc_html__( 'Notes détaillées', 'icademie-avis-produits' ),
			$items
		);
	}

	/**
	 * Administration (Produits › Avis, Commentaires) : notes sous le texte de l'avis.
	 *
	 * @param string          $text    Texte de l'avis.
	 * @param WP_Comment|null $comment Avis.
	 * @return string
	 */
	public static function admin_comment_text( $text, $comment = null ) {
		if ( ! is_admin() || wp_doing_ajax() || ! $comment instanceof WP_Comment || 'product' !== get_post_type( (int) $comment->comment_post_ID ) ) {
			return $text;
		}

		$notes = self::notes( (int) $comment->comment_ID );

		if ( ! $notes ) {
			return $text;
		}

		$criteria = self::criteria();
		$parts    = array();

		foreach ( $notes as $key => $note ) {
			$parts[] = esc_html( $criteria[ $key ] ) . '&nbsp;: <strong>' . (int) $note . '/5</strong>';
		}

		return $text . '<p class="icademie-avis-notes-admin" style="color:#50575e;font-size:12px">' . implode( ' · ', $parts ) . '</p>';
	}

	/**
	 * Encadré « Notes détaillées » sur la fiche d'un avis (Commentaires › Modifier).
	 *
	 * @param WP_Comment $comment Avis.
	 */
	public static function add_meta_box( $comment ) {
		if ( ! $comment instanceof WP_Comment || 'product' !== get_post_type( (int) $comment->comment_post_ID ) || (int) $comment->comment_parent > 0 ) {
			return;
		}

		add_meta_box( 'icademie-avis-criteres', __( 'Notes détaillées', 'icademie-avis-produits' ), array( __CLASS__, 'render_meta_box' ), 'comment', 'normal', 'high' );
	}

	/**
	 * Contenu de l'encadré : une liste de choix par critère.
	 *
	 * @param WP_Comment $comment Avis.
	 */
	public static function render_meta_box( $comment ) {
		$notes = self::notes( (int) $comment->comment_ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p class="description"><?php esc_html_e( 'Notes de 0 à 5 laissées avec l\'avis, en complément de la note générale. « Non noté » retire la note.', 'icademie-avis-produits' ); ?></p>
		<table class="form-table" role="presentation">
			<?php foreach ( self::criteria() as $key => $label ) : ?>
				<?php $id = 'icademie-avis-admin-' . sanitize_html_class( $key ); ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::FIELD . '[' . $key . ']' ); ?>">
							<option value=""><?php esc_html_e( 'Non noté', 'icademie-avis-produits' ); ?></option>
							<?php for ( $note = 0; $note <= self::MAX; $note++ ) : ?>
								<option value="<?php echo (int) $note; ?>"<?php selected( isset( $notes[ $key ] ) ? $notes[ $key ] : null, $note ); ?>><?php echo (int) $note; ?> / 5</option>
							<?php endfor; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Enregistre l'encadré de la fiche d'un avis.
	 *
	 * @param int   $comment_id Avis.
	 * @param array $data       Données de l'avis.
	 */
	public static function save_admin( $comment_id, $data = array() ) {
		if ( ! is_admin() || ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé ci-dessous (entiers de 0 à 5).
		$input = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();

		foreach ( array_keys( self::criteria() ) as $key ) {
			$note = isset( $input[ $key ] ) ? self::note( $input[ $key ] ) : null;

			if ( null === $note ) {
				delete_comment_meta( $comment_id, self::META_PREFIX . $key );
			} else {
				update_comment_meta( $comment_id, self::META_PREFIX . $key, $note );
			}
		}
	}
}
