/**
 * Icademie – Avis sur les produits : notes détaillées et aperçu du formulaire d'avis.
 *
 * Notes obligatoires : à l'envoi, chaque critère sans note est signalé par un message sous le
 * critère (à la place de la bulle du navigateur), et le premier reçoit le focus. Sans ce script,
 * le navigateur ou le site refusent l'avis avec un message. Une réponse à un avis n'a pas de notes
 * détaillées.
 *
 * Aperçu (Opquast 10) : le bouton d'envoi devient « Vérifier mon avis » ; un envoi valable affiche
 * d'abord le récapitulatif de l'avis tel qu'il sera publié, avec « Modifier mon avis » et « Publier
 * mon avis ». Toute modification du formulaire retire l'aperçu. L'anti-spam du site (WP Armour)
 * refuse les avis envoyés sans JavaScript : l'aperçu est donc toujours proposé.
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

	/* Aperçu de l'avis avant publication. */

	var texts = window.icademieAvisApercu;

	if ( ! texts ) {
		return;
	}

	document.querySelectorAll( '#review_form form, .wp-block-woocommerce-product-review-form form' ).forEach( initPreview );

	function initPreview( form ) {
		var submit = form.querySelector( '[type="submit"]' );
		var comment = form.querySelector( 'textarea[name="comment"]' );
		var confirmed = false;
		var panel = null;

		if ( ! submit || ! comment ) {
			return;
		}

		if ( 'INPUT' === submit.tagName ) {
			submit.value = texts.check;
		} else {
			submit.textContent = texts.check;
		}

		function element( tag, className, text ) {
			var node = document.createElement( tag );

			if ( className ) {
				node.className = className;
			}

			if ( undefined !== text ) {
				node.textContent = text;
			}

			return node;
		}

		function row( list, label, value ) {
			var item = element( 'div', 'icademie-avis-apercu__ligne' );

			item.appendChild( element( 'dt', '', label ) );
			item.appendChild( element( 'dd', '', value ) );
			list.appendChild( item );
		}

		function close( focus ) {
			if ( panel ) {
				panel.remove();
				panel = null;
			}

			// Style en ligne : les feuilles du thème peuvent passer outre l'attribut « hidden ».
			submit.style.display = '';

			if ( focus ) {
				comment.focus();
			}
		}

		function open() {
			var rating = form.querySelector( 'select[name="rating"]' );
			var author = form.querySelector( 'input[name="author"]' );
			var groups = form.querySelectorAll( '[data-critere]' );
			var parent = form.querySelector( '[name="comment_parent"]' );
			var isReply = parent && parent.value && '0' !== parent.value;
			var list = element( 'dl', 'icademie-avis-apercu__liste' );
			var heading = element( 'p', 'icademie-avis-apercu__titre', texts.title );
			var actions = element( 'p', 'icademie-avis-apercu__actions' );
			var edit = element( 'button', 'icademie-avis-apercu__modifier', texts.edit );
			var publish = element( 'button', 'icademie-avis-apercu__publier wp-element-button', texts.publish );

			close( false );

			panel = element( 'div', 'icademie-avis-apercu' );
			panel.setAttribute( 'role', 'region' );
			panel.setAttribute( 'aria-labelledby', form.id ? form.id + '-apercu-titre' : 'icademie-avis-apercu-titre' );
			heading.id = panel.getAttribute( 'aria-labelledby' );
			heading.tabIndex = -1;
			panel.appendChild( heading );
			panel.appendChild( element( 'p', 'icademie-avis-apercu__intro', texts.intro + ( texts.moderation ? ' ' + texts.moderation : '' ) ) );

			if ( ! isReply && rating && rating.value ) {
				var option = rating.options[ rating.selectedIndex ];

				row( list, texts.rating, texts.outOf.replace( '%s', rating.value ) + ( option && option.textContent ? ' (' + option.textContent.trim() + ')' : '' ) );
			}

			if ( ! isReply && groups.length ) {
				var notes = [];

				groups.forEach( function ( group ) {
					var checked = group.querySelector( 'input:checked' );
					var legend = group.querySelector( 'legend' );

					if ( checked && legend ) {
						notes.push( legend.textContent.replace( /\*\s*$/, '' ).trim() + ' : ' + texts.outOf.replace( '%s', checked.value ) );
					}
				} );

				if ( notes.length ) {
					row( list, texts.criteria, notes.join( ' · ' ) );
				}
			}

			if ( author && author.value.trim() ) {
				row( list, texts.name, author.value.trim() );
			}

			panel.appendChild( list );
			panel.appendChild( element( 'p', 'icademie-avis-apercu__texte-titre', texts.text ) );
			panel.appendChild( element( 'blockquote', 'icademie-avis-apercu__texte', comment.value.trim() ) );

			edit.type = 'button';
			publish.type = 'button';
			actions.appendChild( edit );
			actions.appendChild( publish );
			panel.appendChild( actions );

			edit.addEventListener( 'click', function () {
				close( true );
			} );

			publish.addEventListener( 'click', function () {
				confirmed = true;
				publish.disabled = true;

				if ( 'function' === typeof form.requestSubmit ) {
					form.requestSubmit( submit );
				} else {
					form.submit();
				}
			} );

			// Avant le paragraphe du bouton (un bloc ne va pas dans un paragraphe).
			var anchor = submit.closest( '.form-submit' ) || submit;

			submit.style.display = 'none';
			anchor.parentNode.insertBefore( panel, anchor );
			heading.focus();
		}

		// Après les contrôles du formulaire (note de WooCommerce, notes détaillées) : seulement si
		// aucun n'a refusé l'envoi.
		document.addEventListener( 'submit', function ( event ) {
			if ( event.target !== form || event.defaultPrevented ) {
				return;
			}

			if ( confirmed ) {
				return;
			}

			event.preventDefault();
			open();
		} );

		// Avis modifié pendant l'aperçu : on revient à la saisie.
		[ 'input', 'change' ].forEach( function ( type ) {
			form.addEventListener( type, function ( event ) {
				if ( panel && ! panel.contains( event.target ) ) {
					close( false );
				}
			} );
		} );
	}
}() );
