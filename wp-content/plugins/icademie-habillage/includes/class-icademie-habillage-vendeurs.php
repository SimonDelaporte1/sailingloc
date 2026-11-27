<?php
/**
 * Liste des propriétaires (WC Vendors) : cartes et barre de recherche accessibles.
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remplace deux modèles de la liste des propriétaires de WC Vendors par ceux de l'extension.
 *
 * « vendor-list-loop.php » (carte d'un propriétaire) : l'avatar, lien vers la boutique, a pour
 * texte alternatif le nom de la boutique (Opquast 117) ; doublon du lien du nom, il est retiré du
 * clavier et des lecteurs d'écran.
 *
 * « vendor-list-filter.php » (recherche et changement d'affichage au-dessus de la liste) :
 * - le champ de recherche a une étiquette visible qui lui est propre (Opquast 69), en français,
 *   au lieu d'un simple texte indicatif anglais (« Search for a vendeur ») ;
 * - la recherche est toujours affichée : sur smartphone, WC Vendors la cache derrière une case à
 *   cocher invisible et une étiquette qui ne reçoit pas le focus (inutilisable au clavier) ;
 * - boutons « grille » et « liste », nombre de résultats et effacement de la recherche nommés en
 *   français, icônes masquées aux lecteurs d'écran.
 *
 * Une copie de l'un de ces modèles placée dans le thème (wc-vendors/front/…) reste prioritaire.
 */
final class Icademie_Habillage_Vendeurs {

	/** Modèles remplacés. */
	const TEMPLATES = array( 'vendor-list-filter.php', 'vendor-list-loop.php' );

	/**
	 * Branche les filtres.
	 */
	public static function init() {
		add_filter( 'wc_get_template', array( __CLASS__, 'template' ), 10, 5 );
		add_filter( 'wcvendors_allowed_html_tags', array( __CLASS__, 'allowed_tags' ) );
	}

	/**
	 * Modèle de l'extension à la place de celui de WC Vendors.
	 *
	 * @param string $template      Fichier trouvé.
	 * @param string $template_name Nom du modèle.
	 * @param array  $args          Variables du modèle.
	 * @param string $template_path Dossier des modèles dans le thème.
	 * @param string $default_path  Dossier des modèles de l'extension qui les fournit.
	 * @return string
	 */
	public static function template( $template, $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		if ( ! in_array( $template_name, self::TEMPLATES, true ) || ! defined( 'WCV_PLUGIN_DIR' ) ) {
			return $template;
		}

		// Seulement le fichier de WC Vendors : une copie du thème garde la priorité.
		if ( 0 !== strpos( wp_normalize_path( (string) $template ), wp_normalize_path( WCV_PLUGIN_DIR ) ) ) {
			return $template;
		}

		$own = dirname( __DIR__ ) . '/templates/wc-vendors/' . $template_name;

		return is_readable( $own ) ? $own : $template;
	}

	/**
	 * WC Vendors filtre la sortie de ces modèles (wp_kses) : « aria-hidden » est accepté sur les
	 * icônes (span) et, avec « tabindex », sur les liens (avatar en double du nom).
	 *
	 * @param array $tags Balises et attributs autorisés.
	 * @return array
	 */
	public static function allowed_tags( $tags ) {
		if ( isset( $tags['span'] ) && is_array( $tags['span'] ) ) {
			$tags['span']['aria-hidden'] = true;
		}

		if ( isset( $tags['a'] ) && is_array( $tags['a'] ) ) {
			$tags['a']['aria-hidden'] = true;
			$tags['a']['tabindex']    = true;
		}

		return $tags;
	}

	/**
	 * Avatar d'un propriétaire, avec le nom de sa boutique pour texte alternatif (WordPress lui
	 * donne un texte alternatif vide).
	 *
	 * @param string $avatar    Balise img de l'avatar.
	 * @param string $shop_name Nom de la boutique.
	 * @return string
	 */
	public static function avatar_with_alt( $avatar, $shop_name ) {
		$shop_name = trim( wp_strip_all_tags( $shop_name ) );

		if ( '' === $avatar || '' === $shop_name ) {
			return $avatar;
		}

		$tags = new WP_HTML_Tag_Processor( $avatar );

		if ( ! $tags->next_tag( 'img' ) ) {
			return $avatar;
		}

		$tags->set_attribute( 'alt', $shop_name );

		return $tags->get_updated_html();
	}

	/**
	 * Nom des propriétaires, comme dans les réglages de WC Vendors (« vendeur », « vendeurs »).
	 *
	 * @param bool $singular Singulier.
	 * @return string
	 */
	public static function vendor_name( $singular ) {
		$name = function_exists( 'wcv_get_vendor_name' ) ? (string) wcv_get_vendor_name( $singular, false ) : '';

		if ( '' === $name ) {
			$name = $singular ? __( 'vendeur', 'icademie-habillage' ) : __( 'vendeurs', 'icademie-habillage' );
		}

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
	}
}
