/**
 * Icademie – Page annonce : prix TTC et détail de la TVA dans les blocs Panier et Validation de la
 * commande de WooCommerce (Opquast 56).
 *
 * « Total » devient « Total TTC » (filtre « totalLabel » de WooCommerce) ; sous le total, dans
 * l'emplacement prévu pour les extensions (ExperimentalOrderMeta), deux lignes : « dont TVA » et
 * « Total HT », calculées sur le total du panier avec le taux de l'extension. Sans ce script, le
 * panier et la commande s'affichent comme avant.
 */
( function () {
	'use strict';

	const config = window.icademieAnnonceTaxes;
	const wc = window.wc || {};
	const wp = window.wp || {};
	const checkout = wc.blocksCheckout;

	if ( ! config || ! checkout || ! wp.plugins || ! wp.element || ! checkout.ExperimentalOrderMeta || ! checkout.TotalsItem ) {
		return;
	}

	const el = wp.element.createElement;
	const rate = Number( config.rate ) || 0;

	checkout.registerCheckoutFilters( 'icademie-page-annonce', {
		totalLabel: () => config.labels.total,
	} );

	/**
	 * Lignes « dont TVA » et « Total HT » (montants en centimes, comme le panier).
	 *
	 * @param {Object} props      Propriétés passées par l'emplacement.
	 * @param {Object} props.cart Panier.
	 * @return {Object|null} Élément.
	 */
	function Detail( { cart } ) {
		const totals = cart && cart.cartTotals;
		const total = totals ? parseInt( totals.total_price, 10 ) || 0 : 0;

		if ( total <= 0 || rate <= 0 ) {
			return null;
		}

		const net = Math.round( total / ( 1 + rate ) );
		const currency = wc.priceFormat && wc.priceFormat.getCurrencyFromPriceResponse ? wc.priceFormat.getCurrencyFromPriceResponse( totals ) : undefined;

		return el(
			'div',
			{ className: 'icademie-annonce-taxes' },
			el( checkout.TotalsItem, { className: 'icademie-annonce-taxes__ligne', currency, label: config.labels.tax, value: total - net } ),
			el( checkout.TotalsItem, { className: 'icademie-annonce-taxes__ligne', currency, label: config.labels.net, value: net } )
		);
	}

	wp.plugins.registerPlugin( 'icademie-annonce-taxes', {
		render: () => el( checkout.ExperimentalOrderMeta, null, el( Detail ) ),
		scope: 'woocommerce-checkout',
	} );
}() );
