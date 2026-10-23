/**
 * Ashbi Testimonials — frontend enhancements: slider buttons, Read more, Load more.
 * Every layout is readable without this script.
 */
( function () {
	'use strict';

	const cfg = window.abtFrontend || { i18n: {} };
	const t = ( key, fallback ) => ( cfg.i18n && cfg.i18n[ key ] ) || fallback;

	function initSlider( root ) {
		const track = root.querySelector( '.abt-slider__track' );
		const prev = root.querySelector( '.abt-slider__prev' );
		const next = root.querySelector( '.abt-slider__next' );
		const nav = root.querySelector( '.abt-slider__nav' );
		if ( ! track || ! prev || ! next || root.dataset.abtReady ) {
			return;
		}
		root.dataset.abtReady = '1';

		const rtl = 'rtl' === getComputedStyle( track ).direction;
		const step = () => {
			const slide = track.querySelector( '.abt-slider__slide' );
			const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
			return slide ? slide.getBoundingClientRect().width + gap : track.clientWidth;
		};
		const update = () => {
			const max = track.scrollWidth - track.clientWidth - 2;
			const pos = Math.abs( track.scrollLeft );
			prev.disabled = pos <= 2;
			next.disabled = pos >= max;
			nav.hidden = max <= 0;
		};
		const go = ( dir ) => track.scrollBy( { left: dir * step() * ( rtl ? -1 : 1 ) } );

		prev.addEventListener( 'click', () => go( -1 ) );
		next.addEventListener( 'click', () => go( 1 ) );
		track.addEventListener( 'keydown', ( e ) => {
			if ( 'ArrowRight' === e.key || 'ArrowLeft' === e.key ) {
				e.preventDefault();
				go( ( 'ArrowRight' === e.key ) !== rtl ? 1 : -1 );
			}
		} );
		track.addEventListener( 'scroll', update, { passive: true } );
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( update ).observe( track );
		}
		update();
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
		const state = JSON.parse( button.dataset.abtMore || '{}' );
		const items = button.closest( '.abt' ).querySelector( '.abt__items' );
		const label = button.textContent;
		button.disabled = true;
		button.textContent = t( 'loading', 'Loading…' );

		const body = new FormData();
		body.append( 'action', 'abt_load_more' );
		body.append( 'atts', JSON.stringify( state.atts || {} ) );
		body.append( 'page', state.page || 2 );

		try {
			const res = await fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			const json = await res.json();
			if ( ! json.success ) {
				throw new Error();
			}
			const first = items.children.length;
			items.insertAdjacentHTML( 'beforeend', json.data.html );
			// Move focus to the first new card so keyboard users keep their place.
			const added = items.children[ first ];
			if ( added ) {
				added.setAttribute( 'tabindex', '-1' );
				added.focus( { preventScroll: false } );
			}
			if ( json.data.hasMore ) {
				state.page = json.data.page;
				button.dataset.abtMore = JSON.stringify( state );
				button.disabled = false;
				button.textContent = label;
			} else {
				button.parentElement.remove();
			}
		} catch ( e ) {
			button.disabled = false;
			button.textContent = label;
			const note = document.createElement( 'p' );
			note.setAttribute( 'role', 'alert' );
			note.textContent = t( 'error', 'Could not load more reviews. Try again.' );
			button.after( note );
			setTimeout( () => note.remove(), 4000 );
		}
	}

	function init( root = document ) {
		root.querySelectorAll( '[data-abt-slider]' ).forEach( initSlider );
	}

	// Delegated so cards added later (Load more, admin preview) work too.
	document.addEventListener( 'click', ( e ) => {
		const more = e.target.closest( '.abt-card__more' );
		if ( more ) {
			toggleMore( more );
			return;
		}
		const load = e.target.closest( '.abt-load-more' );
		if ( load && ! load.disabled ) {
			loadMore( load );
		}
	} );

	window.abtInitFrontend = init;
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => init() );
	} else {
		init();
	}
} )();
