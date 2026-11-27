<?php
/**
 * Textes de WooCommerce et de WC Vendors adaptés à la location : la page de tous les bateaux
 * s'appelle « Location » (et non « Boutique »), la page publique d'un propriétaire « page de
 * location » (et non « boutique » ou « magasin »).
 *
 * Remplacés au moment de la traduction (filtre « gettext_{domaine} ») : rien n'est modifié dans les
 * extensions ni dans les contenus, et les mises à jour de leurs traductions n'y changent rien.
 *
 * Liens « politique de confidentialité » et « conditions générales » des textes de WooCommerce
 * (inscription, validation de commande classique) : ils s'ouvrent dans un nouvel onglet (la saisie
 * en cours n'est pas perdue), désormais annoncé « (nouvel onglet) » (Opquast 146).
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Textes adaptés.
 */
final class Icademie_Habillage_Textes {

	/**
	 * Branche les filtres de traduction.
	 */
	public static function init() {
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'woocommerce' ), 10, 2 );
		add_filter( 'gettext_wc-vendors', array( __CLASS__, 'wc_vendors' ), 10, 2 );
		add_filter( 'woocommerce_get_privacy_policy_text', array( __CLASS__, 'policy_links' ) );
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( __CLASS__, 'policy_links' ) );
	}

	/**
	 * Liens des pages légales dans les textes de WooCommerce : même lien que WooCommerce (classe,
	 * nouvel onglet), avec « (nouvel onglet) » pour les lecteurs d'écran.
	 *
	 * Les marqueurs « [privacy_policy] » et « [terms] » sont remplacés avant que WooCommerce ne le
	 * fasse lui-même (wc_replace_policy_page_link_placeholders(), sans filtre). Sans page
	 * réglée, le marqueur est laissé à WooCommerce (texte sans lien).
	 *
	 * @param string $text Texte avec les marqueurs.
	 * @return string
	 */
	public static function policy_links( $text ) {
		if ( ! is_string( $text ) || ( false === strpos( $text, '[privacy_policy]' ) && false === strpos( $text, '[terms]' ) ) ) {
			return $text;
		}

		$links = array(
			// Libellés de WooCommerce, avec leur traduction.
			'[privacy_policy]' => array( function_exists( 'wc_privacy_policy_page_id' ) ? (int) wc_privacy_policy_page_id() : 0, 'woocommerce-privacy-policy-link', translate( 'privacy policy', 'woocommerce' ) ),
			'[terms]'          => array( function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0, 'woocommerce-terms-and-conditions-link', translate( 'terms and conditions', 'woocommerce' ) ),
		);

		foreach ( $links as $placeholder => $link ) {
			if ( ! $link[0] || false === strpos( $text, $placeholder ) ) {
				continue;
			}

			$text = str_replace(
				$placeholder,
				sprintf(
					'<a href="%1$s" class="%2$s" target="_blank">%3$s<span class="screen-reader-text"> %4$s</span></a>',
					esc_url( get_permalink( $link[0] ) ),
					esc_attr( $link[1] ),
					esc_html( $link[2] ),
					esc_html__( '(nouvel onglet)', 'icademie-habillage' )
				),
				$text
			);
		}

		return $text;
	}

	/**
	 * WooCommerce, partout (site et éditeur) : panier vide.
	 *
	 * @param string $translation Traduction.
	 * @param string $text        Texte d'origine (anglais).
	 * @return string
	 */
	public static function woocommerce( $translation, $text ) {
		// Appelé pour chaque texte de WooCommerce : test rapide d'abord.
		if ( 'Return to shop' !== $text && 'New in store' !== $text ) {
			return $translation;
		}

		$texts = array(
			'Return to shop' => __( 'Voir les bateaux en location', 'icademie-habillage' ),
			'New in store'   => __( 'Nouveautés en location', 'icademie-habillage' ),
		);

		return $texts[ $text ];
	}

	/**
	 * WC Vendors, sur le site (tableau de bord des propriétaires) : menu et réglages de la page
	 * publique du propriétaire. L'administration garde les textes de WC Vendors.
	 *
	 * @param string $translation Traduction.
	 * @param string $text        Texte d'origine (anglais).
	 * @return string
	 */
	public static function wc_vendors( $translation, $text ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ( false === stripos( $text, 'store' ) && false === stripos( $text, 'shop' ) ) ) {
			return $translation;
		}

		$texts = self::wc_vendors_texts();

		return isset( $texts[ trim( $text ) ] ) ? $texts[ trim( $text ) ] : $translation;
	}

	/**
	 * Textes de WC Vendors remplacés, par texte d'origine.
	 *
	 * @return string[]
	 */
	private static function wc_vendors_texts() {
		static $texts = null;

		if ( null === $texts ) {
			$texts = array(
				'View store'                                    => __( 'Voir ma page de location', 'icademie-habillage' ),
				'Store'                                         => __( 'Page de location', 'icademie-habillage' ),
				'Store Information'                             => __( 'Informations de la page de location', 'icademie-habillage' ),
				'Store name'                                    => __( 'Nom de la page de location', 'icademie-habillage' ),
				'Your shop name is public and must be unique.' => __( 'Ce nom est public et doit être unique.', 'icademie-habillage' ),
				'Store website / Blog URL'                      => __( 'Site web ou blog', 'icademie-habillage' ),
				'Store description'                             => __( 'Description de la page de location', 'icademie-habillage' ),
				'Store Contact and Address'                     => __( 'Coordonnées et adresse', 'icademie-habillage' ),
				'Store Phone'                                   => __( 'Téléphone', 'icademie-habillage' ),
				'Store phone'                                   => __( 'Téléphone', 'icademie-habillage' ),
				'This is your store contact number'             => __( 'Numéro auquel les locataires peuvent vous joindre', 'icademie-habillage' ),
				'Store Country'                                 => __( 'Pays', 'icademie-habillage' ),
				'Store country'                                 => __( 'Pays', 'icademie-habillage' ),
				'Store Address'                                 => __( 'Adresse', 'icademie-habillage' ),
				'Store address'                                 => __( 'Adresse', 'icademie-habillage' ),
			);
		}

		return $texts;
	}
}
