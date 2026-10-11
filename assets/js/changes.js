/* Expert Price & Stock Manager - change history dashboard. Data and translations come from wp_localize_script (epmChanges). */
( function () {
	'use strict';
	var cfg = window.epmChanges || {};
	var i18n = cfg.i18n || {};
	function T( s ) { return Object.prototype.hasOwnProperty.call( i18n, s ) ? i18n[ s ] : s; }
	function TF( s ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return T( s ).replace( /%(?:(\d+)\$)?s|%d/g, function ( m, n ) { return String( n ? args[ n - 1 ] : args[ i++ ] ); } );
	}
	function esc( v ) {
		return String( v === null || v === undefined ? '' : v ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function num( n ) { try { return Number( n ).toLocaleString( cfg.locale || undefined ); } catch ( e ) { return String( n ); } }
	var COLORS = [ '#2f6fed', '#8b5cf6', '#0ea5a4', '#f59e0b', '#ec4899', '#94a3b8' ];

	var root = document.getElementById( 'epm-ch-root' );
	var noticeBox = document.getElementById( 'epm-ch-notice' );
	var msgBox = document.createElement( 'div' );
	root.parentNode.insertBefore( msgBox, root );
	var state = { period: 'today', user: '', type: '', search: '', page: 1 };
	var usersLoaded = false;
	var timer;

	root.innerHTML =
		'<div class="epm-ch-bar">' +
			'<div class="epm-seg" id="epm-ch-period"></div><span class="epm-ch-sp"></span>' +
			'<span class="epm-live"><span class="epm-dot"></span>' + esc( T( 'Live updates · every 30 seconds' ) ) + '</span>' +
			'<select id="epm-ch-user"></select><select id="epm-ch-type"></select>' +
		'</div>' +
		'<div class="epm-ch-kpis" id="epm-ch-kpis"></div>' +
		'<div class="epm-ch-grid">' +
			'<div class="epm-card"><h3>' + esc( T( 'Change trend' ) ) + '</h3><div id="epm-ch-chart"></div></div>' +
			'<div class="epm-card"><h3>' + esc( T( 'Change types' ) ) + '</h3><div id="epm-ch-donut"></div></div>' +
			'<div class="epm-card"><h3>' + esc( T( 'Most active users' ) ) + '</h3><div id="epm-ch-users"></div></div>' +
		'</div>' +
		'<div class="epm-card epm-ch-logcard"><h3>' + esc( T( 'Change log' ) ) + ' <small id="epm-ch-count"></small></h3>' +
			'<div class="epm-ch-bar" style="margin-top:0"><input type="text" id="epm-ch-search" placeholder="' + esc( T( 'Search product name or SKU...' ) ) + '" style="flex:1"></div>' +
			'<div class="epm-ch-tablewrap" id="epm-ch-table"></div><div class="epm-ch-pager" id="epm-ch-pager"></div></div>' +
		'<p class="epm-ch-retention">' + esc( TF( 'History is kept for %d days and then deleted automatically.', cfg.retention ) ) + '</p>';

	var periods = [ [ 'today', 'Today' ], [ 'yesterday', 'Yesterday' ], [ '7', 'Last 7 days' ], [ '30', 'Last 30 days' ] ];
	var periodBox = document.getElementById( 'epm-ch-period' );
	periodBox.innerHTML = periods.map( function ( p ) { return '<button type="button" data-p="' + p[ 0 ] + '"' + ( p[ 0 ] === state.period ? ' class="on"' : '' ) + '>' + esc( T( p[ 1 ] ) ) + '</button>'; } ).join( '' );
	var userSel = document.getElementById( 'epm-ch-user' );
	var typeSel = document.getElementById( 'epm-ch-type' );
	typeSel.innerHTML = '<option value="">' + esc( T( 'All change types' ) ) + '</option><option value="price">' + esc( T( 'Price' ) ) + '</option><option value="stock">' + esc( T( 'Stock' ) ) + '</option><option value="other">' + esc( T( 'Other' ) ) + '</option>';
	userSel.innerHTML = '<option value="">' + esc( T( 'All users' ) ) + '</option>';

	function post( data ) {
		var fd = new FormData();
		fd.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } ).then( function ( r ) {
			return r.text().then( function ( t ) {
				try { return JSON.parse( t ); } catch ( e ) {
					return { success: false, data: { message: r.redirected && r.url ? TF( 'The request was redirected to %s by another plugin or a security rule.', r.url ) : T( 'Unexpected server response. Check the browser Network tab or the PHP error log.' ) } };
				}
			} );
		} );
	}

	function delta( cur, prev ) {
		if ( ! prev && ! cur ) return '<span class="epm-chip nu">' + esc( T( 'No change' ) ) + '</span>';
		if ( ! prev ) return '<span class="epm-chip up">▲ ' + esc( TF( '%s vs previous period', '100%' ) ) + '</span>';
		var p = Math.round( ( cur - prev ) / prev * 100 );
		if ( p === 0 ) return '<span class="epm-chip nu">' + esc( T( 'No change' ) ) + '</span>';
		return '<span class="epm-chip ' + ( p > 0 ? 'up' : 'dn' ) + '">' + ( p > 0 ? '▲ ' : '▼ ' ) + esc( TF( '%s vs previous period', num( Math.abs( p ) ) + '%' ) ) + '</span>';
	}

	function spark( pts, color ) {
		var w = 110, h = 34, mx = Math.max.apply( null, pts ), mn = Math.min.apply( null, pts ), n = pts.length;
		if ( n < 2 ) return '';
		var d = pts.map( function ( v, i ) {
			var x = state.rtl ? w - i * ( w / ( n - 1 ) ) : i * ( w / ( n - 1 ) );
			var y = mx === mn ? h / 2 : h - 4 - ( v - mn ) / ( mx - mn ) * ( h - 8 );
			return x.toFixed( 0 ) + ',' + y.toFixed( 0 );
		} ).join( ' ' );
		return '<svg viewBox="0 0 ' + w + ' ' + h + '" width="110" height="34" aria-hidden="true"><polyline points="' + d + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>';
	}
	state.rtl = getComputedStyle( document.body ).direction === 'rtl';

	function renderKpis( d ) {
		var k = d.kpi, s = d.series;
		var cards = [
			[ 'Total changes', k.cur_total, delta( k.cur_total, k.prev_total ), s.map( function ( x ) { return x.price + x.stock + x.other; } ), '#4fd37a' ],
			[ 'Price changes', k.cur_price, delta( k.cur_price, k.prev_price ), s.map( function ( x ) { return x.price; } ), '#2f6fed' ],
			[ 'Stock changes', k.cur_stock, delta( k.cur_stock, k.prev_stock ), s.map( function ( x ) { return x.stock; } ), '#f59e0b' ],
			[ 'Products changed', k.cur_products, '<span class="epm-chip nu">' + esc( TF( '%s active users', num( k.cur_users ) ) ) + '</span>', s.map( function ( x ) { return x.price + x.stock + x.other; } ), '#6b7280' ]
		];
		document.getElementById( 'epm-ch-kpis' ).innerHTML = cards.map( function ( c ) {
			return '<div class="epm-card epm-kpi"><div><small>' + esc( T( c[ 0 ] ) ) + '</small><div class="n">' + num( c[ 1 ] ) + '</div>' + c[ 2 ] + '</div>' + spark( c[ 3 ], c[ 4 ] ) + '</div>';
		} ).join( '' );
	}

	function renderChart( d ) {
		var s = d.series, mx = Math.max.apply( null, s.map( function ( x ) { return x.price + x.stock + x.other; } ).concat( [ 1 ] ) );
		var bars = s.map( function ( x ) {
			var t = x.price + x.stock + x.other;
			var h = function ( v ) { return ( v / mx * 100 ).toFixed( 1 ) + '%'; };
			return '<div class="epm-col" title="' + esc( T( 'Price' ) + ': ' + x.price + ' / ' + T( 'Stock' ) + ': ' + x.stock + ' / ' + T( 'Other' ) + ': ' + x.other ) + '"><div class="epm-stack">' +
				'<i class="o" style="height:' + h( x.other ) + '"></i><i class="s" style="height:' + h( x.stock ) + '"></i><i class="p" style="height:' + h( x.price ) + '"></i></div></div>';
		} ).join( '' );
		var labels = s.map( function ( x ) { var t = x.price + x.stock + x.other; return '<div>' + esc( x.label ) + '<b>' + num( t ) + '</b></div>'; } ).join( '' );
		document.getElementById( 'epm-ch-chart' ).innerHTML = '<div class="epm-chart">' + bars + '</div><div class="epm-xl">' + labels + '</div>' +
			'<div class="epm-leg"><span><u style="background:#2f6fed"></u>' + esc( T( 'Price' ) ) + '</span><span><u style="background:#f59e0b"></u>' + esc( T( 'Stock' ) ) + '</span><span><u style="background:#4fd37a"></u>' + esc( T( 'Other' ) ) + '</span></div>';
	}

	function renderDonut( d ) {
		var k = d.kpi, t = k.cur_price + k.cur_stock + k.cur_other;
		var parts = [ [ k.cur_price, '#2f6fed', 'Price' ], [ k.cur_stock, '#f59e0b', 'Stock' ], [ k.cur_other, '#4fd37a', 'Other' ] ];
		var off = 0;
		var circles = '<circle cx="21" cy="21" r="15.9" fill="none" stroke="#eef0f4" stroke-width="6"/>';
		parts.forEach( function ( p ) {
			var pct = t ? p[ 0 ] / t * 100 : 0;
			if ( pct > 0 ) circles += '<circle cx="21" cy="21" r="15.9" fill="none" stroke="' + p[ 1 ] + '" stroke-width="6" stroke-dasharray="' + pct.toFixed( 2 ) + ' ' + ( 100 - pct ).toFixed( 2 ) + '" stroke-dashoffset="' + ( -off ).toFixed( 2 ) + '" transform="rotate(-90 21 21)"/>';
			off += pct;
		} );
		var legend = parts.map( function ( p ) { return '<div><span><u style="background:' + p[ 1 ] + '"></u>' + esc( T( p[ 2 ] ) ) + '</span><b>' + num( t ? Math.round( p[ 0 ] / t * 100 ) : 0 ) + '%</b></div>'; } ).join( '' );
		var top = d.top_product ? '<div class="epm-top">' + esc( TF( 'Most price changes: %1$s (%2$s times)', d.top_product.name, num( d.top_product.count ) ) ) + '</div>' : '';
		document.getElementById( 'epm-ch-donut' ).innerHTML = '<div class="epm-donut"><svg width="104" height="104" viewBox="0 0 42 42">' + circles + '</svg><div class="epm-dl">' + legend + '</div></div>' + top;
	}

	function renderUsers( d ) {
		var mx = d.users.length ? d.users[ 0 ].count : 1;
		document.getElementById( 'epm-ch-users' ).innerHTML = d.users.length ? d.users.map( function ( u, i ) {
			var color = u.is_order ? '#94a3b8' : COLORS[ i % ( COLORS.length - 1 ) ];
			return '<div class="epm-user"><div class="epm-av" style="background:' + color + '">' + esc( u.is_order ? '⚙' : ( u.name.charAt( 0 ) || '?' ) ) + '</div><div class="m">' + esc( u.name ) + '<small>' + esc( TF( 'Last change %s', u.last_at ) ) + '</small><div class="meter"><i style="width:' + Math.round( u.count / mx * 100 ) + '%"></i></div></div><span class="cnt">' + num( u.count ) + '</span></div>';
		} ).join( '' ) : '<p class="epm-empty">' + esc( T( 'No changes in this period.' ) ) + '</p>';
	}

	function renderNotice( d ) {
		var n = d.notice;
		if ( ! n ) { noticeBox.innerHTML = ''; return; }
		var text = n.days_left > 0 ? TF( 'Records older than the retention limit will be deleted in %1$s day(s) (oldest record: %2$s). Export what you need first.', n.days_left, n.oldest ) : TF( 'The oldest records (from %s) will be deleted in the next automatic cleanup (within 24 hours).', n.oldest );
		noticeBox.innerHTML = '<div class="epm-ch-warn">⏳ ' + esc( text ) + '</div>';
	}

	function renderUserFilter( d ) {
		if ( usersLoaded || ! d.filter_users ) return;
		usersLoaded = true;
		userSel.innerHTML = '<option value="">' + esc( T( 'All users' ) ) + '</option>' + d.filter_users.map( function ( u ) {
			return '<option value="' + esc( u.id ) + '">' + esc( u.id === 'order' ? T( 'Customer orders' ) : u.name ) + '</option>';
		} ).join( '' );
		userSel.value = state.user;
	}

	function renderTable( d ) {
		document.getElementById( 'epm-ch-count' ).textContent = TF( '%s records', num( d.total ) );
		var tb = document.getElementById( 'epm-ch-table' );
		if ( ! d.rows.length ) { tb.innerHTML = '<p class="epm-empty">' + esc( T( 'No changes in this period.' ) ) + '</p>'; document.getElementById( 'epm-ch-pager' ).innerHTML = ''; return; }
		var tagCls = { price: 't-p', stock: 't-s', other: 't-o' };
		tb.innerHTML = '<table><thead><tr><th>' + esc( T( 'Time' ) ) + '</th><th>' + esc( T( 'Product' ) ) + '</th><th>' + esc( T( 'Type' ) ) + '</th><th>' + esc( T( 'Change' ) ) + '</th><th>' + esc( T( 'By' ) ) + '</th><th></th></tr></thead><tbody>' +
			d.rows.map( function ( r ) {
				var pct = r.pct === null ? '' : '<em dir="ltr" class="epm-chip ' + ( r.pct > 0 ? 'up' : 'dn' ) + '">' + ( r.pct > 0 ? '+' : '−' ) + num( Math.abs( r.pct ) ) + '%</em>';
				var thumb = r.thumb ? '<img class="epm-th" src="' + esc( r.thumb ) + '" alt="" loading="lazy">' : '<div class="epm-th"></div>';
				var action = r.can_revert ? '<button type="button" class="epm-undo" data-id="' + r.id + '">↶ ' + esc( T( 'Revert' ) ) + '</button>' : ( r.is_revert ? '<span class="epm-chip nu">' + esc( T( 'Reverted change' ) ) + '</span>' : '' );
				return '<tr><td>' + esc( r.time ) + '<small>' + esc( r.date ) + '</small></td>' +
					'<td><div class="epm-prod">' + thumb + '<div><a href="' + esc( r.edit_link ) + '" target="_blank" rel="noopener">' + esc( r.name ) + '</a><small>' + esc( r.sku ) + '</small></div></div></td>' +
					'<td><span class="epm-tag ' + tagCls[ r.group ] + '">' + esc( r.field ) + '</span></td>' +
					'<td class="epm-chg"><s>' + esc( r.old ) + '</s> ← <b>' + esc( r.new ) + '</b>' + pct + '</td>' +
					'<td><div class="epm-who"><div class="epm-av" style="background:' + ( r.is_order ? '#94a3b8' : '#2f6fed' ) + '">' + esc( r.is_order ? '⚙' : ( r.who.charAt( 0 ) || '?' ) ) + '</div>' + esc( r.who ) + '</div></td>' +
					'<td>' + action + '</td></tr>';
			} ).join( '' ) + '</tbody></table>';
		var pages = Math.max( 1, Math.ceil( d.total / d.per_page ) );
		var from = ( state.page - 1 ) * d.per_page + 1, to = Math.min( d.total, state.page * d.per_page );
		document.getElementById( 'epm-ch-pager' ).innerHTML = '<span>' + esc( TF( 'Showing %1$s to %2$s of %3$s', num( from ), num( to ), num( d.total ) ) ) + '</span>' +
			( pages > 1 ? '<span><button type="button" class="button" data-pg="-1"' + ( state.page <= 1 ? ' disabled' : '' ) + '>' + esc( T( 'Previous' ) ) + '</button> <button type="button" class="button" data-pg="1"' + ( state.page >= pages ? ' disabled' : '' ) + '>' + esc( T( 'Next' ) ) + '</button></span>' : '' );
	}

	var busy = false;
	function load() {
		if ( busy ) return;
		busy = true;
		post( { action: 'epm_log_dashboard', period: state.period, user: state.user, type: state.type, search: state.search, page: state.page } )
			.then( function ( res ) {
				busy = false;
				if ( ! res.success ) { msgBox.innerHTML = '<div class="epm-ch-warn">' + esc( ( res.data && res.data.message ) || T( 'Error loading data.' ) ) + '</div>'; return; }
				msgBox.innerHTML = '';
				var d = res.data;
				renderUserFilter( d ); renderKpis( d ); renderChart( d ); renderDonut( d ); renderUsers( d ); renderTable( d ); renderNotice( d );
			} )
			.catch( function () { busy = false; msgBox.innerHTML = '<div class="epm-ch-warn">' + esc( T( 'Server connection error.' ) ) + '</div>'; } );
	}

	periodBox.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button[data-p]' );
		if ( ! b ) return;
		state.period = b.dataset.p; state.page = 1;
		periodBox.querySelectorAll( 'button' ).forEach( function ( x ) { x.classList.toggle( 'on', x === b ); } );
		load();
	} );
	userSel.addEventListener( 'change', function () { state.user = userSel.value; state.page = 1; load(); } );
	typeSel.addEventListener( 'change', function () { state.type = typeSel.value; state.page = 1; load(); } );
	document.getElementById( 'epm-ch-search' ).addEventListener( 'input', function () {
		var v = this.value;
		clearTimeout( timer );
		timer = setTimeout( function () { state.search = v; state.page = 1; load(); }, 350 );
	} );
	document.getElementById( 'epm-ch-pager' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button[data-pg]' );
		if ( ! b ) return;
		state.page += parseInt( b.dataset.pg, 10 );
		load();
	} );
	document.getElementById( 'epm-ch-table' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.epm-undo' );
		if ( ! b || ! window.confirm( T( 'Revert this change and restore the previous value?' ) ) ) return;
		b.disabled = true;
		post( { action: 'epm_log_revert', id: b.dataset.id } ).then( function ( res ) {
			if ( res.success ) { msgBox.innerHTML = '<div class="epm-ch-ok">' + esc( T( 'The change was reverted.' ) ) + '</div>'; setTimeout( function () { msgBox.innerHTML = ''; }, 3000 ); }
			else { b.disabled = false; msgBox.innerHTML = '<div class="epm-ch-warn">' + esc( ( res.data && res.data.message ) || T( 'Error' ) ) + '</div>'; }
			setTimeout( load, 50 );
		} ).catch( function () { b.disabled = false; } );
	} );

	setInterval( function () { if ( ! document.hidden ) load(); }, 30000 );
	load();
}() );
