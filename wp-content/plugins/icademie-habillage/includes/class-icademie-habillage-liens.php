<?php
/**
 * Liens vides des contenus (Opquast 136).
 *
 * @package Icademie_Habillage
 */

defined( 'ABSPATH' ) || exit;

/**
 * Un lien sans intitulé (ni texte, ni image, ni attribut qui le nomme) reste parfois dans un
 * contenu après une modification dans l'éditeur, par exemple <a href="mailto:…"> </a> autour d'une
 * espace. Invisible à l'écran, il est annoncé « lien » sans nom par les lecteurs d'écran et reçoit
 * le focus au clavier.
 *
 * À l'affichage des contenus (filtre « the_content »), un tel lien est retiré et son contenu
 * (l'espace) gardé. Rien n'est modifié en base : le contenu reste à corriger dans l'éditeur.
 *
 * Un lien est gardé dès qu'il peut avoir un nom : texte (même un seul caractère), image ou autre
 * élément inclus (img, svg, picture…), attribut aria-label, aria-labelledby ou title. Une ancre
 * sans adresse (<a id="…"></a>, cible d'un lien interne) n'est pas un lien : elle est gardée.
 */
final class Icademie_Habillage_Liens {

	/**
	 * Branche le filtre, après la mise en forme des blocs et des paragraphes.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'remove_empty_links' ), 20 );
	}

	/**
	 * Retire les liens sans intitulé d'un contenu.
	 *
	 * @param string $content Contenu affiché.
	 * @return string
	 */
	public static function remove_empty_links( $content ) {
		if ( ! is_string( $content ) || false === stripos( $content, '<a' ) ) {
			return $content;
		}

		$result = preg_replace_callback( '#<a\b([^>]*)>(.*?)</a\s*>#is', array( __CLASS__, 'unwrap_if_empty' ), $content );

		return is_string( $result ) ? $result : $content;
	}

	/**
	 * Le lien trouvé, ou seulement son contenu s'il n'a pas d'intitulé.
	 *
	 * @param string[] $match Lien entier, attributs, contenu.
	 * @return string
	 */
	private static function unwrap_if_empty( $match ) {
		list( $link, $attributes, $inner ) = $match;

		// Pas d'adresse : ancre de page, pas un lien.
		if ( ! preg_match( '/\shref\s*=/i', $attributes ) ) {
			return $link;
		}

		// Nommé par un attribut.
		if ( preg_match( '/\s(?:aria-label|aria-labelledby|title)\s*=\s*(["\'])(?:(?!\1).)*[^\s"\'](?:(?!\1).)*\1/is', $attributes ) ) {
			return $link;
		}

		// Image ou autre élément qui peut porter le nom du lien : on n'y touche pas.
		if ( preg_match( '/<(?:img|svg|picture|video|audio|iframe|object|embed|canvas|input|button|select|textarea|math)\b/i', $inner ) ) {
			return $link;
		}

		$text = html_entity_decode( wp_strip_all_tags( $inner ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\s\x{00A0}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u', '', $text );

		if ( null === $text || '' !== $text ) {
			return $link;
		}

		return $inner;
	}
}
