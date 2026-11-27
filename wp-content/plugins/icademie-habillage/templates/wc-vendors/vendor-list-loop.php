<?php
/**
 * Liste des propriétaires (WC Vendors) : carte d'un propriétaire.
 *
 * Remplace templates/front/vendor-list-loop.php de WC Vendors (version 2.4.2), voir
 * Icademie_Habillage_Vendeurs. Même code, sauf le lien de l'avatar (Opquast 117) : l'image a pour
 * texte alternatif le nom de la boutique, et ce lien, doublon du lien du nom juste en dessous
 * (même adresse), est retiré de la navigation au clavier et des lecteurs d'écran
 * (tabindex="-1", aria-hidden), comme les photos des cartes des bateaux.
 *
 * WC Vendors passe la sortie dans wp_kses() avec sa propre liste d'attributs (aria-hidden et
 * tabindex sur les liens : ajoutés par Icademie_Habillage_Vendeurs).
 *
 * @package Icademie_Habillage
 *
 * @var string $shop_name        Nom de la boutique.
 * @var string $shop_description Description de la boutique.
 * @var string $shop_link        Adresse de la boutique.
 * @var int    $vendor_id        Propriétaire.
 * @var string $avatar           Avatar (balise img).
 * @var string $phone            Téléphone de la boutique.
 * @var string $address          Adresse de la boutique.
 */

defined( 'ABSPATH' ) || exit;

$icademie_avatar = Icademie_Habillage_Vendeurs::avatar_with_alt( (string) $avatar, (string) $shop_name );
?>
<div class="vendor_list">
	<div class="vendor_list_avatar">
		<a href="<?php echo esc_url( $shop_link ); ?>" tabindex="-1" aria-hidden="true"><?php echo wp_kses_post( $icademie_avatar ); ?></a>
	</div>
	<div class="vendor_list_info">
		<h3 class="vendor_list--shop-name">
			<a href="<?php echo esc_url( $shop_link ); ?>"><?php echo esc_html( $shop_name ); ?></a>
		</h3>
		<small class="vendors_list--shop-phone"><span class="dashicons dashicons-smartphone"></span><span><?php echo esc_html( $phone ); ?></span></small> <br/>
		<small class="vendors_list--shop-address"><span class="dashicons dashicons-location"></span><span><?php echo esc_html( $address ); ?></span></small><br/>
		<a class="button vendors_list--shop-link" href="<?php echo esc_url( $shop_link ); ?>"><?php esc_html_e( 'Visit Store', 'wc-vendors' ); ?></a>
	</div>

</div>
