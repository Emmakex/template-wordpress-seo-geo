( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const rawBox = root.querySelector( '[data-seo-geo-raw]' );
	const actionablesBox = root.querySelector( '[data-seo-geo-actionables]' );
	if ( ! rawBox || ! actionablesBox ) {
		return;
	}

	let workspace = null;

	function readDiagnostic() {
		const value = rawBox.textContent ? rawBox.textContent.trim() : '';
		if ( ! value || '{}' === value ) {
			return null;
		}
		try {
			return JSON.parse( value );
		} catch ( error ) {
			return null;
		}
	}

	function actionableItems() {
		const data = readDiagnostic();
		const diagnostics = data && data.actionable_diagnostics && typeof data.actionable_diagnostics === 'object' ? data.actionable_diagnostics : {};
		return Array.isArray( diagnostics.items ) ? diagnostics.items : [];
	}

	function eligible( item ) {
		return item && 'auto-fixable' === item.classification && 'navigation' === item.category && 'environment-link-leakage' === item.code && typeof item.current_url === 'string' && item.current_url && typeof item.suggested_url === 'string' && item.suggested_url;
	}

	function makeText( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value || '' );
		if ( className ) {
			node.className = className;
		}
		return node;
	}

	function ensureWorkspace() {
		if ( workspace && workspace.isConnected ) {
			return workspace;
		}
		workspace = document.createElement( 'section' );
		workspace.className = 'seo-geo-manager-admin__panel seo-geo-manager-correction';
		workspace.hidden = true;
		workspace.setAttribute( 'aria-live', 'polite' );
		const actionablesPanel = actionablesBox.closest( '.seo-geo-manager-admin__panel' );
		( actionablesPanel || actionablesBox ).after( workspace );
		return workspace;
	}

	function sourceId( source ) {
		if ( ! source || typeof source !== 'object' ) {
			return 0;
		}
		return Number( source.id || source.post_id || source.resource_id || 0 );
	}

	function replaceBounded( value, before, after ) {
		if ( typeof value !== 'string' || ! value.includes( before ) ) {
			return null;
		}
		return value.split( before ).join( after );
	}

	function deriveChanges( resource, item ) {
		const changes = {};
		[ 'content', 'excerpt' ].forEach( ( field ) => {
			const next = replaceBounded( resource[ field ], item.current_url, item.suggested_url );
			if ( null !== next && next !== resource[ field ] ) {
				changes[ field ] = next;
			}
		} );
		return changes;
	}

	async function resolvePreviews( item, status ) {
		const sources = Array.isArray( item.sources ) ? item.sources : [];
		const uniqueIds = [ ...new Set( sources.map( sourceId ).filter( ( id ) => id > 0 ) ) ];
		const resolved = [];
		const blocked = [];

		if ( ! uniqueIds.length ) {
			blocked.push( 'El diagnóstico no expone IDs editables para los orígenes detectados.' );
			return { resolved, blocked };
		}

		for ( const id of uniqueIds ) {
			try {
				status.textContent = `Resolviendo recurso #${ id }…`;
				const resource = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/content/${ id }` } );
				const changes = deriveChanges( resource, item );
				if ( ! Object.keys( changes ).length ) {
					blocked.push( `#${ id }: la URL detectada no está en content/excerpt editable; puede proceder del Theme, menú o metadatos.` );
					continue;
				}
				const payload = {
					schema_version: 1,
					target: { id, expected_fingerprint: resource.fingerprint },
					changes,
					allow_published_target: false,
				};
				const preview = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/changes/preview', method: 'POST', data: payload } );
				resolved.push( { resource, payload, preview } );
			} catch ( error ) {
				blocked.push( `#${ id }: ${ error && error.message ? error.message : 'no se pudo preparar el preview REST' }` );
			}
		}
		return { resolved, blocked };
	}

	function renderResult( panel, result ) {
		const summary = document.createElement( 'div' );
		summary.className = 'seo-geo-manager-correction__result';
		summary.appendChild( makeText( 'h3', 'Preview REST validado' ) );
		summary.appendChild( makeText( 'p', `${ result.resolved.length } recurso(s) editable(s) preparado(s); ${ result.blocked.length } origen(es) bloqueado(s).` ) );

		result.resolved.forEach( ( entry ) => {
			const card = document.createElement( 'div' );
			card.className = 'seo-geo-manager-admin__check is-pass';
			card.appendChild( makeText( 'strong', `${ entry.resource.type } #${ entry.resource.id } · ${ entry.resource.title || entry.resource.slug }` ) );
			card.appendChild( makeText( 'p', `Fingerprint verificado: ${ entry.resource.fingerprint }` ) );
			const fields = Object.keys( entry.payload.changes ).join( ', ' );
			card.appendChild( makeText( 'p', `Campos afectados: ${ fields }. Cambios reales: ${ entry.preview.has_changes ? 'sí' : 'no' }.` ) );
			summary.appendChild( card );
		} );

		result.blocked.forEach( ( reason ) => {
			const card = document.createElement( 'div' );
			card.className = 'seo-geo-manager-admin__check is-warning';
			card.appendChild( makeText( 'strong', 'No autocorregible todavía' ) );
			card.appendChild( makeText( 'p', reason ) );
			summary.appendChild( card );
		} );
		panel.appendChild( summary );
	}

	function renderPreparation( item ) {
		const panel = ensureWorkspace();
		panel.hidden = false;
		panel.replaceChildren();
		panel.appendChild( makeText( 'p', 'Correction preview', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( makeText( 'h2', 'Preparar corrección segura' ) );
		panel.appendChild( makeText( 'p', 'Resolveremos cada origen contra WordPress y enviaremos un Preview REST real. Esta acción todavía no escribe nada.', 'description' ) );

		const diff = document.createElement( 'div' );
		diff.className = 'seo-geo-manager-correction__diff';
		[ [ 'Actual', item.current_url ], [ 'Propuesto', item.suggested_url ] ].forEach( ( pair ) => {
			const box = document.createElement( 'div' );
			box.appendChild( makeText( 'span', pair[ 0 ] ) );
			box.appendChild( makeText( 'code', pair[ 1 ] ) );
			diff.appendChild( box );
		} );
		panel.appendChild( diff );

		const status = makeText( 'p', 'Preparando…', 'seo-geo-manager-correction__safety' );
		panel.appendChild( status );
		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-correction__actions';
		const close = makeText( 'button', 'Cerrar preview' );
		close.type = 'button';
		close.className = 'button';
		close.addEventListener( 'click', () => { panel.hidden = true; } );
		const apply = makeText( 'button', 'Aplicar' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.disabled = true;
		apply.title = 'Apply se habilitará en la siguiente microfase, con entorno, idempotencia y confirmación explícita.';
		actions.appendChild( close );
		actions.appendChild( apply );
		panel.appendChild( actions );

		resolvePreviews( item, status ).then( ( result ) => {
			status.textContent = result.resolved.length && ! result.blocked.length ? 'Preview válido. No se ha modificado WordPress.' : 'Preview parcial: hay orígenes que requieren otro adaptador o revisión.';
			renderResult( panel, result );
		} ).catch( ( error ) => {
			status.textContent = error && error.message ? error.message : 'No se pudo preparar la corrección.';
		} );
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	function wireButtons() {
		const items = actionableItems();
		const rows = actionablesBox.querySelectorAll( '.seo-geo-manager-admin__check' );
		rows.forEach( ( row, index ) => {
			const item = items[ index ];
			const existing = row.querySelector( '[data-seo-geo-prepare-correction]' );
			if ( ! eligible( item ) ) {
				existing?.remove();
				return;
			}
			if ( existing ) {
				return;
			}
			const button = makeText( 'button', 'Preparar corrección' );
			button.type = 'button';
			button.className = 'button button-secondary seo-geo-manager-correction__prepare';
			button.setAttribute( 'data-seo-geo-prepare-correction', item.id || String( index ) );
			button.addEventListener( 'click', () => renderPreparation( item ) );
			row.appendChild( button );
		} );
	}

	const observer = new MutationObserver( wireButtons );
	observer.observe( actionablesBox, { childList: true, subtree: true } );
	observer.observe( rawBox, { childList: true, characterData: true, subtree: true } );
	wireButtons();
}() );
