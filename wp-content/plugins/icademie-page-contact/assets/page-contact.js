/**
 * Icademie – Page Contact.
 *
 * Améliore la page Contact, qui fonctionne aussi sans JavaScript (le serveur vérifie tout) :
 * - la liste « Objet de la demande » et le champ « N° de réservation » suivent le profil choisi ;
 * - les champs sont vérifiés avant l'envoi, avec les mêmes messages que le serveur, affichés sous
 *   chaque champ et résumés en haut du formulaire ;
 * - le nom de la pièce jointe choisie s'affiche, avec un bouton pour la retirer ;
 * - la carte OpenStreetMap ne se charge que si le visiteur la demande ;
 * - le bandeau d'assistance prend une ombre quand il reste collé en bas de l'écran.
 */
( function () {
	'use strict';

	const root = document.querySelector( '.icademie-contact' );

	if ( ! root ) {
		return;
	}

	const form = root.querySelector( '.icademie-contact__form' );

	if ( form ) {
		initForm( form );
	}

	root.querySelectorAll( '.icademie-contact__map[data-embed]' ).forEach( initMap );
	initAssistance( root.querySelector( '.icademie-contact__assistance' ) );

	// Après un envoi (confirmation ou erreurs), le message affiché reçoit le focus.
	const notice = root.querySelector( '[data-focus]:not([hidden])' );

	if ( notice ) {
		notice.focus();
	}

	/**
	 * Formulaire de contact.
	 *
	 * @param {HTMLFormElement} form Formulaire.
	 */
	function initForm( form ) {
		let config = {};

		try {
			config = JSON.parse( form.dataset.config || '{}' );
		} catch ( error ) {
			return;
		}

		const messages = config.messages || {};
		const control = ( name ) => form.elements[ 'icademie_contact[' + name + ']' ];
		const profiles = Array.from( form.querySelectorAll( 'input[name="icademie_contact[profil]"]' ) );
		const subject = control( 'objet' );
		const reservationField = form.querySelector( '[data-field="reservation"]' );
		const fileInput = form.querySelector( '.icademie-contact__file-input' );
		const summary = form.parentElement.querySelector( '[data-summary]' );
		const submitButton = form.querySelector( '[type="submit"]' );
		const submitLabel = submitButton.textContent;

		// Après une première tentative d'envoi, les erreurs se mettent à jour pendant la saisie.
		let attempted = !! form.querySelector( '[aria-invalid="true"]' );

		const currentProfile = () => {
			const checked = profiles.find( ( radio ) => radio.checked );
			return checked ? checked.value : '';
		};

		/* Objets de demande et numéro de réservation selon le profil. */

		const placeholder = subject.querySelector( 'option[value=""]' );
		const subjectsByProfile = {};

		subject.querySelectorAll( 'optgroup[data-profil]' ).forEach( ( group ) => {
			subjectsByProfile[ group.dataset.profil ] = Array.from( group.querySelectorAll( 'option' ), ( option ) => [ option.value, option.textContent ] );
		} );

		const syncProfile = () => {
			const profile = currentProfile();
			const selected = subject.value;
			const options = ( subjectsByProfile[ profile ] || [] ).map( ( [ value, label ] ) => new Option( label, value, false, value === selected ) );

			subject.replaceChildren( placeholder, ...options );

			if ( ! options.some( ( option ) => option.selected ) ) {
				placeholder.selected = true;
			}

			if ( reservationField ) {
				const label = ( config.reservationLabels || {} )[ profile ];
				const input = control( 'reservation' );

				// Pas de numéro de réservation pour « Autre demande ».
				reservationField.hidden = ! label;
				input.disabled = ! label;

				if ( label ) {
					reservationField.querySelector( 'label' ).textContent = label;
				}
			}
		};

		profiles.forEach( ( radio ) => radio.addEventListener( 'change', () => {
			syncProfile();

			if ( attempted ) {
				showError( 'objet', rules.objet() );
			}
		} ) );

		syncProfile();

		/* Vérification des champs : mêmes règles et mêmes messages que le serveur. */

		const namePattern = /^[\p{L}\p{M}][\p{L}\p{M}\s'’.-]*$/u;
		const emailPattern = /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/;
		const phonePattern = /^\+?[0-9\s().-]{6,30}$/;
		const length = ( value ) => Array.from( value.trim() ).length;

		const checkName = ( name ) => {
			const value = control( name ).value.trim().replace( /\s+/g, ' ' );

			if ( ! value ) {
				return messages[ name ];
			}

			return length( value ) > config.nameMax || ! namePattern.test( value ) ? messages[ name + '_invalide' ] : '';
		};

		const checkFile = () => {
			const file = fileInput && fileInput.files && fileInput.files[ 0 ];

			if ( ! file ) {
				return '';
			}

			if ( ! config.extensions.includes( file.name.split( '.' ).pop().toLowerCase() ) ) {
				return messages.fichier_format;
			}

			return file.size > config.maxSize ? messages.fichier_taille : '';
		};

		const rules = {
			profil: () => ( currentProfile() ? '' : messages.profil ),
			nom: () => checkName( 'nom' ),
			prenom: () => checkName( 'prenom' ),
			email: () => {
				const value = control( 'email' ).value.trim();

				if ( ! value ) {
					return messages.email;
				}

				return emailPattern.test( value ) ? '' : messages.email_invalide;
			},
			telephone: () => {
				const value = control( 'telephone' ).value.trim();

				return ! value || ( phonePattern.test( value ) && value.replace( /\D/g, '' ).length >= 6 ) ? '' : messages.telephone;
			},
			objet: () => ( subject.value ? '' : messages.objet ),
			reservation: () => {
				const input = control( 'reservation' );

				return input.disabled || length( input.value ) <= config.reservationMax ? '' : messages.reservation;
			},
			message: () => {
				const count = length( control( 'message' ).value );

				if ( ! count ) {
					return messages.message;
				}

				if ( count < config.messageMin ) {
					return messages.message_court;
				}

				return count > config.messageMax ? messages.message_long : '';
			},
			fichier: checkFile,
			rgpd: () => ( control( 'rgpd' ).checked ? '' : messages.rgpd ),
		};

		/* Compteur de caractères du message (même décompte que la vérification). */

		const counter = form.querySelector( '[data-counter]' );

		if ( counter ) {
			const counterValue = counter.querySelector( '[data-counter-value]' );
			const counterStatus = counter.querySelector( '[data-counter-status]' );
			const counterLocale = document.documentElement.lang || 'fr-FR';
			let counterTimer = 0;

			const updateCounter = () => {
				const count = length( control( 'message' ).value );
				const left = config.messageMax - count;

				counterValue.textContent = count.toLocaleString( counterLocale );
				counter.classList.toggle( 'is-near', left <= 500 );

				// Près de la limite seulement, une fois la saisie arrêtée : pas d'annonce à chaque touche.
				window.clearTimeout( counterTimer );
				counterTimer = window.setTimeout( () => {
					if ( left > 500 ) {
						counterStatus.textContent = '';
					} else if ( left <= 0 ) {
						counterStatus.textContent = config.counterFull;
					} else {
						counterStatus.textContent = 1 === left ? config.counterOne : config.counterLeft.replace( '%s', left.toLocaleString( counterLocale ) );
					}
				}, 800 );
			};

			counter.hidden = false;
			control( 'message' ).addEventListener( 'input', updateCounter );
			updateCounter();
		}

		/**
		 * Affiche (ou retire) le message d'erreur d'un champ et met à jour son état ARIA.
		 *
		 * @param {string} name    Clé du champ.
		 * @param {string} message Message, vide si le champ est valable.
		 */
		const showError = ( name, message ) => {
			const wrapper = form.querySelector( '[data-field="' + name + '"]' );
			const error = document.getElementById( 'icademie-contact-' + name + '-erreur' );

			if ( ! wrapper || ! error ) {
				return;
			}

			error.querySelector( 'span' ).textContent = message;
			error.hidden = ! message;

			wrapper.querySelectorAll( 'input, select, textarea' ).forEach( ( field ) => {
				const ids = ( field.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( ( id ) => id && id !== error.id );

				if ( message ) {
					field.setAttribute( 'aria-invalid', 'true' );
					ids.push( error.id );
				} else {
					field.removeAttribute( 'aria-invalid' );
				}

				if ( ids.length ) {
					field.setAttribute( 'aria-describedby', ids.join( ' ' ) );
				} else {
					field.removeAttribute( 'aria-describedby' );
				}
			} );
		};

		const validate = () => {
			const errors = {};

			Object.keys( rules ).forEach( ( name ) => {
				const message = rules[ name ]();

				showError( name, message );

				if ( message ) {
					errors[ name ] = message;
				}
			} );

			return errors;
		};

		/**
		 * Résumé des erreurs en haut du formulaire, avec un lien vers chaque champ.
		 *
		 * @param {Object} errors Clé du champ => message.
		 */
		const renderSummary = ( errors ) => {
			const entries = Object.entries( errors );

			if ( ! summary ) {
				return;
			}

			summary.hidden = ! entries.length;

			if ( ! entries.length ) {
				return;
			}

			summary.querySelector( '[data-summary-title]' ).textContent = entries.length > 1 ? config.summaryMany.replace( '%d', entries.length ) : config.summaryOne;
			summary.querySelector( 'ul' ).replaceChildren( ...entries.map( ( [ name, message ] ) => {
				const item = document.createElement( 'li' );
				const link = document.createElement( 'a' );

				link.href = '#' + targetId( name );
				link.textContent = message;
				item.append( link );

				return item;
			} ) );
		};

		const targetId = ( name ) => {
			if ( 'profil' === name ) {
				return ( profiles.find( ( radio ) => radio.checked ) || profiles[ 0 ] ).id;
			}

			return 'icademie-contact-' + name;
		};

		// Lien du résumé : place le focus sur le champ, en gardant son libellé visible.
		if ( summary ) {
			summary.addEventListener( 'click', ( event ) => {
				const link = event.target.closest( 'a[href^="#"]' );
				const target = link && document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );

				if ( ! target ) {
					return;
				}

				event.preventDefault();
				( target.closest( '[data-field]' ) || target ).scrollIntoView( { block: 'center' } );
				target.focus( { preventScroll: true } );
			} );
		}

		form.noValidate = true;

		form.addEventListener( 'submit', ( event ) => {
			attempted = true;

			const errors = validate();

			if ( Object.keys( errors ).length ) {
				event.preventDefault();
				renderSummary( errors );

				if ( summary ) {
					summary.focus();
				}

				return;
			}

			renderSummary( {} );

			// Évite un double envoi pendant le téléversement de la pièce jointe.
			submitButton.disabled = true;
			submitButton.textContent = config.sending;
		} );

		// Retour sur la page avec le bouton « Précédent » : le bouton d'envoi redevient utilisable.
		window.addEventListener( 'pageshow', ( event ) => {
			if ( event.persisted ) {
				submitButton.disabled = false;
				submitButton.textContent = submitLabel;
			}
		} );

		// Pendant la saisie : une erreur affichée disparaît dès que le champ est corrigé.
		form.addEventListener( 'input', ( event ) => {
			const wrapper = event.target.closest( '[data-field]' );
			const name = wrapper && wrapper.dataset.field;

			if ( name && rules[ name ] && event.target.getAttribute( 'aria-invalid' ) && ! rules[ name ]() ) {
				showError( name, '' );
			}
		} );

		// En quittant un champ, après une première tentative d'envoi : le champ est vérifié.
		form.addEventListener( 'change', ( event ) => {
			const wrapper = event.target.closest( '[data-field]' );
			const name = wrapper && wrapper.dataset.field;

			if ( name && rules[ name ] && ( attempted || 'fichier' === name ) ) {
				showError( name, rules[ name ]() );
			}
		} );

		/* Pièce jointe : nom et taille du fichier choisi, bouton pour le retirer. */

		if ( fileInput ) {
			const chosen = form.querySelector( '[data-file-chosen]' );
			const fileLabel = form.querySelector( '[data-file-label]' );

			const formatSize = ( bytes ) => {
				const units = config.units || { kb: 'Ko', mb: 'Mo' };

				if ( bytes < 1024 * 1024 ) {
					return Math.max( 1, Math.round( bytes / 1024 ) ) + ' ' + units.kb;
				}

				return ( bytes / ( 1024 * 1024 ) ).toLocaleString( document.documentElement.lang || undefined, { maximumFractionDigits: 1 } ) + ' ' + units.mb;
			};

			const syncFile = () => {
				const file = fileInput.files && fileInput.files[ 0 ];

				chosen.hidden = ! file;
				fileLabel.textContent = file ? config.changeFile : config.addFile;

				if ( file ) {
					chosen.querySelector( '[data-file-name]' ).textContent = file.name + ' · ' + formatSize( file.size );
				}
			};

			fileInput.addEventListener( 'change', syncFile );

			chosen.querySelector( '[data-file-remove]' ).addEventListener( 'click', () => {
				fileInput.value = '';
				syncFile();
				showError( 'fichier', '' );
				fileInput.focus();
			} );

			syncFile();
		}
	}

	/**
	 * Plan d'accès : la carte OpenStreetMap ne se charge (et ne contacte OpenStreetMap) qu'au clic.
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

			map.querySelector( '.icademie-contact__map-placeholder' ).hidden = true;
			map.append( iframe );
			iframe.focus();
		} );
	}

	/**
	 * Bandeau d'assistance : ombre portée seulement quand il reste collé en bas de l'écran.
	 *
	 * Collé à 16 px du bas, le bandeau dépasse d'un pixel d'une zone d'observation réduite de 17 px :
	 * il n'y est donc pas entièrement visible, contrairement à sa place normale en fin de page.
	 *
	 * @param {HTMLElement|null} banner Bandeau.
	 */
	function initAssistance( banner ) {
		if ( ! banner || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		const observer = new IntersectionObserver( ( [ entry ] ) => {
			const stuck = entry.intersectionRatio < 1 && !! entry.rootBounds && entry.boundingClientRect.bottom > entry.rootBounds.bottom;

			banner.classList.toggle( 'is-stuck', stuck );
		}, { rootMargin: '0px 0px -17px 0px', threshold: [ 1 ] } );

		observer.observe( banner );
	}
}() );
