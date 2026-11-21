<?php
/**
 * Prix des bateaux dans la table de recherche de WooCommerce.
 *
 * @package Icademie_Page_Categorie
 */

defined( 'ABSPATH' ) || exit;

/**
 * Garde à jour le prix de chaque bateau dans la table de recherche des produits de WooCommerce
 * (wc_product_meta_lookup).
 *
 * WooCommerce lit cette table pour filtrer par prix (« min_price », « max_price ») et trier par
 * prix ; le filtre « Prix par jour » de la page catégorie y lit aussi les prix le plus bas et le
 * plus haut de la catégorie.
 *
 * Quand un vendeur enregistre un bateau depuis son tableau de bord, WC Vendors fait enregistrer le
 * produit par WooCommerce, puis écrit le prix : la table garde l'ancien prix (0 € pour un nouveau
 * bateau). Le bateau ne compte alors ni pour le filtre ni pour le tri par prix, et le filtre se
 * masque quand tous les bateaux de la catégorie semblent à 0 €.
 *
 * - Après chaque enregistrement d'un vendeur, la ligne du bateau est recalculée.
 * - Les bateaux déjà enregistrés sont recalculés une fois, à l'activation de l'extension (même
 *   calcul que l'outil « Régénérer les tables de recherche de produits » de WooCommerce).
 */
final class Icademie_Page_Categorie_Prices {

	/** Option : version du dernier recalcul de tous les prix. */
	const OPTION = 'icademie_page_categorie_prix_recalcules';

	/** Version du recalcul (à changer pour le relancer une fois). */
	const REPAIR_VERSION = '1';

	/**
	 * Branche les recalculs sur WordPress.
	 */
	public static function init() {
		// Après le dernier enregistrement de WC Vendors (prix, stock, variations).
		add_action( 'wcv_save_product', array( __CLASS__, 'refresh' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_repair' ) );
	}

	/**
	 * Recalcule la ligne d'un bateau dans la table de recherche, d'après ses données enregistrées.
	 *
	 * @param int $product_id Bateau.
	 */
	public static function refresh( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || ! class_exists( 'WC_Data_Store' ) || 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		$store = WC_Data_Store::load( 'product' );

		// WooCommerce 10.8 et suivants : recalcul de la seule ligne du bateau.
		if ( $store->has_callable( 'refresh_product_lookup_table' ) ) {
			$store->refresh_product_lookup_table( $product_id );
		} elseif ( function_exists( 'wc_update_product_lookup_tables_column' ) ) {
			wc_update_product_lookup_tables_column( 'min_max_price' );
		}

		wc_delete_product_transients( $product_id );
	}

	/**
	 * Recalcule les prix de tous les bateaux, une seule fois (première page d'administration après
	 * l'installation ou la mise à jour de l'extension).
	 */
	public static function maybe_repair() {
		if ( self::REPAIR_VERSION === get_option( self::OPTION ) ) {
			return;
		}

		self::repair();
	}

	/**
	 * Recalcule les prix de tous les bateaux dans la table de recherche, d'après leur prix enregistré.
	 */
	public static function repair() {
		if ( ! function_exists( 'wc_update_product_lookup_tables_column' ) ) {
			return;
		}

		wc_update_product_lookup_tables_column( 'min_max_price' );
		wp_cache_set_last_changed( 'posts' );
		update_option( self::OPTION, self::REPAIR_VERSION, false );
	}
}
