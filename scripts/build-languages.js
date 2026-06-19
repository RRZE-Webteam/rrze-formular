const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const ROOT = path.join( __dirname, '..' );
const LANG_DIR = path.join( ROOT, 'languages' );
const DOMAIN = 'rrze-formular';
const LOCALE = 'de_DE';
const JS_SCRIPTS = [
	'build/formular.js',
	'build/rrze-formular-guided-tour.js',
];

const translations = require( './de-translations.json' );

function walk( dir, files = [] ) {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		if ( [ 'node_modules', 'build', '.git', 'languages', 'scripts' ].includes( entry.name ) ) {
			continue;
		}
		const fullPath = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			walk( fullPath, files );
		} else if ( /\.(php|js|json)$/.test( entry.name ) ) {
			files.push( fullPath );
		}
	}
	return files;
}

function extractStrings( content ) {
	const strings = [];
	const patterns = [
		/__\(\s*'((?:\\'|[^'])*)'\s*,\s*'rrze-formular'\)/g,
		/__\(\s*"((?:\\"|[^"])*)"\s*,\s*'rrze-formular'\)/g,
		/__\(\s*\n\s*'((?:\\'|[^'])*)'\s*,\s*\n\s*'rrze-formular'\s*\n\s*\)/g,
		/__\(\s*\n\s*"((?:\\"|[^"])*)"\s*,\s*\n\s*'rrze-formular'\s*\n\s*\)/g,
		/esc_html__\(\s*'((?:\\'|[^'])*)'\s*,\s*'rrze-formular'\)/g,
		/esc_html_e\(\s*'((?:\\'|[^'])*)'\s*,\s*'rrze-formular'\)/g,
		/esc_attr_e\(\s*'((?:\\'|[^'])*)'\s*,\s*'rrze-formular'\)/g,
		/_e\(\s*'((?:\\'|[^'])*)'\s*,\s*'rrze-formular'\)/g,
	];

	for ( const pattern of patterns ) {
		let match;
		while ( ( match = pattern.exec( content ) ) ) {
			strings.push( match[ 1 ].replace( /\\'/g, "'" ) );
		}
	}

	return strings;
}

function poEscape( value ) {
	return value.replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' );
}

function translate( msgid ) {
	if ( translations[ msgid ] ) {
		return translations[ msgid ];
	}
	return msgid;
}

function collectMsgids() {
	const msgids = new Set();
	for ( const file of walk( ROOT ) ) {
		const content = fs.readFileSync( file, 'utf8' );
		for ( const string of extractStrings( content ) ) {
			msgids.add( string );
		}
	}

	const blockJsonPath = path.join( ROOT, 'blocks/formular/block.json' );
	if ( fs.existsSync( blockJsonPath ) ) {
		const blockJson = JSON.parse( fs.readFileSync( blockJsonPath, 'utf8' ) );
		if ( blockJson.title ) {
			msgids.add( blockJson.title );
		}
		if ( blockJson.description ) {
			msgids.add( blockJson.description );
		}
		if ( Array.isArray( blockJson.keywords ) ) {
			blockJson.keywords.forEach( ( keyword ) => msgids.add( keyword ) );
		}
	}

	return [ ...msgids ].sort();
}

function writePot( msgids ) {
	const header = `# Copyright (C) 2026 RRZE Webteam
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: RRZE Formular\\n"
"Report-Msgid-Bugs-To: https://github.com/RRZE-Webteam/rrze-formular\\n"
"Last-Translator: RRZE Webteam\\n"
"Language-Team: German\\n"
"Language: de_DE\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
`;

	const body = msgids
		.map( ( msgid ) => `msgid "${ poEscape( msgid ) }"\nmsgstr "${ poEscape( translate( msgid ) ) }"\n` )
		.join( '\n' );

	fs.mkdirSync( LANG_DIR, { recursive: true } );
	fs.writeFileSync( path.join( LANG_DIR, `${ DOMAIN }.pot` ), header + '\n' + body, 'utf8' );
	fs.writeFileSync( path.join( LANG_DIR, `${ DOMAIN }-${ LOCALE }.po` ), header + '\n' + body, 'utf8' );
}

function writeMo() {
	const poPath = path.join( LANG_DIR, `${ DOMAIN }-${ LOCALE }.po` );
	const moPath = path.join( LANG_DIR, `${ DOMAIN }-${ LOCALE }.mo` );
	execSync( `msgfmt -o ${ JSON.stringify( moPath ) } ${ JSON.stringify( poPath ) }` );
}

function writeJson( msgids ) {
	const localeData = {
		'': {
			domain: 'messages',
			lang: LOCALE,
			'plural-forms': 'nplurals=2; plural=(n != 1);',
		},
	};

	for ( const msgid of msgids ) {
		localeData[ msgid ] = [ translate( msgid ) ];
	}

	const jsonPayload = JSON.stringify( { locale_data: { messages: localeData } }, null, 2 ) + '\n';
	const written = [];

	for ( const scriptPath of JS_SCRIPTS ) {
		const hash = require( 'crypto' ).createHash( 'md5' ).update( scriptPath ).digest( 'hex' );
		const jsonPath = path.join( LANG_DIR, `${ DOMAIN }-${ LOCALE }-${ hash }.json` );
		fs.writeFileSync( jsonPath, jsonPayload, 'utf8' );
		written.push( path.basename( jsonPath ) );
	}

	for ( const file of fs.readdirSync( LANG_DIR ) ) {
		if (
			file.startsWith( `${ DOMAIN }-${ LOCALE }-` ) &&
			file.endsWith( '.json' ) &&
			! written.includes( file )
		) {
			fs.unlinkSync( path.join( LANG_DIR, file ) );
		}
	}
}

const msgids = collectMsgids();
const missing = msgids.filter( ( msgid ) => ! translations[ msgid ] );
if ( missing.length ) {
	console.warn( `Missing ${ missing.length } German translation(s):` );
	missing.forEach( ( msgid ) => console.warn( `  - ${ msgid }` ) );
}

writePot( msgids );
writeMo();
writeJson( msgids );
console.log( `Generated ${ msgids.length } strings for ${ LOCALE }.` );
