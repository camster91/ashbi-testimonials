/**
 * Ashbi Testimonials — admin editor.
 *
 * Plain JavaScript, no build step. Talks to admin-ajax with the abt_admin nonce.
 */
( function () {
	'use strict';

	const cfg = window.abtAdmin || {};
	const { __, _n, sprintf } = window.wp && window.wp.i18n ? window.wp.i18n : {
		__: ( s ) => s,
		_n: ( s, p, n ) => ( 1 === n ? s : p ),
		sprintf: ( s, ...a ) => s.replace( /%(\d+\$)?[sd]/g, () => a.shift() ),
	};

	const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
	const $$ = ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) );

	const esc = ( value ) =>
		String( value ?? '' ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	async function request( action, data = {} ) {
		const body = new FormData();
		body.append( 'action', 'abt_' + action );
		body.append( 'nonce', cfg.nonce );
		Object.entries( data ).forEach( ( [ key, value ] ) => {
			body.append( key, typeof value === 'object' && value !== null ? JSON.stringify( value ) : value );
		} );
		let json;
		try {
			const res = await fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } );
			json = await res.json();
		} catch ( e ) {
			throw new Error( __( 'The server could not be reached. Check your connection and try again.', 'ashbi-testimonials' ) );
		}
		if ( ! json || ! json.success ) {
			throw new Error( ( json && json.data && json.data.message ) || __( 'Something went wrong. Please try again.', 'ashbi-testimonials' ) );
		}
		return json.data;
	}

	function status( message, isError = false ) {
		const el = $( '#abt-status' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.classList.add( 'is-visible' );
		el.classList.toggle( 'is-error', isError );
		clearTimeout( status.timer );
		if ( ! isError ) {
			status.timer = setTimeout( () => el.classList.remove( 'is-visible' ), 4000 );
		}
	}

	function starsHtml( rating ) {
		if ( ! rating ) {
			return '<span class="abt-row__norating">' + esc( __( 'No rating', 'ashbi-testimonials' ) ) + '</span>';
		}
		/* translators: %d: rating out of 5 */
		const label = sprintf( __( 'Rated %d out of 5', 'ashbi-testimonials' ), rating );
		return '<span class="abt-mini-stars" role="img" aria-label="' + esc( label ) + '">' + '★'.repeat( rating ) + '<span>' + '★'.repeat( 5 - rating ) + '</span></span>';
	}

	function initials( name ) {
		return String( name || '?' )
			.trim()
			.split( /\s+/ )
			.slice( 0, 2 )
			.map( ( w ) => w.charAt( 0 ).toUpperCase() )
			.join( '' );
	}

	function avatarHtml( item ) {
		if ( item.avatar ) {
			return '<img class="abt-row__avatar" src="' + esc( item.avatar ) + '" alt="" />';
		}
		return '<span class="abt-row__avatar abt-row__avatar--initials" aria-hidden="true">' + esc( initials( item.author ) ) + '</span>';
	}

	async function copy( text, button ) {
		try {
			await navigator.clipboard.writeText( text );
		} catch ( e ) {
			const area = document.createElement( 'textarea' );
			area.value = text;
			document.body.appendChild( area );
			area.select();
			document.execCommand( 'copy' ); // eslint-disable-line
			area.remove();
		}
		if ( button ) {
			const old = button.textContent;
			button.textContent = __( 'Copied!', 'ashbi-testimonials' );
			setTimeout( () => ( button.textContent = old ), 1500 );
		}
	}

	function debounce( fn, wait = 300 ) {
		let t;
		return ( ...args ) => {
			clearTimeout( t );
			t = setTimeout( () => fn( ...args ), wait );
		};
	}

	/* ==================================================================
	   Shared: dialogs, copy buttons, collection dialog
	   ================================================================== */

	function openDialog( dialog ) {
		if ( dialog && ! dialog.open ) {
			dialog.showModal();
		}
	}

	document.addEventListener( 'click', ( e ) => {
		const copyBtn = e.target.closest( '[data-abt-copy]' );
		if ( copyBtn ) {
			copy( copyBtn.dataset.abtCopy, copyBtn );
			return;
		}
		const opener = e.target.closest( '[data-abt-open]' );
		if ( opener ) {
			openDialog( document.getElementById( opener.dataset.abtOpen ) );
			const menu = opener.closest( 'details' );
			if ( menu ) {
				menu.open = false;
			}
			return;
		}
		if ( e.target.closest( '[data-abt-close]' ) ) {
			e.target.closest( 'dialog' ).close();
			return;
		}
		// Clicking the backdrop closes a dialog.
		if ( e.target instanceof HTMLDialogElement ) {
			const r = e.target.getBoundingClientRect();
			if ( e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom ) {
				e.target.close();
			}
		}
	} );

	const collectionDialog = $( '#abt-collection-dialog' );
	if ( collectionDialog ) {
		const form = $( '[data-abt-collection-form]', collectionDialog );
		const title = $( '#abt-collection-dialog-title' );
		const definitions = $( '[data-abt-field-definitions]', form );
		function addField( field = {} ) {
			if ( definitions.children.length >= 10 ) { return; }
			const row = document.createElement( 'fieldset' );
			row.className = 'abt-custom-definition';
			row.dataset.key = field.key || 'field_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 6 );
			row.innerHTML = '<legend>' + esc( __( 'Review field', 'ashbi-testimonials' ) ) + '</legend>' +
				'<label>' + esc( __( 'Label', 'ashbi-testimonials' ) ) + '<input data-field-label required maxlength="80" value="' + esc( field.label || '' ) + '" /></label>' +
				'<label>' + esc( __( 'Type', 'ashbi-testimonials' ) ) + '<select data-field-type>' +
				Object.entries( { text: __( 'Text', 'ashbi-testimonials' ), textarea: __( 'Long text', 'ashbi-testimonials' ), select: __( 'Choices', 'ashbi-testimonials' ), checkbox: __( 'Checkbox', 'ashbi-testimonials' ), url: __( 'Website URL', 'ashbi-testimonials' ) } ).map( ( [ value, label ] ) => '<option value="' + value + '">' + esc( label ) + '</option>' ).join( '' ) + '</select></label>' +
				'<label data-field-choices>' + esc( __( 'Choices, one per line (maximum 20)', 'ashbi-testimonials' ) ) + '<textarea data-field-options rows="3">' + esc( ( field.options || [] ).join( '\n' ) ) + '</textarea></label>' +
				'<label><input type="checkbox" data-field-required /> ' + esc( __( 'Required', 'ashbi-testimonials' ) ) + '</label> ' +
				'<label><input type="checkbox" data-field-public /> ' + esc( __( 'Publish answers with approved reviews', 'ashbi-testimonials' ) ) + '</label> ' +
				'<button type="button" class="button-link" data-field-remove>' + esc( __( 'Remove field', 'ashbi-testimonials' ) ) + '</button>';
			$( '[data-field-type]', row ).value = field.type || 'text';
			$( '[data-field-required]', row ).checked = !! field.required;
			$( '[data-field-public]', row ).checked = !! field.public;
			const choices = () => { $( '[data-field-choices]', row ).hidden = $( '[data-field-type]', row ).value !== 'select'; };
			$( '[data-field-type]', row ).addEventListener( 'change', choices );
			choices();
			$( '[data-field-remove]', row ).addEventListener( 'click', () => { row.remove(); $( '[data-abt-add-field]', form ).focus(); } );
			definitions.append( row );
			return row;
		}
		$( '[data-abt-add-field]', form ).addEventListener( 'click', () => { const row = addField(); if ( row ) { $( '[data-field-label]', row ).focus(); } } );


		const fill = ( c ) => {
			definitions.replaceChildren();
			Object.values( c.fields || {} ).forEach( addField );
			const editing = !! c.id;
			form.elements.id.value = c.id || 0;
			form.elements.name.value = c.name || '';
			form.elements.slug.value = c.slug || '';
			form.elements.layout.value = c.layout || 'grid';
			form.elements.accent_default.checked = ! c.accent;
			if ( c.accent ) {
				form.elements.accent.value = c.accent;
			}
			const radio = $( 'input[name="type"][value="' + ( c.type || 'general' ) + '"]', form );
			if ( radio ) {
				radio.checked = true;
			}
			title.textContent = editing ? title.dataset.edit : title.dataset.new;
			$$( '[data-abt-edit-only]', form ).forEach( ( el ) => ( el.hidden = ! editing ) );
			openDialog( collectionDialog );
			form.elements.name.focus();
		};

		document.addEventListener( 'click', ( e ) => {
			const create = e.target.closest( '[data-abt-new-collection]' );
			if ( create ) {
				const type = create.dataset.abtNewCollection || 'general';
				const layouts = { store: 'slider', product: 'list', software: 'grid', service: 'masonry', general: 'grid' };
				fill( { type, layout: layouts[ type ] || 'grid' } );
			}
			const edit = e.target.closest( '[data-abt-edit-collection]' );
			if ( edit ) {
				fill( JSON.parse( edit.dataset.abtEditCollection ) );
			}
		} );

		form.elements.accent.addEventListener( 'input', () => ( form.elements.accent_default.checked = false ) );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const els = form.elements;
			if ( ! els.name.value.trim() ) {
				els.name.focus();
				return;
			}
			const submit = $( '[type="submit"]', form );
			submit.disabled = true;
			try {
				const saved = await request( 'collection_save', {
					collection: {
						id: els.id.value,
						name: els.name.value,
						slug: els.slug.value,
						type: ( $( 'input[name="type"]:checked', form ) || {} ).value,
						layout: els.layout.value,
						accent: els.accent_default.checked ? '' : els.accent.value,
						fields: Array.from( definitions.children ).map( ( row ) => ( {
							key: row.dataset.key, label: $( '[data-field-label]', row ).value,
							type: $( '[data-field-type]', row ).value, required: $( '[data-field-required]', row ).checked,
							public: $( '[data-field-public]', row ).checked,
							options: $( '[data-field-type]', row ).value === 'select' ? $( '[data-field-options]', row ).value.split( /\r?\n/ ).map( ( x ) => x.trim() ).filter( Boolean ) : [],
						} ) ),
					},
				} );
				collectionDialog.close();
				window.location.href = saved.url;
			} catch ( err ) {
				submit.disabled = false;
				window.alert( err.message ); // eslint-disable-line no-alert
			}
		} );

		$( '[data-abt-delete-collection]', form ).addEventListener( 'click', async () => {
			/* eslint-disable no-alert */
			if ( ! window.confirm( __( 'Delete this collection? Shortcodes using it will stop showing reviews.', 'ashbi-testimonials' ) ) ) {
				return;
			}
			const trash = window.confirm( __( 'Also move its testimonials to the trash?\n\nOK = trash them. Cancel = keep them in All testimonials.', 'ashbi-testimonials' ) );
			/* eslint-enable no-alert */
			try {
				await request( 'collection_delete', { id: form.elements.id.value, trash_items: trash ? '1' : '0' } );
				window.location.href = cfg.pageUrl;
			} catch ( err ) {
				window.alert( err.message ); // eslint-disable-line no-alert
			}
		} );
	}

	const cardFilter = $( '[data-abt-filter-cards]' );
	if ( cardFilter ) {
		cardFilter.addEventListener( 'input', () => {
			const q = cardFilter.value.trim().toLowerCase();
			$$( '[data-abt-card-name]' ).forEach( ( card ) => ( card.hidden = q && ! card.dataset.abtCardName.includes( q ) ) );
		} );
	}

	/* ==================================================================
	   Collection screen
	   ================================================================== */

	const app = $( '[data-abt-app]' );
	if ( ! app ) {
		return;
	}

	const collectionId = parseInt( app.dataset.collection, 10 );
	const collectionSlug = app.dataset.slug;
	const state = {
		status: app.dataset.status || 'any',
		search: '',
		rating: 0,
		orderby: 'date',
		order: 'desc',
		product: '',
		page: 1,
		items: [],
		selected: new Set(),
	};
	const rowsEl = $( '[data-abt-rows]', app );
	const pagerEl = $( '[data-abt-pager]', app );
	const statsEl = $( '[data-abt-stats]', app );
	const bulkEl = $( '[data-abt-bulk]', app );

	const statusLabels = {
		publish: __( 'Published', 'ashbi-testimonials' ),
		pending: __( 'Pending', 'ashbi-testimonials' ),
		draft: __( 'Draft', 'ashbi-testimonials' ),
		trash: __( 'Trash', 'ashbi-testimonials' ),
	};

	let loadRequest = 0;
	async function load() {
		const requestId = ++loadRequest;
		rowsEl.setAttribute( 'aria-busy', 'true' );
		rowsEl.classList.add( 'is-loading' );
		const product = state.product.trim();
		try {
			const data = await request( 'list', {
				collection: collectionId,
				status: state.status,
				search: state.search,
				rating: state.rating,
				orderby: state.orderby,
				order: state.order,
				product_id: /^\d+$/.test( product ) ? product : '',
				subject: /^\d+$/.test( product ) ? '' : product,
				page: state.page,
				per_page: 20,
			} );
			if ( requestId !== loadRequest ) {
				return;
			}
			state.items = data.items;
			state.selected.clear();
			renderRows( data );
			renderCounts( data.counts );
			renderStats( data.stats, data.counts );
			renderPager( data );
		} catch ( err ) {
			if ( requestId !== loadRequest ) {
				return;
			}
			rowsEl.innerHTML = '<p class="abt-empty">' + esc( err.message ) + '</p>';
		}
		rowsEl.classList.remove( 'is-loading' );
		rowsEl.setAttribute( 'aria-busy', 'false' );
		updateBulk();
	}

	function renderRows( data ) {
		if ( ! data.items.length ) {
			const filtered = state.search || state.rating || state.product || 'any' !== state.status;
			if ( 'trash' === state.status && ! state.search ) {
				rowsEl.innerHTML = '<div class="abt-empty"><span class="dashicons dashicons-trash" aria-hidden="true"></span><h3>' + esc( __( 'The trash is empty', 'ashbi-testimonials' ) ) + '</h3></div>';
				return;
			}
			rowsEl.innerHTML =
				'<div class="abt-empty">' +
				'<span class="dashicons dashicons-format-quote" aria-hidden="true"></span>' +
				'<h3>' + esc( filtered ? __( 'Nothing matches these filters', 'ashbi-testimonials' ) : __( 'No testimonials yet', 'ashbi-testimonials' ) ) + '</h3>' +
				'<p>' + esc( filtered ? __( 'Try clearing the search or choosing another tab.', 'ashbi-testimonials' ) : __( 'Add your first one, import a CSV, or place a review form on your site.', 'ashbi-testimonials' ) ) + '</p>' +
				( filtered ? '' : '<p><button type="button" class="button button-primary" data-abt-add>' + esc( __( 'Add testimonial', 'ashbi-testimonials' ) ) + '</button></p>' ) +
				'</div>';
			return;
		}
		rowsEl.innerHTML = data.items
			.map( ( item ) => {
				const meta = [ item.role, item.company ].filter( Boolean ).join( ' · ' );
				const about = item.product_title || item.subject;
				const text = ( item.content || '' ).replace( /<[^>]+>/g, '' );
				return (
					'<article class="abt-row" data-id="' + item.id + '">' +
					'<label class="abt-row__check"><span class="screen-reader-text">' + esc( sprintf( __( 'Select %s', 'ashbi-testimonials' ), item.author ) ) + '</span><input type="checkbox" data-abt-select value="' + item.id + '" /></label>' +
					avatarHtml( item ) +
					'<div class="abt-row__main">' +
					'<div class="abt-row__top"><button type="button" class="abt-row__name" data-abt-edit="' + item.id + '">' + esc( item.author ) + '</button>' +
					( item.verified ? '<span class="abt-badge abt-badge--ok">' + esc( __( 'Verified', 'ashbi-testimonials' ) ) + '</span>' : '' ) +
					( item.featured ? '<span class="abt-badge abt-badge--star">' + esc( __( 'Featured', 'ashbi-testimonials' ) ) + '</span>' : '' ) +
					'</div>' +
					( meta ? '<div class="abt-row__meta">' + esc( meta ) + '</div>' : '' ) +
					'<div class="abt-row__rating">' + starsHtml( item.rating ) + ( item.headline ? ' <strong>' + esc( item.headline ) + '</strong>' : '' ) + '</div>' +
					'<p class="abt-row__text">' + esc( text.length > 220 ? text.slice( 0, 220 ) + '…' : text ) + '</p>' +
					'</div>' +
					'<div class="abt-row__side">' +
					'<span class="abt-pill abt-pill--' + esc( item.status ) + '">' + esc( statusLabels[ item.status ] || item.status ) + '</span>' +
					'<span class="abt-row__date">' + esc( item.date ) + ( item.source ? ' · ' + esc( item.source ) : '' ) + '</span>' +
					( about ? '<span class="abt-row__about" title="' + esc( about ) + '"><span class="dashicons dashicons-tag" aria-hidden="true"></span>' + esc( about ) + '</span>' : '' ) +
					'<span class="abt-row__actions">' +
					( 'trash' === item.status
						? '<button type="button" class="button button-small" data-abt-restore="' + item.id + '">' + esc( __( 'Restore', 'ashbi-testimonials' ) ) + '</button>'
						: ( 'publish' !== item.status ? '<button type="button" class="button button-small" data-abt-approve="' + item.id + '">' + esc( __( 'Approve', 'ashbi-testimonials' ) ) + '</button>' : '' ) +
						  '<button type="button" class="button button-small" data-abt-edit="' + item.id + '">' + esc( __( 'Edit', 'ashbi-testimonials' ) ) + '</button>' ) +
					'</span>' +
					'</div>' +
					'</article>'
				);
			} )
			.join( '' );
	}

	function renderCounts( counts ) {
		$$( '[data-abt-count]', app ).forEach( ( el ) => {
			const n = counts[ el.dataset.abtCount ] || 0;
			el.textContent = n.toLocaleString();
		} );
		$$( '[data-abt-tab]', app ).forEach( ( tab ) => {
			tab.setAttribute( 'aria-selected', tab.dataset.abtTab === state.status ? 'true' : 'false' );
			tab.classList.toggle( 'abt-tab--alert', 'pending' === tab.dataset.abtTab && counts.pending > 0 );
		} );
	}

	function renderStats( stats, counts ) {
		const pct = ( n ) => ( stats.rated ? Math.round( ( n / stats.rated ) * 100 ) : 0 );
		statsEl.innerHTML =
			'<div class="abt-stat"><span class="abt-stat__label">' + esc( __( 'Average rating', 'ashbi-testimonials' ) ) + '</span>' +
			'<strong class="abt-stat__value">' + ( stats.rated ? esc( Number( stats.average ).toFixed( 1 ) ) : '—' ) + '</strong>' +
			( stats.rated ? starsHtml( Math.round( stats.average ) ) : '' ) + '</div>' +
			'<div class="abt-stat"><span class="abt-stat__label">' + esc( __( 'Published', 'ashbi-testimonials' ) ) + '</span><strong class="abt-stat__value">' + esc( ( counts.publish || 0 ).toLocaleString() ) + '</strong></div>' +
			'<div class="abt-stat' + ( counts.pending ? ' abt-stat--alert' : '' ) + '"><span class="abt-stat__label">' + esc( __( 'Waiting for approval', 'ashbi-testimonials' ) ) + '</span><strong class="abt-stat__value">' + esc( ( counts.pending || 0 ).toLocaleString() ) + '</strong></div>' +
			'<div class="abt-stat abt-stat--bars"><ul class="abt-mini-bars">' +
			[ 5, 4, 3, 2, 1 ]
				.map( ( s ) => '<li><span>' + s + '★</span><span class="abt-mini-bars__bar"><span style="width:' + pct( stats.breakdown[ s ] || 0 ) + '%"></span></span><span>' + esc( ( stats.breakdown[ s ] || 0 ).toLocaleString() ) + '</span></li>' )
				.join( '' ) +
			'</ul></div>';
	}

	function renderPager( data ) {
		if ( data.pages <= 1 ) {
			pagerEl.innerHTML = data.total ? '<span class="abt-pager__total">' + esc( sprintf( _n( '%s testimonial', '%s testimonials', data.total, 'ashbi-testimonials' ), data.total.toLocaleString() ) ) + '</span>' : '';
			return;
		}
		pagerEl.innerHTML =
			'<span class="abt-pager__total">' + esc( sprintf( _n( '%s testimonial', '%s testimonials', data.total, 'ashbi-testimonials' ), data.total.toLocaleString() ) ) + '</span>' +
			'<button type="button" class="button" data-abt-page="' + ( state.page - 1 ) + '"' + ( state.page <= 1 ? ' disabled' : '' ) + '>&larr; ' + esc( __( 'Previous', 'ashbi-testimonials' ) ) + '</button>' +
			'<span class="abt-pager__pos">' + esc( sprintf( __( 'Page %1$s of %2$s', 'ashbi-testimonials' ), state.page, data.pages ) ) + '</span>' +
			'<button type="button" class="button" data-abt-page="' + ( state.page + 1 ) + '"' + ( state.page >= data.pages ? ' disabled' : '' ) + '>' + esc( __( 'Next', 'ashbi-testimonials' ) ) + ' &rarr;</button>';
	}

	function updateBulk() {
		const n = state.selected.size;
		bulkEl.hidden = ! state.items.length;
		bulkEl.classList.toggle( 'has-selection', n > 0 );
		$( '[data-abt-selected-count]', bulkEl ).textContent = n ? sprintf( _n( '%s selected', '%s selected', n, 'ashbi-testimonials' ), n ) : __( 'Select all', 'ashbi-testimonials' );
		$( '[data-abt-select-all]', bulkEl ).checked = n > 0 && n === state.items.length;
		$$( '[data-abt-bulk-action],[data-abt-bulk-delete],[data-abt-bulk-purge],[data-abt-bulk-collection]', bulkEl ).forEach( ( b ) => ( b.disabled = ! n ) );
		// The trash only offers restore and permanent deletion.
		const inTrash = 'trash' === state.status;
		$$( '.button, label', bulkEl ).forEach( ( el ) => {
			if ( ! el.matches( '.abt-inline-check' ) ) {
				el.hidden = el.hasAttribute( 'data-abt-trash-only' ) ? ! inTrash : inTrash;
			}
		} );
	}

	/* Filters */
	$$( '[data-abt-tab]', app ).forEach( ( tab ) =>
		tab.addEventListener( 'click', () => {
			state.status = tab.dataset.abtTab;
			state.page = 1;
			load();
		} )
	);
	$( '[data-abt-search]', app ).addEventListener(
		'input',
		debounce( ( e ) => {
			state.search = e.target.value;
			state.page = 1;
			load();
		} )
	);
	$( '[data-abt-product-filter]', app ).addEventListener(
		'input',
		debounce( ( e ) => {
			state.product = e.target.value;
			state.page = 1;
			load();
		} )
	);
	$( '[data-abt-rating]', app ).addEventListener( 'change', ( e ) => {
		state.rating = parseInt( e.target.value, 10 );
		state.page = 1;
		load();
	} );
	$( '[data-abt-sort]', app ).addEventListener( 'change', ( e ) => {
		[ state.orderby, state.order ] = e.target.value.split( ':' );
		state.page = 1;
		load();
	} );
	pagerEl.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-abt-page]' );
		if ( btn && ! btn.disabled ) {
			state.page = parseInt( btn.dataset.abtPage, 10 );
			load();
			app.scrollIntoView( { behavior: 'smooth' } );
		}
	} );

	/* Selection and bulk actions */
	rowsEl.addEventListener( 'change', ( e ) => {
		if ( e.target.matches( '[data-abt-select]' ) ) {
			const id = parseInt( e.target.value, 10 );
			e.target.checked ? state.selected.add( id ) : state.selected.delete( id );
			e.target.closest( '.abt-row' ).classList.toggle( 'is-selected', e.target.checked );
			updateBulk();
		}
	} );
	$( '[data-abt-select-all]', bulkEl ).addEventListener( 'change', ( e ) => {
		$$( '[data-abt-select]', rowsEl ).forEach( ( box ) => {
			box.checked = e.target.checked;
			box.closest( '.abt-row' ).classList.toggle( 'is-selected', box.checked );
			const id = parseInt( box.value, 10 );
			box.checked ? state.selected.add( id ) : state.selected.delete( id );
		} );
		updateBulk();
	} );

	async function bulk( action, value, ids = Array.from( state.selected ) ) {
		try {
			const res = await request( 'bulk', { ids, bulk: action, value } );
			status( sprintf( _n( '%s testimonial updated.', '%s testimonials updated.', res.updated, 'ashbi-testimonials' ), res.updated ) );
			load();
		} catch ( err ) {
			status( err.message, true );
		}
	}

	$$( '[data-abt-bulk-action]', bulkEl ).forEach( ( btn ) =>
		btn.addEventListener( 'click', () => {
			const [ action, value ] = btn.dataset.abtBulkAction.split( ':' );
			bulk( action, value );
		} )
	);
	const bulkCollection = $( '[data-abt-bulk-collection]', bulkEl );
	( cfg.collections || [] ).forEach( ( c ) => bulkCollection.insertAdjacentHTML( 'beforeend', '<option value="' + c.id + '">' + esc( c.name ) + '</option>' ) );
	bulkCollection.addEventListener( 'change', () => {
		if ( bulkCollection.value ) {
			bulk( 'add_collection', bulkCollection.value );
			bulkCollection.value = '';
		}
	} );
	$( '[data-abt-bulk-delete]', bulkEl ).addEventListener( 'click', async () => {
		const ids = Array.from( state.selected );
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( _n( 'Move %s testimonial to the trash?', 'Move %s testimonials to the trash?', ids.length, 'ashbi-testimonials' ), ids.length ) ) ) {
			return;
		}
		try {
			await request( 'delete', { ids } );
			status( __( 'Moved to the trash. Restore it from the Trash tab.', 'ashbi-testimonials' ) );
			load();
		} catch ( err ) {
			status( err.message, true );
		}
	} );

	$( '[data-abt-bulk-purge]', bulkEl ).addEventListener( 'click', () => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Permanently delete the selected testimonials? This cannot be undone.', 'ashbi-testimonials' ) ) ) {
			bulk( 'purge', '1' );
		}
	} );

	rowsEl.addEventListener( 'click', ( e ) => {
		const restore = e.target.closest( '[data-abt-restore]' );
		if ( restore ) {
			bulk( 'restore', '1', [ parseInt( restore.dataset.abtRestore, 10 ) ] );
			return;
		}
		const approve = e.target.closest( '[data-abt-approve]' );
		if ( approve ) {
			bulk( 'status', 'publish', [ parseInt( approve.dataset.abtApprove, 10 ) ] );
			return;
		}
		const edit = e.target.closest( '[data-abt-edit]' );
		if ( edit ) {
			const item = state.items.find( ( i ) => i.id === parseInt( edit.dataset.abtEdit, 10 ) );
			openEditor( item );
		}
	} );

	/* ==================================================================
	   Editor
	   ================================================================== */

	const editor = $( '#abt-editor' );
	const eform = $( '[data-abt-editor-form]', editor );
	const etitle = $( '#abt-editor-title' );
	const eerror = $( '[data-abt-editor-error]', editor );
	const starInput = $( '[data-abt-star-input]', editor );
	const collectionsBox = $( '[data-abt-collections]', editor );
	const avatarPreview = $( '[data-abt-avatar-preview]', editor );
	const avatarRemove = $( '[data-abt-avatar-remove]', editor );
	let avatarSrc = '';
	let customSnapshots = {};
	let customSkipped = new Set();
	const customBox = $( '[data-abt-custom-values]', editor );
	function customValues() {
		const next = JSON.parse( JSON.stringify( customSnapshots ) );
		$$( '[data-custom-key]', customBox ).forEach( ( input ) => {
			const field = next[ input.dataset.customKey ];
			if ( ! field ) { return; }
			field.value = input.type === 'checkbox' ? ( input.checked ? '1' : '0' ) : input.value;
		} );
		return next;
	}
	function renderCustom( populate = true ) {
		customSnapshots = customValues();
		const defs = {};
		$$( 'input[name="collections"]:checked', collectionsBox ).forEach( ( input ) => {
			const c = ( cfg.collections || [] ).find( ( entry ) => entry.id === Number( input.value ) );
			Object.entries( c && c.fields || {} ).forEach( ( [ key, field ] ) => { if ( ! defs[ key ] ) { defs[ key ] = field; } } );
		} );
		Object.entries( defs ).forEach( ( [ key, field ] ) => {
			if ( populate && ! customSnapshots[ key ] && ! customSkipped.has( key ) && Object.keys( customSnapshots ).length < 10 ) { customSnapshots[ key ] = { label: field.label, type: field.type, public: field.public, value: '' }; }
		} );
		customBox.innerHTML = Object.entries( customSnapshots ).map( ( [ key, field ] ) => {
			const isPublic = field.public && defs[ key ] && defs[ key ].public && defs[ key ].type === field.type;
			const label = esc( field.label ) + ' (' + esc( isPublic ? __( 'public', 'ashbi-testimonials' ) : __( 'not published', 'ashbi-testimonials' ) ) + ')';
			const attr = ' data-custom-key="' + esc( key ) + '"';
			let control;
			if ( field.type === 'textarea' ) {
				control = '<textarea' + attr + ' maxlength="2000" rows="3">' + esc( field.value ) + '</textarea>';
			} else if ( field.type === 'select' && defs[ key ] ) {
				const options = Array.from( new Set( [ '', field.value, ...defs[ key ].options ] ) );
				control = '<select' + attr + '>' + options.map( ( value ) => '<option value="' + esc( value ) + '"' + ( value === field.value ? ' selected' : '' ) + '>' + esc( value ) + '</option>' ).join( '' ) + '</select>';
			} else {
				control = '<input' + attr + ' type="' + ( field.type === 'checkbox' ? 'checkbox' : field.type === 'url' ? 'url' : 'text' ) + '" maxlength="500" value="' + esc( field.value ) + '"' + ( field.type === 'checkbox' && field.value === '1' ? ' checked' : '' ) + ' />';
			}
			return '<div class="abt-field"><label>' + label + control + '</label>' + '<button type="button" class="button-link" data-custom-remove="' + esc( key ) + '">' + esc( __( 'Remove stored answer', 'ashbi-testimonials' ) ) + '</button>' + '</div>';
		} ).join( '' );
		const missing = Object.entries( defs ).filter( ( [ key ] ) => ! customSnapshots[ key ] );
		customBox.insertAdjacentHTML( 'beforeend', '<p class="description">' + esc( sprintf( __( '%s of 10 stored answers. Remove an answer to make room for another question.', 'ashbi-testimonials' ), Object.keys( customSnapshots ).length ) ) + '</p>' );
		if ( missing.length ) {
			customBox.insertAdjacentHTML( 'beforeend', '<label>' + esc( __( 'Additional question', 'ashbi-testimonials' ) ) + '<select data-custom-choice>' + missing.map( ( [ key, field ] ) => '<option value="' + esc( key ) + '">' + esc( field.label ) + '</option>' ).join( '' ) + '</select></label><button type="button" class="button" data-custom-add' + ( Object.keys( customSnapshots ).length >= 10 ? ' disabled' : '' ) + '>' + esc( __( 'Add stored answer', 'ashbi-testimonials' ) ) + '</button>' );
			$( '[data-custom-add]', customBox ).addEventListener( 'click', () => {
				customSnapshots = customValues(); const key = $( '[data-custom-choice]', customBox ).value; const field = defs[ key ];
				if ( ! field || Object.keys( customSnapshots ).length >= 10 ) { return; }
				customSkipped.delete( key ); customSnapshots[ key ] = { label: field.label, type: field.type, public: field.public, value: '' }; customBox.replaceChildren(); renderCustom( false );
			} );
		}
	}
	customBox.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-custom-remove]' );
		if ( ! button ) { return; }
		customSnapshots = customValues(); customSkipped.add( button.dataset.customRemove ); delete customSnapshots[ button.dataset.customRemove ]; customBox.replaceChildren(); renderCustom( false );
	} );
	collectionsBox.addEventListener( 'change', renderCustom );


	collectionsBox.innerHTML = ( cfg.collections || [] ).length
		? cfg.collections.map( ( c ) => '<label class="abt-inline-check"><input type="checkbox" name="collections" value="' + c.id + '" /> ' + esc( c.name ) + '</label>' ).join( '' )
		: '<p class="description">' + esc( __( 'Create a collection on the dashboard to group testimonials.', 'ashbi-testimonials' ) ) + '</p>';

	function setRating( value ) {
		eform.elements.rating.value = value;
		$$( 'button[data-value]', starInput ).forEach( ( b ) => {
			const v = parseInt( b.dataset.value, 10 );
			b.classList.toggle( 'is-on', v > 0 && v <= value );
			if ( v > 0 ) {
				b.setAttribute( 'aria-pressed', v === value ? 'true' : 'false' );
			}
		} );
	}
	starInput.addEventListener( 'click', ( e ) => {
		const b = e.target.closest( 'button[data-value]' );
		if ( b ) {
			setRating( parseInt( b.dataset.value, 10 ) );
		}
	} );

	function setAvatar( src ) {
		avatarSrc = src || '';
		const name = eform.elements.author.value;
		avatarPreview.innerHTML = avatarSrc ? '<img src="' + esc( avatarSrc ) + '" alt="" />' : '<span>' + esc( initials( name ) ) + '</span>';
		avatarRemove.hidden = ! avatarSrc;
	}
	eform.elements.author.addEventListener( 'input', () => ! avatarSrc && setAvatar( '' ) );
	eform.elements.avatar_url.addEventListener( 'change', () => {
		eform.elements.avatar_id.value = 0;
		setAvatar( eform.elements.avatar_url.value );
	} );
	avatarRemove.addEventListener( 'click', () => {
		eform.elements.avatar_id.value = 0;
		eform.elements.avatar_url.value = '';
		setAvatar( '' );
	} );
	let mediaFrame;
	$( '[data-abt-avatar-pick]', editor ).addEventListener( 'click', () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		if ( ! mediaFrame ) {
			mediaFrame = window.wp.media( { title: __( 'Choose a photo', 'ashbi-testimonials' ), library: { type: 'image' }, multiple: false } );
			mediaFrame.on( 'select', () => {
				const att = mediaFrame.state().get( 'selection' ).first().toJSON();
				eform.elements.avatar_id.value = att.id;
				eform.elements.avatar_url.value = '';
				setAvatar( ( att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url ) || att.url );
			} );
		}
		mediaFrame.open();
	} );

	/* Product picker */
	const picker = $( '[data-abt-picker]', editor );
	const pickInput = $( '[data-abt-picker-input]', picker );
	const pickResults = $( '[data-abt-picker-results]', picker );
	const pickChip = $( '[data-abt-picker-chip]', picker );

	function setProduct( id, label ) {
		eform.elements.product_id.value = id || 0;
		$( '[data-abt-picker-label]', picker ).textContent = label || '';
		pickChip.hidden = ! id;
		pickInput.hidden = !! id;
		pickResults.hidden = true;
		pickInput.value = '';
	}
	pickInput.addEventListener(
		'input',
		debounce( async () => {
			const q = pickInput.value.trim();
			if ( q.length < 2 && ! /^\d+$/.test( q ) ) {
				pickResults.hidden = true;
				return;
			}
			try {
				const found = await request( 'search_posts', { q } );
				pickResults.innerHTML = found.length
					? found.map( ( p ) => '<li role="option" tabindex="-1" data-id="' + p.id + '" data-label="' + esc( p.title ) + '">' + esc( p.title ) + ' <small>' + esc( p.type ) + ' #' + p.id + '</small></li>' ).join( '' )
					: '<li class="is-empty">' + esc( __( 'No matches', 'ashbi-testimonials' ) ) + '</li>';
				pickResults.hidden = false;
			} catch ( err ) {
				pickResults.hidden = true;
			}
		}, 250 )
	);
	pickResults.addEventListener( 'click', ( e ) => {
		const li = e.target.closest( 'li[data-id]' );
		if ( li ) {
			setProduct( li.dataset.id, li.dataset.label );
		}
	} );
	pickInput.addEventListener( 'keydown', ( e ) => {
		if ( 'ArrowDown' === e.key && ! pickResults.hidden ) {
			e.preventDefault();
			const first = $( 'li[data-id]', pickResults );
			if ( first ) {
				first.focus();
			}
		}
	} );
	pickResults.addEventListener( 'keydown', ( e ) => {
		const li = e.target.closest( 'li' );
		if ( ! li ) {
			return;
		}
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			li.click();
		} else if ( 'ArrowDown' === e.key && li.nextElementSibling ) {
			e.preventDefault();
			li.nextElementSibling.focus();
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			( li.previousElementSibling || pickInput ).focus();
		}
	} );
	$( '[data-abt-picker-clear]', picker ).addEventListener( 'click', () => {
		setProduct( 0, '' );
		pickInput.focus();
	} );

	function today() {
		const d = new Date();
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
	}

	function openEditor( item ) {
		const editing = !! ( item && item.id );
		const data = item || {};
		const els = eform.elements;
		eform.reset();
		eerror.hidden = true;
		els.id.value = data.id || 0;
		[ 'author', 'headline', 'role', 'company', 'location', 'email', 'subject', 'source', 'source_url', 'avatar_url' ].forEach( ( k ) => ( els[ k ].value = data[ k ] || '' ) );
		els.content.value = data.content || '';
		els.avatar_id.value = data.avatar_id || 0;
		els.origin.value = data.origin || 'manual';
		els.status.value = data.status || 'publish';
		els.date.value = data.date || today();
		els.verified.checked = !! data.verified;
		els.featured.checked = !! data.featured;
		setRating( editing ? data.rating || 0 : 5 );
		setAvatar( data.avatar || '' );
		setProduct( data.product_id, data.product_title || ( data.product_id ? '#' + data.product_id : '' ) );

		customBox.replaceChildren();
		customSkipped = new Set();
		customSnapshots = data.custom_fields && ! Array.isArray( data.custom_fields ) ? data.custom_fields : {};
		const assigned = editing ? data.collections || [] : collectionId > 0 ? [ collectionId ] : [];
		$$( 'input[name="collections"]', collectionsBox ).forEach( ( box ) => ( box.checked = assigned.includes( parseInt( box.value, 10 ) ) ) );

		renderCustom();
		etitle.textContent = editing ? etitle.dataset.edit : etitle.dataset.new;
		$( '[data-abt-editor-delete]', editor ).hidden = ! editing;
		$( '[data-abt-save-new]', editor ).hidden = editing;
		openDialog( editor );
		( editing ? els.content : els.author ).focus();
	}

	async function saveEditor( addAnother ) {
		const els = eform.elements;
		if ( ! els.author.value.trim() ) {
			eerror.textContent = __( 'Add the reviewer’s name.', 'ashbi-testimonials' );
			eerror.hidden = false;
			els.author.focus();
			return;
		}
		const item = {
			id: els.id.value,
			author: els.author.value,
			content: els.content.value,
			headline: els.headline.value,
			rating: els.rating.value,
			role: els.role.value,
			company: els.company.value,
			location: els.location.value,
			email: els.email.value,
			avatar_id: els.avatar_id.value,
			avatar_url: els.avatar_url.value,
			product_id: els.product_id.value,
			subject: els.subject.value,
			origin: els.origin.value,
			source: els.source.value,
			source_url: els.source_url.value,
			status: els.status.value,
			date: els.date.value,
			verified: els.verified.checked ? 1 : 0,
			featured: els.featured.checked ? 1 : 0,
			custom_fields: customValues(),
			collections: $$( 'input[name="collections"]:checked', collectionsBox ).map( ( b ) => parseInt( b.value, 10 ) ),
		};
		const buttons = $$( '.abt-dialog__foot button', editor );
		buttons.forEach( ( b ) => ( b.disabled = true ) );
		try {
			await request( 'save', { item } );
			status( sprintf( __( 'Saved testimonial from %s.', 'ashbi-testimonials' ), item.author ) );
			load();
			if ( addAnother ) {
				openEditor( null );
			} else {
				editor.close();
			}
		} catch ( err ) {
			eerror.textContent = err.message;
			eerror.hidden = false;
		}
		buttons.forEach( ( b ) => ( b.disabled = false ) );
	}

	eform.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		saveEditor( false );
	} );
	$( '[data-abt-save-new]', editor ).addEventListener( 'click', () => saveEditor( true ) );
	$( '[data-abt-editor-delete]', editor ).addEventListener( 'click', async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Move this testimonial to the trash?', 'ashbi-testimonials' ) ) ) {
			return;
		}
		try {
			await request( 'delete', { ids: [ parseInt( eform.elements.id.value, 10 ) ] } );
			editor.close();
			status( __( 'Testimonial moved to the trash.', 'ashbi-testimonials' ) );
			load();
		} catch ( err ) {
			eerror.textContent = err.message;
			eerror.hidden = false;
		}
	} );
	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-abt-add]' ) ) {
			openEditor( null );
		}
	} );
	document.addEventListener( 'keydown', ( e ) => {
		// "N" adds a testimonial when focus is not in a field.
		if ( 'n' === e.key && ! e.metaKey && ! e.ctrlKey && ! e.target.closest( 'input,textarea,select,[contenteditable],dialog' ) ) {
			openEditor( null );
		}
	} );

	/* ==================================================================
	   Shortcode builder
	   ================================================================== */

	const builder = $( '[data-abt-builder]' );
	const builderCode = $( '[data-abt-builder-code]', builder );
	const builderPreview = $( '[data-abt-builder-preview]', builder );
	const partLabels = {
		avatar: __( 'Photo', 'ashbi-testimonials' ),
		rating: __( 'Stars', 'ashbi-testimonials' ),
		headline: __( 'Headline', 'ashbi-testimonials' ),
		role: __( 'Job title', 'ashbi-testimonials' ),
		company: __( 'Company', 'ashbi-testimonials' ),
		date: __( 'Date', 'ashbi-testimonials' ),
		source: __( 'Source', 'ashbi-testimonials' ),
		product: __( 'Product', 'ashbi-testimonials' ),
		verified: __( 'Verified', 'ashbi-testimonials' ),
		location: __( 'Location', 'ashbi-testimonials' ),
	};
	const defaultParts = ( cfg.defaultShow || '' ).split( ',' );
	$( '[data-abt-builder-parts]', builder ).innerHTML = ( cfg.parts || [] )
		.map( ( p ) => '<label class="abt-inline-check"><input type="checkbox" name="show" value="' + p + '"' + ( defaultParts.includes( p ) ? ' checked' : '' ) + ' /> ' + esc( partLabels[ p ] || p ) + '</label>' )
		.join( '' );

	function builderAtts() {
		const els = builder.elements;
		const atts = {};
		if ( collectionSlug ) {
			atts.collection = collectionSlug;
		}
		const layout = ( $( 'input[name="layout"]:checked', builder ) || {} ).value || 'grid';
		atts.layout = layout;
		if ( ! [ 'list', 'spotlight', 'compact' ].includes( layout ) && '3' !== els.columns.value ) {
			atts.columns = els.columns.value;
		}
		if ( '9' !== els.limit.value && els.limit.value ) {
			atts.limit = els.limit.value;
		}
		if ( 'date' !== els.orderby.value ) {
			atts.orderby = els.orderby.value;
		}
		if ( '0' !== els.min_rating.value ) {
			atts.min_rating = els.min_rating.value;
		}
		if ( 'light' !== els.theme.value ) {
			atts.theme = els.theme.value;
		}
		if ( parseInt( els.words.value, 10 ) > 0 ) {
			atts.words = els.words.value;
		}
		const show = $$( 'input[name="show"]:checked', builder ).map( ( b ) => b.value );
		if ( show.join( ',' ) !== defaultParts.join( ',' ) ) {
			atts.show = show.join( ',' );
		}
		[ 'summary', 'load_more', 'featured', 'verified' ].forEach( ( k ) => {
			if ( els[ k ].checked ) {
				atts[ k ] = '1';
			}
		} );
		if ( els.product_current.checked ) {
			atts.product = 'current';
		}
		return atts;
	}

	const refreshPreview = debounce( async ( atts ) => {
		builderPreview.classList.add( 'is-loading' );
		const previewAtts = Object.assign( {}, atts );
		delete previewAtts.product;
		try {
			const res = await request( 'preview', { atts: previewAtts } );
			builderPreview.innerHTML = res.html;
			initFrontend( builderPreview );
		} catch ( err ) {
			builderPreview.innerHTML = '<p class="abt-preview-empty">' + esc( err.message ) + '</p>';
		}
		builderPreview.classList.remove( 'is-loading' );
	}, 400 );

	function refreshBuilder() {
		const atts = builderAtts();
		builderCode.textContent = '[ashbi_testimonials' + Object.entries( atts ).map( ( [ k, v ] ) => ' ' + k + '="' + v + '"' ).join( '' ) + ']';
		$( '[data-abt-builder-note]', builder ).hidden = ! atts.product;
		refreshPreview( atts );
	}
	builder.addEventListener( 'input', refreshBuilder );
	builder.addEventListener( 'change', refreshBuilder );
	builder.addEventListener( 'submit', ( e ) => e.preventDefault() );
	$( '[data-abt-builder-copy]', builder ).addEventListener( 'click', ( e ) => copy( builderCode.textContent, e.currentTarget ) );
	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-abt-open="abt-builder-dialog"]' ) ) {
			refreshBuilder();
		}
	} );

	// The preview uses the real frontend markup; wire its sliders and Read more buttons.
	function initFrontend( root ) {
		if ( window.abtInitFrontend ) {
			window.abtInitFrontend( root );
		}
	}

	/* ==================================================================
	   CSV import
	   ================================================================== */

	/** RFC 4180 parser: quoted fields, escaped quotes, embedded newlines. */
	function parseCsv( text ) {
		const rows = [];
		let row = [];
		let field = '';
		let quoted = false;
		text = text.replace( /^﻿/, '' );
		for ( let i = 0; i < text.length; i++ ) {
			const c = text[ i ];
			if ( quoted ) {
				if ( '"' === c ) {
					if ( '"' === text[ i + 1 ] ) {
						field += '"';
						i++;
					} else {
						quoted = false;
					}
				} else {
					field += c;
				}
			} else if ( '"' === c ) {
				quoted = true;
			} else if ( ',' === c ) {
				row.push( field );
				field = '';
			} else if ( '\n' === c || '\r' === c ) {
				if ( '\r' === c && '\n' === text[ i + 1 ] ) {
					i++;
				}
				row.push( field );
				rows.push( row );
				row = [];
				field = '';
			} else {
				field += c;
			}
		}
		if ( field !== '' || row.length ) {
			row.push( field );
			rows.push( row );
		}
		return rows.filter( ( r ) => r.some( ( v ) => v.trim() !== '' ) );
	}

	const importForm = $( '[data-abt-import]' );
	const importCollection = $( '[data-abt-import-collection]', importForm );
	if ( importCollection ) {
		importCollection.innerHTML =
			'<option value="0">' + esc( __( 'None', 'ashbi-testimonials' ) ) + '</option>' +
			( cfg.collections || [] ).map( ( c ) => '<option value="' + c.id + '">' + esc( c.name ) + '</option>' ).join( '' );
	}

	importForm.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		const file = $( '#abt-i-file', importForm ).files[ 0 ];
		const result = $( '[data-abt-import-result]', importForm );
		const progress = $( '[data-abt-import-progress]', importForm );
		const submit = $( '[type="submit"]', importForm );
		if ( ! file ) {
			result.textContent = __( 'Choose a CSV file first.', 'ashbi-testimonials' );
			return;
		}

		const rows = parseCsv( await file.text() );
		if ( rows.length < 2 ) {
			result.textContent = __( 'That file has no rows to import.', 'ashbi-testimonials' );
			return;
		}
		const head = rows.shift().map( ( h ) => h.trim().toLowerCase().replace( /\s+/g, '_' ) );
		const records = rows.map( ( r ) => Object.fromEntries( head.map( ( h, i ) => [ h, r[ i ] ?? '' ] ) ) );
		const target = collectionId > 0 ? collectionId : importCollection ? importCollection.value : 0;

		submit.disabled = true;
		progress.hidden = false;
		let created = 0;
		const errors = [];
		const size = 100;
		for ( let i = 0; i < records.length; i += size ) {
			try {
				const res = await request( 'import', { rows: records.slice( i, i + size ), collection: target } );
				created += res.created;
				res.errors.forEach( ( er ) => errors.push( sprintf( __( 'Row %1$s: %2$s', 'ashbi-testimonials' ), i + er.row + 2, er.message ) ) );
			} catch ( err ) {
				errors.push( err.message );
				break;
			}
			const done = Math.min( records.length, i + size );
			$( 'span', progress ).style.width = Math.round( ( done / records.length ) * 100 ) + '%';
			result.textContent = sprintf( __( 'Imported %1$s of %2$s…', 'ashbi-testimonials' ), done.toLocaleString(), records.length.toLocaleString() );
		}

		result.textContent =
			sprintf( _n( 'Imported %s testimonial.', 'Imported %s testimonials.', created, 'ashbi-testimonials' ), created.toLocaleString() ) +
			( errors.length ? ' ' + sprintf( _n( '%s row skipped:', '%s rows skipped:', errors.length, 'ashbi-testimonials' ), errors.length ) + ' ' + errors.slice( 0, 5 ).join( '; ' ) : '' );
		submit.disabled = false;
		load();
	} );

	load();
} )();
