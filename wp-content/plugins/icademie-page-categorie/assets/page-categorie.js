/**
 * Icademie – Page catégorie : recherche, filtres, tri, photos des cartes, « Afficher plus » et
 * « Lire la suite ».
 *
 * Amélioration progressive : sans ce script, la recherche et les filtres restent affichés dans la
 * page et s'envoient avec leur bouton, le tri avec le bouton « Trier », les photos défilent au doigt
 * ou avec la barre de défilement, « Afficher plus » ouvre la page suivante et le texte éditorial est
 * affiché en entier.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.icademie-categorie' );

	// Panneaux : élément <dialog> du navigateur (fenêtre modale, touche Échap, focus gardé).
	if ( ! root || 'function' !== typeof window.HTMLDialogElement ) {
		return;
	}

	root.classList.add( 'has-js' );

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var listing = root.querySelector( '[data-listing]' );
	var dialogs = [];

	/** Largeurs des zonings : ordinateur à partir de 1024 px, smartphone en dessous de 600 px. */
	function isNarrow() {
		return root.clientWidth < 1024;
	}

	function isPhone() {
		return root.clientWidth < 600;
	}

	initSearch();
	initFilters();
	initSort();
	root.querySelectorAll( '[data-gallery]' ).forEach( initGallery );
	initMore();
	initReadMore();

	// Retour arrière (page gardée en mémoire par le navigateur) : la page redevient utilisable.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			setLoading( false );
		}
	} );

	// Passage à la largeur « ordinateur » (rotation, fenêtre agrandie) : les panneaux se ferment.
	if ( 'function' === typeof window.ResizeObserver ) {
		new window.ResizeObserver( function () {
			if ( ! isNarrow() ) {
				dialogs.forEach( function ( dialog ) {
					if ( dialog.open ) {
						dialog.close();
					}
				} );
			}
		} ).observe( root );
	}

	/**
	 * Panneau : le formulaire quitte la page pour le panneau à l'ouverture, et y revient à la
	 * fermeture (ses valeurs restent les mêmes).
	 *
	 * @param {HTMLDialogElement} dialog  Panneau.
	 * @param {HTMLElement}       content Formulaire.
	 * @param {HTMLElement}       opener  Bouton qui ouvre le panneau.
	 * @return {Object|null} Fonction « open ».
	 */
	function panel( dialog, content, opener ) {
		if ( ! dialog || ! content || ! opener ) {
			return null;
		}

		var body = dialog.querySelector( '[data-dialog-body]' );
		var marker = document.createComment( 'icademie-categorie' );

		dialogs.push( dialog );

		function open() {
			if ( dialog.open ) {
				return;
			}

			content.parentNode.insertBefore( marker, content );
			body.appendChild( content );
			document.documentElement.classList.add( 'icademie-categorie-panneau' );
			dialog.showModal();
		}

		dialog.addEventListener( 'close', function () {
			if ( marker.parentNode ) {
				marker.parentNode.insertBefore( content, marker );
				marker.parentNode.removeChild( marker );
			}

			document.documentElement.classList.remove( 'icademie-categorie-panneau' );

			if ( ! opener.hidden && opener.offsetParent ) {
				opener.focus();
			}
		} );

		dialog.querySelectorAll( '[data-dialog-close]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				dialog.close();
			} );
		} );

		// Clic à côté du panneau (sur le voile).
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		opener.hidden = false;
		opener.addEventListener( 'click', open );

		return { open: open };
	}

	/**
	 * Liste en cours de chargement (après l'envoi d'un formulaire).
	 *
	 * @param {boolean} loading Chargement en cours.
	 */
	function setLoading( loading ) {
		if ( ! listing ) {
			return;
		}

		listing.classList.toggle( 'is-loading', loading );

		if ( loading ) {
			listing.setAttribute( 'aria-busy', 'true' );
		} else {
			listing.removeAttribute( 'aria-busy' );
			root.querySelectorAll( '[data-filters-slot]' ).forEach( function ( slot ) {
				slot.inert = false;
			} );
			root.querySelectorAll( 'form[data-sending]' ).forEach( function ( form ) {
				delete form.dataset.sending;
			} );
		}
	}

	/**
	 * Zone 4 : recherche. Tablette et smartphone : le résumé ouvre le formulaire dans un panneau.
	 * La date de retour ne peut pas précéder le départ.
	 */
	function initSearch() {
		var section = root.querySelector( '[data-search]' );

		if ( ! section ) {
			return;
		}

		var form = section.querySelector( '[data-search-form]' );
		var depart = form && form.querySelector( '[data-search-depart]' );
		var retour = form && form.querySelector( '[data-search-retour]' );

		panel( section.querySelector( '[data-dialog="search"]' ), form, section.querySelector( '[data-search-open]' ) );

		if ( depart && retour ) {
			var syncReturn = function () {
				retour.min = depart.value || depart.min;

				if ( retour.value && retour.value < retour.min ) {
					retour.value = '';
				}
			};

			depart.addEventListener( 'change', syncReturn );
			syncReturn();
		}
	}

	/**
	 * Zone 5 : filtres.
	 *
	 * - Ordinateur : colonne de filtres, appliqués dès qu'on les change (cases, pastilles,
	 *   curseurs au relâchement, compteur après une pause, prix en quittant les champs du prix).
	 * - Tablette et smartphone : panneau ouvert par « Filtres (n) », appliqués avec « Afficher les
	 *   bateaux ».
	 */
	function initFilters() {
		var form = root.querySelector( '[data-filters-form]' );

		if ( ! form ) {
			return;
		}

		var slot = root.querySelector( '[data-filters-slot]' );
		var dialog = root.querySelector( '[data-dialog="filters"]' );
		var timer = null;

		panel( dialog, form, root.querySelector( '[data-filters-open]' ) );

		function autoApply() {
			return ! isNarrow() && ! ( dialog && dialog.open );
		}

		function submit() {
			window.clearTimeout( timer );

			if ( form.dataset.sending ) {
				return;
			}

			if ( 'function' === typeof form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}

		function submitSoon( delay ) {
			if ( ! autoApply() ) {
				return;
			}

			window.clearTimeout( timer );
			timer = window.setTimeout( submit, delay );
		}

		form.addEventListener( 'submit', function () {
			form.dataset.sending = '1';
			setLoading( true );

			// Plus de clic pendant le chargement : il serait perdu avec l'ancienne page.
			window.setTimeout( function () {
				if ( slot && slot.contains( form ) ) {
					slot.inert = true;
				}
			}, 0 );
		} );

		form.addEventListener( 'change', function ( event ) {
			var field = event.target;

			if ( 'number' === field.type ) {
				// Prix minimum puis maximum : appliqué quand on quitte le groupe de champs.
				var group = field.closest( 'fieldset' );

				window.setTimeout( function () {
					if ( group && group.contains( document.activeElement ) ) {
						group.addEventListener( 'focusout', function leave( focusEvent ) {
							if ( ! group.contains( focusEvent.relatedTarget ) ) {
								group.removeEventListener( 'focusout', leave );
								submitSoon( 150 );
							}
						} );
					} else {
						submitSoon( 150 );
					}
				}, 0 );
				return;
			}

			submitSoon( 'range' === field.type ? 300 : 250 );
		} );

		form.querySelectorAll( '[data-price]' ).forEach( initPrice );
		form.querySelectorAll( '[data-range]' ).forEach( initRange );
		form.querySelectorAll( '[data-stepper]' ).forEach( function ( group ) {
			initStepper( group, function () {
				submitSoon( 800 );
			} );
		} );
	}

	/**
	 * Prix : le curseur règle le prix maximum (au bout du curseur : pas de maximum).
	 *
	 * @param {HTMLElement} group Groupe du prix.
	 */
	function initPrice( group ) {
		var range = group.querySelector( '[data-price-range]' );
		var max = group.querySelector( '[data-price-max]' );

		if ( ! range || ! max ) {
			return;
		}

		range.hidden = false;

		range.addEventListener( 'input', function () {
			max.value = Number( range.value ) >= Number( range.max ) ? '' : range.value;
		} );

		max.addEventListener( 'input', function () {
			var value = parseFloat( String( max.value ).replace( ',', '.' ) );

			range.value = isNaN( value ) ? range.max : String( Math.min( value, Number( range.max ) ) );
		} );
	}

	/**
	 * Curseur double (longueur) : les deux poignées ne se croisent pas ; l'intervalle choisi est
	 * affiché au-dessus et coloré sur la barre.
	 *
	 * @param {HTMLElement} group Groupe du curseur.
	 */
	function initRange( group ) {
		var low = group.querySelector( '[data-range-min]' );
		var high = group.querySelector( '[data-range-max]' );
		var output = group.querySelector( '[data-range-output]' );
		var track = group.querySelector( '[data-range-track]' );
		var steps = [];

		if ( ! low || ! high ) {
			return;
		}

		try {
			steps = JSON.parse( group.getAttribute( 'data-steps' ) || '[]' );
		} catch ( error ) {
			steps = [];
		}

		function label( index ) {
			return undefined !== steps[ index ] ? steps[ index ] : String( index );
		}

		function update( moved ) {
			var last = Number( low.max ) || 1;
			var a = Number( low.value );
			var b = Number( high.value );

			if ( a > b ) {
				if ( moved === low ) {
					low.value = String( b );
					a = b;
				} else {
					high.value = String( a );
					b = a;
				}
			}

			low.setAttribute( 'aria-valuetext', label( a ) );
			high.setAttribute( 'aria-valuetext', label( b ) );

			if ( output ) {
				output.textContent = label( a ) + ' – ' + label( b );
			}

			if ( track ) {
				track.style.setProperty( '--icademie-de', ( 100 * a / last ) + '%' );
				track.style.setProperty( '--icademie-a', ( 100 * b / last ) + '%' );
			}

			// Poignées au même endroit, dans la moitié droite : celle du minimum passe dessus.
			low.style.zIndex = ( a === b && a > last / 2 ) ? '3' : '';
		}

		low.addEventListener( 'input', function () {
			update( low );
		} );
		high.addEventListener( 'input', function () {
			update( high );
		} );
		update();
	}

	/**
	 * Compteur (passagers) : − et + ; sous 1, aucun minimum.
	 *
	 * @param {HTMLElement} group    Groupe du compteur.
	 * @param {Function}    onChange Appelée après un clic sur − ou +.
	 */
	function initStepper( group, onChange ) {
		var input = group.querySelector( '[data-stepper-input]' );
		var down = group.querySelector( '[data-stepper-down]' );
		var up = group.querySelector( '[data-stepper-up]' );

		if ( ! input || ! down || ! up ) {
			return;
		}

		var max = parseInt( input.max, 10 ) || 99;

		function value() {
			var number = parseInt( input.value, 10 );

			return isNaN( number ) ? 0 : number;
		}

		function sync() {
			down.disabled = value() <= 0;
			up.disabled = value() >= max;
		}

		function set( number ) {
			input.value = number > 0 ? String( Math.min( number, max ) ) : '';
			sync();
			onChange();
		}

		down.hidden = false;
		up.hidden = false;

		down.addEventListener( 'click', function () {
			set( value() - 1 );
		} );
		up.addEventListener( 'click', function () {
			set( value() + 1 );
		} );
		input.addEventListener( 'input', sync );
		sync();
	}

	/**
	 * Zone 6 : tri appliqué dès qu'il change (comme la liste de tri de WooCommerce).
	 */
	function initSort() {
		var form = root.querySelector( '[data-sort-form]' );
		var select = form && form.querySelector( '[data-sort-select]' );

		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			if ( form.dataset.sending ) {
				return;
			}

			form.dataset.sending = '1';
			setLoading( true );
			form.submit();
		} );
	}

	/**
	 * Photos d'une carte : boutons précédent / suivant (souris) et points de position.
	 *
	 * @param {HTMLElement} media Zone des photos.
	 */
	function initGallery( media ) {
		var track = media.querySelector( '[data-gallery-track]' );
		var prev = media.querySelector( '[data-gallery-prev]' );
		var next = media.querySelector( '[data-gallery-next]' );
		var dots = media.querySelectorAll( '[data-gallery-dots] > span' );
		var frame = 0;

		if ( ! track || ! prev || ! next ) {
			return;
		}

		function index() {
			return track.clientWidth ? Math.round( track.scrollLeft / track.clientWidth ) : 0;
		}

		function update() {
			var current = index();

			frame = 0;
			dots.forEach( function ( dot, position ) {
				dot.classList.toggle( 'is-active', position === current );
			} );
			prev.disabled = current <= 0;
			next.disabled = current >= dots.length - 1;
		}

		function go( delta ) {
			track.scrollTo( {
				left: ( index() + delta ) * track.clientWidth,
				behavior: reduceMotion ? 'auto' : 'smooth',
			} );
		}

		prev.hidden = false;
		next.hidden = false;
		prev.addEventListener( 'click', function () {
			go( -1 );
		} );
		next.addEventListener( 'click', function () {
			go( 1 );
		} );
		track.addEventListener( 'scroll', function () {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		update();
	}

	/**
	 * Zone 8, smartphone : « Afficher plus » ajoute les bateaux de la page suivante sous les
	 * autres. En cas d'erreur, la page suivante s'ouvre normalement.
	 */
	function initMore() {
		var more = root.querySelector( '[data-more]' );
		var link = more && more.querySelector( '[data-more-link]' );
		var list = root.querySelector( '[data-results-list]' );
		var status = more && more.querySelector( '[data-more-status]' );

		if ( ! link || ! list || ! status || 'function' !== typeof window.fetch || 'function' !== typeof window.DOMParser ) {
			return;
		}

		var locale = document.documentElement.lang || 'fr';

		function format( to ) {
			var from = Number( more.dataset.from ) || 1;
			var total = Number( more.dataset.total ) || to;
			var number = function ( value ) {
				return Number( value ).toLocaleString( locale );
			};

			if ( 1 === from ) {
				return String( more.dataset.formatFirst ).replace( '%1$s', number( to ) ).replace( '%2$s', number( total ) );
			}

			return String( more.dataset.formatRange ).replace( '%1$s', number( from ) ).replace( '%2$s', number( to ) ).replace( '%3$s', number( total ) );
		}

		link.addEventListener( 'click', function ( event ) {
			if ( ! isPhone() ) {
				return;
			}

			event.preventDefault();

			if ( 'true' === link.getAttribute( 'aria-busy' ) ) {
				return;
			}

			var url = link.href;

			link.setAttribute( 'aria-busy', 'true' );

			window.fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( String( response.status ) );
					}

					return response.text();
				} )
				.then( function ( html ) {
					var page = new window.DOMParser().parseFromString( html, 'text/html' );
					var items = page.querySelectorAll( '.icademie-categorie [data-results-list] > li' );
					var first = null;

					if ( ! items.length ) {
						throw new Error( 'empty' );
					}

					items.forEach( function ( item ) {
						var node = document.importNode( item, true );

						list.appendChild( node );
						first = first || node;
						node.querySelectorAll( '[data-gallery]' ).forEach( initGallery );
					} );

					var to = ( Number( more.dataset.to ) || 0 ) + items.length;
					var following = page.querySelector( '.icademie-categorie [data-more-link]' );

					more.dataset.to = String( to );
					status.textContent = format( to );

					// Barre de progression : bateaux vus sur le total.
					var bar = more.querySelector( '[data-more-bar]' );

					if ( bar && Number( more.dataset.total ) ) {
						bar.style.width = Math.min( 100, to / Number( more.dataset.total ) * 100 ) + '%';
					}

					if ( following ) {
						link.href = following.href;
						link.removeAttribute( 'aria-busy' );
					} else {
						link.parentNode.removeChild( link );
					}

					// Le premier bateau ajouté reçoit le focus : la lecture continue là.
					var title = first.querySelector( '.icademie-categorie__card-title a' );

					if ( title ) {
						title.focus( { preventScroll: true } );
					}
				} )
				.catch( function () {
					window.location.href = url;
				} );
		} );
	}

	/**
	 * Zone 9, smartphone : texte replié après quelques lignes, « Lire la suite » / « Réduire ».
	 */
	function initReadMore() {
		var prose = root.querySelector( '[data-readmore]' );
		var toggle = root.querySelector( '[data-readmore-toggle]' );

		if ( ! prose || ! toggle ) {
			return;
		}

		var more = toggle.querySelector( '[data-readmore-more]' );
		var less = toggle.querySelector( '[data-readmore-less]' );

		prose.classList.add( 'is-collapsible' );

		function measure() {
			var expanded = prose.classList.contains( 'is-expanded' );

			toggle.hidden = ! expanded && prose.scrollHeight <= prose.clientHeight + 4;
		}

		toggle.addEventListener( 'click', function () {
			var expanded = prose.classList.toggle( 'is-expanded' );

			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );

			if ( more && less ) {
				more.hidden = expanded;
				less.hidden = ! expanded;
			}
		} );

		measure();

		if ( 'function' === typeof window.ResizeObserver ) {
			new window.ResizeObserver( measure ).observe( root );
		}
	}
}() );
