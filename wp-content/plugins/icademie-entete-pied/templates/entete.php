<?php
/**
 * En-tête SailingLoc.
 *
 * Ordinateur : logo, menu principal (sous-menus déroulants), langue, recherche, panier,
 * « Top destinations » (bouton qui déplie l'arborescence des catégories), « Proposer mon bateau » et
 * connexion. Tablette et smartphone : le menu s'ouvre en plein écran ; sur smartphone, « Top
 * destinations » et « Proposer mon bateau » y sont rangés, au-dessus de la connexion.
 * Sans script, le menu reste affiché dans la barre, à toutes les largeurs.
 *
 * @package Icademie_Entete_Pied
 *
 * @var array    $data Données préparées par Icademie_Entete_Pied_View::header().
 * @var callable $icon Fonction des icônes.
 */

defined( 'ABSPATH' ) || exit;

$uid = wp_unique_id( 'icademie-entete-' );

/**
 * Liste des entrées du menu.
 *
 * @param array[]  $items   Entrées.
 * @param string   $prefix  Préfixe des identifiants.
 * @param bool     $panel   Menu du panneau plein écran (sous-menus dépliables).
 * @param callable $icon    Icônes.
 * @param int      $depth   Profondeur.
 */
$icademie_entete_menu = static function ( $items, $prefix, $panel, $icon, $depth = 1 ) use ( &$icademie_entete_menu ) {
	echo '<ul class="icademie-entete__menu icademie-entete__menu--' . (int) $depth . '">';

	foreach ( $items as $index => $item ) {
		$id      = $prefix . '-' . $index;
		$classes = 'icademie-entete__item' . ( $item['children'] ? ' has-children' : '' ) . ( $item['current'] ? ' is-current' : '' );
		$attrs   = ( $item['page'] ? ' aria-current="page"' : '' ) . ( $item['new_tab'] ? ' target="_blank" rel="noopener"' : '' );

		echo '<li class="' . esc_attr( $classes ) . '">';

		if ( $item['children'] && $panel ) {
			// Panneau : sous-menu dépliable ; le lien de l'entrée, s'il existe, vient en premier (« Tout voir : France »
			// plutôt que « France » répété sous « France »).
			echo '<details class="icademie-entete__details"' . ( $item['current'] ? ' open' : '' ) . '><summary class="icademie-entete__summary">' . esc_html( $item['label'] ) . call_user_func( $icon, 'chevron-down', 18 ) . '</summary>';
			if ( '' !== $item['url'] ) {
				array_unshift(
					$item['children'],
					array(
						/* translators: %s: entrée du menu. */
						'label'    => sprintf( __( 'Tout voir : %s', 'icademie-entete-pied' ), $item['label'] ),
						'url'      => $item['url'],
						'current'  => $item['page'],
						'page'     => $item['page'],
						'new_tab'  => $item['new_tab'],
						'children' => array(),
					)
				);
			}
			$icademie_entete_menu( $item['children'], $id, $panel, $icon, $depth + 1 );
			echo '</details>';
		} elseif ( $item['children'] ) {
			// Barre : lien de l'entrée (s'il existe) et bouton du sous-menu.
			if ( '' !== $item['url'] ) {
				echo '<a class="icademie-entete__link" href="' . esc_url( $item['url'] ) . '"' . $attrs . '>' . esc_html( $item['label'] ) . '</a>';
				/* translators: %s: entrée du menu. */
				echo '<button type="button" class="icademie-entete__toggle" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" data-submenu-toggle><span class="icademie-ep__sr">' . esc_html( sprintf( __( 'Sous-menu « %s »', 'icademie-entete-pied' ), $item['label'] ) ) . '</span>' . call_user_func( $icon, 'chevron-down', 16 ) . '</button>';
			} else {
				echo '<button type="button" class="icademie-entete__link icademie-entete__toggle icademie-entete__toggle--label" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" data-submenu-toggle>' . esc_html( $item['label'] ) . call_user_func( $icon, 'chevron-down', 16 ) . '</button>';
			}
			echo '<div class="icademie-entete__submenu" id="' . esc_attr( $id ) . '">';
			$icademie_entete_menu( $item['children'], $id, $panel, $icon, $depth + 1 );
			echo '</div>';
		} else {
			echo '<a class="icademie-entete__link" href="' . esc_url( $item['url'] ) . '"' . $attrs . '>' . esc_html( $item['label'] ) . '</a>';
		}

		echo '</li>';
	}

	echo '</ul>';
};

/**
 * Bouton « Top destinations » : un clic déplie l'arborescence des catégories (élément details : il
 * s'ouvre aussi sans script). Barre : carte déroulante, une colonne par catégorie principale ;
 * panneau : liste sous le bouton.
 *
 * @param array    $destinations « label » et « children » (catégories, avec leurs sous-catégories).
 * @param string   $context      « barre » ou « panneau ».
 * @param callable $icon         Icônes.
 */
$icademie_entete_destinations = static function ( $destinations, $context, $icon ) {
	$tree = static function ( $items, $depth ) use ( &$tree ) {
		echo '<ul class="icademie-entete__tree icademie-entete__tree--' . (int) $depth . '">';

		foreach ( $items as $item ) {
			echo '<li class="icademie-entete__tree-item' . ( $item['current'] ? ' is-current' : '' ) . '">';
			echo '<a class="icademie-entete__tree-link" href="' . esc_url( $item['url'] ) . '"' . ( $item['page'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['label'] ) . '</a>';
			if ( $item['children'] ) {
				$tree( $item['children'], $depth + 1 );
			}
			echo '</li>';
		}

		echo '</ul>';
	};
	// Une colonne par catégorie principale, quatre au plus.
	$columns = (int) min( 4, count( $destinations['children'] ) );
	?>
	<details class="icademie-entete__destinations icademie-entete__destinations--<?php echo esc_attr( $context ); ?>" data-destinations>
		<summary class="icademie-entete__button icademie-entete__button--outline icademie-entete__destinations-toggle">
			<?php echo esc_html( $destinations['label'] ); ?>
			<?php echo call_user_func( $icon, 'chevron-down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
		</summary>
		<nav class="icademie-entete__destinations-menu" aria-label="<?php echo esc_attr( $destinations['label'] ); ?>" style="--ep-colonnes:<?php echo (int) $columns; ?>">
			<?php $tree( $destinations['children'], 1 ); ?>
		</nav>
	</details>
	<?php
};

/**
 * Formulaire de recherche de bateaux (recherche de produits de WooCommerce).
 *
 * @param string $id   Identifiant du champ.
 * @param array  $data Données de l'en-tête.
 * @param callable $icon Icônes.
 */
$icademie_entete_search = static function ( $id, $data, $icon ) {
	?>
	<form class="icademie-entete__search-form" role="search" method="get" action="<?php echo esc_url( $data['search_action'] ); ?>">
		<label class="icademie-entete__search-label" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Rechercher un bateau', 'icademie-entete-pied' ); ?></label>
		<span class="icademie-entete__search-row">
			<input class="icademie-entete__search-input" type="search" id="<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( $data['search_query'] ); ?>" placeholder="<?php esc_attr_e( 'Modèle, type, port…', 'icademie-entete-pied' ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit" class="icademie-entete__button">
				<?php echo call_user_func( $icon, 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				<?php esc_html_e( 'Rechercher', 'icademie-entete-pied' ); ?>
			</button>
		</span>
	</form>
	<?php
};

$logo      = $data['logo'];
$has_panel = $data['menu'] || $data['destinations'];
?>
<header class="icademie-entete<?php echo $data['compact'] ? ' is-compact' : ''; ?>" data-entete>
	<div class="icademie-entete__bar">
		<?php if ( $has_panel ) : ?>
			<button type="button" class="icademie-entete__icon-button icademie-entete__menu-open" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $uid ); ?>-panneau" data-menu-open hidden>
				<?php echo call_user_func( $icon, 'menu', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				<span class="icademie-ep__sr"><?php esc_html_e( 'Menu', 'icademie-entete-pied' ); ?></span>
			</button>
		<?php endif; ?>

		<a class="icademie-ep__logo icademie-entete__logo" href="<?php echo esc_url( $data['home'] ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $logo['first'] ); ?><?php if ( '' !== $logo['last'] ) : ?><b><?php echo esc_html( $logo['last'] ); ?></b><?php endif; ?><span class="icademie-ep__sr"><?php esc_html_e( ', accueil', 'icademie-entete-pied' ); ?></span>
		</a>

		<?php if ( $data['menu'] ) : ?>
			<nav class="icademie-entete__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'icademie-entete-pied' ); ?>" data-nav>
				<?php $icademie_entete_menu( $data['menu'], $uid . '-sous-menu', false, $icon ); ?>
			</nav>
		<?php endif; ?>

		<div class="icademie-entete__actions">
			<?php if ( '' !== $data['lang'] && ! $data['compact'] ) : ?>
				<span class="icademie-entete__lang"><?php echo esc_html( $data['lang'] ); ?></span>
			<?php endif; ?>
			<?php if ( $data['search'] ) : ?>
				<button type="button" class="icademie-entete__icon-button icademie-entete__search-open" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-recherche" data-search-toggle hidden>
					<?php echo call_user_func( $icon, 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-ep__sr"><?php esc_html_e( 'Rechercher un bateau', 'icademie-entete-pied' ); ?></span>
				</button>
			<?php endif; ?>
			<?php if ( '' !== $data['cart_url'] ) : ?>
				<?php /* Affiché par le script quand le panier n'est pas vide (cookie de WooCommerce) : pages en cache justes. */ ?>
				<a class="icademie-entete__icon-button icademie-entete__cart" href="<?php echo esc_url( $data['cart_url'] ); ?>" data-cart hidden>
					<?php echo call_user_func( $icon, 'cart', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-ep__sr"><?php esc_html_e( 'Panier', 'icademie-entete-pied' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $data['destinations'] ) : ?>
				<?php $icademie_entete_destinations( $data['destinations'], 'barre', $icon ); ?>
			<?php endif; ?>
			<?php if ( '' !== $data['propose'] ) : ?>
				<a class="icademie-entete__button icademie-entete__button--outline icademie-entete__propose" href="<?php echo esc_url( $data['propose'] ); ?>"><?php esc_html_e( 'Proposer mon bateau', 'icademie-entete-pied' ); ?></a>
			<?php endif; ?>
			<a class="icademie-entete__button icademie-entete__button--light icademie-entete__account" href="<?php echo esc_url( $data['account_url'] ); ?>">
				<?php echo call_user_func( $icon, 'user', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
				<span class="icademie-entete__account-label"><?php echo esc_html( $data['account_label'] ); ?></span>
			</a>
		</div>
	</div>

	<?php if ( $data['search'] ) : ?>
		<div class="icademie-entete__search" id="<?php echo esc_attr( $uid ); ?>-recherche" data-search-panel>
			<?php $icademie_entete_search( $uid . '-mots', $data, $icon ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $has_panel ) : ?>
		<dialog class="icademie-entete__panel" id="<?php echo esc_attr( $uid ); ?>-panneau" aria-label="<?php esc_attr_e( 'Menu', 'icademie-entete-pied' ); ?>" data-menu-panel>
			<div class="icademie-entete__panel-head">
				<span class="icademie-ep__logo" aria-hidden="true"><?php echo esc_html( $logo['first'] ); ?><?php if ( '' !== $logo['last'] ) : ?><b><?php echo esc_html( $logo['last'] ); ?></b><?php endif; ?></span>
				<button type="button" class="icademie-entete__icon-button" data-menu-close>
					<?php echo call_user_func( $icon, 'close', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-ep__sr"><?php esc_html_e( 'Fermer le menu', 'icademie-entete-pied' ); ?></span>
				</button>
			</div>
			<?php if ( $data['menu'] ) : ?>
				<nav class="icademie-entete__panel-nav" aria-label="<?php esc_attr_e( 'Menu principal', 'icademie-entete-pied' ); ?>">
					<?php $icademie_entete_menu( $data['menu'], $uid . '-panneau-menu', true, $icon ); ?>
				</nav>
			<?php endif; ?>
			<div class="icademie-entete__panel-actions">
				<?php if ( $data['destinations'] ) : ?>
					<?php /* Affiché quand le bouton de la barre est masqué (smartphone, barre trop étroite). */ ?>
					<?php $icademie_entete_destinations( $data['destinations'], 'panneau', $icon ); ?>
				<?php endif; ?>
				<?php if ( '' !== $data['propose'] ) : ?>
					<a class="icademie-entete__button icademie-entete__button--outline" href="<?php echo esc_url( $data['propose'] ); ?>"><?php esc_html_e( 'Proposer mon bateau', 'icademie-entete-pied' ); ?></a>
				<?php endif; ?>
				<a class="icademie-entete__button icademie-entete__button--light" href="<?php echo esc_url( $data['account_url'] ); ?>">
					<?php echo call_user_func( $icon, 'user', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<?php echo esc_html( $data['account_label'] ); ?>
				</a>
			</div>
			<?php if ( $data['search'] ) : ?>
				<div class="icademie-entete__panel-search">
					<?php $icademie_entete_search( $uid . '-panneau-mots', $data, $icon ); ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $data['lang'] ) : ?>
				<p class="icademie-entete__lang icademie-entete__panel-lang"><?php echo esc_html( $data['lang'] ); ?></p>
			<?php endif; ?>
		</dialog>
	<?php endif; ?>
</header>
