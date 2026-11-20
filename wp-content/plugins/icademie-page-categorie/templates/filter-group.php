<?php
/**
 * Un groupe de filtres (zone 5).
 *
 * @package Icademie_Page_Categorie
 *
 * @var array $group Groupe préparé par Icademie_Page_Categorie_View::filter_group().
 */

defined( 'ABSPATH' ) || exit;

$group_id = $group['id'];
$name     = $group['key'];
?>
<fieldset class="icademie-categorie__group icademie-categorie__group--<?php echo esc_attr( $group['control'] ); ?>"<?php echo 'price' === $group['control'] ? ' data-price' : ''; ?><?php echo 'range' === $group['control'] ? ' data-range data-steps="' . esc_attr( (string) wp_json_encode( $group['steps'] ) ) . '"' : ''; ?><?php echo 'stepper' === $group['control'] ? ' data-stepper' : ''; ?>>
	<legend class="icademie-categorie__legend"><?php echo esc_html( $group['label'] ); ?></legend>

	<?php if ( 'price' === $group['control'] ) : ?>
		<?php
		$range_value = null !== $group['max'] ? min( $group['ceil'], (int) ceil( $group['max'] ) ) : $group['ceil'];
		?>
		<label class="icademie-categorie__sr" for="<?php echo esc_attr( $group_id ); ?>-curseur"><?php esc_html_e( 'Prix maximum par jour', 'icademie-page-categorie' ); ?></label>
		<input class="icademie-categorie__range" type="range" id="<?php echo esc_attr( $group_id ); ?>-curseur" min="<?php echo (int) $group['floor']; ?>" max="<?php echo (int) $group['ceil']; ?>" step="<?php echo (int) $group['step']; ?>" value="<?php echo (int) $range_value; ?>" data-price-range hidden>
		<div class="icademie-categorie__price">
			<label class="icademie-categorie__price-field">
				<span class="icademie-categorie__sr"><?php esc_html_e( 'Prix minimum par jour', 'icademie-page-categorie' ); ?></span>
				<input class="icademie-categorie__control" type="number" name="<?php echo esc_attr( $name ); ?>_min" value="<?php echo null !== $group['min'] ? esc_attr( wc_format_decimal( $group['min'], false, true ) ) : ''; ?>" min="0" step="any" inputmode="decimal" placeholder="<?php esc_attr_e( 'Min', 'icademie-page-categorie' ); ?>" data-price-min>
				<span class="icademie-categorie__price-unit" aria-hidden="true"><?php echo esc_html( $group['currency'] ); ?></span>
			</label>
			<label class="icademie-categorie__price-field">
				<span class="icademie-categorie__sr"><?php esc_html_e( 'Prix maximum par jour', 'icademie-page-categorie' ); ?></span>
				<input class="icademie-categorie__control" type="number" name="<?php echo esc_attr( $name ); ?>_max" value="<?php echo null !== $group['max'] ? esc_attr( wc_format_decimal( $group['max'], false, true ) ) : ''; ?>" min="0" step="any" inputmode="decimal" placeholder="<?php esc_attr_e( 'Max', 'icademie-page-categorie' ); ?>" data-price-max>
				<span class="icademie-categorie__price-unit" aria-hidden="true"><?php echo esc_html( $group['currency'] ); ?></span>
			</label>
		</div>

	<?php elseif ( 'checkboxes' === $group['control'] || 'choice' === $group['control'] ) : ?>
		<?php
		$type    = 'choice' === $group['control'] ? 'radio' : 'checkbox';
		$field   = 'choice' === $group['control'] ? $name : $name . '[]';
		$visible = array_slice( $group['options'], 0, $group['visible'] );
		$more    = array_slice( $group['options'], $group['visible'] );
		$none    = ! in_array( true, wp_list_pluck( $group['options'], 'checked' ), true );
		?>
		<ul class="icademie-categorie__options">
			<?php if ( 'radio' === $type ) : ?>
				<li>
					<label class="icademie-categorie__check">
						<input type="radio" name="<?php echo esc_attr( $field ); ?>" value=""<?php checked( $none ); ?>>
						<span><?php esc_html_e( 'Indifférent', 'icademie-page-categorie' ); ?></span>
					</label>
				</li>
			<?php endif; ?>
			<?php foreach ( $visible as $option ) : ?>
				<li>
					<label class="icademie-categorie__check">
						<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"<?php checked( $option['checked'] ); ?>>
						<span><?php echo esc_html( $option['label'] ); ?></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $more ) : ?>
			<details class="icademie-categorie__more-options">
				<summary>
					<?php
					/* translators: %d: nombre de valeurs repliées. */
					echo esc_html( sprintf( _n( 'Voir %d autre choix', 'Voir %d autres choix', count( $more ), 'icademie-page-categorie' ), count( $more ) ) );
					?>
				</summary>
				<ul class="icademie-categorie__options">
					<?php foreach ( $more as $option ) : ?>
						<li>
							<label class="icademie-categorie__check">
								<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"<?php checked( $option['checked'] ); ?>>
								<span><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>

	<?php elseif ( 'range' === $group['control'] ) : ?>
		<?php
		$last = count( $group['steps'] ) - 1;
		$from = round( 100 * $group['min'] / max( 1, $last ), 2 );
		$to   = round( 100 * $group['max'] / max( 1, $last ), 2 );
		?>
		<p class="icademie-categorie__range-output" aria-hidden="true" data-range-output><?php echo esc_html( $group['steps'][ $group['min'] ] . ' – ' . $group['steps'][ $group['max'] ] ); ?></p>
		<div class="icademie-categorie__dual" style="<?php echo esc_attr( '--icademie-de:' . $from . '%;--icademie-a:' . $to . '%' ); ?>" data-range-track>
			<?php /* translators: %s: nom du filtre (« Longueur »). */ ?>
			<label class="icademie-categorie__sr" for="<?php echo esc_attr( $group_id ); ?>-min"><?php echo esc_html( sprintf( __( '%s : minimum', 'icademie-page-categorie' ), $group['label'] ) ); ?></label>
			<input class="icademie-categorie__range" type="range" id="<?php echo esc_attr( $group_id ); ?>-min" name="<?php echo esc_attr( $name ); ?>_min" min="0" max="<?php echo (int) $last; ?>" step="1" value="<?php echo (int) $group['min']; ?>" aria-valuetext="<?php echo esc_attr( $group['steps'][ $group['min'] ] ); ?>" data-range-min>
			<?php /* translators: %s: nom du filtre (« Longueur »). */ ?>
			<label class="icademie-categorie__sr" for="<?php echo esc_attr( $group_id ); ?>-max"><?php echo esc_html( sprintf( __( '%s : maximum', 'icademie-page-categorie' ), $group['label'] ) ); ?></label>
			<input class="icademie-categorie__range" type="range" id="<?php echo esc_attr( $group_id ); ?>-max" name="<?php echo esc_attr( $name ); ?>_max" min="0" max="<?php echo (int) $last; ?>" step="1" value="<?php echo (int) $group['max']; ?>" aria-valuetext="<?php echo esc_attr( $group['steps'][ $group['max'] ] ); ?>" data-range-max>
		</div>

	<?php elseif ( 'stepper' === $group['control'] ) : ?>
		<?php
		$is_passengers = 'passagers' === $name;
		?>
		<div class="icademie-categorie__stepper">
			<button type="button" class="icademie-categorie__step" data-stepper-down hidden>
				<span aria-hidden="true">−</span>
				<?php /* translators: %s: nom du filtre. */ ?>
				<span class="icademie-categorie__sr"><?php echo esc_html( $is_passengers ? __( 'Un passager de moins', 'icademie-page-categorie' ) : sprintf( __( '%s : moins', 'icademie-page-categorie' ), $group['label'] ) ); ?></span>
			</button>
			<?php /* translators: %s: nom du filtre. */ ?>
			<label class="icademie-categorie__sr" for="<?php echo esc_attr( $group_id ); ?>-nombre"><?php echo esc_html( $is_passengers ? __( 'Nombre de passagers', 'icademie-page-categorie' ) : sprintf( __( '%s : nombre minimal', 'icademie-page-categorie' ), $group['label'] ) ); ?></label>
			<input class="icademie-categorie__control icademie-categorie__stepper-input" type="number" id="<?php echo esc_attr( $group_id ); ?>-nombre" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $group['value'] ? (int) $group['value'] : ''; ?>" min="1" max="<?php echo (int) $group['max']; ?>" step="1" inputmode="numeric" placeholder="<?php esc_attr_e( 'Tous', 'icademie-page-categorie' ); ?>" data-stepper-input>
			<button type="button" class="icademie-categorie__step" data-stepper-up hidden>
				<span aria-hidden="true">+</span>
				<?php /* translators: %s: nom du filtre. */ ?>
				<span class="icademie-categorie__sr"><?php echo esc_html( $is_passengers ? __( 'Un passager de plus', 'icademie-page-categorie' ) : sprintf( __( '%s : plus', 'icademie-page-categorie' ), $group['label'] ) ); ?></span>
			</button>
		</div>

	<?php elseif ( 'minimum' === $group['control'] || 'rating' === $group['control'] ) : ?>
		<?php
		$options = 'rating' === $group['control'] ? $group['choices'] : $group['options'];
		$current = $group['current'];
		?>
		<div class="icademie-categorie__pills">
			<label class="icademie-categorie__pill">
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value=""<?php checked( '', $current ); ?>>
				<span><?php esc_html_e( 'Toutes', 'icademie-page-categorie' ); ?></span>
			</label>
			<?php foreach ( $options as $option ) : ?>
				<label class="icademie-categorie__pill">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"<?php checked( $option['value'], $current ); ?>>
					<?php if ( 'rating' === $group['control'] ) : ?>
						<span><span aria-hidden="true"><?php echo esc_html( $option['label'] ); ?></span><span class="icademie-categorie__sr"><?php echo esc_html( $option['sr'] ); ?></span></span>
					<?php else : ?>
						<span><?php echo esc_html( $option['label'] ); ?><?php if ( false === strpos( $option['label'], '+' ) ) : ?><span class="icademie-categorie__sr"> <?php esc_html_e( 'ou plus', 'icademie-page-categorie' ); ?></span><?php endif; ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</fieldset>
