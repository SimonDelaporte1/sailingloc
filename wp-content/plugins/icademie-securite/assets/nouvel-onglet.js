/**
 * SailingLoc – Sécurité et performances : liens qui ouvrent un nouvel onglet sans le dire
 * (Opquast 146).
 *
 * Certains liens sont écrits dans la page par des scripts d'extensions ou de services tiers,
 * après son chargement : bandeau de cookies (Cookiebot), conditions de la validation de commande
 * (bloc de WooCommerce). Pour chaque lien qui s'ouvre dans un autre onglet (attribut target) et
 * dont le nom n'avertit pas déjà (« nouvel onglet », « nouvelle fenêtre »…), le script ajoute
 * « (nouvel onglet) » au nom du lien : texte réservé aux lecteurs d'écran, ou fin de l'attribut
 * aria-label s'il nomme le lien. Les liens ajoutés plus tard sont traités à leur arrivée.
 */
( function () {
	'use strict';

	var config = window.icademieNouvelOnglet || {};
	var label = config.text || '(nouvel onglet)';
	var warned = /nouvel onglet|nouvelle fenêtre|new tab|new window/i;
	var hidden = 'position:absolute!important;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0';
	var count = 0;

	function accessibleText( link ) {
		var parts = [ link.getAttribute( 'aria-label' ) || '', link.textContent || '', link.getAttribute( 'title' ) || '' ];
		var labelledby = link.getAttribute( 'aria-labelledby' );

		if ( labelledby ) {
			labelledby.split( /\s+/ ).forEach( function ( id ) {
				var element = id && document.getElementById( id );

				if ( element ) {
					parts.push( element.textContent || '' );
				}
			} );
		}

		Array.prototype.forEach.call( link.querySelectorAll( 'img[alt]' ), function ( image ) {
			parts.push( image.getAttribute( 'alt' ) );
		} );

		return parts.join( ' ' );
	}

	function fix( link ) {
		var target = ( link.getAttribute( 'target' ) || '' ).toLowerCase();
		var span;

		if ( ! target || '_self' === target || '_top' === target || '_parent' === target || link.hasAttribute( 'data-icademie-onglet' ) || warned.test( accessibleText( link ) ) ) {
			return;
		}

		link.setAttribute( 'data-icademie-onglet', '' );

		// Nom donné par un attribut : on le complète.
		if ( link.hasAttribute( 'aria-label' ) ) {
			link.setAttribute( 'aria-label', link.getAttribute( 'aria-label' ) + ' ' + label );
			return;
		}

		span = document.createElement( 'span' );
		span.setAttribute( 'style', hidden );
		span.textContent = ' ' + label;

		if ( link.hasAttribute( 'aria-labelledby' ) ) {
			span.id = 'icademie-onglet-' + ( ++count );
			link.setAttribute( 'aria-labelledby', link.getAttribute( 'aria-labelledby' ) + ' ' + span.id );
		}

		link.appendChild( span );
	}

	function scan( root ) {
		if ( 1 !== root.nodeType ) {
			return;
		}

		if ( root.matches( 'a[target]' ) ) {
			fix( root );
		}

		Array.prototype.forEach.call( root.querySelectorAll( 'a[target]' ), fix );
	}

	scan( document.documentElement );

	if ( 'function' === typeof window.MutationObserver ) {
		new window.MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, scan );
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
