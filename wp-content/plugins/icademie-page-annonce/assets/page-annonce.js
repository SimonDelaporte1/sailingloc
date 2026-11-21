/**
 * Icademie – Page annonce : partage, photos, réservation, calendrier des disponibilités, listes
 * repliées, carte et bateaux similaires.
 *
 * Amélioration progressive : sans ce script, le module de réservation s'envoie normalement (le total
 * est calculé par le panier), les photos s'ouvrent en grand dans le navigateur, les dates déjà
 * réservées sont listées en texte, les équipements, la description et les avis sont affichés en
 * entier, et la carte reste un lien vers OpenStreetMap.
 */
( function () {
	'use strict';

	const root = document.querySelector( '.icademie-annonce' );

	// Fenêtres : élément <dialog> du navigateur (fenêtre modale, touche Échap, focus gardé).
	if ( ! root || 'function' !== typeof window.HTMLDialogElement ) {
		return;
	}

	root.classList.add( 'has-js' );

	const reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const locale = document.documentElement.lang || 'fr-FR';
	const DAY = 86400000;

	/** Largeurs des zonings : smartphone en dessous de 600 px, ordinateur à partir de 1024 px. */
	const isPhone = () => root.clientWidth < 600;

	const behavior = () => ( reduceMotion ? 'auto' : 'smooth' );

	/**
	 * Remplace %s, %1$s, %2$s… dans un texte traduit.
	 *
	 * @param {string} text   Texte.
	 * @param {...*}   values Valeurs.
	 * @return {string} Texte complété.
	 */
	function sprintf( text, ...values ) {
		let next = 0;

		return String( text ).replace( /%(?:(\d)\$)?s/g, ( match, position ) => {
			const value = position ? values[ Number( position ) - 1 ] : values[ next++ ];
			return undefined === value ? '' : String( value );
		} );
	}

	/* Dates au format AAAA-MM-JJ, calculées en temps universel (pas de décalage d'heure d'été). */

	function parseDate( value ) {
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( value || '' ) ) {
			return null;
		}

		const [ year, month, day ] = value.split( '-' ).map( Number );
		const date = new Date( Date.UTC( year, month - 1, day ) );

		return date.getUTCMonth() === month - 1 ? date : null;
	}

	function formatDate( date ) {
		return date.toISOString().slice( 0, 10 );
	}

	function addDays( value, days ) {
		const date = parseDate( value );
		return date ? formatDate( new Date( date.getTime() + days * DAY ) ) : value;
	}

	function countDays( start, end ) {
		const from = parseDate( start );
		const to = parseDate( end );
		return from && to && to >= from ? Math.round( ( to - from ) / DAY ) + 1 : 0;
	}

	const longDate = new Intl.DateTimeFormat( locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const mediumDate = new Intl.DateTimeFormat( locale, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const shortDate = new Intl.DateTimeFormat( locale, { day: 'numeric', month: 'short', timeZone: 'UTC' } );

	const dialogs = [];

	/**
	 * Fenêtre modale : ouverture, fermeture (bouton, Échap, clic sur le voile), retour du focus.
	 *
	 * @param {HTMLDialogElement} dialog  Fenêtre.
	 * @param {Object}            options onOpen, onClose.
	 * @return {Object|null} open( opener ), close().
	 */
	function modal( dialog, options = {} ) {
		if ( ! dialog ) {
			return null;
		}

		let opener = null;

		dialogs.push( dialog );

		dialog.querySelectorAll( '[data-dialog-close]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => dialog.close() );
		} );

		// Clic à côté de la fenêtre (sur le voile).
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'close', () => {
			document.documentElement.classList.remove( 'icademie-annonce-panneau' );

			if ( options.onClose ) {
				options.onClose();
			}

			if ( opener && opener.isConnected && opener.offsetParent ) {
				opener.focus();
			}
		} );

		return {
			open( from ) {
				if ( dialog.open ) {
					return;
				}

				opener = from || document.activeElement;

				if ( options.onBeforeOpen ) {
					options.onBeforeOpen();
				}

				document.documentElement.classList.add( 'icademie-annonce-panneau' );
				dialog.showModal();

				if ( options.onOpen ) {
					options.onOpen();
				}
			},
			close() {
				if ( dialog.open ) {
					dialog.close();
				}
			},
		};
	}

	initShare();
	initPhotos();
	const booking = initBooking();
	const calendar = initCalendar( booking );
	const bookingPanel = initBookingPanel();
	initReadMore();
	root.querySelectorAll( '[data-expand]' ).forEach( initExpand );
	initReviewForm();
	root.querySelectorAll( '.icademie-annonce__map[data-embed]' ).forEach( initMap );
	initSimilar();

	// Changement de largeur (rotation, fenêtre agrandie) : nombre de mois, barre du bas, fenêtres.
	if ( 'function' === typeof window.ResizeObserver ) {
		let width = root.clientWidth;

		new window.ResizeObserver( () => {
			if ( root.clientWidth === width ) {
				return;
			}

			width = root.clientWidth;

			if ( calendar ) {
				calendar.resize();
			}

			if ( bookingPanel ) {
				bookingPanel.resize();
			}

			root.dispatchEvent( new CustomEvent( 'icademie-annonce-resize' ) );
		} ).observe( root );
	}

	/**
	 * Zone 3 : fenêtre de partage (réseaux sociaux, e-mail, lien à copier, partage du téléphone).
	 */
	function initShare() {
		const dialog = root.querySelector( '[data-dialog="share"]' );
		const status = dialog && dialog.querySelector( '[data-share-status]' );
		const share = modal( dialog, {
			onBeforeOpen() {
				status.textContent = '';
			},
		} );

		if ( ! share ) {
			return;
		}

		root.querySelectorAll( '[data-share-open]' ).forEach( ( button ) => {
			button.hidden = false;
			button.addEventListener( 'click', () => share.open( button ) );
		} );

		const copy = dialog.querySelector( '[data-share-copy]' );

		if ( copy ) {
			copy.addEventListener( 'click', () => {
				const url = copy.dataset.shareCopy;
				const done = () => {
					status.textContent = copy.dataset.labelDone;
				};
				const fail = () => {
					status.textContent = copy.dataset.labelFail;
				};

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( url ).then( done, fail );
					return;
				}

				// Adresse en http (site de test) : ancienne méthode de copie.
				const field = document.createElement( 'textarea' );
				field.value = url;
				field.setAttribute( 'readonly', '' );
				field.style.position = 'fixed';
				field.style.opacity = '0';
				dialog.append( field );
				field.select();

				try {
					if ( document.execCommand( 'copy' ) ) {
						done();
					} else {
						fail();
					}
				} catch ( error ) {
					fail();
				}

				field.remove();
				copy.focus();
			} );
		}

		const native = dialog.querySelector( '[data-share-native]' );
		const nativeButton = native && native.querySelector( '[data-share-native-button]' );

		if ( nativeButton && 'function' === typeof navigator.share ) {
			native.hidden = false;
			nativeButton.addEventListener( 'click', () => {
				navigator
					.share( {
						title: nativeButton.dataset.title,
						text: nativeButton.dataset.text,
						url: nativeButton.dataset.url,
					} )
					.then( () => share.close() )
					.catch( () => {} );
			} );
		}
	}

	/**
	 * Zone 4 : photos. Smartphone : carrousel avec compteur. Clic sur une photo : visionneuse en plein
	 * écran (flèches, glisser, touches ← et →).
	 */
	function initPhotos() {
		const gallery = root.querySelector( '[data-photos]' );
		const track = gallery && gallery.querySelector( '[data-photos-track]' );

		if ( ! track ) {
			return;
		}

		const current = gallery.querySelector( '[data-photos-current]' );
		let frame = 0;

		if ( current ) {
			track.addEventListener( 'scroll', () => {
				if ( frame ) {
					return;
				}

				frame = window.requestAnimationFrame( () => {
					frame = 0;
					current.textContent = String( track.clientWidth ? Math.round( track.scrollLeft / track.clientWidth ) + 1 : 1 );
				} );
			}, { passive: true } );
		}

		const dialog = root.querySelector( '[data-dialog="lightbox"]' );
		const slides = dialog && dialog.querySelector( '[data-lightbox-track]' );

		if ( ! slides ) {
			return;
		}

		const count = slides.children.length;
		const counter = dialog.querySelector( '[data-lightbox-current]' );
		const prev = dialog.querySelector( '[data-lightbox-prev]' );
		const next = dialog.querySelector( '[data-lightbox-next]' );
		let start = 0;
		let slideFrame = 0;

		const index = () => ( slides.clientWidth ? Math.round( slides.scrollLeft / slides.clientWidth ) : 0 );

		function update() {
			const position = index();

			slideFrame = 0;
			counter.textContent = String( position + 1 );

			if ( prev && next ) {
				prev.disabled = position <= 0;
				next.disabled = position >= count - 1;
			}
		}

		function go( delta ) {
			slides.scrollTo( { left: ( index() + delta ) * slides.clientWidth, behavior: behavior() } );
		}

		const lightbox = modal( dialog, {
			onOpen() {
				slides.scrollTo( { left: start * slides.clientWidth, behavior: 'auto' } );
				update();
			},
		} );

		root.querySelectorAll( '[data-photo]' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				start = Math.min( count - 1, Math.max( 0, Number( link.dataset.photo ) || 0 ) );
				lightbox.open( link );
			} );
		} );

		slides.addEventListener( 'scroll', () => {
			if ( ! slideFrame ) {
				slideFrame = window.requestAnimationFrame( update );
			}
		}, { passive: true } );

		if ( prev && next ) {
			prev.addEventListener( 'click', () => go( -1 ) );
			next.addEventListener( 'click', () => go( 1 ) );
		}

		dialog.addEventListener( 'keydown', ( event ) => {
			if ( 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
				event.preventDefault();
				go( 'ArrowLeft' === event.key ? -1 : 1 );
			}
		} );
	}

	/**
	 * Zone 5 : module de réservation. Le total (prix × jours, frais de service, caution affichée mais
	 * non comptée) se met à jour avec les dates ; des dates déjà réservées sont refusées avant l'envoi.
	 *
	 * @return {Object|null} Dates choisies et mise à jour, pour le calendrier.
	 */
	function initBooking() {
		const form = root.querySelector( '[data-booking-form]' );
		const startInput = form && form.querySelector( '[data-date-start]' );
		const endInput = form && form.querySelector( '[data-date-end]' );
		const calendarNode = root.querySelector( '[data-calendar]' );

		if ( ! startInput || ! endInput ) {
			return null;
		}

		const booked = new Set( calendarNode ? JSON.parse( calendarNode.dataset.booked || '[]' ) : [] );
		const today = startInput.min;
		const price = Number( form.dataset.price ) || 0;
		const rate = Number( form.dataset.feeRate ) || 0;
		const format = JSON.parse( form.dataset.format || '{}' );
		const error = form.querySelector( '[data-dates-error]' );
		const hint = form.querySelector( '[data-lines-hint]' );
		const status = form.querySelector( '[data-booking-status]' );
		const label = form.querySelector( '[data-line-label]' );
		const rental = form.querySelector( '[data-line-rental]' );
		const fee = form.querySelector( '[data-line-fee]' );
		const total = form.querySelector( '[data-line-total]' );
		const barDates = document.querySelector( '[data-bar-dates]' );
		const listeners = [];
		let announceTimer = 0;

		function money( amount ) {
			const decimals = Number( format.decimals ) || 0;
			const fixed = Math.abs( amount ).toFixed( decimals ).split( '.' );
			const whole = fixed[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, format.thousand || '' );
			const number = ( amount < 0 ? '-' : '' ) + whole + ( decimals ? ( format.decimal || ',' ) + fixed[ 1 ] : '' );

			return sprintf( format.format || '%1$s%2$s', format.symbol || '', number );
		}

		function round( amount ) {
			const factor = Math.pow( 10, Number( format.decimals ) || 0 );
			return Math.round( amount * factor ) / factor;
		}

		/** Un jour déjà réservé entre deux dates (comprises) ? */
		function hasBooked( start, end ) {
			for ( let day = start; day <= end; day = addDays( day, 1 ) ) {
				if ( booked.has( day ) ) {
					return true;
				}
			}

			return false;
		}

		function update() {
			const start = startInput.value;
			const end = endInput.value;
			let message = '';

			endInput.min = start && start >= today ? start : today;

			if ( start && end && end < start ) {
				message = form.dataset.errorOrder;
			} else if ( start && end && hasBooked( start, end ) ) {
				message = form.dataset.errorBooked;
			} else if ( start && ! end && booked.has( start ) ) {
				message = form.dataset.errorBooked;
			}

			error.textContent = message;
			error.hidden = '' === message;
			endInput.setCustomValidity( message );
			[ startInput, endInput ].forEach( ( input ) => {
				if ( message ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}
			} );

			const days = message ? 0 : countDays( start, end );

			if ( days ) {
				const rentalAmount = round( price * days );
				const feeAmount = round( rentalAmount * rate );

				label.textContent = 1 === days ? form.dataset.labelOne : sprintf( form.dataset.labelMany, days.toLocaleString( locale ) );
				rental.textContent = money( rentalAmount );
				fee.textContent = money( feeAmount );
				total.textContent = money( rentalAmount + feeAmount );
				hint.hidden = true;

				if ( barDates ) {
					barDates.textContent = shortDate.format( parseDate( start ) ) + ' – ' + shortDate.format( parseDate( end ) ) + ' · ' + money( rentalAmount + feeAmount );
				}

				window.clearTimeout( announceTimer );
				announceTimer = window.setTimeout( () => {
					status.textContent = 1 === days ? sprintf( form.dataset.announceOne, money( rentalAmount + feeAmount ) ) : sprintf( form.dataset.announce, money( rentalAmount + feeAmount ), days.toLocaleString( locale ) );
				}, 400 );
			} else {
				label.textContent = form.dataset.labelEmpty;
				rental.textContent = '—';
				fee.textContent = '—';
				total.textContent = '—';
				hint.hidden = false;

				if ( barDates ) {
					barDates.textContent = '';
				}
			}

			listeners.forEach( ( listener ) => listener( start, end ) );
		}

		[ startInput, endInput ].forEach( ( input ) => {
			input.addEventListener( 'input', update );
			input.addEventListener( 'change', update );
		} );

		// Retour choisi avant le départ : on le vide quand le départ passe après.
		startInput.addEventListener( 'change', () => {
			if ( startInput.value && endInput.value && endInput.value < startInput.value ) {
				endInput.value = '';
				update();
			}
		} );

		form.addEventListener( 'submit', ( event ) => {
			update();

			if ( ! error.hidden ) {
				event.preventDefault();
				endInput.focus();
			}
		} );

		update();

		return {
			booked,
			today,
			get start() {
				return startInput.value;
			},
			get end() {
				return endInput.value;
			},
			hasBooked,
			/**
			 * Dates choisies dans le calendrier.
			 *
			 * @param {string} start Départ.
			 * @param {string} end   Retour (vide : à choisir).
			 */
			set( start, end ) {
				startInput.value = start;
				endInput.value = end;
				update();
			},
			onChange( listener ) {
				listeners.push( listener );
			},
		};
	}

	/**
	 * Zone 9 : calendrier des disponibilités (2 mois, 1 sur smartphone). Jours déjà réservés barrés ;
	 * un clic choisit le départ, un second le retour (champs du module de réservation).
	 *
	 * Clavier : un seul jour dans l'ordre de tabulation, flèches pour se déplacer, Page précédente /
	 * suivante pour changer de mois.
	 *
	 * @param {Object|null} dates Module de réservation.
	 * @return {Object|null} resize().
	 */
	function initCalendar( dates ) {
		const node = root.querySelector( '[data-calendar]' );

		if ( ! node ) {
			return null;
		}

		const status = root.querySelector( '[data-calendar-status]' );
		const booked = dates ? dates.booked : new Set( JSON.parse( node.dataset.booked || '[]' ) );
		const today = node.dataset.today;
		const first = today.slice( 0, 8 ) + '01';
		const last = monthAdd( first, 23 );
		const monthTitle = new Intl.DateTimeFormat( locale, { month: 'long', year: 'numeric', timeZone: 'UTC' } );
		const narrow = new Intl.DateTimeFormat( locale, { weekday: 'narrow', timeZone: 'UTC' } );
		const weekday = new Intl.DateTimeFormat( locale, { weekday: 'long', timeZone: 'UTC' } );
		let month = dates && dates.start && dates.start >= first ? dates.start.slice( 0, 8 ) + '01' : first;
		let focus = '';
		let count = 0;

		function monthAdd( value, months ) {
			const date = parseDate( value );
			return formatDate( new Date( Date.UTC( date.getUTCFullYear(), date.getUTCMonth() + months, 1 ) ) );
		}

		function capitalize( text ) {
			return text.charAt( 0 ).toLocaleUpperCase( locale ) + text.slice( 1 );
		}

		function selected( day ) {
			if ( ! dates || ! dates.start ) {
				return false;
			}

			return dates.end ? day >= dates.start && day <= dates.end : day === dates.start;
		}

		function cell( day ) {
			const number = String( parseDate( day ).getUTCDate() );
			const label = longDate.format( parseDate( day ) );

			if ( day < today || booked.has( day ) ) {
				const span = document.createElement( 'span' );
				const sr = document.createElement( 'span' );

				span.className = 'icademie-annonce__day ' + ( day < today ? 'is-past' : 'is-booked' );
				span.textContent = number;
				sr.className = 'icademie-annonce__sr';
				sr.textContent = ', ' + ( day < today ? node.dataset.labelPast : node.dataset.labelBooked );
				span.append( sr );
				return span;
			}

			const button = document.createElement( 'button' );
			const isSelected = selected( day );
			let suffix = '';

			if ( dates && day === dates.start ) {
				suffix = ', ' + node.dataset.labelStart;
			} else if ( dates && day === dates.end ) {
				suffix = ', ' + node.dataset.labelEnd;
			}

			button.type = 'button';
			button.className = 'icademie-annonce__day' + ( isSelected ? ' is-selected' : '' ) + ( day === today ? ' is-today' : '' );
			button.dataset.day = day;
			button.tabIndex = -1;
			button.textContent = number;
			button.setAttribute( 'aria-label', capitalize( label ) + suffix );
			button.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );

			if ( ! dates ) {
				button.disabled = true;
			}

			return button;
		}

		function navButton( delta, disabled ) {
			const button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'icademie-annonce__round';
			button.disabled = disabled;
			button.dataset.monthStep = String( delta );
			button.innerHTML = '<svg class="icademie-annonce__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + ( delta < 0 ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6' ) + '"/></svg>';
			button.setAttribute( 'aria-label', delta < 0 ? node.dataset.labelPrev : node.dataset.labelNext );
			return button;
		}

		function renderMonth( start, position ) {
			const wrapper = document.createElement( 'div' );
			const head = document.createElement( 'div' );
			const title = document.createElement( 'h3' );
			const table = document.createElement( 'table' );
			const id = 'icademie-annonce-mois-' + start;
			const date = parseDate( start );

			wrapper.className = 'icademie-annonce__month';
			head.className = 'icademie-annonce__month-head';
			title.className = 'icademie-annonce__month-title';
			title.id = id;
			title.textContent = capitalize( monthTitle.format( date ) );

			if ( 0 === position ) {
				head.append( navButton( -1, start <= first ) );
			}

			head.append( title );

			if ( count - 1 === position ) {
				head.append( navButton( 1, monthAdd( start, count - 1 ) >= last ) );
			}

			table.className = 'icademie-annonce__days';
			table.setAttribute( 'aria-labelledby', id );

			const headRow = table.createTHead().insertRow();

			// Lundi 1er janvier 2024 : en-têtes du lundi au dimanche.
			for ( let i = 0; i < 7; i++ ) {
				const th = document.createElement( 'th' );
				const day = new Date( Date.UTC( 2024, 0, 1 + i ) );

				th.scope = 'col';
				th.abbr = weekday.format( day );
				th.textContent = narrow.format( day ).toLocaleUpperCase( locale );
				headRow.append( th );
			}

			const body = table.createTBody();
			const offset = ( date.getUTCDay() + 6 ) % 7;
			const length = new Date( Date.UTC( date.getUTCFullYear(), date.getUTCMonth() + 1, 0 ) ).getUTCDate();

			// Toujours 6 semaines : même hauteur pour tous les mois.
			for ( let week = 0; week < 6; week++ ) {
				const row = body.insertRow();

				for ( let i = 0; i < 7; i++ ) {
					const td = row.insertCell();
					const number = week * 7 + i - offset + 1;

					if ( number >= 1 && number <= length ) {
						td.append( cell( start.slice( 0, 8 ) + String( number ).padStart( 2, '0' ) ) );
					}
				}
			}

			wrapper.append( head, table );
			return wrapper;
		}

		function render( focusDay ) {
			count = isPhone() ? 1 : 2;

			if ( monthAdd( month, count - 1 ) > last ) {
				month = monthAdd( last, 1 - count );
			}

			if ( month < first ) {
				month = first;
			}

			const months = [];

			for ( let i = 0; i < count; i++ ) {
				months.push( renderMonth( monthAdd( month, i ), i ) );
			}

			node.replaceChildren( ...months );

			// Un seul jour dans l'ordre de tabulation : le jour actif, le départ, ou le premier libre.
			const buttons = Array.from( node.querySelectorAll( 'button[data-day]' ) );
			const wanted = [ focusDay, focus, dates && dates.start, dates && dates.end ];
			let active = null;

			for ( const day of wanted ) {
				active = day ? buttons.find( ( button ) => button.dataset.day === day ) : null;

				if ( active ) {
					break;
				}
			}

			active = active || buttons[ 0 ];

			if ( active ) {
				active.tabIndex = 0;
				focus = active.dataset.day;

				if ( focusDay && active.dataset.day === focusDay ) {
					active.focus();
				}
			}
		}

		function choose( day ) {
			if ( ! dates ) {
				return;
			}

			const start = dates.start;
			const end = dates.end;

			if ( ! start || end || day < start ) {
				dates.set( day, '' );
				status.textContent = sprintf( node.dataset.statusStart, mediumDate.format( parseDate( day ) ) );
			} else if ( dates.hasBooked( start, day ) ) {
				status.textContent = node.dataset.statusBooked;
			} else {
				dates.set( start, day );

				const days = countDays( start, day );
				status.textContent = 1 === days ? sprintf( node.dataset.statusOne, mediumDate.format( parseDate( day ) ) ) : sprintf( node.dataset.statusRange, mediumDate.format( parseDate( start ) ), mediumDate.format( parseDate( day ) ), days.toLocaleString( locale ) );
			}

			focus = day;
			render( day );
		}

		function show( day ) {
			const target = day.slice( 0, 8 ) + '01';

			if ( target < month ) {
				month = target;
			} else if ( target > monthAdd( month, count - 1 ) ) {
				month = monthAdd( target, 1 - count );
			}
		}

		node.addEventListener( 'click', ( event ) => {
			const step = event.target.closest( '[data-month-step]' );

			if ( step && ! step.disabled ) {
				month = monthAdd( month, Number( step.dataset.monthStep ) );
				render();

				// Le bouton est recréé : le focus revient sur le même bouton s'il est toujours actif.
				const again = node.querySelector( '[data-month-step="' + step.dataset.monthStep + '"]' );

				( again && ! again.disabled ? again : node.querySelector( '[data-month-step]:not(:disabled)' ) || node ).focus();
				return;
			}

			const button = event.target.closest( 'button[data-day]' );

			if ( button ) {
				choose( button.dataset.day );
			}
		} );

		node.addEventListener( 'keydown', ( event ) => {
			const button = event.target.closest( 'button[data-day]' );
			const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

			if ( ! button ) {
				return;
			}

			let step = moves[ event.key ] || 0;
			let target = '';

			if ( 'PageUp' === event.key || 'PageDown' === event.key ) {
				const date = parseDate( button.dataset.day );
				const months = 'PageUp' === event.key ? -1 : 1;
				const next = new Date( Date.UTC( date.getUTCFullYear(), date.getUTCMonth() + months, 1 ) );
				const length = new Date( Date.UTC( next.getUTCFullYear(), next.getUTCMonth() + 1, 0 ) ).getUTCDate();

				next.setUTCDate( Math.min( date.getUTCDate(), length ) );
				target = formatDate( next );
				step = months;
			} else if ( 'Home' === event.key || 'End' === event.key ) {
				const position = ( parseDate( button.dataset.day ).getUTCDay() + 6 ) % 7;

				target = addDays( button.dataset.day, 'Home' === event.key ? -position : 6 - position );
				step = 'Home' === event.key ? 1 : -1;
			} else if ( step ) {
				target = addDays( button.dataset.day, step );
			} else {
				return;
			}

			event.preventDefault();

			// Jour passé ou réservé : on continue dans la même direction jusqu'à un jour libre.
			const direction = step > 0 ? 1 : -1;
			const jump = Math.abs( step ) === 7 ? 7 * direction : direction;

			for ( let i = 0; i < 120 && target >= today && target <= monthAdd( last, 1 ); i++ ) {
				if ( ! booked.has( target ) ) {
					break;
				}

				target = addDays( target, jump );
			}

			if ( target < today ) {
				target = today;

				while ( booked.has( target ) ) {
					target = addDays( target, 1 );
				}
			}

			if ( target >= monthAdd( last, 1 ) ) {
				return;
			}

			show( target );
			focus = target;
			render( target );
		} );

		if ( dates ) {
			// Dates saisies dans le module : le calendrier les affiche.
			dates.onChange( ( start ) => {
				if ( node.contains( document.activeElement ) ) {
					return;
				}

				if ( start && start >= first ) {
					show( start );
				}

				render();
			} );
		}

		render();

		return {
			resize() {
				if ( ( isPhone() ? 1 : 2 ) !== count ) {
					render();
				}
			},
		};
	}

	/**
	 * Smartphone : barre fixe en bas de l'écran ; « Réserver » ouvre le module en plein écran.
	 *
	 * @return {Object|null} resize().
	 */
	function initBookingPanel() {
		const bar = document.querySelector( '[data-booking-bar]' );
		const dialog = root.querySelector( '[data-dialog="booking"]' );
		const card = root.querySelector( '[data-booking-card]' );
		const body = dialog && dialog.querySelector( '[data-dialog-body]' );

		if ( ! bar || ! card || ! body ) {
			return null;
		}

		const marker = document.createComment( 'icademie-annonce-reservation' );
		const panel = modal( dialog, {
			onBeforeOpen() {
				card.parentNode.insertBefore( marker, card );
				body.append( card );
			},
			onOpen() {
				const field = card.querySelector( 'input:not([type="hidden"]), select, button' );

				if ( field ) {
					field.focus();
				}
			},
			onClose() {
				if ( marker.parentNode ) {
					marker.parentNode.insertBefore( card, marker );
					marker.remove();
				}
			},
		} );

		bar.querySelector( '[data-booking-open]' ).addEventListener( 'click', ( event ) => panel.open( event.currentTarget ) );

		// Après un refus de réservation (dates, passagers), le module rouvre avec le message.
		const notices = document.querySelector( '.woocommerce-error, .wc-block-components-notice-banner.is-error' );

		function resize() {
			const phone = isPhone();

			bar.hidden = ! phone;
			document.documentElement.classList.toggle( 'icademie-annonce-barre', phone );

			if ( ! phone ) {
				panel.close();
			}
		}

		resize();

		if ( notices && isPhone() && card.querySelector( '[data-date-start]' ) && card.querySelector( '[data-date-start]' ).value ) {
			panel.open( bar.querySelector( '[data-booking-open]' ) );
		}

		return { resize };
	}

	/**
	 * Zone 7 : description repliée au-delà de quelques lignes, « Lire la suite » pour la déplier.
	 */
	function initReadMore() {
		const section = root.querySelector( '[data-read-more]' );
		const text = section && section.querySelector( '[data-read-more-text]' );
		const toggle = section && section.querySelector( '[data-read-more-toggle]' );

		if ( ! text || ! toggle ) {
			return;
		}

		const more = toggle.textContent;
		let open = false;

		function check() {
			if ( open ) {
				return;
			}

			text.classList.add( 'is-clamped' );

			const overflows = text.scrollHeight > text.clientHeight + 2;

			text.classList.toggle( 'is-clamped', overflows );
			toggle.hidden = ! overflows;
		}

		toggle.addEventListener( 'click', () => {
			open = ! open;
			text.classList.toggle( 'is-clamped', ! open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = open ? toggle.dataset.labelLess : more;

			if ( ! open ) {
				section.scrollIntoView( { block: 'nearest', behavior: behavior() } );
			}
		} );

		check();
		root.addEventListener( 'icademie-annonce-resize', check );

		// Police des titres ou images chargées plus tard : hauteur à revoir.
		window.addEventListener( 'load', check );
	}

	/**
	 * Zones 8 et 12 : équipements et avis repliés (9, 8 ou 6 équipements, 2 ou 1 avis visibles selon
	 * la largeur) ; le bouton déplie toute la liste.
	 *
	 * @param {HTMLElement} section Zone.
	 */
	function initExpand( section ) {
		const toggle = section.querySelector( '[data-expand-toggle]' );

		if ( ! toggle ) {
			return;
		}

		const more = toggle.textContent;

		section.classList.add( 'is-collapsed' );
		toggle.hidden = false;
		toggle.dataset.ready = '1';

		toggle.addEventListener( 'click', () => {
			const collapsed = section.classList.toggle( 'is-collapsed' );

			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			toggle.textContent = collapsed ? more : toggle.dataset.labelLess;

			if ( collapsed ) {
				section.scrollIntoView( { block: 'nearest', behavior: behavior() } );
			}
		} );

		// Lien vers un avis replié (après l'envoi d'un avis) : la liste se déplie.
		const hash = window.location.hash.slice( 1 );
		const target = hash && document.getElementById( hash );

		if ( target && section.contains( target ) && ! target.offsetParent ) {
			toggle.click();
			target.scrollIntoView();
		}
	}

	/**
	 * Zone 12 : formulaire de dépôt d'avis replié derrière « Donner mon avis ».
	 */
	function initReviewForm() {
		const wrapper = root.querySelector( '[data-review-form]' );
		const toggle = wrapper && wrapper.querySelector( '[data-review-form-toggle]' );
		const body = wrapper && wrapper.querySelector( '[data-review-form-body]' );

		if ( ! toggle || ! body ) {
			return;
		}

		const hash = window.location.hash.slice( 1 );
		const target = hash && document.getElementById( hash );

		// Lien direct vers le formulaire (#respond, #review_form) : il reste ouvert.
		if ( target && body.contains( target ) ) {
			return;
		}

		body.hidden = true;
		toggle.hidden = false;

		toggle.addEventListener( 'click', () => {
			const open = body.hidden;

			body.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			// Premier champ affiché (la liste des notes de WooCommerce est remplacée par des étoiles).
			if ( open ) {
				const field = Array.from( body.querySelectorAll( 'a[href], button, input:not([type="hidden"]), select, textarea' ) ).find( ( element ) => ! element.disabled && element.getClientRects().length && 'hidden' !== getComputedStyle( element ).visibility );

				if ( field ) {
					field.focus();
				}
			}
		} );
	}

	/**
	 * Zone 11 : la carte OpenStreetMap ne se charge (et ne contacte OpenStreetMap) qu'au clic, comme
	 * sur la page contact.
	 *
	 * @param {HTMLElement} map Encart de la carte.
	 */
	function initMap( map ) {
		const button = map.querySelector( '[data-map-load]' );

		if ( ! button ) {
			return;
		}

		button.hidden = false;

		button.addEventListener( 'click', () => {
			const iframe = document.createElement( 'iframe' );

			iframe.src = map.dataset.embed;
			iframe.title = map.dataset.title || '';
			iframe.referrerPolicy = 'strict-origin-when-cross-origin';

			map.querySelector( '.icademie-annonce__map-placeholder' ).hidden = true;
			map.append( iframe );
			iframe.focus();
		} );
	}

	/**
	 * Zone 13 : bateaux similaires en carrousel (flèches sur ordinateur, points sur tablette et
	 * smartphone, glisser au doigt), photos des cartes comme sur les pages catégorie.
	 */
	function initSimilar() {
		const section = root.querySelector( '[data-similar]' );
		const track = section && section.querySelector( '[data-similar-track]' );

		if ( ! track ) {
			return;
		}

		const nav = section.querySelector( '[data-similar-nav]' );
		const prev = section.querySelector( '[data-similar-prev]' );
		const next = section.querySelector( '[data-similar-next]' );
		const dots = section.querySelector( '[data-similar-dots]' );
		let frame = 0;

		function pages() {
			return track.clientWidth ? Math.max( 1, Math.ceil( ( track.scrollWidth - 2 ) / track.clientWidth ) ) : 1;
		}

		function page() {
			const max = track.scrollWidth - track.clientWidth;

			if ( max <= 1 ) {
				return 0;
			}

			return track.scrollLeft >= max - 1 ? pages() - 1 : Math.round( track.scrollLeft / track.clientWidth );
		}

		function update() {
			const total = pages();
			const current = page();

			frame = 0;
			nav.hidden = total <= 1;
			prev.disabled = track.scrollLeft <= 1;
			next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 1;

			if ( dots.children.length !== ( total > 1 ? total : 0 ) ) {
				dots.replaceChildren( ...Array.from( { length: total > 1 ? total : 0 }, () => document.createElement( 'span' ) ) );
			}

			Array.from( dots.children ).forEach( ( dot, position ) => dot.classList.toggle( 'is-active', position === current ) );
		}

		function go( delta ) {
			track.scrollBy( { left: delta * track.clientWidth, behavior: behavior() } );
		}

		prev.addEventListener( 'click', () => go( -1 ) );
		next.addEventListener( 'click', () => go( 1 ) );
		track.addEventListener( 'scroll', () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		root.addEventListener( 'icademie-annonce-resize', update );
		update();

		section.querySelectorAll( '[data-gallery]' ).forEach( initCardPhotos );
	}

	/**
	 * Photos d'une carte de bateau similaire : boutons précédent / suivant (souris) et points.
	 *
	 * @param {HTMLElement} media Zone des photos de la carte.
	 */
	function initCardPhotos( media ) {
		const track = media.querySelector( '[data-gallery-track]' );
		const prev = media.querySelector( '[data-gallery-prev]' );
		const next = media.querySelector( '[data-gallery-next]' );
		const dots = media.querySelectorAll( '[data-gallery-dots] > span' );
		let frame = 0;

		if ( ! track || ! prev || ! next ) {
			return;
		}

		const index = () => ( track.clientWidth ? Math.round( track.scrollLeft / track.clientWidth ) : 0 );

		function update() {
			const current = index();

			frame = 0;
			dots.forEach( ( dot, position ) => dot.classList.toggle( 'is-active', position === current ) );
			prev.disabled = current <= 0;
			next.disabled = current >= dots.length - 1;
		}

		prev.hidden = false;
		next.hidden = false;
		prev.addEventListener( 'click', () => track.scrollTo( { left: ( index() - 1 ) * track.clientWidth, behavior: behavior() } ) );
		next.addEventListener( 'click', () => track.scrollTo( { left: ( index() + 1 ) * track.clientWidth, behavior: behavior() } ) );
		track.addEventListener( 'scroll', () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		update();
	}
}() );
