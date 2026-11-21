<?php
/**
 * Plugin Name:       Icademie – Avis sur les produits
 * Description:       Active les avis (commentaire et note) sur chaque nouveau produit : produit créé par un vendeur depuis son tableau de bord WC Vendors, ajouté dans l'administration ou dupliqué. La case « Activer les avis » (onglet « Avancé » du produit) reste modifiable dans l'administration. Les produits existants ne sont pas modifiés. Notes détaillées : en plus de la note générale, l'avis note de 0 à 5 la conformité de l'annonce, la propreté, la communication et le rapport qualité-prix ; chaque avis affiche ses notes et la fiche du produit la moyenne de chaque critère.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       icademie-avis-produits
 *
 * @package Icademie_Avis_Produits
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-icademie-avis-criteres.php';

/**
 * Avis activés sur chaque nouveau produit.
 *
 * Sans ce plugin, un nouveau produit reçoit le réglage de WordPress des nouvelles publications
 * (Réglages › Commentaires : « Autoriser les visiteurs à publier des commentaires sur les nouvelles
 * publications »). WC Vendors n'indique rien quand un vendeur crée un produit, et son formulaire n'a
 * pas de case pour les avis : si ce réglage est décoché, aucun produit de vendeur n'accepte d'avis.
 *
 * Ce plugin donne aux produits leur propre valeur par défaut, avis activés, sans changer celle des
 * articles et des pages :
 * - produit créé par un vendeur (tableau de bord WC Vendors) ;
 * - produit ajouté dans l'administration (Produits › Ajouter un produit) : la case « Activer les
 *   avis » est cochée d'avance, l'administrateur peut la décocher ;
 * - copie d'un produit (« Dupliquer » de WooCommerce, dans l'administration ou dans le tableau de
 *   bord vendeur ; « Cloner » ou « Nouveau brouillon » de Yoast Duplicate Post) : la copie accepte
 *   les avis même si le produit copié ne les accepte pas.
 *
 * Les produits existants ne changent pas. Un produit créé avec un état d'avis précisé (import CSV,
 * API REST) garde cet état.
 *
 * Les notes (étoiles) font partie des avis : elles s'affichent si l'option de WooCommerce « Activer
 * les évaluations par étoiles sur les avis » est cochée (WooCommerce › Réglages › Produits).
 */
final class Icademie_Avis_Produits {

	const VERSION = '1.1.0';

	/** Fichier principal de l'extension. */
	const FILE = __FILE__;

	/**
	 * Branche les filtres et actions.
	 */
	public static function init() {
		add_filter( 'get_default_comment_status', array( __CLASS__, 'default_status' ), 10, 3 );
		add_action( 'woocommerce_product_duplicate_before_save', array( __CLASS__, 'open_product_copy' ) );
		add_filter( 'duplicate_post_new_post', array( __CLASS__, 'open_post_copy' ), 10, 2 );
	}

	/**
	 * Avis activés par défaut sur un nouveau produit.
	 *
	 * WordPress prend cette valeur pour tout produit créé sans état d'avis précisé : enregistrement
	 * d'un vendeur (WC Vendors), brouillon automatique de l'écran « Ajouter un produit » (d'où la
	 * case cochée d'avance). Les rétroliens (pingbacks, trackbacks) gardent le réglage de WordPress.
	 *
	 * @param string $status       Valeur par défaut, « open » ou « closed ».
	 * @param string $post_type    Type de contenu créé.
	 * @param string $comment_type Type de réaction : « comment », « pingback » ou « trackback ».
	 * @return string
	 */
	public static function default_status( $status, $post_type, $comment_type ) {
		if ( 'product' === $post_type && 'pingback' !== $comment_type && 'trackback' !== $comment_type ) {
			return 'open';
		}

		return $status;
	}

	/**
	 * Copie faite par WooCommerce : lien « Dupliquer » de l'administration et du tableau de bord
	 * vendeur (WC Vendors utilise le même code).
	 *
	 * Sans cela, la copie reprend l'état du produit copié, souvent un produit créé avant ce plugin.
	 * Les variations d'un produit variable sont copiées par la même action : WooCommerce ferme
	 * toujours leurs avis, on n'y touche pas.
	 *
	 * @param WC_Product $copy Copie, pas encore enregistrée.
	 */
	public static function open_product_copy( $copy ) {
		if ( $copy instanceof WC_Product && ! $copy->is_type( 'variation' ) ) {
			$copy->set_reviews_allowed( true );
		}
	}

	/**
	 * Copie faite par Yoast Duplicate Post (« Cloner », « Nouveau brouillon »), quand cette extension
	 * est activée pour les produits.
	 *
	 * La copie de « Réécrire et republier » ne passe pas par ce filtre : elle garde l'état du produit,
	 * qu'elle remplace ensuite.
	 *
	 * @param array   $new_post Données de la copie.
	 * @param WP_Post $post     Produit copié.
	 * @return array
	 */
	public static function open_post_copy( $new_post, $post ) {
		if ( $post instanceof WP_Post && 'product' === $post->post_type ) {
			$new_post['comment_status'] = 'open';
		}

		return $new_post;
	}
}

Icademie_Avis_Produits::init();
Icademie_Avis_Criteres::init();
