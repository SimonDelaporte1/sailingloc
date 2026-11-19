<?php
/**
 * Plugin Name:       Icademie – Formulaire produit des vendeurs
 * Description:       Simplifie le formulaire produit du tableau de bord WC Vendors : produit toujours simple et virtuel (sans choix pour le vendeur), prix de vente placé sous le nom du produit (sans les dates « De » / « À »), encart « Général » supprimé.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, wc-vendors
 * Text Domain:       icademie-formulaire-vendeur
 *
 * @package Icademie_Formulaire_Vendeur
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire produit du tableau de bord vendeur.
 */
final class Icademie_Formulaire_Vendeur {

	/**
	 * Branche les filtres et actions.
	 */
	public static function init() {
		add_filter( 'wcv_dashboard_template_base_dir', array( __CLASS__, 'product_form_template_dir' ), 10, 4 );
		add_action( 'wcvendors_before_product_save', array( __CLASS__, 'force_simple_virtual' ) );

		// Retire les champs « De » / « À » (dates de la promotion) affichés sous le prix de vente :
		// WC Vendors n'affiche rien pour un champ vide. Sans ces champs, chaque enregistrement par
		// le vendeur efface d'éventuelles anciennes dates et le prix de vente reste le prix du produit.
		add_filter( 'wcv_product_sale_price_date_from', '__return_empty_array' );
		add_filter( 'wcv_product_sale_price_date_to', '__return_empty_array' );
	}

	/**
	 * Fait charger templates/product-edit.php (ce plugin) au lieu du formulaire de WC Vendors.
	 *
	 * Un fichier wc-vendors/dashboard/product-edit.php placé dans le thème reste prioritaire,
	 * comme pour n'importe quel template WC Vendors.
	 *
	 * @param string $base_dir Dossier par défaut du template.
	 * @param string $object   Objet du tableau de bord (product, order…).
	 * @param string $action   Action demandée (edit…).
	 * @param string $template Variante de formulaire demandée ('' pour le formulaire standard).
	 * @return string
	 */
	public static function product_form_template_dir( $base_dir, $object, $action, $template ) {
		if ( 'product' === $object && 'edit' === $action && empty( $template ) ) {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		return $base_dir;
	}

	/**
	 * Impose un produit simple et virtuel, quelles que soient les valeurs envoyées.
	 *
	 * Le formulaire n'affiche plus ces choix, mais une requête peut être forgée. WC Vendors lit
	 * directement $_POST (sans filtre) pendant tout l'enregistrement : on corrige donc les valeurs
	 * avant qu'il commence, pour que tout le reste (expédition ignorée car virtuel, prix…) suive.
	 * Un produit simple n'a pas de variations : on retire aussi celles qui seraient envoyées.
	 */
	public static function force_simple_virtual() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce déjà vérifié par WC Vendors avant cette action.
		$_POST['product-type'] = 'simple';
		$_POST['_virtual']     = 'yes';
		unset( $_POST['variable_post_id'] );
		// phpcs:enable
	}
}

Icademie_Formulaire_Vendeur::init();
