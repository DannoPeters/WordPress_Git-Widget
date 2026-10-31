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
	function init( root ) {
		if ( root.gwReady ) {
			return;
		}
		root.gwReady = true;
		root.querySelectorAll( 'select' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				show( root, select.value );
			} );
		} );
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
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
	function run() {
		document.querySelectorAll( '[data-gw-picker]' ).forEach( init );
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
