/**
 * Champs anti-spam de WP Armour : invisibles pour tout le monde, pas seulement à l'écran.
 *
 * WP Armour ajoute à chaque formulaire deux champs pièges, sortis de l'écran par le CSS : un champ
 * qui doit rester vide (« Secondary Website Url: », toujours avec le même identifiant) et un champ
 * prérempli (« WPA »). Le second recevait le focus au clavier, hors de l'écran, et les deux étaient
 * lus par les lecteurs d'écran.
 *
 * Ici, après leur ajout : conteneur masqué aux technologies d'assistance (aria-hidden), champs
 * retirés de la navigation au clavier, identifiants rendus uniques, libellé en français pour qui
 * affiche la page sans styles. Les noms et les valeurs des champs, que WP Armour contrôle à
 * l'envoi, ne changent pas.
 */
( function () {
	'use strict';

	var SELECTOR = '.wpa_hidden_field, .form_field_sec, .altEmail_container, #altEmail_container';
	var settings = window.icademieAntiSpam || {};
	var label = settings.label || 'Ne pas modifier ce champ';
	var counter = 0;

	function relabel( node ) {
		for ( var i = 0; i < node.childNodes.length; i++ ) {
			var child = node.childNodes[ i ];

			if ( 3 === child.nodeType && child.nodeValue.trim() ) {
				child.nodeValue = label + ' ';
				return;
			}
		}
	}

	function fix( container ) {
		if ( container.hasAttribute( 'data-icademie-anti-spam' ) ) {
			return;
		}

		container.setAttribute( 'data-icademie-anti-spam', '' );
		container.setAttribute( 'aria-hidden', 'true' );

		// Conteneur à identifiant fixe (anciennes versions : « altEmail_container ») : unique aussi.
		if ( container.id ) {
			container.id = container.id + '-' + ( ++counter );
		}

		Array.prototype.forEach.call( container.querySelectorAll( 'input, textarea, select, button' ), function ( field ) {
			field.setAttribute( 'tabindex', '-1' );
			field.setAttribute( 'autocomplete', 'off' );

			if ( field.id ) {
				var id = field.id + '-' + ( ++counter );

				Array.prototype.forEach.call( container.querySelectorAll( 'label[for="' + field.id + '"]' ), function ( item ) {
					item.setAttribute( 'for', id );
				} );

				field.id = id;
			}
		} );

		Array.prototype.forEach.call( container.querySelectorAll( 'label' ), relabel );
	}

	function scan( root ) {
		if ( ! root || 1 !== root.nodeType ) {
			return;
		}

		if ( root.matches && root.matches( SELECTOR ) ) {
			fix( root );
		}

		Array.prototype.forEach.call( root.querySelectorAll( SELECTOR ), fix );
	}

	scan( document.body );

	// WP Armour ajoute ses champs quand la page est prête, parfois après ce script.
	if ( window.MutationObserver ) {
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, scan );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}
}() );
