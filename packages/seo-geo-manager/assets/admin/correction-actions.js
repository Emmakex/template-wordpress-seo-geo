( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root ) {
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
		const diagnostics = data && data.actionable_diagnostics && typeof data.actionable_diagnostics === 'object'
			? data.actionable_diagnostics
			: {};
		return Array.isArray( diagnostics.items ) ? diagnostics.items : [];
	}

	function eligible( item ) {
		return item &&
			'auto-fixable' === item.classification &&
			'navigation' === item.category &&
			'environment-link-leakage' === item.code &&
			typeof item.current_url === 'string' && item.current_url &&
			typeof item.suggested_url === 'string' && item.suggested_url;
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
		workspace.setAttribute( 'data-seo-geo-correction-workspace', '' );
		workspace.setAttribute( 'aria-live', 'polite' );

		const actionablesPanel = actionablesBox.closest( '.seo-geo-manager-admin__panel' );
		if ( actionablesPanel ) {
			actionablesPanel.after( workspace );
		} else {
			actionablesBox.after( workspace );
		}
		return workspace;
	}

	function sourceLabel( source ) {
		if ( source && typeof source.permalink === 'string' && source.permalink ) {
			return source.permalink;
		}
		const kind = source && source.kind ? source.kind : 'origen';
		const id = source && source.id ? source.id : '—';
		return `${ kind } #${ id }`;
	}

	function renderPreparation( item ) {
		const panel = ensureWorkspace();
		panel.hidden = false;
		panel.replaceChildren();

		panel.appendChild( makeText( 'p', 'Correction preview', 'seo-geo-manager-admin__eyebrow' ) );
		panel.appendChild( makeText( 'h2', 'Preparar corrección segura' ) );
		panel.appendChild( makeText( 'p', 'Esta fase no modifica WordPress. Confirma la causa raíz y el destino determinista antes de habilitar Apply.', 'description' ) );

		const flow = document.createElement( 'ol' );
		flow.className = 'seo-geo-manager-correction__flow';
		[ 'Preparar', 'Preview', 'Aplicar', 'Verificar', 'Rollback' ].forEach( ( label, index ) => {
			const step = makeText( 'li', label );
			if ( index < 2 ) {
				step.classList.add( 'is-ready' );
			}
			flow.appendChild( step );
		} );
		panel.appendChild( flow );

		const diff = document.createElement( 'div' );
		diff.className = 'seo-geo-manager-correction__diff';
		const before = document.createElement( 'div' );
		before.appendChild( makeText( 'span', 'Actual' ) );
		before.appendChild( makeText( 'code', item.current_url ) );
		const after = document.createElement( 'div' );
		after.appendChild( makeText( 'span', 'Propuesto' ) );
		after.appendChild( makeText( 'code', item.suggested_url ) );
		diff.appendChild( before );
		diff.appendChild( after );
		panel.appendChild( diff );

		const facts = document.createElement( 'ul' );
		facts.className = 'seo-geo-manager-admin__facts';
		[
			[ 'Causa raíz', item.code || 'environment-link-leakage' ],
			[ 'Detecciones agrupadas', item.occurrences || 1 ],
			[ 'Confianza', item.confidence || 'high' ],
			[ 'Recurso destino', item.target_resource_id || '—' ],
		].forEach( ( pair ) => {
			const row = document.createElement( 'li' );
			row.appendChild( makeText( 'span', pair[ 0 ] ) );
			row.appendChild( makeText( 'strong', pair[ 1 ] ) );
			facts.appendChild( row );
		} );
		panel.appendChild( facts );

		const sources = Array.isArray( item.sources ) ? item.sources : [];
		if ( sources.length ) {
			const details = document.createElement( 'details' );
			details.appendChild( makeText( 'summary', `Orígenes detectados (${ sources.length })` ) );
			const list = document.createElement( 'ul' );
			sources.forEach( ( source ) => list.appendChild( makeText( 'li', sourceLabel( source ) ) ) );
			details.appendChild( list );
			panel.appendChild( details );
		}

		const safety = makeText( 'p', 'Apply permanece bloqueado en esta microfase: antes debemos resolver cada origen a un adaptador editable y obtener su fingerprint actual. No se hará sustitución global ni escritura ciega.', 'seo-geo-manager-correction__safety' );
		panel.appendChild( safety );

		const actions = document.createElement( 'div' );
		actions.className = 'seo-geo-manager-correction__actions';
		const close = makeText( 'button', 'Cerrar preview' );
		close.type = 'button';
		close.className = 'button';
		close.addEventListener( 'click', () => {
			panel.hidden = true;
		} );
		const apply = makeText( 'button', 'Aplicar' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.disabled = true;
		apply.title = 'Se habilitará cuando todos los orígenes tengan adaptador editable y fingerprint verificado.';
		actions.appendChild( close );
		actions.appendChild( apply );
		panel.appendChild( actions );
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
