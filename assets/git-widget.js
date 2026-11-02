/* Git Widget: calendar year picker. Shows the panel for the chosen view; works for any number of widgets. */
( function () {
	function show( root, view ) {
		root.querySelectorAll( '.gw-cal-panel' ).forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-view' ) !== view;
		} );
		root.querySelectorAll( '[role="tab"]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-selected', b.getAttribute( 'data-view' ) === view ? 'true' : 'false' );
		} );
		// Keep the small-screen dropdown and the year buttons showing the same choice.
		root.querySelectorAll( 'select' ).forEach( function ( s ) {
			s.value = view;
		} );
	}
	function pageYear( view ) {
		if ( view === 'last' ) return 'last';
		if ( view === 'all' ) return 'all';
		return view.charAt( 0 ) === 'y' ? view.slice( 1 ) : view;
	}
	function goToYear( view ) {
		try {
			var url = new URL( window.location.href );
			url.searchParams.set( 'gw_year', pageYear( view ) );
			window.location.href = url.toString();
		} catch ( e ) {
			window.location.search = 'gw_year=' + encodeURIComponent( pageYear( view ) );
		}
	}
	function init( root ) {
		if ( root.gwReady ) {
			return;
		}
		root.gwReady = true;
		root.querySelectorAll( 'select' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				if ( root.hasAttribute( 'data-gw-page' ) ) { goToYear( select.value ); return; }
				show( root, select.value );
			} );
		} );
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				if ( root.hasAttribute( 'data-gw-page' ) ) { goToYear( tab.getAttribute( 'data-view' ) ); return; }
				show( root, tab.getAttribute( 'data-view' ) );
				// In a scrolling row, bring the chosen year into view.
				if ( tab.scrollIntoView ) {
					tab.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
				}
			} );
			// Arrow keys move between years, like any tab list.
			tab.addEventListener( 'keydown', function ( e ) {
				var step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[ e.key ];
				if ( step ) {
					e.preventDefault();
					var next = tabs[ ( i + step + tabs.length ) % tabs.length ];
					next.focus();
					next.click();
				}
			} );
		} );
	}
	// Free edition: keep each widget's credit line present and readable.
	var CREDIT = 'Git Widget by <a href="https://dannopeters.ca" target="_blank" rel="noopener">DannoPeters</a>';
	function lum( c ) {
		var v = ( c.match( /[\d.]+/g ) || [ 0, 0, 0 ] ).slice( 0, 3 ).map( function ( x ) {
			x /= 255;
			return x <= 0.03928 ? x / 12.92 : Math.pow( ( x + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * v[ 0 ] + 0.7152 * v[ 1 ] + 0.0722 * v[ 2 ];
	}
	function background( el ) {
		for ( var e = el; e; e = e.parentElement ) {
			var c = getComputedStyle( e ).backgroundColor;
			if ( c && c !== 'transparent' && ! /rgba\([^)]*,\s*0(\.\d+)?\)$/.test( c ) ) {
				return c;
			}
		}
		return 'rgb(255, 255, 255)';
	}
	function credit( w ) {
		if ( w.getAttribute( 'data-gw-edition' ) !== 'free' ) {
			return;
		}
		var p = w.querySelector( '[data-gw-credit]' );
		if ( ! p || ! p.querySelector( 'a[href*="dannopeters.ca"]' ) ) {
			if ( p ) {
				p.parentNode.removeChild( p );
			}
			p = document.createElement( 'p' );
			p.className = 'gw-credit';
			p.setAttribute( 'data-gw-credit', '' );
			p.innerHTML = CREDIT;
			w.appendChild( p );
		}
		var s = getComputedStyle( p ), box = p.getBoundingClientRect(), op = 1;
		for ( var e = p; e && e !== w.parentElement; e = e.parentElement ) {
			op *= parseFloat( getComputedStyle( e ).opacity );
		}
		var a = lum( s.color ), b = lum( background( p ) );
		var contrast = ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
		if ( s.display === 'none' || s.visibility === 'hidden' || op < 0.35 || box.width < 20 || box.height < 6 || parseFloat( s.fontSize ) < 8 || contrast < 1.5 || ( s.clipPath && s.clipPath !== 'none' ) ) {
			var fix = 'display:block!important;visibility:visible!important;opacity:.7!important;position:static!important;transform:none!important;clip-path:none!important;font-size:11px!important;width:auto!important;height:auto!important;overflow:visible!important;margin:.5rem 0 0!important;text-align:right!important;color:' + ( b > 0.4 ? '#57606a' : '#9198a1' ) + '!important;text-indent:0!important';
			p.setAttribute( 'style', fix );
			w.style.setProperty( 'display', 'block', 'important' );
			w.style.setProperty( 'visibility', 'visible', 'important' );
		}
	}
	function run() {
		document.querySelectorAll( '[data-gw-picker]' ).forEach( init );
		document.querySelectorAll( '[data-gw]' ).forEach( credit );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
	// Beaver Builder re-renders modules while editing.
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'fl-builder.layout-rendered fl-builder.preview-rendered', run );
	}
} )();
