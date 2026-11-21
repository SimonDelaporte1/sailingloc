<?php
/**
 * Conditions de location fixées par le propriétaire (encart 10, et caution du module de réservation).
 *
 * @package Icademie_Page_Annonce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Six rubriques choisies par le vendeur dans des listes : caution, annulation, embarquement,
 * débarquement, permis ou expérience, carburant.
 *
 * - Formulaire produit du tableau de bord vendeur (WC Vendors) : encart « Conditions de location »
 *   sous le prix, six listes obligatoires (affichées par l'extension « Formulaire produit des
 *   vendeurs » avec l'action « icademie_formulaire_vendeur_conditions »).
 * - Administration : onglet « Conditions de location » des données du produit.
 * - Fiche annonce : encart « Conditions de location » ; la caution apparaît aussi dans le module de
 *   réservation (empreinte, non débitée).
 *
 * Chaque choix est une métadonnée du produit (« _icademie_condition_{rubrique} ») qui garde la clé
 * du choix : les libellés peuvent être reformulés sans toucher aux annonces.
 */
final class Icademie_Annonce_Conditions {

	/** Préfixe des métadonnées des produits. */
	const META_PREFIX = '_icademie_condition_';

	/** Nom des champs envoyés. */
	const FIELD = 'icademie_conditions';

	/**
	 * Branche les formulaires et les enregistrements.
	 */
	public static function init() {
		// Tableau de bord vendeur (WC Vendors).
		add_action( 'icademie_formulaire_vendeur_conditions', array( __CLASS__, 'render_vendor_fields' ) );
		add_action( 'wcv_save_product_meta', array( __CLASS__, 'save_vendor' ) );

		// Administration : données du produit.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_admin_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_admin_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_admin' ) );
	}

	/**
	 * Rubriques et choix proposés, dans l'ordre d'affichage.
	 *
	 * @return array[] Par clé : « label », « choices » (libellés par clé de choix), « help ».
	 */
	public static function definitions() {
		$amounts = array( 0, 300, 500, 800, 1000, 1500, 2000, 3000, 5000 );
		$caution = array();

		foreach ( $amounts as $amount ) {
			$caution[ (string) $amount ] = 0 === $amount ? __( 'Sans caution', 'icademie-page-annonce' ) : self::amount_text( $amount );
		}

		$definitions = array(
			'caution'      => array(
				'label'   => __( 'Caution', 'icademie-page-annonce' ),
				'choices' => $caution,
				'help'    => __( 'Empreinte bancaire prise au départ, non débitée sauf dommage.', 'icademie-page-annonce' ),
			),
			'annulation'   => array(
				'label'   => __( 'Annulation', 'icademie-page-annonce' ),
				'choices' => array(
					'souple'           => __( 'Souple : remboursement intégral jusqu\'à 24 h avant le départ', 'icademie-page-annonce' ),
					'moderee'          => __( 'Modérée : remboursement intégral jusqu\'à 5 jours avant le départ', 'icademie-page-annonce' ),
					'stricte'          => __( 'Stricte : remboursement de 50 % jusqu\'à 14 jours avant le départ', 'icademie-page-annonce' ),
					'non_remboursable' => __( 'Non remboursable', 'icademie-page-annonce' ),
				),
				'help'    => '',
			),
			'embarquement' => array(
				'label'   => __( 'Embarquement', 'icademie-page-annonce' ),
				'choices' => array(
					'8h'       => __( 'À partir de 8 h', 'icademie-page-annonce' ),
					'9h'       => __( 'À partir de 9 h', 'icademie-page-annonce' ),
					'10h'      => __( 'À partir de 10 h', 'icademie-page-annonce' ),
					'14h'      => __( 'À partir de 14 h', 'icademie-page-annonce' ),
					'17h'      => __( 'À partir de 17 h', 'icademie-page-annonce' ),
					'convenir' => __( 'Horaire à convenir avec le propriétaire', 'icademie-page-annonce' ),
				),
				'help'    => '',
			),
			'debarquement' => array(
				'label'   => __( 'Débarquement', 'icademie-page-annonce' ),
				'choices' => array(
					'12h'      => __( 'Avant 12 h', 'icademie-page-annonce' ),
					'17h'      => __( 'Avant 17 h', 'icademie-page-annonce' ),
					'18h'      => __( 'Avant 18 h', 'icademie-page-annonce' ),
					'19h'      => __( 'Avant 19 h', 'icademie-page-annonce' ),
					'20h'      => __( 'Avant 20 h', 'icademie-page-annonce' ),
					'convenir' => __( 'Horaire à convenir avec le propriétaire', 'icademie-page-annonce' ),
				),
				'help'    => '',
			),
			'permis'       => array(
				'label'   => __( 'Permis / expérience', 'icademie-page-annonce' ),
				'choices' => array(
					'aucun'      => __( 'Sans permis', 'icademie-page-annonce' ),
					'cotier'     => __( 'Permis côtier requis', 'icademie-page-annonce' ),
					'fluvial'    => __( 'Permis fluvial requis', 'icademie-page-annonce' ),
					'hauturier'  => __( 'Permis hauturier requis', 'icademie-page-annonce' ),
					'experience' => __( 'Expérience de navigation demandée', 'icademie-page-annonce' ),
					'skipper'    => __( 'Non requis : location avec skipper', 'icademie-page-annonce' ),
				),
				'help'    => '',
			),
			'carburant'    => array(
				'label'   => __( 'Carburant', 'icademie-page-annonce' ),
				'choices' => array(
					'inclus'       => __( 'Inclus dans le prix', 'icademie-page-annonce' ),
					'plein'        => __( 'Plein au départ, à refaire au retour', 'icademie-page-annonce' ),
					'consommation' => __( 'Facturé selon la consommation', 'icademie-page-annonce' ),
					'locataire'    => __( 'À la charge du locataire', 'icademie-page-annonce' ),
					'sans_objet'   => __( 'Sans objet (voile, électrique ou rames)', 'icademie-page-annonce' ),
				),
				'help'    => '',
			),
		);

		/**
		 * Conditions de location proposées aux vendeurs.
		 *
		 * @param array[] $definitions Rubriques : libellé, choix (libellés par clé), aide.
		 */
		return (array) apply_filters( 'icademie_annonce_conditions', $definitions );
	}

	/**
	 * Montant sans décimales, à la façon de WooCommerce (« 1 000 € »).
	 *
	 * @param float $amount Montant.
	 * @return string
	 */
	public static function amount_text( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'decimals' => 0 ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Choix enregistrés d'un produit.
	 *
	 * @param int $product_id Produit.
	 * @return array[] Rubriques renseignées : « key », « label », « value » (clé du choix), « text ».
	 */
	public static function values( $product_id ) {
		$values = array();

		foreach ( self::definitions() as $key => $definition ) {
			$value = (string) get_post_meta( (int) $product_id, self::META_PREFIX . $key, true );

			if ( '' !== $value && isset( $definition['choices'][ $value ] ) ) {
				$values[ $key ] = array(
					'key'   => $key,
					'label' => $definition['label'],
					'value' => $value,
					'text'  => $definition['choices'][ $value ],
				);
			}
		}

		return $values;
	}

	/**
	 * Caution d'un produit.
	 *
	 * @param int $product_id Produit.
	 * @return int|null Montant (0 : sans caution), ou null si le vendeur ne l'a pas renseignée.
	 */
	public static function deposit( $product_id ) {
		$values = self::values( $product_id );

		return isset( $values['caution'] ) && is_numeric( $values['caution']['value'] ) ? (int) $values['caution']['value'] : null;
	}

	/**
	 * Enregistre les choix envoyés (valeurs de la liste seulement).
	 *
	 * @param int   $product_id Produit.
	 * @param array $input      Choix envoyés, par rubrique.
	 */
	private static function save( $product_id, $input ) {
		foreach ( self::definitions() as $key => $definition ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			$value = is_scalar( $input[ $key ] ) ? sanitize_key( (string) $input[ $key ] ) : '';

			if ( '' !== $value && isset( $definition['choices'][ $value ] ) ) {
				update_post_meta( $product_id, self::META_PREFIX . $key, $value );
			} elseif ( '' === $value ) {
				delete_post_meta( $product_id, self::META_PREFIX . $key );
			}
		}
	}

	/**
	 * Choix envoyés par un formulaire.
	 *
	 * @return array
	 */
	private static function posted() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Jetons vérifiés par WC Vendors et WooCommerce avant l'enregistrement.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé dans save() (clés de la liste).
		$input = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : null;
		// phpcs:enable

		return is_array( $input ) ? $input : array();
	}

	/**
	 * Encart « Conditions de location » du formulaire du vendeur, sous le prix.
	 *
	 * @param int $product_id Produit modifié (0 pour un nouveau produit).
	 */
	public static function render_vendor_fields( $product_id ) {
		$values = self::values( (int) $product_id );
		?>
		<div class="icademie-conditions-vendeur" style="margin:0 0 40px">
			<h3 style="margin:0 0 4px"><?php esc_html_e( 'Conditions de location', 'icademie-page-annonce' ); ?></h3>
			<p style="margin:0 0 16px"><?php esc_html_e( 'Affichées sur l\'annonce. La caution apparaît aussi dans le module de réservation (empreinte bancaire, non débitée).', 'icademie-page-annonce' ); ?></p>
			<?php // Aide de la caution dans l'introduction : les colonnes flottantes de WC Vendors restent alignées. ?>
			<div class="wcv-cols-group wcv-horizontal-gutters">
				<?php foreach ( self::definitions() as $key => $definition ) : ?>
					<?php $id = 'icademie-condition-' . sanitize_html_class( $key ); ?>
					<div class="all-50 small-100 tiny-100">
						<div class="control-group">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $definition['label'] ); ?> <span class="required" aria-hidden="true">*</span></label>
							<div class="control select">
								<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::FIELD . '[' . $key . ']' ); ?>" required>
									<option value=""><?php esc_html_e( 'Choisir…', 'icademie-page-annonce' ); ?></option>
									<?php foreach ( $definition['choices'] as $value => $text ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"<?php selected( isset( $values[ $key ] ) ? $values[ $key ]['value'] : '', (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Enregistre les conditions envoyées par le vendeur (après le reste du produit).
	 *
	 * @param int $product_id Produit.
	 */
	public static function save_vendor( $product_id ) {
		$input = self::posted();

		// Appelé par WC Vendors après ses propres contrôles (jeton, produit du vendeur connecté).
		$owner = (int) get_post_field( 'post_author', (int) $product_id ) === get_current_user_id();

		if ( $input && ( $owner || current_user_can( 'edit_post', $product_id ) ) ) {
			self::save( (int) $product_id, $input );
		}
	}

	/**
	 * Onglet « Conditions de location » des données du produit (administration).
	 *
	 * @param array[] $tabs Onglets.
	 * @return array[]
	 */
	public static function add_admin_tab( $tabs ) {
		$tabs['icademie_conditions'] = array(
			'label'    => __( 'Conditions de location', 'icademie-page-annonce' ),
			'target'   => 'icademie_conditions_data',
			'class'    => array(),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Contenu de l'onglet : une liste par rubrique.
	 */
	public static function render_admin_panel() {
		global $post;

		$values = self::values( $post ? (int) $post->ID : 0 );

		echo '<div id="icademie_conditions_data" class="panel woocommerce_options_panel hidden"><div class="options_group">';
		echo '<p class="form-field">' . esc_html__( 'Conditions affichées sur l\'annonce, choisies par le vendeur dans son tableau de bord. La caution apparaît aussi dans le module de réservation.', 'icademie-page-annonce' ) . '</p>';

		foreach ( self::definitions() as $key => $definition ) {
			woocommerce_wp_select(
				array(
					'id'          => 'icademie-condition-' . $key,
					'name'        => self::FIELD . '[' . $key . ']',
					'label'       => $definition['label'],
					'value'       => isset( $values[ $key ] ) ? $values[ $key ]['value'] : '',
					'options'     => array( '' => __( 'Non renseigné', 'icademie-page-annonce' ) ) + $definition['choices'],
					'desc_tip'    => '' !== $definition['help'],
					'description' => $definition['help'],
				)
			);
		}

		echo '</div></div>';
	}

	/**
	 * Enregistre l'onglet de l'administration.
	 *
	 * @param WC_Product $product Produit enregistré.
	 */
	public static function save_admin( $product ) {
		$input = self::posted();

		if ( $input && $product instanceof WC_Product && $product->get_id() ) {
			self::save( $product->get_id(), $input );
		}
	}
}
