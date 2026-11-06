/**
 * Ashbi Testimonials — frontend enhancements: slider buttons, Read more, Load more.
 * Every layout is readable without this script.
 */
( function () {
	'use strict';

	const cfg = window.abtFrontend || { i18n: {} };
	const t = ( key, fallback ) => ( cfg.i18n && cfg.i18n[ key ] ) || fallback;
	let quoteId = 0;

	function initSlider( root ) {
		const track = root.querySelector( '.abt-slider__track' );
		const prev = root.querySelector( '.abt-slider__prev' );
		const next = root.querySelector( '.abt-slider__next' );
		const nav = root.querySelector( '.abt-slider__nav' );
		if ( ! track || ! prev || ! next || ! nav || root.dataset.abtReady ) {
			return;
		}
		root.dataset.abtReady = '1';

		const rtl = () => 'rtl' === getComputedStyle( track ).direction;
		const step = () => {
			const slide = track.querySelector( '.abt-slider__slide' );
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
		const quote = button.closest( '.abt-card__quote' );
		const excerpt = quote?.querySelector( '.abt-card__excerpt' );
		const full = quote?.querySelector( '.abt-card__full' );
		if ( ! excerpt || ! full || button.hasAttribute( 'aria-expanded' ) ) {
			return;
		}

		if ( ! full.id ) {
			do {
				quoteId += 1;
			} while ( document.getElementById( 'abt-quote-' + quoteId ) );
			full.id = 'abt-quote-' + quoteId;
		}
		button.setAttribute( 'aria-controls', full.id );
		button.setAttribute( 'aria-expanded', 'false' );
		excerpt.hidden = false;
		full.hidden = true;
		button.hidden = false;
	}

	function toggleMore( button ) {
		const quote = button.closest( '.abt-card__quote' );
		const excerpt = quote.querySelector( '.abt-card__excerpt' );
		const full = quote.querySelector( '.abt-card__full' );
		const open = 'true' !== button.getAttribute( 'aria-expanded' );
		excerpt.hidden = open;
		full.hidden = ! open;
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		button.textContent = open ? t( 'less', 'Show less' ) : t( 'more', 'Read more' );
	}

	async function loadMore( button ) {
		const items = button.closest( '.abt' ).querySelector( '.abt__items' );
		const controls = button.parentElement;
		const label = button.textContent;
		const hadFocus = document.activeElement === button;
		controls.querySelector( '.abt-load-more__notice' )?.remove();
		button.setAttribute( 'aria-disabled', 'true' );
		button.textContent = t( 'loading', 'Loading…' );
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 12000 );

		try {
			const state = JSON.parse( button.dataset.abtMore );
			const body = new FormData();
			body.append( 'action', 'abt_load_more' );
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
				button.dataset.abtMore = JSON.stringify( state );
			} else {
				button.remove();
				if ( ! added ) {
					const note = document.createElement( 'p' );
					note.className = 'abt-load-more__notice';
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
			note.className = 'abt-load-more__notice';
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
		const wrapper = form.closest( '.abt-form-wrap' );
		let note = wrapper.querySelector( '.abt-form__notice' );
		if ( ! note ) { note = document.createElement( 'p' ); wrapper.prepend( note ); }
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 15000 );
		form.setAttribute( 'aria-busy', 'true' ); button.disabled = true;
		button.textContent = t( 'loading', 'Loading…' );
		let status = 'error';
		try {
			const body = new FormData( form );
			if ( Number( form.dataset && form.dataset.abtProduct ) > 0 ) {
				const proof = new FormData(); proof.append( 'action', 'abt_purchase_nonce' ); proof.append( 'product_id', form.dataset.abtProduct );
				const proofController = new AbortController();
				const proofTimeout = setTimeout( () => proofController.abort(), 3000 );
				try {
					const context = await fetch( cfg.ajaxUrl, { method: 'POST', body: proof, credentials: 'same-origin', signal: proofController.signal } );
					const result = await context.json();
					if ( result.success && result.data && result.data.nonce ) { body.append( 'abt_purchase_nonce', result.data.nonce ); }
				} catch ( error ) { /* Purchase proof is optional; the review still submits. */ } finally { clearTimeout( proofTimeout ); }
			}
			const response = await fetch( form.getAttribute( 'action' ), { method: 'POST', body, credentials: 'same-origin', signal: controller.signal } );
			if ( response.ok ) { status = new URL( response.url ).searchParams.get( 'abt_status' ) || 'error'; }
		} catch ( error ) { status = 'error'; }
		finally { clearTimeout( timeout ); form.removeAttribute( 'aria-busy' ); button.disabled = false; button.textContent = label; }
		const messages = cfg.form || {};
		const success = status === 'thanks';
		note.className = 'abt-form__notice abt-form__notice--' + ( success ? 'ok' : 'error' );
		note.setAttribute( 'role', success ? 'status' : 'alert' );
		note.setAttribute( 'tabindex', '-1' );
		note.textContent = messages[ status ] || messages.error || 'Your review could not be saved. Please try again.';
		if ( success ) { form.reset(); }
		note.focus();
	}

	document.addEventListener( 'submit', ( event ) => {
		if ( event.target.matches( '.abt-form' ) ) { event.preventDefault(); submitReview( event.target ); }
	} );

	function init( root = document ) {
		root.querySelectorAll( '[data-abt-slider]' ).forEach( initSlider );
		root.querySelectorAll( '.abt-card__more' ).forEach( initQuote );
		if ( cfg.ajaxUrl ) {
			root.querySelectorAll( '.abt__more' ).forEach( ( controls ) => { controls.hidden = false; } );
		}
	}

	// Delegated so cards added later (Load more, admin preview) work too.
	document.addEventListener( 'click', ( e ) => {
		const more = e.target.closest( '.abt-card__more' );
		if ( more ) {
			toggleMore( more );
			return;
		}
		const load = e.target.closest( '.abt-load-more' );
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
					if ( node.matches( '[data-abt-slider], .abt-card__more, .abt__more' ) ) {
						init( node.parentElement || node );
					} else if ( node.querySelector( '[data-abt-slider], .abt-card__more, .abt__more' ) ) {
						init( node );
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	window.abtInitFrontend = init;
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
