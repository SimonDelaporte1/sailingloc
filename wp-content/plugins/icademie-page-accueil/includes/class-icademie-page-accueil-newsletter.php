<?php
/**
 * Formulaire d'inscription à la newsletter : celui de la page Newsletter.
 *
 * @package Icademie_Page_Accueil
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrouve le formulaire HubSpot de la page Newsletter et l'affiche sur l'accueil.
 *
 * Le formulaire n'est pas recopié : il est lu à chaque affichage dans le contenu de la page
 * Newsletter. Changer de formulaire sur cette page le change donc aussi sur l'accueil.
 *
 * Intégrations reconnues :
 * - code d'intégration actuel de HubSpot : <div class="hs-form-frame" data-region data-form-id
 *   data-portal-id> et le script https://js-<région>.hsforms.net/forms/embed/<compte>.js
 *   (c'est celui de la page Newsletter du site) ;
 * - ancien code : hbspt.forms.create( { region, portalId, formId } ) ;
 * - code court de l'extension HubSpot : [hubspot type="form" …].
 *
 * Seuls les identifiants sont repris, vérifiés un par un : aucun code HTML ou JavaScript de la
 * page n'est recopié tel quel.
 */
final class Icademie_Page_Accueil_Newsletter {

	/** Poignée du script de HubSpot. */
	const SCRIPT_HANDLE = 'icademie-page-accueil-hubspot';

	/** Conteneur de l'ancien code d'intégration. */
	const LEGACY_TARGET = 'icademie-accueil-hubspot';

	/**
	 * Page Newsletter : celle de l'adresse indiquée dans les réglages, sinon la page « newsletter ».
	 *
	 * @return WP_Post|null Page (ou article) publiée du site, ou null : adresse vide sans page
	 *                      « newsletter », ou adresse qui ne mène à aucune page du site.
	 */
	public static function page() {
		$url = (string) Icademie_Page_Accueil_Settings::get( 'newsletter_url' );

		if ( '' === $url ) {
			$page = get_page_by_path( 'newsletter' );
		} else {
			// Pas de get_post( 0 ) : il renverrait le contenu affiché, pas une page de l'adresse.
			$post_id = url_to_postid( $url );
			$page    = $post_id ? get_post( $post_id ) : null;
		}

		return ( $page instanceof WP_Post && 'publish' === $page->post_status && is_post_type_viewable( $page->post_type ) ) ? $page : null;
	}

	/**
	 * Adresse de la newsletter : celle des réglages, sinon celle de la page « newsletter ».
	 *
	 * Sert au bouton qui remplace le formulaire (sans JavaScript, ou sans formulaire lisible).
	 *
	 * @return string Adresse, ou vide (zone Newsletter masquée).
	 */
	public static function url() {
		$url = (string) Icademie_Page_Accueil_Settings::get( 'newsletter_url' );

		if ( '' !== $url ) {
			return $url;
		}

		$page = self::page();

		return $page ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Formulaire HubSpot trouvé dans le contenu d'une page.
	 *
	 * @param WP_Post $page Page Newsletter.
	 * @return array|null Type (« frame », « legacy » ou « shortcode »), compte, formulaire, région
	 *                    et adresse du script ; null si aucun formulaire n'est trouvé.
	 */
	public static function form( $page ) {
		$content = (string) $page->post_content;

		// Code d'intégration actuel.
		if ( preg_match( '/<div\b[^>]*\bhs-form-frame\b[^>]*>/i', $content, $tag ) ) {
			$portal = self::attribute( $tag[0], 'data-portal-id' );
			$form   = self::attribute( $tag[0], 'data-form-id' );
			$region = self::attribute( $tag[0], 'data-region' );

			if ( self::valid( $portal, $form, $region ) ) {
				// Le script de la page s'il est là (même adresse exacte), sinon l'adresse de HubSpot.
				$script = preg_match( '#https://js(?:-[a-z0-9]+)?\.hsforms\.net/forms/embed/' . preg_quote( $portal, '#' ) . '\.js#i', $content, $src )
					? $src[0]
					: self::script_host( $region ) . '/forms/embed/' . $portal . '.js';

				return array(
					'type'   => 'frame',
					'portal' => $portal,
					'form'   => $form,
					'region' => $region,
					'script' => $script,
				);
			}
		}

		// Ancien code d'intégration.
		if ( preg_match( '/hbspt\.forms\.create\s*\(\s*\{(.*?)\}\s*\)/is', $content, $call ) ) {
			$portal = preg_match( '/\bportalId\s*:\s*["\']?(\d+)/', $call[1], $m ) ? $m[1] : '';
			$form   = preg_match( '/\bformId\s*:\s*["\']([0-9a-f-]+)["\']/i', $call[1], $m ) ? $m[1] : '';
			$region = preg_match( '/\bregion\s*:\s*["\']([a-z0-9]+)["\']/i', $call[1], $m ) ? $m[1] : '';

			if ( self::valid( $portal, $form, $region ) ) {
				return array(
					'type'   => 'legacy',
					'portal' => $portal,
					'form'   => $form,
					'region' => $region,
					'script' => self::script_host( $region ) . '/forms/embed/v2.js',
				);
			}
		}

		// Extension HubSpot.
		if ( preg_match( '/\[hubspot\b[^\]]*\btype=["\']?form\b[^\]]*\]/i', $content, $shortcode ) ) {
			$portal = preg_match( '/\bportal=["\']?(\d+)/i', $shortcode[0], $m ) ? $m[1] : '';
			$form   = preg_match( '/\bid=["\']?([0-9a-f-]+)/i', $shortcode[0], $m ) ? $m[1] : '';

			return array(
				'type'      => 'shortcode',
				'portal'    => $portal,
				'form'      => $form,
				'region'    => '',
				'script'    => '',
				'shortcode' => $shortcode[0],
			);
		}

		return null;
	}

	/**
	 * Valeur d'un attribut d'une balise HTML.
	 *
	 * @param string $tag  Balise ouvrante.
	 * @param string $name Nom de l'attribut.
	 * @return string
	 */
	private static function attribute( $tag, $name ) {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
			return trim( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Identifiants HubSpot valides : compte (chiffres), formulaire (UUID), région (na1, eu1…).
	 *
	 * @param string $portal Compte.
	 * @param string $form   Formulaire.
	 * @param string $region Région (vide : na1).
	 * @return bool
	 */
	private static function valid( $portal, $form, $region ) {
		return (bool) preg_match( '/^\d{1,12}$/', $portal )
			&& (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $form )
			&& ( '' === $region || (bool) preg_match( '/^[a-z]{2,4}\d{1,2}$/', $region ) );
	}

	/**
	 * Domaine des scripts de formulaires HubSpot d'une région.
	 *
	 * @param string $region Région (na1, eu1…).
	 * @return string
	 */
	private static function script_host( $region ) {
		return ( '' === $region || 'na1' === $region ) ? 'https://js.hsforms.net' : 'https://js-' . $region . '.hsforms.net';
	}

	/**
	 * Code du formulaire, et chargement du script de HubSpot.
	 *
	 * Dans l'aperçu de l'éditeur, les scripts ne s'exécutent pas : un cadre indique où le formulaire
	 * sera affiché.
	 *
	 * @param array $form Formulaire (voir form()).
	 * @return string HTML.
	 */
	public static function markup( $form ) {
		if ( Icademie_Page_Accueil::is_editor_preview() ) {
			return '<p class="icademie-accueil__newsletter-preview">' . esc_html__( 'Formulaire HubSpot de la page Newsletter (affiché sur le site).', 'icademie-page-accueil' ) . '</p>';
		}

		switch ( $form['type'] ) {
			case 'frame':
				// Même chargement que sur la page Newsletter : script différé, sans numéro de version.
				wp_enqueue_script(
					self::SCRIPT_HANDLE,
					$form['script'],
					array(),
					null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Adresse de HubSpot, à garder exacte.
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					)
				);

				return sprintf(
					'<div class="hs-form-frame" data-region="%1$s" data-form-id="%2$s" data-portal-id="%3$s"></div>',
					esc_attr( '' !== $form['region'] ? $form['region'] : 'na1' ),
					esc_attr( $form['form'] ),
					esc_attr( $form['portal'] )
				);

			case 'legacy':
				wp_enqueue_script( self::SCRIPT_HANDLE, $form['script'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Adresse de HubSpot.

				$settings = array(
					'portalId' => $form['portal'],
					'formId'   => $form['form'],
					'target'   => '#' . self::LEGACY_TARGET,
				);

				if ( '' !== $form['region'] ) {
					$settings['region'] = $form['region'];
				}

				wp_add_inline_script( self::SCRIPT_HANDLE, 'hbspt.forms.create(' . wp_json_encode( $settings ) . ');' );

				return '<div id="' . esc_attr( self::LEGACY_TARGET ) . '"></div>';

			case 'shortcode':
				return shortcode_exists( 'hubspot' ) ? do_shortcode( $form['shortcode'] ) : '';
		}

		return '';
	}
}
