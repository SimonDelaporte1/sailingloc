/**
 * Icademie – Page d'accueil : reprise des dates de la recherche sur la fiche du bateau.
 *
 * - Résultats de recherche (boutique, catégories) : les dates de l'adresse (?depart=…&retour=…)
 *   sont gardées le temps de la visite (stockage de session du navigateur, sans cookie).
 * - Fiche d'un bateau : si les champs « Date de début » et « Date de fin » de l'extension
 *   « Icademie – Dates de début et de fin » sont vides, ils reçoivent ces dates. Le visiteur peut
 *   toujours les changer avant d'ajouter le bateau au panier.
 */
( function () {
	'use strict';

	var KEY = 'icademie-recherche-dates';
	var DATE = /^\d{4}-\d{2}-\d{2}$/;

	function read() {
		try {
			var dates = JSON.parse( window.sessionStorage.getItem( KEY ) || 'null' );
			return dates && 'object' === typeof dates ? dates : null;
		} catch ( error ) {
			return null;
		}
	}

	function write( dates ) {
		try {
			window.sessionStorage.setItem( KEY, JSON.stringify( dates ) );
		} catch ( error ) {
			// Stockage indisponible : rien à reprendre.
		}
	}

	var params = new URLSearchParams( window.location.search );
	var depart = params.get( 'depart' ) || '';
	var retour = params.get( 'retour' ) || '';

	if ( DATE.test( depart ) ) {
		write( { depart: depart, retour: DATE.test( retour ) ? retour : '' } );
	}

	var start = document.querySelector( 'input[name="icademie_date_debut"]' );
	var end = document.querySelector( 'input[name="icademie_date_fin"]' );

	// Pas de champs de dates, ou dates déjà saisies (par exemple après une erreur d'ajout au panier).
	if ( ! start || ! end || start.value || end.value ) {
		return;
	}

	var dates = read();

	if ( ! dates || ! DATE.test( dates.depart || '' ) || ( start.min && dates.depart < start.min ) ) {
		return;
	}

	start.value = dates.depart;
	// Le script des dates ajuste alors la date minimale du champ « fin ».
	start.dispatchEvent( new Event( 'change', { bubbles: true } ) );

	if ( DATE.test( dates.retour || '' ) && dates.retour >= dates.depart ) {
		end.value = dates.retour;
		end.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
}() );
