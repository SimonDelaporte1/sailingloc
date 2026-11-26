<?php
/**
 * Accès aux données personnelles telles que WooCommerce les exporte (outil « Exporter les données
 * personnelles » de WordPress), pour un compte et ses commandes plutôt que pour une adresse e-mail.
 *
 * Chargé seulement au moment du téléchargement.
 *
 * @package Icademie_Comptes
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Privacy_Exporters', false ) ) {
	include_once WC_ABSPATH . 'includes/class-wc-privacy-exporters.php';
}

/**
 * Exportateurs de WooCommerce, rendus accessibles.
 */
final class Icademie_Comptes_Export_WC extends WC_Privacy_Exporters {

	/**
	 * Adresses et coordonnées du client.
	 *
	 * @param WP_User $user Compte.
	 * @return array Paires « name »/« value ».
	 */
	public static function customer( $user ) {
		return self::get_customer_personal_data( $user );
	}

	/**
	 * Données personnelles d'une commande.
	 *
	 * @param WC_Order $order Commande.
	 * @return array Paires « name »/« value ».
	 */
	public static function order( $order ) {
		return self::get_order_personal_data( $order );
	}
}
