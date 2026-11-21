<?php
/**
 * Contenu de la page catégorie : zones 2 à 10 des zonings.
 *
 * @package Icademie_Page_Categorie
 *
 * @var array $data Données préparées par Icademie_Page_Categorie_View::data().
 */

defined( 'ABSPATH' ) || exit;

$icon = array( 'Icademie_Page_Categorie', 'icon' );

$header     = $data['header'];
$search     = $data['search'];
$groups     = $data['filters']['groups'];
$chips      = $data['chips'];
$results    = $data['results'];
$pagination = $data['pagination'];
$active     = Icademie_Page_Categorie_Filters::count_active( $chips );

/* translators: Note moyenne, par exemple « 4,5 ». */
$rating_format = __( '%s sur 5', 'icademie-page-categorie' );

// Champs cachés : dates de la recherche et tri, gardés par le formulaire des filtres.
$filters_hidden = Icademie_Page_Categorie_Filters::without_filters( $data['params'] );
?>
<div class="icademie-categorie__inner">

	<?php if ( $data['setup'] ) : ?>
		<div class="icademie-categorie__setup" role="note">
			<p><strong><?php esc_html_e( 'Page de la catégorie : à compléter', 'icademie-page-categorie' ); ?></strong> <?php esc_html_e( '(message visible seulement par les gestionnaires de la boutique)', 'icademie-page-categorie' ); ?></p>
			<ul>
				<?php foreach ( $data['setup'] as $item ) : ?>
					<li>
						<?php if ( '' !== $item['url'] ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['label'] ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php /* Zone 2 : fil d'Ariane (smartphone : retour au niveau au-dessus). */ ?>
	<?php if ( $data['breadcrumb']['items'] ) : ?>
		<nav class="icademie-categorie__breadcrumb" aria-label="<?php esc_attr_e( 'Fil d\'Ariane', 'icademie-page-categorie' ); ?>">
			<ol class="icademie-categorie__crumbs">
				<?php foreach ( $data['breadcrumb']['items'] as $crumb ) : ?>
					<li class="icademie-categorie__crumb">
						<?php if ( '' !== $crumb['url'] ) : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $data['breadcrumb']['parent'] ) : ?>
				<a class="icademie-categorie__back" href="<?php echo esc_url( $data['breadcrumb']['parent']['url'] ); ?>">
					<?php echo call_user_func( $icon, 'chevron-left', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<span class="icademie-categorie__sr"><?php esc_html_e( 'Retour à', 'icademie-page-categorie' ); ?> </span><?php echo esc_html( $data['breadcrumb']['parent']['label'] ); ?>
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<?php /* Zone 3 : en-tête de la catégorie. */ ?>
	<header class="icademie-categorie__head<?php echo '' !== $header['image'] ? ' has-image' : ''; ?>">
		<div class="icademie-categorie__head-text">
			<h1 class="icademie-categorie__title"><?php echo esc_html( $header['title'] ); ?></h1>
			<p class="icademie-categorie__count"><?php echo esc_html( $header['count_text'] ); ?></p>
			<?php if ( '' !== $header['description'] ) : ?>
				<div class="icademie-categorie__intro"><?php echo wp_kses_post( $header['description'] ); ?></div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $header['image'] ) : ?>
			<div class="icademie-categorie__head-media">
				<?php echo $header['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image produite par WordPress. ?>
			</div>
		<?php endif; ?>
	</header>

	<?php /* Résultats d'une recherche de produits : les mots cherchés, modifiables. */ ?>
	<?php if ( $data['text_search'] ) : ?>
		<form class="icademie-categorie__text-search" role="search" method="get" action="<?php echo esc_url( $data['text_search']['action'] ); ?>">
			<label class="icademie-categorie__label" for="icademie-categorie-mots"><?php esc_html_e( 'Rechercher un bateau', 'icademie-page-categorie' ); ?></label>
			<div class="icademie-categorie__text-search-row">
				<input class="icademie-categorie__control" type="search" id="icademie-categorie-mots" name="s" value="<?php echo esc_attr( $data['text_search']['query'] ); ?>" autocomplete="off">
				<input type="hidden" name="post_type" value="product">
				<button type="submit" class="icademie-categorie__button">
					<?php echo call_user_func( $icon, 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
					<?php esc_html_e( 'Rechercher', 'icademie-page-categorie' ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>

	<?php /* Zone 4 : moteur de recherche de la page d'accueil (tablette et smartphone : résumé). */ ?>
	<?php if ( $search ) : ?>
		<section class="icademie-categorie__search" aria-label="<?php esc_attr_e( 'Modifier la recherche', 'icademie-page-categorie' ); ?>" data-search>
			<button type="button" class="icademie-categorie__summary" aria-haspopup="dialog" data-search-open hidden>
				<span class="icademie-categorie__summary-icon"><?php echo call_user_func( $icon, 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?></span>
				<span class="icademie-categorie__summary-text">
					<span class="icademie-categorie__sr"><?php esc_html_e( 'Modifier la recherche :', 'icademie-page-categorie' ); ?> </span>
					<span class="icademie-categorie__summary-part"><?php echo esc_html( $search['summary']['destination'] ); ?></span>
					<span class="icademie-categorie__summary-sep" aria-hidden="true">·</span>
					<?php if ( '' !== $search['summary']['dates'] ) : ?>
						<span class="icademie-categorie__summary-part"><?php echo esc_html( $search['summary']['dates'] ); ?></span>
					<?php else : ?>
						<span class="icademie-categorie__summary-part is-empty"><?php esc_html_e( 'dates', 'icademie-page-categorie' ); ?></span>
					<?php endif; ?>
					<?php if ( $search['has_passengers'] ) : ?>
						<span class="icademie-categorie__summary-sep" aria-hidden="true">·</span>
						<?php if ( '' !== $search['summary']['passengers'] ) : ?>
							<span class="icademie-categorie__summary-part"><?php echo esc_html( $search['summary']['passengers'] ); ?></span>
						<?php else : ?>
							<span class="icademie-categorie__summary-part is-empty"><?php esc_html_e( 'passagers', 'icademie-page-categorie' ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</span>
				<span class="icademie-categorie__summary-action" aria-hidden="true"><?php esc_html_e( 'Modifier', 'icademie-page-categorie' ); ?></span>
			</button>

			<div class="icademie-categorie__search-slot" data-search-slot>
				<form class="icademie-categorie__search-form" role="search" aria-label="<?php esc_attr_e( 'Rechercher un bateau', 'icademie-page-categorie' ); ?>" method="get" action="<?php echo esc_url( $search['action'] ); ?>" data-search-form>
					<input type="hidden" name="<?php echo esc_attr( $search['marker'] ); ?>" value="1">
					<input type="hidden" name="<?php echo esc_attr( Icademie_Page_Categorie_Filters::SEARCH_MARKER ); ?>" value="1">
					<?php foreach ( $search['hidden'] as $name => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endforeach; ?>

					<div class="icademie-categorie__field icademie-categorie__field--destination">
						<label class="icademie-categorie__label" for="icademie-categorie-destination"><?php esc_html_e( 'Destination ou port', 'icademie-page-categorie' ); ?></label>
						<select class="icademie-categorie__control" id="icademie-categorie-destination" name="destination">
							<option value=""><?php esc_html_e( 'Toutes les destinations', 'icademie-page-categorie' ); ?></option>
							<?php foreach ( $search['destinations'] as $option ) : ?>
								<option value="<?php echo esc_attr( $option['value'] ); ?>"<?php selected( $option['value'], $search['destination'] ); ?>><?php echo esc_html( str_repeat( "\u{00A0}\u{00A0}\u{00A0}", (int) $option['depth'] ) . $option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="icademie-categorie__field icademie-categorie__field--depart">
						<label class="icademie-categorie__label" for="icademie-categorie-depart"><?php esc_html_e( 'Départ', 'icademie-page-categorie' ); ?></label>
						<input class="icademie-categorie__control" type="date" id="icademie-categorie-depart" name="depart" value="<?php echo esc_attr( $search['depart'] ); ?>" min="<?php echo esc_attr( $search['today'] ); ?>" data-search-depart>
					</div>

					<div class="icademie-categorie__field icademie-categorie__field--retour">
						<label class="icademie-categorie__label" for="icademie-categorie-retour"><?php esc_html_e( 'Retour', 'icademie-page-categorie' ); ?></label>
						<input class="icademie-categorie__control" type="date" id="icademie-categorie-retour" name="retour" value="<?php echo esc_attr( $search['retour'] ); ?>" min="<?php echo esc_attr( '' !== $search['depart'] ? $search['depart'] : $search['today'] ); ?>" data-search-retour>
					</div>

					<?php if ( $search['has_passengers'] ) : ?>
						<div class="icademie-categorie__field icademie-categorie__field--passagers">
							<label class="icademie-categorie__label" for="icademie-categorie-passagers"><?php esc_html_e( 'Passagers', 'icademie-page-categorie' ); ?></label>
							<input class="icademie-categorie__control" type="number" id="icademie-categorie-passagers" name="passagers" value="<?php echo $search['passengers'] ? (int) $search['passengers'] : ''; ?>" min="1" max="<?php echo (int) ( $search['passengers_max'] ? $search['passengers_max'] : Icademie_Page_Accueil_Search::PASSENGERS_LIMIT ); ?>" step="1" inputmode="numeric" placeholder="2">
						</div>
					<?php endif; ?>

					<button type="submit" class="icademie-categorie__button icademie-categorie__search-submit">
						<?php echo call_user_func( $icon, 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php esc_html_e( 'Mettre à jour', 'icademie-page-categorie' ); ?>
					</button>
				</form>
			</div>

			<dialog class="icademie-categorie__dialog icademie-categorie__dialog--search" aria-labelledby="icademie-categorie-recherche-titre" data-dialog="search">
				<div class="icademie-categorie__dialog-head">
					<h2 class="icademie-categorie__dialog-title" id="icademie-categorie-recherche-titre"><?php esc_html_e( 'Modifier la recherche', 'icademie-page-categorie' ); ?></h2>
					<button type="button" class="icademie-categorie__close" data-dialog-close>
						<?php echo call_user_func( $icon, 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-categorie__sr"><?php esc_html_e( 'Fermer', 'icademie-page-categorie' ); ?></span>
					</button>
				</div>
				<div class="icademie-categorie__dialog-body" data-dialog-body></div>
			</dialog>
		</section>
	<?php endif; ?>

	<div class="icademie-categorie__listing<?php echo $groups ? ' has-filters' : ''; ?>" id="icademie-categorie-resultats" data-listing>

		<?php /* Zone 5 : filtres (ordinateur : colonne ; tablette et smartphone : panneau). */ ?>
		<?php if ( $groups ) : ?>
			<aside class="icademie-categorie__sidebar" aria-labelledby="icademie-categorie-filtres-titre" data-filters-slot>
				<form class="icademie-categorie__filters" method="get" action="<?php echo esc_url( $data['base'] ); ?>#icademie-categorie-resultats" data-filters-form>
					<h2 class="icademie-categorie__filters-title" id="icademie-categorie-filtres-titre"><?php esc_html_e( 'Filtres', 'icademie-page-categorie' ); ?></h2>
					<input type="hidden" name="<?php echo esc_attr( Icademie_Page_Categorie_Filters::MARKER ); ?>" value="1">
					<?php foreach ( $data['base_args'] as $name => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endforeach; ?>
					<?php foreach ( $filters_hidden as $name => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endforeach; ?>

					<?php foreach ( $groups as $group ) : ?>
						<?php include __DIR__ . '/filter-group.php'; ?>
					<?php endforeach; ?>

					<div class="icademie-categorie__filters-actions">
						<a class="icademie-categorie__reset" href="<?php echo esc_url( $data['clear_url'] ); ?>#icademie-categorie-resultats"><?php esc_html_e( 'Réinitialiser les filtres', 'icademie-page-categorie' ); ?></a>
						<button type="submit" class="icademie-categorie__button icademie-categorie__filters-submit" data-filters-submit><?php esc_html_e( 'Afficher les bateaux', 'icademie-page-categorie' ); ?></button>
					</div>
				</form>
			</aside>

			<dialog class="icademie-categorie__dialog icademie-categorie__dialog--filters" aria-labelledby="icademie-categorie-filtres-panneau" data-dialog="filters">
				<div class="icademie-categorie__dialog-head">
					<h2 class="icademie-categorie__dialog-title" id="icademie-categorie-filtres-panneau"><?php esc_html_e( 'Filtres', 'icademie-page-categorie' ); ?></h2>
					<button type="button" class="icademie-categorie__close" data-dialog-close>
						<?php echo call_user_func( $icon, 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<span class="icademie-categorie__sr"><?php esc_html_e( 'Fermer', 'icademie-page-categorie' ); ?></span>
					</button>
				</div>
				<div class="icademie-categorie__dialog-body" data-dialog-body></div>
			</dialog>
		<?php endif; ?>

		<div class="icademie-categorie__results" data-results>

			<?php /* Zone 6 : nombre de résultats, filtres actifs et tri. */ ?>
			<div class="icademie-categorie__toolbar<?php echo $chips ? ' has-chips' : ''; ?>">
				<?php if ( $groups ) : ?>
					<button type="button" class="icademie-categorie__button icademie-categorie__button--secondary icademie-categorie__filters-open" aria-haspopup="dialog" data-filters-open hidden>
						<?php echo call_user_func( $icon, 'filters', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
						<?php esc_html_e( 'Filtres', 'icademie-page-categorie' ); ?>
						<?php if ( $active ) : ?>
							<span aria-hidden="true">(<?php echo (int) $active; ?>)</span>
							<?php /* translators: %d: nombre de filtres actifs. */ ?>
							<span class="icademie-categorie__sr"><?php echo esc_html( sprintf( _n( ', %d filtre actif', ', %d filtres actifs', $active, 'icademie-page-categorie' ), $active ) ); ?></span>
						<?php endif; ?>
					</button>
				<?php endif; ?>

				<p class="icademie-categorie__total">
					<?php
					if ( $results['total'] > 0 ) {
						/* translators: %s: nombre de bateaux trouvés. */
						echo esc_html( sprintf( _n( '%s résultat', '%s résultats', $results['total'], 'icademie-page-categorie' ), number_format_i18n( $results['total'] ) ) );
					} else {
						esc_html_e( 'Aucun résultat', 'icademie-page-categorie' );
					}
					?>
				</p>

				<?php if ( $chips ) : ?>
					<div class="icademie-categorie__active">
						<ul class="icademie-categorie__chips" aria-label="<?php esc_attr_e( 'Filtres actifs', 'icademie-page-categorie' ); ?>">
							<?php foreach ( $chips as $chip ) : ?>
								<li>
									<a class="icademie-categorie__chip" href="<?php echo esc_url( $chip['url'] ); ?>#icademie-categorie-resultats">
										<span class="icademie-categorie__sr"><?php esc_html_e( 'Retirer le filtre :', 'icademie-page-categorie' ); ?> </span><?php echo esc_html( $chip['label'] ); ?>
										<?php echo call_user_func( $icon, 'close', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
						<a class="icademie-categorie__clear" href="<?php echo esc_url( $data['clear_url'] ); ?>#icademie-categorie-resultats"><?php esc_html_e( 'Tout effacer', 'icademie-page-categorie' ); ?></a>
					</div>
				<?php endif; ?>

				<?php if ( count( $data['sort']['options'] ) > 1 && $results['total'] > 1 ) : ?>
					<form class="icademie-categorie__sort" method="get" action="<?php echo esc_url( $data['base'] ); ?>#icademie-categorie-resultats" data-sort-form>
						<?php foreach ( $data['base_args'] as $name => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php endforeach; ?>
						<?php foreach ( $data['sort']['hidden'] as $name => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php endforeach; ?>
						<label class="icademie-categorie__sort-label" for="icademie-categorie-tri"><?php esc_html_e( 'Trier par', 'icademie-page-categorie' ); ?></label>
						<span class="icademie-categorie__sort-face" aria-hidden="true">
							<?php echo call_user_func( $icon, 'sort', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
							<?php esc_html_e( 'Trier', 'icademie-page-categorie' ); ?>
						</span>
						<select class="icademie-categorie__control icademie-categorie__sort-select" id="icademie-categorie-tri" name="orderby" data-sort-select>
							<?php foreach ( $data['sort']['options'] as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $data['sort']['current'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="icademie-categorie__button icademie-categorie__button--secondary icademie-categorie__sort-submit" data-sort-submit><?php esc_html_e( 'Trier', 'icademie-page-categorie' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<?php /* Zone 7 : résultats. */ ?>
			<h2 class="icademie-categorie__sr"><?php esc_html_e( 'Bateaux', 'icademie-page-categorie' ); ?></h2>

			<?php if ( $results['cards'] ) : ?>
				<ul class="icademie-categorie__grid" data-results-list>
					<?php foreach ( $results['cards'] as $card ) : ?>
						<?php include __DIR__ . '/card.php'; ?>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="icademie-categorie__empty">
					<?php if ( $chips ) : ?>
						<p><?php esc_html_e( 'Aucun bateau ne correspond à tous ces critères.', 'icademie-page-categorie' ); ?></p>
						<a class="icademie-categorie__button icademie-categorie__button--secondary" href="<?php echo esc_url( $data['clear_url'] ); ?>"><?php esc_html_e( 'Effacer les filtres', 'icademie-page-categorie' ); ?></a>
					<?php elseif ( 'search' === $data['type'] ) : ?>
						<?php /* translators: %s: mots cherchés. */ ?>
						<p><?php echo esc_html( sprintf( __( 'Aucun bateau ne correspond à « %s ». Essayez d\'autres mots, ou parcourez tous les bateaux.', 'icademie-page-categorie' ), $data['text_search']['query'] ) ); ?></p>
						<a class="icademie-categorie__button icademie-categorie__button--secondary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Voir tous les bateaux', 'icademie-page-categorie' ); ?></a>
					<?php else : ?>
						<p><?php esc_html_e( 'Aucun bateau n\'est encore proposé ici.', 'icademie-page-categorie' ); ?></p>
						<a class="icademie-categorie__button icademie-categorie__button--secondary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Voir tous les bateaux', 'icademie-page-categorie' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php /* Zone 8 : pagination (ordinateur, tablette) et « Afficher plus » (smartphone). */ ?>
			<?php if ( $pagination['links'] ) : ?>
				<nav class="icademie-categorie__pagination" aria-label="<?php esc_attr_e( 'Pages des résultats', 'icademie-page-categorie' ); ?>">
					<ul>
						<?php foreach ( $pagination['links'] as $link ) : ?>
							<li><?php echo wp_kses_post( $link ); ?></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<?php if ( $pagination['pages'] > 1 ) : ?>
				<?php
				/* translators: 1: nombre de bateaux affichés, 2: nombre total. */
				$first_format = __( '%1$s sur %2$s bateaux affichés', 'icademie-page-categorie' );
				/* translators: 1: premier bateau affiché, 2: dernier, 3: nombre total. */
				$range_format = __( 'Bateaux %1$s à %2$s sur %3$s', 'icademie-page-categorie' );

				// Premier bateau de la page affichée (1 sur la première page).
				$from = $pagination['shown'] - count( $results['cards'] ) + 1;
				?>
				<div class="icademie-categorie__more" data-more data-from="<?php echo (int) $from; ?>" data-to="<?php echo (int) $pagination['shown']; ?>" data-total="<?php echo (int) $pagination['total']; ?>" data-format-first="<?php echo esc_attr( $first_format ); ?>" data-format-range="<?php echo esc_attr( $range_format ); ?>">
					<p class="icademie-categorie__more-status" data-more-status aria-live="polite">
						<?php
						if ( 1 === $from ) {
							echo esc_html( sprintf( $first_format, number_format_i18n( $pagination['shown'] ), number_format_i18n( $pagination['total'] ) ) );
						} else {
							echo esc_html( sprintf( $range_format, number_format_i18n( $from ), number_format_i18n( $pagination['shown'] ), number_format_i18n( $pagination['total'] ) ) );
						}
						?>
					</p>
					<?php if ( '' !== $pagination['next'] ) : ?>
						<a class="icademie-categorie__button icademie-categorie__button--secondary icademie-categorie__more-link" href="<?php echo esc_url( $pagination['next'] ); ?>" data-more-link><?php esc_html_e( 'Afficher plus de bateaux', 'icademie-page-categorie' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Zone 9 : contenu éditorial et questions fréquentes. */ ?>
	<?php if ( $data['editorial'] || $data['faq'] ) : ?>
		<section class="icademie-categorie__editorial<?php echo ( $data['editorial'] && $data['faq'] ) ? ' has-faq' : ''; ?>"<?php echo ( $data['editorial'] && '' !== $data['editorial']['title'] ) ? ' aria-labelledby="icademie-categorie-contenu-titre"' : ' aria-label="' . esc_attr__( 'À propos de la destination', 'icademie-page-categorie' ) . '"'; ?>>
			<?php if ( $data['editorial'] ) : ?>
				<div class="icademie-categorie__editorial-text">
					<?php if ( '' !== $data['editorial']['title'] ) : ?>
						<h2 class="icademie-categorie__section-title" id="icademie-categorie-contenu-titre"><?php echo esc_html( $data['editorial']['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $data['editorial']['content'] ) : ?>
						<div class="icademie-categorie__prose" id="icademie-categorie-contenu-texte" data-readmore>
							<?php echo $data['editorial']['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contenu nettoyé à l'enregistrement, mis en forme comme une page. ?>
						</div>
						<button type="button" class="icademie-categorie__readmore" aria-controls="icademie-categorie-contenu-texte" aria-expanded="false" data-readmore-toggle hidden>
							<span data-readmore-more><?php esc_html_e( 'Lire la suite', 'icademie-page-categorie' ); ?></span>
							<span data-readmore-less hidden><?php esc_html_e( 'Réduire', 'icademie-page-categorie' ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $data['faq'] ) : ?>
				<div class="icademie-categorie__faq">
					<h2 class="icademie-categorie__faq-title"><?php esc_html_e( 'Questions fréquentes', 'icademie-page-categorie' ); ?></h2>
					<?php foreach ( $data['faq'] as $item ) : ?>
						<details class="icademie-categorie__faq-item">
							<summary class="icademie-categorie__faq-question">
								<span><?php echo esc_html( $item['question'] ); ?></span>
								<?php echo call_user_func( $icon, 'chevron-down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe. ?>
							</summary>
							<div class="icademie-categorie__faq-answer icademie-categorie__prose">
								<?php echo $item['answer']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Réponse nettoyée à l'enregistrement, mise en forme comme une page. ?>
							</div>
						</details>
					<?php endforeach; ?>
					<script type="application/ld+json"><?php echo Icademie_Page_Categorie_View::faq_schema( $data['faq'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encodé, balises échappées. ?></script>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php /* Zone 10 : maillage interne. */ ?>
	<?php if ( $data['links'] ) : ?>
		<nav class="icademie-categorie__links" aria-labelledby="icademie-categorie-maillage-titre">
			<h2 class="icademie-categorie__links-title" id="icademie-categorie-maillage-titre"><?php esc_html_e( 'Voir aussi', 'icademie-page-categorie' ); ?></h2>
			<ul class="icademie-categorie__links-list">
				<?php foreach ( $data['links'] as $link ) : ?>
					<li><a class="icademie-categorie__tag" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

</div>
