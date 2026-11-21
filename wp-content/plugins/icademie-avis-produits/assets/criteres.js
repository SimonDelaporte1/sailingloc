/**
 * Icademie – Avis sur les produits : notes détaillées du formulaire d'avis.
 *
 * Notes obligatoires : à l'envoi, chaque critère sans note est signalé par un message sous le
 * critère (à la place de la bulle du navigateur), et le premier reçoit le focus. Sans ce script,
 * le navigateur ou le site refusent l'avis avec un message. Une réponse à un avis n'a pas de notes
 * détaillées.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-icademie-criteres]' ).forEach( function ( box ) {
		var form = box.closest( 'form' );
		var groups = box.querySelectorAll( '[data-critere]' );

		if ( ! form || ! box.hasAttribute( 'data-required' ) ) {
			return;
		}

		function check( group, show ) {
			var ok = !! group.querySelector( 'input:checked' );
			var error = group.querySelector( '.icademie-avis-critere__erreur' );

			if ( ok || show ) {
				error.textContent = ok ? '' : error.getAttribute( 'data-erreur' );
				error.hidden = ok;
				group.classList.toggle( 'is-invalid', ! ok );
			}

			return ok;
		}

		var focused = false;

		groups.forEach( function ( group ) {
			group.addEventListener( 'change', function () {
				check( group, false );
			} );
		} );

		// Formulaire validé par le navigateur (champs « required ») : message sous le critère.
		box.addEventListener( 'invalid', function ( event ) {
			var group = event.target.closest( '[data-critere]' );

			if ( ! group ) {
				return;
			}

			event.preventDefault();
			check( group, true );

			// Premier critère oublié de cet envoi.
			if ( ! focused ) {
				focused = true;
				event.target.focus();
				window.setTimeout( function () {
					focused = false;
				}, 0 );
			}
		}, true );

		// Formulaire sans validation du navigateur (« novalidate ») : même contrôle à l'envoi.

		form.addEventListener( 'submit', function ( event ) {
			var parent = form.querySelector( '[name="comment_parent"]' );
			var first = null;

			if ( parent && parent.value && '0' !== parent.value ) {
				return;
			}

			groups.forEach( function ( group ) {
				if ( ! check( group, true ) && ! first ) {
					first = group;
				}
			} );

			if ( first ) {
				event.preventDefault();
				first.querySelector( 'input' ).focus();
			}
		} );
	} );
}() );
