/**
 * Icademie – Données de démonstration : génération et suppression par appels successifs (chaque
 * appel traite un lot, pour rester dans le temps d'exécution de l'hébergement).
 */
( function () {
	'use strict';

	const root = document.querySelector( '[data-icademie-demo]' );
	const config = window.icademieDemo;

	if ( ! root || ! config ) {
		return;
	}

	const form = root.querySelector( '[data-demo-form]' );
	const start = root.querySelector( '[data-demo-start]' );
	const resume = root.querySelector( '[data-demo-resume]' );
	const confirm = root.querySelector( '[data-demo-confirm]' );
	const remove = root.querySelector( '[data-demo-delete]' );
	const box = root.querySelector( '[data-demo-status]' );
	const bar = root.querySelector( '[data-demo-progress]' );
	const message = root.querySelector( '[data-demo-message]' );
	const sleep = ( ms ) => new Promise( ( done ) => setTimeout( done, ms ) );

	async function call( op, fields ) {
		const body = new FormData( 'demarrer' === op && form ? form : undefined );

		body.append( 'action', 'icademie_demo' );
		body.append( 'nonce', config.nonce );
		body.append( 'op', op );

		Object.entries( fields || {} ).forEach( ( [ key, value ] ) => body.append( key, value ) );

		// Coupure passagère (hébergement mutualisé) : trois essais.
		for ( let attempt = 0; ; attempt++ ) {
			try {
				const response = await fetch( config.ajax, { method: 'POST', body, credentials: 'same-origin' } );
				const json = await response.json();

				if ( ! json.success ) {
					throw new Error( ( json.data && json.data.message ) || config.i18n.erreur );
				}

				return json.data;
			} catch ( error ) {
				if ( error instanceof SyntaxError || error instanceof TypeError ) {
					if ( attempt < 2 ) {
						await sleep( 3000 );
						continue;
					}

					throw new Error( config.i18n.reseau );
				}

				throw error;
			}
		}
	}

	function show( status ) {
		box.hidden = false;
		bar.max = Math.max( 1, status.total || 1 );
		bar.value = Math.min( bar.max, status.fait || 0 );
		message.textContent = status.message;
	}

	function busy( on ) {
		[ start, resume, remove, confirm ].forEach( ( element ) => {
			if ( element ) {
				element.disabled = on || ( element === remove && ! confirm.checked );
			}
		} );
	}

	async function run( op, first ) {
		busy( true );
		box.hidden = false;

		try {
			let status = first || await call( op );

			show( status );

			while ( 'supprimer' === op ? 'vide' !== status.etat : 'en_cours' === status.etat ) {
				// Autre onglet en cours : on attend qu'il ait fini son lot.
				if ( status.occupe ) {
					await sleep( 2000 );
				}

				status = await call( op );
				show( status );
			}

			message.textContent = 'supprimer' === op ? config.i18n.supprime : config.i18n.termine;
			await sleep( 1500 );
			window.location.reload();
		} catch ( error ) {
			message.textContent = error.message;
			busy( false );
		}
	}

	if ( form && start ) {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			busy( true );
			box.hidden = false;

			try {
				run( 'continuer', await call( 'demarrer' ) );
			} catch ( error ) {
				message.textContent = error.message;
				busy( false );
			}
		} );
	}

	if ( resume ) {
		resume.addEventListener( 'click', () => run( 'continuer' ) );
	}

	if ( confirm && remove ) {
		confirm.addEventListener( 'change', () => {
			remove.disabled = ! confirm.checked;
		} );
		remove.addEventListener( 'click', () => run( 'supprimer' ) );
	}
}() );
