/**
 * Icademie – Dates de début et de fin.
 *
 * Empêche de choisir une date de fin antérieure à la date de début : la date
 * minimale du champ « fin » suit la date de début choisie.
 */
document.querySelectorAll( '.icademie-dates' ).forEach( ( wrapper ) => {
	const start = wrapper.querySelector( 'input[name="icademie_date_debut"]' );
	const end = wrapper.querySelector( 'input[name="icademie_date_fin"]' );

	if ( ! start || ! end ) {
		return;
	}

	const syncEndMin = () => {
		end.min = start.value || start.min;

		if ( end.value && end.value < end.min ) {
			end.value = '';
		}
	};

	start.addEventListener( 'change', syncEndMin );
	syncEndMin();
} );
