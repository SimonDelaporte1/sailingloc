/**
 * Icademie – Page d'accueil : moteur de recherche, carrousels et onglets.
 *
 * Amélioration progressive : sans ce script, la recherche fonctionne, les carrousels défilent
 * (barre de défilement, glissement du doigt) et les deux parcours de « Comment ça marche » sont
 * affichés l'un sous l'autre.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.icademie-accueil' );

	if ( ! root ) {
		return;
	}

	/** Clé des dates gardées pour la fiche du bateau (voir reprise-dates.js). */
	var DATES_KEY = 'icademie-recherche-dates';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	initSearch();
	root.querySelectorAll( '[data-carousel]' ).forEach( initCarousel );
	root.querySelectorAll( '[data-tabs]' ).forEach( initTabs );

	/**
	 * Recherche : la date de retour ne peut pas précéder le départ ; les dates choisies sont gardées
	 * (le temps de la visite) pour pré-remplir la fiche du bateau.
	 */
	function initSearch() {
		var form = root.querySelector( '[data-search]' );

		if ( ! form ) {
			return;
		}

		var depart = form.querySelector( '[data-search-depart]' );
		var retour = form.querySelector( '[data-search-retour]' );

		function syncReturn() {
			if ( ! depart || ! retour ) {
				return;
			}

			retour.min = depart.value || depart.min;

			if ( retour.value && retour.value < retour.min ) {
				retour.value = '';
			}
		}

		if ( depart ) {
			depart.addEventListener( 'change', syncReturn );
			syncReturn();
		}

		form.addEventListener( 'submit', function () {
			try {
				if ( depart && depart.value ) {
					window.sessionStorage.setItem( DATES_KEY, JSON.stringify( {
						depart: depart.value,
						retour: retour && retour.value ? retour.value : '',
					} ) );
				} else {
					window.sessionStorage.removeItem( DATES_KEY );
				}
			} catch ( error ) {
				// Stockage indisponible (navigation privée stricte) : les dates restent dans l'adresse.
			}
		} );
	}

	/**
	 * Carrousel : boutons précédent / suivant (ordinateur) et points de position (tablette,
	 * smartphone), à jour pendant le défilement.
	 *
	 * @param {HTMLElement} section Section qui contient le carrousel.
	 */
	function initCarousel( section ) {
		var track = section.querySelector( '[data-carousel-track]' );

		if ( ! track || ! track.children.length ) {
			return;
		}

		var nav = section.querySelector( '[data-carousel-nav]' );
		var prev = nav ? nav.querySelector( '[data-carousel-prev]' ) : null;
		var next = nav ? nav.querySelector( '[data-carousel-next]' ) : null;
		var dotsBox = section.querySelector( '[data-carousel-dots]' );
		var dots = [];
		var frame = 0;

		// Un carrousel sans lien (les avis) doit recevoir le focus pour défiler au clavier, mais
		// seulement quand il déborde : sinon, ce serait un arrêt de tabulation inutile.
		var needsTabStop = ! track.querySelector( 'a[href], button, input, select, textarea, [tabindex]' );

		section.classList.add( 'is-enhanced' );

		/** Largeur d'une carte, espace compris. */
		function step() {
			var first = track.children[ 0 ];
			var gap = parseFloat( window.getComputedStyle( track ).columnGap ) || 0;

			return first ? first.getBoundingClientRect().width + gap : track.clientWidth;
		}

		/** Nombre de positions : première carte visible, de la première à la dernière possible. */
		function positions() {
			var max = track.scrollWidth - track.clientWidth;

			return max > 1 ? Math.round( max / step() ) + 1 : 1;
		}

		function buildDots( count ) {
			if ( ! dotsBox || dots.length === count ) {
				return;
			}

			dotsBox.textContent = '';
			dots = [];

			for ( var i = 0; i < count; i++ ) {
				var dot = document.createElement( 'span' );
				dotsBox.appendChild( dot );
				dots.push( dot );
			}
		}

		function update() {
			frame = 0;

			var max = track.scrollWidth - track.clientWidth;
			var overflow = max > 1;
			var left = Math.abs( track.scrollLeft );

			if ( nav ) {
				nav.hidden = ! overflow;
			}

			if ( needsTabStop ) {
				if ( overflow ) {
					track.setAttribute( 'tabindex', '0' );
				} else {
					track.removeAttribute( 'tabindex' );
				}
			}

			if ( prev ) {
				prev.disabled = left <= 1;
			}

			if ( next ) {
				next.disabled = left >= max - 1;
			}

			if ( dotsBox ) {
				var count = positions();
				var index = left >= max - 1 ? count - 1 : Math.round( left / step() );

				buildDots( count );
				dotsBox.hidden = ! overflow;
				dots.forEach( function ( dot, i ) {
					dot.className = i === index ? 'is-active' : '';
				} );
			}
		}

		function schedule() {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		}

		/**
		 * Fait défiler d'un écran de cartes.
		 *
		 * @param {number} direction -1 (précédentes) ou 1 (suivantes).
		 */
		function go( direction ) {
			var size = step();
			var visible = Math.max( 1, Math.floor( ( track.clientWidth + 1 ) / size ) );

			track.scrollBy( {
				left: direction * visible * size,
				behavior: reduceMotion ? 'auto' : 'smooth',
			} );
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( -1 );
			} );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				go( 1 );
			} );
		}

		track.addEventListener( 'scroll', schedule, { passive: true } );

		if ( 'ResizeObserver' in window ) {
			new window.ResizeObserver( schedule ).observe( track );
		} else {
			window.addEventListener( 'resize', schedule );
		}

		update();
	}

	/**
	 * Onglets « Je loue un bateau » / « Je propose mon bateau » (motif ARIA « tabs ») : flèches
	 * gauche et droite, Début et Fin pour changer d'onglet.
	 *
	 * @param {HTMLElement} section Section qui contient les onglets.
	 */
	function initTabs( section ) {
		var list = section.querySelector( '[data-tabs-list]' );

		if ( ! list ) {
			return;
		}

		var tabs = Array.prototype.slice.call( list.querySelectorAll( '[data-tab]' ) );
		var panels = tabs.map( function ( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		} );

		if ( ! tabs.length || panels.indexOf( null ) !== -1 ) {
			return;
		}

		var heading = section.querySelector( 'h2[id]' );

		list.setAttribute( 'role', 'tablist' );

		if ( heading ) {
			list.setAttribute( 'aria-labelledby', heading.id );
		}

		tabs.forEach( function ( tab, i ) {
			tab.setAttribute( 'role', 'tab' );
			panels[ i ].setAttribute( 'role', 'tabpanel' );
			panels[ i ].setAttribute( 'aria-labelledby', tab.id );
			panels[ i ].tabIndex = 0;

			tab.addEventListener( 'click', function () {
				select( i, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var target = null;

				switch ( event.key ) {
					case 'ArrowRight':
						target = ( i + 1 ) % tabs.length;
						break;
					case 'ArrowLeft':
						target = ( i - 1 + tabs.length ) % tabs.length;
						break;
					case 'Home':
						target = 0;
						break;
					case 'End':
						target = tabs.length - 1;
						break;
				}

				if ( null !== target ) {
					event.preventDefault();
					select( target, true );
				}
			} );
		} );

		function select( index, focus ) {
			tabs.forEach( function ( tab, i ) {
				var selected = i === index;

				tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
				tab.tabIndex = selected ? 0 : -1;
				panels[ i ].hidden = ! selected;
			} );

			if ( focus ) {
				tabs[ index ].focus();
			}
		}

		section.classList.add( 'is-enhanced' );
		list.hidden = false;
		select( 0, false );
	}
}() );
