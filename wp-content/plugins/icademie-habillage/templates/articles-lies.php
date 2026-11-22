<?php
/**
 * Articles liés, sous un article.
 *
 * @package Icademie_Habillage
 *
 * @var array $data « cards ».
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="icademie-habillage__lies" aria-labelledby="icademie-habillage-lies-titre">
	<h2 class="icademie-habillage__section-titre" id="icademie-habillage-lies-titre"><?php esc_html_e( 'À lire aussi', 'icademie-habillage' ); ?></h2>
	<ul class="icademie-habillage__cartes">
		<?php foreach ( $data['cards'] as $card ) : ?>
			<?php include __DIR__ . '/carte.php'; ?>
		<?php endforeach; ?>
	</ul>
</section>
