/**
 * Icademie – Page catégorie : liens du maillage interne sur la fiche d'une catégorie.
 *
 * Ajouter un lien, le monter, le descendre ou le retirer. Une adresse de catégorie choisie dans les
 * propositions remplit le libellé s'il est vide. Sans ce script, une ligne vide est proposée sous
 * les liens enregistrés.
 */
( function () {
	'use strict';

	var table = document.querySelector( '[data-icademie-links]' );

	if ( ! table ) {
		return;
	}

	var body = table.querySelector( '[data-links-body]' );
	var template = document.querySelector( '[data-link-template]' );
	var add = document.querySelector( '[data-link-add]' );
	var list = document.getElementById( 'icademie-categorie-adresses' );
	var limit = parseInt( table.getAttribute( 'data-limit' ), 10 ) || 12;
	var counter = body.querySelectorAll( '[data-link-row]' ).length;

	function rows() {
		return Array.prototype.slice.call( body.querySelectorAll( '[data-link-row]' ) );
	}

	function isEmpty( row ) {
		return ! row.querySelector( '[data-link-label]' ).value.trim() && ! row.querySelector( '[data-link-url]' ).value.trim();
	}

	function refresh() {
		var all = rows();

		all.forEach( function ( row, index ) {
			row.querySelector( '[data-link-up]' ).hidden = 0 === index;
			row.querySelector( '[data-link-down]' ).hidden = index === all.length - 1;
			row.querySelector( '[data-link-remove]' ).hidden = 1 === all.length && isEmpty( row );
		} );

		add.hidden = all.length >= limit;
	}

	function addRow() {
		if ( rows().length >= limit || ! template ) {
			return null;
		}

		var html = template.innerHTML.replace( /__INDEX__/g, 'n' + counter++ );
		var holder = document.createElement( 'tbody' );

		holder.innerHTML = html.trim();

		var row = holder.querySelector( '[data-link-row]' );

		body.appendChild( row );
		refresh();

		return row;
	}

	add.addEventListener( 'click', function () {
		var row = addRow();

		if ( row ) {
			row.querySelector( '[data-link-label]' ).focus();
		}
	} );

	body.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		var row = button && button.closest( '[data-link-row]' );

		if ( ! row ) {
			return;
		}

		if ( button.hasAttribute( 'data-link-up' ) && row.previousElementSibling ) {
			body.insertBefore( row, row.previousElementSibling );
			button.focus();
		} else if ( button.hasAttribute( 'data-link-down' ) && row.nextElementSibling ) {
			body.insertBefore( row.nextElementSibling, row );
			button.focus();
		} else if ( button.hasAttribute( 'data-link-remove' ) ) {
			var focus = row.nextElementSibling || row.previousElementSibling;

			body.removeChild( row );

			if ( ! rows().length ) {
				focus = addRow();
			}

			if ( focus ) {
				focus.querySelector( '[data-link-label]' ).focus();
			}
		}

		refresh();
	} );

	// Adresse d'une catégorie proposée : son nom devient le libellé, s'il est vide.
	body.addEventListener( 'change', function ( event ) {
		var input = event.target;

		if ( ! input.hasAttribute( 'data-link-url' ) || ! list ) {
			return;
		}

		var label = input.closest( '[data-link-row]' ).querySelector( '[data-link-label]' );

		if ( label.value.trim() ) {
			return;
		}

		Array.prototype.forEach.call( list.options, function ( option ) {
			if ( option.value === input.value.trim() ) {
				label.value = option.label;
			}
		} );
	} );

	body.addEventListener( 'input', refresh );

	refresh();
}() );
