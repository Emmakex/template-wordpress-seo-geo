( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) return;

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) node.className = className;
		return node;
	}

	function statusLabel( value ) {
		const labels = {
			blocked: 'Bloqueado',
			'needs-input': 'Falta origen histórico',
			'ready-for-guarded-write': 'Listo para operación protegida',
			verified: 'Verificada',
			'not-requested': 'No solicitada',
			'direct-ready': 'Directo · rutas preservadas',
			'atomic-ready': 'Atómico · 301 preparado',
			'atomic-runtime-blocked': 'Runtime 301 bloqueado',
			pass: 'Correcto',
			warning: 'Con avisos',
			unknown: 'Desconocido'
		};
		return labels[ value ] || value || '—';
	}

	function metric( label, value ) {
		const item = document.createElement( 'div' );
		item.className = 'seo-geo-manager-admin__metric';
		item.appendChild( text( 'span', label ) );
		item.appendChild( text( 'strong', value ) );
		return item;
	}

	function buildPanel() {
		const section = document.createElement( 'section' );
		section.className = 'seo-geo-manager-admin__panel';
		section.setAttribute( 'data-seo-geo-field-gate', '' );

		const heading = document.createElement( 'div' );
		heading.className = 'seo-geo-manager-admin__panel-heading';
		const copy = document.createElement( 'div' );
		copy.appendChild( text( 'p', 'Field acceptance', 'seo-geo-manager-admin__eyebrow' ) );
		copy.appendChild( text( 'h2', 'Field Gate · Build / Finish' ) );
		copy.appendChild( text( 'p', 'Preflight de solo lectura: combina Site Intelligence, frontend renderizado, autoridad histórica y plan de permalinks. Nunca ejecuta Apply ni activa redirecciones.', 'description' ) );
		heading.appendChild( copy );
		section.appendChild( heading );

		const controls = document.createElement( 'div' );
		controls.className = 'seo-geo-manager-admin__actions';

		const legacyLabel = document.createElement( 'label' );
		legacyLabel.appendChild( text( 'span', 'Origen histórico ' ) );
		const legacy = document.createElement( 'input' );
		legacy.type = 'url';
		legacy.className = 'regular-text';
		legacy.placeholder = 'https://example.com/';
		legacy.setAttribute( 'data-seo-geo-field-legacy', '' );
		legacyLabel.appendChild( legacy );
		controls.appendChild( legacyLabel );

		const renderedLabel = document.createElement( 'label' );
		const rendered = document.createElement( 'input' );
		rendered.type = 'checkbox';
		rendered.checked = true;
		rendered.setAttribute( 'data-seo-geo-field-rendered', '' );
		renderedLabel.appendChild( rendered );
		renderedLabel.appendChild( document.createTextNode( ' Verificar frontend renderizado' ) );
		controls.appendChild( renderedLabel );

		const run = text( 'button', 'Ejecutar preflight de campo' );
		run.type = 'button';
		run.className = 'button button-primary';
		run.setAttribute( 'data-seo-geo-field-run', '' );
		controls.appendChild( run );
		section.appendChild( controls );

		const status = text( 'p', 'Todavía no se ha ejecutado el Field Gate.', 'description' );
		status.setAttribute( 'data-seo-geo-field-status', '' );
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		section.appendChild( status );

		const metrics = document.createElement( 'div' );
		metrics.className = 'seo-geo-manager-admin__metrics';
		metrics.setAttribute( 'data-seo-geo-field-metrics', '' );
		metrics.hidden = true;
		section.appendChild( metrics );

		const decision = document.createElement( 'div' );
		decision.setAttribute( 'data-seo-geo-field-decision', '' );
		section.appendChild( decision );

		const details = document.createElement( 'details' );
		details.className = 'seo-geo-manager-admin__raw';
		const summary = text( 'summary', 'Ver evidencia JSON del Field Gate' );
		details.appendChild( summary );
		const raw = text( 'pre', '{}' );
		raw.setAttribute( 'data-seo-geo-field-raw', '' );
		details.appendChild( raw );

		const evidenceActions = document.createElement( 'div' );
		evidenceActions.className = 'seo-geo-manager-admin__actions';

		const copyButton = text( 'button', 'Copiar JSON' );
		copyButton.type = 'button';
		copyButton.className = 'button button-secondary';
		copyButton.setAttribute( 'data-seo-geo-field-copy', '' );
		evidenceActions.appendChild( copyButton );

		const downloadButton = text( 'button', 'Descargar JSON' );
		downloadButton.type = 'button';
		downloadButton.className = 'button button-secondary';
		downloadButton.disabled = true;
		downloadButton.setAttribute( 'data-seo-geo-field-download', '' );
		evidenceActions.appendChild( downloadButton );

		details.appendChild( evidenceActions );
		section.appendChild( details );

		const rawAnchor = root.querySelector( '.seo-geo-manager-admin__raw' );
		if ( rawAnchor ) rawAnchor.before( section );
		else root.appendChild( section );

		return section;
	}

	function renderReport( panel, report ) {
		const gate = report && report.field_gate ? report.field_gate : {};
		const build = gate.build_finish || {};
		const authority = gate.historical_authority || {};
		const plan = gate.permalink_plan || {};
		const environment = report && report.environment ? report.environment : {};
		const metrics = panel.querySelector( '[data-seo-geo-field-metrics]' );
		const decision = panel.querySelector( '[data-seo-geo-field-decision]' );
		const raw = panel.querySelector( '[data-seo-geo-field-raw]' );
		const downloadButton = panel.querySelector( '[data-seo-geo-field-download]' );

		if ( metrics ) {
			metrics.replaceChildren(
				metric( 'Estado', statusLabel( gate.status ) ),
				metric( 'Build / Finish', statusLabel( build.status ) ),
				metric( 'Autoridad histórica', statusLabel( authority.status ) ),
				metric( 'Permalinks', statusLabel( plan.status ) )
			);
			metrics.hidden = false;
		}

		if ( decision ) {
			decision.replaceChildren();
			const heading = text( 'h3', gate.guarded_write_eligible ? 'Siguiente operación técnicamente habilitada' : 'Siguiente acción' );
			decision.appendChild( heading );
			decision.appendChild( text( 'p', gate.reason || '—' ) );
			const list = document.createElement( 'ul' );
			list.appendChild( text( 'li', `Acción: ${ gate.next_action || '—' }` ) );
			list.appendChild( text( 'li', `Modo de escritura: ${ gate.guarded_write_mode || 'none' }` ) );
			list.appendChild( text( 'li', `Bloqueos: ${ Number( build.blockers || 0 ) } · Avisos: ${ Number( build.warnings || 0 ) }` ) );
			list.appendChild( text( 'li', `Entorno: ${ environment.type || '—' } · ${ environment.home_url || '—' }` ) );
			decision.appendChild( list );
		}

		if ( raw ) raw.textContent = JSON.stringify( report, null, 2 );
		if ( downloadButton ) downloadButton.disabled = false;
	}

	function downloadJson( raw, status ) {
		const json = raw.textContent || '{}';
		const blob = new Blob( [ json ], { type: 'application/json;charset=utf-8' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		const host = ( window.location.hostname || 'wordpress' ).replace( /[^a-z0-9.-]+/gi, '-' );
		const stamp = new Date().toISOString().replace( /[:.]/g, '-' );

		link.href = url;
		link.download = `seo-geo-field-gate-${ host }-${ stamp }.json`;
		link.style.display = 'none';
		document.body.appendChild( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
		status.textContent = 'JSON del Field Gate descargado.';
	}

	const panel = buildPanel();
	const run = panel.querySelector( '[data-seo-geo-field-run]' );
	const legacy = panel.querySelector( '[data-seo-geo-field-legacy]' );
	const rendered = panel.querySelector( '[data-seo-geo-field-rendered]' );
	const status = panel.querySelector( '[data-seo-geo-field-status]' );
	const raw = panel.querySelector( '[data-seo-geo-field-raw]' );
	const copyButton = panel.querySelector( '[data-seo-geo-field-copy]' );
	const downloadButton = panel.querySelector( '[data-seo-geo-field-download]' );
	if ( ! run || ! legacy || ! rendered || ! status || ! raw || ! copyButton || ! downloadButton ) return;

	async function execute() {
		run.disabled = true;
		downloadButton.disabled = true;
		status.textContent = 'Ejecutando preflight de solo lectura…';
		try {
			const params = new URLSearchParams();
			params.set( 'include_rendered', rendered.checked ? '1' : '0' );
			if ( legacy.value.trim() ) params.set( 'legacy_base_url', legacy.value.trim() );
			const report = await window.wp.apiFetch( { path: `/seo-geo-manager/v1/field-gate/preflight?${ params.toString() }` } );
			renderReport( panel, report );
			const gate = report && report.field_gate ? report.field_gate : {};
			status.textContent = `Field Gate completado: ${ statusLabel( gate.status ) }. No se ha realizado ninguna escritura.`;
		} catch ( error ) {
			status.textContent = error && error.message ? error.message : 'No se pudo completar el Field Gate.';
		} finally {
			run.disabled = false;
		}
	}

	run.addEventListener( 'click', execute );
	copyButton.addEventListener( 'click', async function () {
		try {
			await navigator.clipboard.writeText( raw.textContent || '{}' );
			status.textContent = 'JSON del Field Gate copiado.';
		} catch ( error ) {
			status.textContent = 'No se pudo copiar automáticamente. Usa “Descargar JSON” para guardar la evidencia como archivo.';
		}
	} );
	downloadButton.addEventListener( 'click', function () {
		downloadJson( raw, status );
	} );
}() );
