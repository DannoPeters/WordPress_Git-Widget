#!/usr/bin/env node
/**
 * Check pages for Git Widget and whether each free widget still shows its credit.
 *
 * Usage: node tools/check-attribution.js https://example.com/page [more urls...]
 * Needs Playwright (npm i playwright). Loads each page in a real browser, so CSS tricks that hide the
 * credit (display:none, zero opacity, tiny text, off-screen, same colour as the background) are caught.
 */
const { chromium } = require( 'playwright' );

( async () => {
	const urls = process.argv.slice( 2 );
	if ( ! urls.length ) {
		console.log( 'Usage: node tools/check-attribution.js <url> [url...]' );
		process.exit( 1 );
	}
	const browser = await chromium.launch();
	let problems = 0;
	for ( const url of urls ) {
		const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
		try {
			await page.goto( url, { waitUntil: 'networkidle', timeout: 45000 } );
		} catch ( e ) {
			console.log( `\n${ url }\n  could not load: ${ e.message.split( '\n' )[ 0 ] }` );
			await page.close();
			continue;
		}
		const r = await page.evaluate( () => {
			const gen = [ ...document.querySelectorAll( 'meta[name="generator"]' ) ].map( ( m ) => m.content ).filter( ( c ) => /Git Widget/i.test( c ) );
			const sheet = [ ...document.querySelectorAll( 'link[rel="stylesheet"],style' ) ].some( ( l ) => /git-widget/.test( l.href || l.id || '' ) );
			// Widgets with their markers, plus any that look like Git Widget but had the markers stripped.
			const marked = [ ...document.querySelectorAll( '[data-gw]' ) ];
			const unmarked = [ ...document.querySelectorAll( '.gw .gw-sections' ) ].map( ( s ) => s.closest( '.gw' ) ).filter( ( w ) => ! w.hasAttribute( 'data-gw' ) );
			const rgb = ( c ) => ( c.match( /[\d.]+/g ) || [] ).map( Number );
			const lum = ( c ) => {
				const [ r, g, b ] = rgb( c ).map( ( v ) => ( v /= 255 ) <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ) );
				return 0.2126 * r + 0.7152 * g + 0.0722 * b;
			};
			const bgOf = ( el ) => {
				for ( let e = el; e; e = e.parentElement ) {
					const c = getComputedStyle( e ).backgroundColor;
					if ( rgb( c ).length && ( rgb( c )[ 3 ] === undefined || rgb( c )[ 3 ] > 0.5 ) && c !== 'rgba(0, 0, 0, 0)' ) return c;
				}
				return 'rgb(255, 255, 255)';
			};
			const check = ( w ) => {
				const edition = w.getAttribute( 'data-gw-edition' ) || 'unknown (markers removed)';
				const credit = w.querySelector( '[data-gw-credit], .gw-credit' );
				const out = { version: w.getAttribute( 'data-gw' ) || '?', edition, credit: 'missing', issues: [] };
				if ( ! credit ) return out;
				const link = credit.querySelector( 'a[href*="dannopeters.ca"]' );
				if ( ! link ) out.issues.push( 'link to dannopeters.ca removed' );
				let opacity = 1;
				for ( let e = credit; e; e = e.parentElement ) {
					const s = getComputedStyle( e );
					if ( s.display === 'none' ) out.issues.push( 'display:none' );
					if ( s.visibility === 'hidden' ) out.issues.push( 'visibility:hidden' );
					opacity *= parseFloat( s.opacity );
				}
				const s = getComputedStyle( credit );
				const box = credit.getBoundingClientRect();
				if ( opacity < 0.35 ) out.issues.push( `nearly transparent (opacity ${ opacity.toFixed( 2 ) })` );
				if ( box.width < 20 || box.height < 6 ) out.issues.push( 'shrunk to nothing' );
				if ( parseFloat( s.fontSize ) < 8 ) out.issues.push( `text too small (${ s.fontSize })` );
				if ( box.right < 0 || box.bottom < -window.scrollY || box.left > document.documentElement.scrollWidth ) out.issues.push( 'moved off-screen' );
				if ( s.clipPath && s.clipPath !== 'none' ) out.issues.push( 'clipped' );
				const a = lum( s.color ), b = lum( bgOf( credit ) );
				const contrast = ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
				if ( contrast < 1.5 ) out.issues.push( `same colour as background (contrast ${ contrast.toFixed( 2 ) })` );
				out.credit = out.issues.length ? 'hidden' : 'visible';
				return out;
			};
			return { gen, sheet, widgets: [ ...marked, ...unmarked ].map( check ) };
		} );
		console.log( `\n${ url }` );
		console.log( `  generator: ${ r.gen.join( ', ' ) || 'none' }${ r.sheet ? '' : '  (Git Widget stylesheet not found)' }` );
		if ( ! r.widgets.length ) console.log( '  no Git Widget on this page' );
		r.widgets.forEach( ( w, i ) => {
			const ok = w.edition === 'pro' || w.credit === 'visible';
			if ( ! ok ) problems++;
			console.log( `  widget ${ i + 1 }: ${ w.edition } ${ w.version }, credit ${ w.credit }${ w.issues.length ? ' [' + w.issues.join( '; ' ) + ']' : '' }  ${ ok ? 'OK' : 'NEEDS A LOOK' }` );
		} );
		await page.close();
	}
	await browser.close();
	console.log( problems ? `\n${ problems } widget(s) need a look.` : '\nAll good.' );
	process.exit( problems ? 2 : 0 );
} )();
