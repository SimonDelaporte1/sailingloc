<?php
/**
 * Plugin Name:       Icademie – Dates de début et de fin
 * Description:       Sur les produits qui ont l'attribut « date », affiche deux sélecteurs de date (début et fin) dans le formulaire d'ajout au panier, puis enregistre les dates choisies dans le panier et la commande. Le prix de ces produits est un prix par jour : dans le panier, il est multiplié par le nombre de jours choisis.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       icademie-dates-produit
 *
 * @package Icademie_Dates_Produit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dates de début et de fin choisies par le client sur la fiche produit.
 *
 * Un produit est concerné dès qu'il possède l'attribut « date » : attribut
 * personnalisé nommé « date » ou attribut global « Date » (pa_date). Cet
 * attribut sert seulement d'interrupteur ; ses valeurs ne sont pas utilisées.
 *
 * L'attribut ne doit pas être « Utilisé pour les variations » : une variation
 * est un choix dans une liste fixe de valeurs, alors qu'une date est libre.
 *
 * Le prix de ces produits est un prix par jour. Dans le panier, l'article coûte
 * prix × nombre de jours, dates de début et de fin comprises (du 10 au 12 :
 * 3 jours ; début et fin le même jour : 1 jour).
 */
final class Icademie_Dates_Produit {

	const VERSION = '1.1.0';

	/** Attribut qui active les dates sur un produit. */
	const ATTRIBUTE = 'date';

	/** Champs envoyés avec le formulaire d'ajout au panier. */
	const FIELD_START = 'icademie_date_debut';
	const FIELD_END   = 'icademie_date_fin';

	/** Clé des dates dans un article du panier. */
	const CART_KEY = 'icademie_dates';

	/**
	 * Branche le plugin sur les hooks de WooCommerce.
	 */
	public static function init() {
		// Fiche produit. Quand une extension ajoute des champs via ce hook, le bloc
		// « Ajouter au panier avec options » passe en envoi de formulaire classique
		// (POST) : les dates partent avec le reste du formulaire.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_fields' ) );

		// Panier.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_dates' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );

		// Prix par jour × nombre de jours : appliqué quand l'article entre dans le
		// panier, puis à chaque chargement du panier depuis la session.
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'apply_price_per_day' ), 20 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'apply_price_per_day' ), 20 );

		// Commande : les dates sont copiées sur la ligne de commande, visible dans
		// l'admin, le compte client, le tableau de bord vendeur et les e-mails.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 3 );

		// Listes de produits (boutique, catégories, produits liés…) : sans dates,
		// pas d'ajout direct au panier ; le bouton mène à la fiche produit.
		add_filter( 'woocommerce_product_supports', array( __CLASS__, 'disable_ajax_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_add_to_cart_args' ), 10, 2 );
	}

	/**
	 * Le produit demande-t-il des dates ?
	 *
	 * @param WC_Product|int $product Produit, variation ou ID.
	 * @return bool
	 */
	public static function requires_dates( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		if ( ! $product instanceof WC_Product || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return false;
		}

		$attributes = $product->get_attributes();

		return isset( $attributes[ self::ATTRIBUTE ] ) || isset( $attributes[ wc_attribute_taxonomy_name( self::ATTRIBUTE ) ] );
	}

	/**
	 * Affiche les deux sélecteurs de date dans le formulaire d'ajout au panier.
	 */
	public static function render_fields() {
		global $product;

		if ( ! $product instanceof WC_Product || ! self::requires_dates( $product ) ) {
			return;
		}

		wp_enqueue_style( 'icademie-dates-produit', plugins_url( 'assets/dates-produit.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script( 'icademie-dates-produit', plugins_url( 'assets/dates-produit.js', __FILE__ ), array(), self::VERSION, true );

		$today = current_datetime()->format( 'Y-m-d' );

		// Si l'ajout au panier vient d'échouer, on réaffiche les dates saisies.
		$start = self::get_posted_date( self::FIELD_START );
		$end   = self::get_posted_date( self::FIELD_END );

		$id_start = wp_unique_id( 'icademie-date-debut-' );
		$id_end   = wp_unique_id( 'icademie-date-fin-' );
		?>
		<div class="icademie-dates">
			<p class="icademie-dates__field">
				<label for="<?php echo esc_attr( $id_start ); ?>"><?php esc_html_e( 'Date de début', 'icademie-dates-produit' ); ?></label>
				<input type="date" id="<?php echo esc_attr( $id_start ); ?>" name="<?php echo esc_attr( self::FIELD_START ); ?>" value="<?php echo esc_attr( $start ); ?>" min="<?php echo esc_attr( $today ); ?>" required>
			</p>
			<p class="icademie-dates__field">
				<label for="<?php echo esc_attr( $id_end ); ?>"><?php esc_html_e( 'Date de fin', 'icademie-dates-produit' ); ?></label>
				<input type="date" id="<?php echo esc_attr( $id_end ); ?>" name="<?php echo esc_attr( self::FIELD_END ); ?>" value="<?php echo esc_attr( $end ); ?>" min="<?php echo esc_attr( $start ? $start : $today ); ?>" required>
			</p>
		</div>
		<?php
	}

	/**
	 * Refuse l'ajout au panier si les dates sont absentes ou incohérentes.
	 *
	 * Les champs « required » et « min » bloquent déjà la saisie dans le
	 * navigateur ; ce contrôle côté serveur est celui qui fait foi.
	 *
	 * @param bool $passed     Validation des autres contrôles.
	 * @param int  $product_id ID du produit (le parent pour une variation).
	 * @return bool
	 */
	public static function validate_dates( $passed, $product_id ) {
		if ( ! $passed || ! self::requires_dates( $product_id ) ) {
			return $passed;
		}

		$start = self::get_posted_date( self::FIELD_START );
		$end   = self::get_posted_date( self::FIELD_END );

		if ( '' === $start || '' === $end ) {
			wc_add_notice( __( 'Veuillez choisir une date de début et une date de fin.', 'icademie-dates-produit' ), 'error' );
			return false;
		}

		// Au format AAAA-MM-JJ, l'ordre alphabétique est l'ordre chronologique.
		if ( $start < current_datetime()->format( 'Y-m-d' ) ) {
			wc_add_notice( __( 'La date de début ne peut pas être passée.', 'icademie-dates-produit' ), 'error' );
			return false;
		}

		if ( $end < $start ) {
			wc_add_notice( __( 'La date de fin doit être identique ou postérieure à la date de début.', 'icademie-dates-produit' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Attache les dates à l'article ajouté au panier.
	 *
	 * Le même produit réservé à des dates différentes donne deux lignes de panier.
	 *
	 * @param array $cart_item_data Données de l'article.
	 * @param int   $product_id     ID du produit.
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! self::requires_dates( $product_id ) ) {
			return $cart_item_data;
		}

		$start = self::get_posted_date( self::FIELD_START );
		$end   = self::get_posted_date( self::FIELD_END );

		if ( '' !== $start && '' !== $end ) {
			$cart_item_data[ self::CART_KEY ] = array(
				'debut' => $start,
				'fin'   => $end,
			);
		}

		return $cart_item_data;
	}

	/**
	 * Prix de l'article du panier : prix du produit (par jour) × nombre de jours.
	 *
	 * WooCommerce recharge le produit depuis la base à chaque chargement du panier :
	 * le prix de départ est donc toujours le prix par jour actuel, jamais un prix déjà
	 * multiplié. Le prix normal et le prix promo sont multipliés avec le prix, pour
	 * qu'une éventuelle promotion reste affichée correctement.
	 *
	 * Le total de la ligne (prix × quantité), la commande et la commission du vendeur
	 * découlent de ce prix.
	 *
	 * @param array $cart_item Article du panier.
	 * @return array
	 */
	public static function apply_price_per_day( $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $cart_item;
		}

		$days = self::count_days( $cart_item[ self::CART_KEY ] );

		if ( $days < 2 ) {
			return $cart_item;
		}

		// Contexte « edit » : prix enregistrés, sans les filtres d'affichage (conversion
		// de devise…), qui s'appliqueront ensuite au prix multiplié.
		$product = $cart_item['data'];
		$product->set_price( self::multiply_price( $product->get_price( 'edit' ), $days ) );
		$product->set_regular_price( self::multiply_price( $product->get_regular_price( 'edit' ), $days ) );
		$product->set_sale_price( self::multiply_price( $product->get_sale_price( 'edit' ), $days ) );

		return $cart_item;
	}

	/**
	 * Affiche les dates et la durée sous le nom du produit (panier, mini-panier, commande).
	 *
	 * @param array $item_data Lignes déjà affichées.
	 * @param array $cart_item Article du panier.
	 * @return array
	 */
	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => __( 'Date de début', 'icademie-dates-produit' ),
			'value' => self::format_date( $cart_item[ self::CART_KEY ]['debut'] ),
		);
		$item_data[] = array(
			'key'   => __( 'Date de fin', 'icademie-dates-produit' ),
			'value' => self::format_date( $cart_item[ self::CART_KEY ]['fin'] ),
		);

		$days = self::count_days( $cart_item[ self::CART_KEY ] );

		if ( $days > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Durée', 'icademie-dates-produit' ),
				'value' => self::format_days( $days ),
			);
		}

		return $item_data;
	}

	/**
	 * Copie les dates et la durée sur la ligne de commande.
	 *
	 * @param WC_Order_Item_Product $item          Ligne de commande.
	 * @param string                $cart_item_key Clé de l'article du panier.
	 * @param array                 $values        Article du panier.
	 */
	public static function add_order_item_meta( $item, $cart_item_key, $values ) {
		if ( empty( $values[ self::CART_KEY ] ) ) {
			return;
		}

		$dates = $values[ self::CART_KEY ];
		$days  = self::count_days( $dates );

		// Version lisible, affichée partout où WooCommerce montre la ligne.
		$item->add_meta_data( __( 'Date de début', 'icademie-dates-produit' ), self::format_date( $dates['debut'] ), true );
		$item->add_meta_data( __( 'Date de fin', 'icademie-dates-produit' ), self::format_date( $dates['fin'] ), true );

		if ( $days > 0 ) {
			$item->add_meta_data( __( 'Durée', 'icademie-dates-produit' ), self::format_days( $days ), true );
		}

		// Version technique, cachée (préfixe « _ »), pour un export ou un calcul.
		$item->add_meta_data( '_icademie_date_debut', $dates['debut'], true );
		$item->add_meta_data( '_icademie_date_fin', $dates['fin'], true );

		if ( $days > 0 ) {
			$item->add_meta_data( '_icademie_nb_jours', $days, true );
		}
	}

	/**
	 * Désactive l'ajout au panier en AJAX depuis les listes de produits.
	 *
	 * @param bool       $supports Valeur d'origine.
	 * @param string     $feature  Fonctionnalité demandée.
	 * @param WC_Product $product  Produit.
	 * @return bool
	 */
	public static function disable_ajax_add_to_cart( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && self::requires_dates( $product ) ) {
			return false;
		}

		return $supports;
	}

	/**
	 * Le bouton des listes de produits mène à la fiche produit.
	 *
	 * @param string     $url     URL d'origine.
	 * @param WC_Product $product Produit.
	 * @return string
	 */
	public static function loop_add_to_cart_url( $url, $product ) {
		return self::is_simple_with_dates( $product ) ? $product->get_permalink() : $url;
	}

	/**
	 * Texte du bouton des listes de produits.
	 *
	 * Le bouton de la fiche produit utilise un autre texte et n'est pas modifié.
	 *
	 * @param string     $text    Texte d'origine.
	 * @param WC_Product $product Produit.
	 * @return string
	 */
	public static function loop_add_to_cart_text( $text, $product ) {
		return self::is_simple_with_dates( $product ) ? __( 'Choisir les dates', 'icademie-dates-produit' ) : $text;
	}

	/**
	 * Aligne le libellé lu par les lecteurs d'écran sur le texte visible du bouton.
	 *
	 * @param array      $args    Arguments du bouton.
	 * @param WC_Product $product Produit.
	 * @return array
	 */
	public static function loop_add_to_cart_args( $args, $product ) {
		// Le bouton « submit » est celui du formulaire de la fiche produit : on n'y touche pas.
		$is_form_button = isset( $args['attributes']['type'] ) && 'submit' === $args['attributes']['type'];

		if ( ! $is_form_button && self::is_simple_with_dates( $product ) ) {
			/* translators: %s: nom du produit. */
			$args['attributes']['aria-label'] = sprintf( __( 'Choisir les dates pour « %s »', 'icademie-dates-produit' ), $product->get_name() );
		}

		return $args;
	}

	/**
	 * Produit simple, achetable, qui demande des dates.
	 *
	 * Les produits variables mènent déjà à leur fiche (« Choisir les options »).
	 *
	 * @param WC_Product $product Produit.
	 * @return bool
	 */
	private static function is_simple_with_dates( $product ) {
		return $product instanceof WC_Product
			&& $product->is_type( 'simple' )
			&& $product->is_purchasable()
			&& $product->is_in_stock()
			&& self::requires_dates( $product );
	}

	/**
	 * Date envoyée par le formulaire, au format AAAA-MM-JJ, ou chaîne vide.
	 *
	 * @param string $field Nom du champ.
	 * @return string
	 */
	private static function get_posted_date( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Le formulaire d'ajout au panier de WooCommerce n'a pas de nonce.
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		// Rejette les dates impossibles (ex. 2026-02-30) que PHP corrigerait en silence.
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	/**
	 * Formate une date AAAA-MM-JJ selon le réglage « Format de date » du site.
	 *
	 * @param string $value Date AAAA-MM-JJ.
	 * @return string
	 */
	private static function format_date( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );

		return $date ? wp_date( wc_date_format(), $date->getTimestamp() ) : $value;
	}

	/**
	 * Nombre de jours de la période, dates de début et de fin comprises.
	 *
	 * @param array $dates Dates « debut » et « fin » au format AAAA-MM-JJ.
	 * @return int Nombre de jours (1 si début et fin sont le même jour), 0 si les dates sont invalides.
	 */
	private static function count_days( $dates ) {
		// En UTC, tous les jours durent 24 h : pas d'écart au changement d'heure.
		$utc   = new DateTimeZone( 'UTC' );
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', isset( $dates['debut'] ) ? (string) $dates['debut'] : '', $utc );
		$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', isset( $dates['fin'] ) ? (string) $dates['fin'] : '', $utc );

		if ( ! $start || ! $end || $end < $start ) {
			return 0;
		}

		return $start->diff( $end )->days + 1;
	}

	/**
	 * Multiplie un prix par le nombre de jours ; un prix vide reste vide.
	 *
	 * @param string $price Prix enregistré.
	 * @param int    $days  Nombre de jours.
	 * @return float|string
	 */
	private static function multiply_price( $price, $days ) {
		return ( '' === $price || null === $price ) ? '' : (float) $price * $days;
	}

	/**
	 * Durée lisible : « 1 jour », « 3 jours ».
	 *
	 * @param int $days Nombre de jours.
	 * @return string
	 */
	private static function format_days( $days ) {
		/* translators: %s: nombre de jours. */
		return sprintf( _n( '%s jour', '%s jours', $days, 'icademie-dates-produit' ), number_format_i18n( $days ) );
	}
}

Icademie_Dates_Produit::init();
