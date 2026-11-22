/**
 * Icademie – En-tête et pied de page : panneau du menu (tablette, smartphone, menu trop long),
 * sous-menus, « Top destinations », recherche, lien du panier, colonnes repliables du pied de page
 * (smartphone).
 *
 * Amélioration progressive : sans ce script, le menu reste dans la barre (il passe à la ligne),
 * les sous-menus s'ouvrent au survol et au clavier, « Top destinations » s'ouvre au clic (élément
 * details), la recherche est affichée sous la barre et les colonnes du pied de page sont dépliées.
 */
( function () {
	'use strict';

	const reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const CHEVRON = '<svg class="icademie-ep__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';

	document.querySelectorAll( '[data-entete]' ).forEach( initHeader );
	document.querySelectorAll( '[data-pied]' ).forEach( initFooter );

	/**
	 * En-tête.
	 *
	 * @param {HTMLElement} header En-tête.
	 */
	function initHeader( header ) {
		header.classList.add( 'has-js' );

		const bar = header.querySelector( '.icademie-entete__bar' );
		const nav = header.querySelector( '[data-nav]' );
		const panel = header.querySelector( '[data-menu-panel]' );
		const opener = header.querySelector( '[data-menu-open]' );
		const panelReady = !! ( panel && opener && 'function' === typeof window.HTMLDialogElement );
		const destinations = header.querySelector( '.icademie-entete__destinations--barre' );
		const searchToggle = header.querySelector( '[data-search-toggle]' );
		const searchPanel = header.querySelector( '[data-search-panel]' );
		const cart = header.querySelector( '[data-cart]' );

		// Panier : affiché quand il contient un article (cookie posé par WooCommerce).
		if ( cart && document.cookie.split( '; ' ).some( ( cookie ) => 0 === cookie.indexOf( 'woocommerce_items_in_cart=' ) ) ) {
			cart.hidden = false;
		}

		// Panneau du menu : fenêtre modale plein écran.
		if ( panelReady ) {
			opener.hidden = false;
			opener.addEventListener( 'click', () => {
				closeSearch();
				closeDestinations();
				document.documentElement.classList.add( 'icademie-entete-panneau' );
				panel.showModal();
			} );
			panel.querySelector( '[data-menu-close]' ).addEventListener( 'click', () => panel.close() );
			panel.addEventListener( 'close', () => {
				document.documentElement.classList.remove( 'icademie-entete-panneau' );
				if ( opener.offsetParent ) {
					opener.focus();
				}
			} );
		}

		// Sous-menus de la barre : ouverts au survol et au focus (CSS), ouverts ou fermés au clic.
		header.querySelectorAll( '[data-submenu-toggle]' ).forEach( ( toggle ) => {
			const item = toggle.closest( '.icademie-entete__item' );

			toggle.addEventListener( 'click', () => {
				const open = 'true' !== toggle.getAttribute( 'aria-expanded' );

				closeSubmenus( item );
				closeDestinations();
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				item.classList.toggle( 'is-open', open );
				item.classList.toggle( 'is-closed', ! open );
			} );

			// Sous-menu ouvert au survol : pas par-dessus la carte des destinations.
			item.addEventListener( 'mouseenter', closeDestinations );

			item.addEventListener( 'mouseleave', () => {
				if ( ! item.contains( document.activeElement ) ) {
					reset( item, toggle );
				}
			} );

			item.addEventListener( 'focusout', ( event ) => {
				if ( ! item.contains( event.relatedTarget ) ) {
					reset( item, toggle );
				}
			} );

			item.addEventListener( 'keydown', ( event ) => {
				if ( 'Escape' === event.key && ( item.classList.contains( 'is-open' ) || item.matches( ':hover, :focus-within' ) ) ) {
					event.stopPropagation();
					toggle.setAttribute( 'aria-expanded', 'false' );
					item.classList.remove( 'is-open' );
					item.classList.add( 'is-closed' );
					toggle.focus();
				}
			} );
		} );

		function reset( item, toggle ) {
			item.classList.remove( 'is-open', 'is-closed' );
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		function closeSubmenus( except ) {
			header.querySelectorAll( '.icademie-entete__item.is-open' ).forEach( ( item ) => {
				if ( item !== except ) {
					reset( item, item.querySelector( '[data-submenu-toggle]' ) );
				}
			} );
		}

		document.addEventListener( 'click', ( event ) => {
			if ( ! header.contains( event.target ) ) {
				closeSubmenus( null );
			}

			if ( destinations && ! destinations.contains( event.target ) ) {
				closeDestinations();
			}
		} );

		// « Top destinations » (barre) : la carte se ferme au clic à côté, à la touche Échap et quand le
		// focus la quitte ; elle reste dans l'écran.
		function closeDestinations() {
			if ( destinations && destinations.open ) {
				destinations.open = false;
			}
		}

		function placeDestinations() {
			const menu = destinations.querySelector( '.icademie-entete__destinations-menu' );
			const margin = 16;
			const width = document.documentElement.clientWidth;

			destinations.style.removeProperty( '--ep-decalage' );

			const rect = menu.getBoundingClientRect();

			if ( rect.left < margin ) {
				destinations.style.setProperty( '--ep-decalage', Math.floor( rect.left - margin ) + 'px' );
			} else if ( rect.right > width - margin ) {
				destinations.style.setProperty( '--ep-decalage', Math.ceil( rect.right - ( width - margin ) ) + 'px' );
			}
		}

		if ( destinations ) {
			const summary = destinations.querySelector( 'summary' );

			destinations.addEventListener( 'toggle', () => {
				if ( destinations.open ) {
					closeSubmenus( null );
					placeDestinations();
				}
			} );

			destinations.addEventListener( 'keydown', ( event ) => {
				if ( 'Escape' === event.key && destinations.open ) {
					event.stopPropagation();
					destinations.open = false;
					summary.focus();
				}
			} );

			destinations.addEventListener( 'focusout', ( event ) => {
				if ( event.relatedTarget && ! destinations.contains( event.relatedTarget ) ) {
					closeDestinations();
				}
			} );
		}

		// Recherche : bandeau sous la barre.
		function closeSearch() {
			if ( searchPanel && searchToggle ) {
				searchPanel.classList.remove( 'is-open' );
				searchToggle.setAttribute( 'aria-expanded', 'false' );
			}
		}

		if ( searchPanel && searchToggle ) {
			searchToggle.hidden = false;
			searchToggle.addEventListener( 'click', () => {
				const open = ! searchPanel.classList.contains( 'is-open' );

				searchPanel.classList.toggle( 'is-open', open );
				searchToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

				if ( open ) {
					searchPanel.querySelector( 'input[type="search"]' ).focus();
				}
			} );
			searchPanel.addEventListener( 'keydown', ( event ) => {
				if ( 'Escape' === event.key ) {
					closeSearch();
					searchToggle.focus();
				}
			} );
		}

		// Menu trop long pour tenir sur une ligne (ordinateur) : rangé dans le panneau. Barre trop étroite
		// pour « Top destinations » (libellé long, petite tablette) : le bouton passe dans le panneau.
		function fit() {
			if ( ! panelReady ) {
				return;
			}

			header.classList.remove( 'is-collapsed', 'is-tight' );

			const desktop = header.clientWidth >= 1024;

			if ( nav && desktop && nav.scrollWidth > nav.clientWidth + 1 ) {
				header.classList.add( 'is-collapsed' );
			}

			if ( destinations && destinations.offsetParent && bar.scrollWidth > bar.clientWidth + 1 ) {
				closeDestinations();
				header.classList.add( 'is-tight' );

				if ( desktop ) {
					header.classList.add( 'is-collapsed' );
				}
			}

			// Retour à la largeur où le menu tient dans la barre : panneau fermé.
			if ( panel.open && desktop && ! header.classList.contains( 'is-collapsed' ) ) {
				panel.close();
			}
		}

		fit();

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( fit );
		}

		if ( 'function' === typeof window.ResizeObserver ) {
			let width = header.clientWidth;

			new window.ResizeObserver( () => {
				if ( header.clientWidth !== width ) {
					width = header.clientWidth;
					fit();
				}
			} ).observe( header );
		}
	}

	/**
	 * Pied de page : sur smartphone, chaque titre de colonne devient un bouton qui déplie sa liste.
	 *
	 * @param {HTMLElement} footer Pied de page.
	 */
	function initFooter( footer ) {
		footer.classList.add( 'has-js' );

		const columns = Array.from( footer.querySelectorAll( '[data-pied-column]' ) );
		let phone = null;

		function apply() {
			const now = footer.clientWidth < 600;

			if ( now === phone ) {
				return;
			}

			phone = now;

			columns.forEach( ( column ) => {
				const heading = column.querySelector( '[data-pied-heading]' );
				const list = column.querySelector( 'ul' );
				const label = heading.textContent.trim();

				if ( phone ) {
					const button = document.createElement( 'button' );

					button.type = 'button';
					button.className = 'icademie-pied__toggle';
					button.setAttribute( 'aria-expanded', 'false' );
					button.setAttribute( 'aria-controls', list.id );
					button.innerHTML = '<span></span>' + CHEVRON;
					button.firstChild.textContent = label;
					button.addEventListener( 'click', () => {
						const open = 'true' !== button.getAttribute( 'aria-expanded' );

						button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
						list.hidden = ! open;

						if ( open && ! reduceMotion ) {
							list.scrollIntoView( { block: 'nearest' } );
						}
					} );
					heading.replaceChildren( button );
					list.hidden = true;
				} else {
					heading.textContent = label;
					list.hidden = false;
				}
			} );
		}

		apply();

		if ( 'function' === typeof window.ResizeObserver ) {
			new window.ResizeObserver( apply ).observe( footer );
		}
	}
}() );
