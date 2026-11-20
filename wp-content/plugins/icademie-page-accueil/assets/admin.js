/**
 * Icademie – Page d'accueil : champs d'image de l'administration (médiathèque).
 *
 * Utilisé sur Réglages › Page d'accueil et sur les valeurs de l'attribut « Type de bateau ».
 */
( function ( $ ) {
	'use strict';

	function reset( $field ) {
		$field.find( '[data-media-input]' ).val( '' );
		$field.find( '[data-media-preview]' ).empty();
		$field.find( '[data-media-remove]' ).prop( 'hidden', true );
	}

	$( document ).on( 'click', '[data-media-choose]', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var $field = $button.closest( '[data-icademie-media]' );
		var frame = $field.data( 'icademieFrame' );

		if ( ! frame ) {
			frame = wp.media( {
				title: $button.data( 'title' ),
				button: { text: $button.data( 'button' ) },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				var url = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;

				$field.find( '[data-media-input]' ).val( image.id ).trigger( 'change' );
				$field.find( '[data-media-preview]' ).empty().append(
					$( '<img>', { src: url, alt: '' } ).css( { maxWidth: '200px', height: 'auto', display: 'block' } )
				);
				$field.find( '[data-media-remove]' ).prop( 'hidden', false );
			} );

			$field.data( 'icademieFrame', frame );
		}

		frame.open();
	} );

	$( document ).on( 'click', '[data-media-remove]', function ( event ) {
		event.preventDefault();
		reset( $( this ).closest( '[data-icademie-media]' ) );
	} );

	// Liste des valeurs : le formulaire d'ajout est envoyé sans recharger la page ; une fois la
	// valeur ajoutée, l'image choisie est retirée du formulaire pour la valeur suivante.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		var data = settings && 'string' === typeof settings.data ? settings.data : '';

		if ( -1 !== data.indexOf( 'action=add-tag' ) && xhr.responseXML && ! $( xhr.responseXML ).find( 'wp_error' ).length ) {
			$( '#addtag [data-icademie-media]' ).each( function () {
				reset( $( this ) );
			} );
		}
	} );
}( jQuery ) );
