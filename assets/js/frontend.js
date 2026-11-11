/**
 * Ashbi Testimonials — frontend enhancements: slider buttons, Read more, Load more.
 * Every layout is readable without this script.
 */
( function () {
	'use strict';

	const cfg = window.ashbteFrontend || { i18n: {} };

	// Cached pages can hold an expired nonce, so fetch a current one once per page view.
	// The request is bounded so a stalled refresh never leaves the form or Load more stuck.
	let noncePromise = null;
	function freshNonce() {
		if ( ! noncePromise ) {
			const body = new FormData();
			body.append( 'action', 'ashbte_public_nonce' );
			const controller = new AbortController();
			const timeout = setTimeout( () => controller.abort(), 5000 );
			noncePromise = fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal } )
				.then( ( res ) => res.json() )
				.then( ( json ) => {
					if ( json && json.success && json.data && json.data.nonce ) { return json.data.nonce; }
					throw new Error( 'nonce' );
				} )
				.catch( () => {
					noncePromise = null; // Let the next interaction try again.
					return cfg.nonce || '';
				} )
				.finally( () => clearTimeout( timeout ) );
		}
		return noncePromise;
	}
	const t = ( key, fallback ) => ( cfg.i18n && cfg.i18n[ key ] ) || fallback;
	let quoteId = 0;

	function initSlider( root ) {
		const track = root.querySelector( '.ashbte-slider__track' );
		const prev = root.querySelector( '.ashbte-slider__prev' );
		const next = root.querySelector( '.ashbte-slider__next' );
		const nav = root.querySelector( '.ashbte-slider__nav' );
		if ( ! track || ! prev || ! next || ! nav || root.dataset.ashbteReady ) {
			return;
		}
		root.dataset.ashbteReady = '1';

		const rtl = () => 'rtl' === getComputedStyle( track ).direction;
		const step = () => {
			const slide = track.querySelector( '.ashbte-slider__slide' );
			const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
			return slide ? slide.getBoundingClientRect().width + gap : track.clientWidth;
		};
		const update = () => {
			const max = track.scrollWidth - track.clientWidth - 2;
			const pos = Math.abs( track.scrollLeft );
			prev.setAttribute( 'aria-disabled', String( pos <= 2 ) );
			next.setAttribute( 'aria-disabled', String( pos >= max ) );
			nav.hidden = max <= 0;
		};
		const go = ( dir ) => track.scrollBy( { left: dir * step() * ( rtl() ? -1 : 1 ) } );

		prev.addEventListener( 'click', () => {
			if ( 'true' !== prev.getAttribute( 'aria-disabled' ) ) {
				go( -1 );
			}
		} );
		next.addEventListener( 'click', () => {
			if ( 'true' !== next.getAttribute( 'aria-disabled' ) ) {
				go( 1 );
			}
		} );
		track.addEventListener( 'keydown', ( e ) => {
			if ( e.target === track && ! nav.hidden && ! e.altKey && ! e.ctrlKey && ! e.metaKey && ! e.shiftKey && ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) ) {
				e.preventDefault();
				go( ( 'ArrowRight' === e.key ) !== rtl() ? 1 : -1 );
			}
		} );
		track.addEventListener( 'scroll', update, { passive: true } );
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( update ).observe( track );
		}
		update();
	}

	function initQuote( button ) {
		const quote = button.closest( '.ashbte-card__quote' );
		const excerpt = quote?.querySelector( '.ashbte-card__excerpt' );
		const full = quote?.querySelector( '.ashbte-card__full' );
		if ( ! excerpt || ! full || button.hasAttribute( 'aria-expanded' ) ) {
			return;
		}

		if ( ! full.id ) {
			do {
				quoteId += 1;
			} while ( document.getElementById( 'ashbte-quote-' + quoteId ) );
			full.id = 'ashbte-quote-' + quoteId;
		}
		button.setAttribute( 'aria-controls', full.id );
		button.setAttribute( 'aria-expanded', 'false' );
		excerpt.hidden = false;
		full.hidden = true;
		button.hidden = false;
	}

	function toggleMore( button ) {
		const quote = button.closest( '.ashbte-card__quote' );
		const excerpt = quote.querySelector( '.ashbte-card__excerpt' );
		const full = quote.querySelector( '.ashbte-card__full' );
		const open = 'true' !== button.getAttribute( 'aria-expanded' );
		excerpt.hidden = open;
		full.hidden = ! open;
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		button.textContent = open ? t( 'less', 'Show less' ) : t( 'more', 'Read more' );
	}

	async function loadMore( button ) {
		const items = button.closest( '.ashbte' ).querySelector( '.ashbte__items' );
		const controls = button.parentElement;
		const label = button.textContent;
		const hadFocus = document.activeElement === button;
		controls.querySelector( '.ashbte-load-more__notice' )?.remove();
		button.setAttribute( 'aria-disabled', 'true' );
		button.textContent = t( 'loading', 'Loading…' );
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 12000 );

		try {
			const state = JSON.parse( button.dataset.ashbteMore );
			const body = new FormData();
			body.append( 'action', 'ashbte_load_more' );
			body.append( 'nonce', await freshNonce() );
			body.append( 'atts', JSON.stringify( state.atts ) );
			body.append( 'page', state.page );
			const res = await fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal } );
			const json = await res.json();
			if ( ! res.ok || ! json.success || 'string' !== typeof json.data?.html ) {
				throw new Error();
			}
			const first = items.children.length;
			items.insertAdjacentHTML( 'beforeend', json.data.html );
			Array.from( items.children ).slice( first ).forEach( init );
			// Keep keyboard users at the first new card unless they moved away while loading.
			const added = items.children[ first ];
			if ( added && hadFocus && document.activeElement === button ) {
				added.setAttribute( 'tabindex', '-1' );
				added.focus();
			}
			if ( json.data.hasMore ) {
				state.page = json.data.page;
				button.dataset.ashbteMore = JSON.stringify( state );
			} else {
				button.remove();
				if ( ! added ) {
					const note = document.createElement( 'p' );
					note.className = 'ashbte-load-more__notice';
					note.setAttribute( 'role', 'status' );
					note.textContent = t( 'end', 'All available reviews are shown.' );
					controls.append( note );
					if ( hadFocus && document.activeElement === document.body ) {
						note.tabIndex = -1;
						note.focus();
					}
				}
			}
		} catch ( e ) {
			const note = document.createElement( 'p' );
			note.className = 'ashbte-load-more__notice';
			note.setAttribute( 'role', 'alert' );
			note.textContent = t( 'error', 'Could not load more reviews. Try again.' );
			button.after( note );
		} finally {
			clearTimeout( timeout );
			button.removeAttribute( 'aria-disabled' );
			button.textContent = label;
		}
	}

	// Keep review text in the form on errors; no browser storage or personal data in URLs.
	async function submitReview( form ) {
		if ( form.getAttribute( 'aria-busy' ) === 'true' ) { return; }
		const button = form.querySelector( '[type="submit"]' );
		const label = button.textContent;
		const wrapper = form.closest( '.ashbte-form-wrap' );
		const previousField = form.querySelector( '[aria-errormessage="' + wrapper.id + '-notice"]' );
		if ( previousField ) {
			previousField.removeAttribute( 'aria-invalid' );
			previousField.removeAttribute( 'aria-errormessage' );
		}
		let note = wrapper.querySelector( '.ashbte-form__notice' );
		if ( ! note ) { note = document.createElement( 'p' ); wrapper.prepend( note ); }
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 15000 );
		form.setAttribute( 'aria-busy', 'true' ); button.disabled = true;
		button.textContent = t( 'loading', 'Loading…' );
		let status = 'error';
		let invalidField = '';
		try {
			const body = new FormData( form );
			const nonce = await freshNonce();
			if ( nonce ) { body.set( 'ashbte_nonce', nonce ); }
			if ( Number( form.dataset && form.dataset.ashbteProduct ) > 0 ) {
				const proof = new FormData(); proof.append( 'action', 'ashbte_purchase_nonce' ); proof.append( 'nonce', nonce ); proof.append( 'product_id', form.dataset.ashbteProduct );
				const proofController = new AbortController();
				const proofTimeout = setTimeout( () => proofController.abort(), 3000 );
				try {
					const context = await fetch( cfg.ajaxUrl, { method: 'POST', body: proof, credentials: 'same-origin', signal: proofController.signal } );
					const result = await context.json();
					if ( result.success && result.data && result.data.nonce ) { body.append( 'ashbte_purchase_nonce', result.data.nonce ); }
				} catch ( error ) { /* Purchase proof is optional; the review still submits. */ } finally { clearTimeout( proofTimeout ); }
			}
			const response = await fetch( form.getAttribute( 'action' ), { method: 'POST', body, credentials: 'same-origin', signal: controller.signal } );
			if ( response.ok ) {
				const params = new URL( response.url ).searchParams;
				status = params.get( 'ashbte_status' ) || 'error';
				if ( status === 'custom_invalid' || status === 'custom_url' ) {
					invalidField = params.get( 'ashbte_field' ) || '';
				}
			}
		} catch ( error ) { status = 'error'; }
		finally { clearTimeout( timeout ); form.removeAttribute( 'aria-busy' ); button.disabled = false; button.textContent = label; }
		const messages = cfg.form || {};
		const success = status === 'thanks';
		note.className = 'ashbte-form__notice ashbte-form__notice--' + ( success ? 'ok' : 'error' );
		note.setAttribute( 'role', success ? 'status' : 'alert' );
		note.setAttribute( 'tabindex', '-1' );
		note.textContent = messages[ status ] || messages.error || 'Your review could not be saved. Please try again.';
		if ( success ) { form.reset(); }
		const field = invalidField && form.elements.namedItem( 'ashbte_custom[' + invalidField + ']' );
		if ( field ) {
			note.id = wrapper.id + '-notice';
			field.setAttribute( 'aria-invalid', 'true' );
			field.setAttribute( 'aria-errormessage', note.id );
			field.addEventListener( 'input', () => {
				field.removeAttribute( 'aria-invalid' );
				field.removeAttribute( 'aria-errormessage' );
			}, { once: true } );
			field.focus();
		} else {
			note.focus();
		}
	}

	document.addEventListener( 'submit', ( event ) => {
		if ( event.target.matches( '.ashbte-form' ) ) { event.preventDefault(); submitReview( event.target ); }
	} );

	function init( root = document ) {
		root.querySelectorAll( '[data-ashbte-slider]' ).forEach( initSlider );
		root.querySelectorAll( '.ashbte-card__more' ).forEach( initQuote );
		if ( cfg.ajaxUrl ) {
			root.querySelectorAll( '.ashbte__more' ).forEach( ( controls ) => { controls.hidden = false; } );
		}
	}

	// Delegated so cards added later (Load more, admin preview) work too.
	document.addEventListener( 'click', ( e ) => {
		const more = e.target.closest( '.ashbte-card__more' );
		if ( more ) {
			toggleMore( more );
			return;
		}
		const load = e.target.closest( '.ashbte-load-more' );
		if ( load && 'true' !== load.getAttribute( 'aria-disabled' ) ) {
			loadMore( load );
		}
	} );

	function start() {
		init();
		if ( ! document.body || 'undefined' === typeof MutationObserver ) { return; }
		const observer = new MutationObserver( ( records ) => {
			for ( const record of records ) {
				for ( const node of record.addedNodes ) {
					if ( 1 !== node.nodeType ) { continue; }
					if ( node.matches( '[data-ashbte-slider], .ashbte-card__more, .ashbte__more' ) ) {
						init( node.parentElement || node );
					} else if ( node.querySelector( '[data-ashbte-slider], .ashbte-card__more, .ashbte__more' ) ) {
						init( node );
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	window.ashbteInitFrontend = init;
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
