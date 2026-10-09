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

	function typeLabel( type ) {
		const labels = {
			'content-change': 'Contenido',
			'theme-structured-content': 'Contenido estructurado',
			'permalink-structure': 'Permalinks',
			'permalink-structure-with-redirects': 'Permalinks + 301',
			'navigation-change': 'Navegación'
		};
		return labels[ type ] || type || 'Operación';
	}

	function statusLabel( status ) {
		const labels = {
			applied: 'Aplicada',
			'rolled-back': 'Revertida',
			failed: 'Fallida',
			'verification-failed': 'Verificación fallida',
			'verification-failed-rolled-back': 'Fallo verificado · restaurada'
		};
		return labels[ status ] || status || 'Desconocido';
	}

	function rollbackLabel( state ) {
		if ( 'completed' === state ) return 'Rollback completado';
		if ( 'guarded' === state ) return 'Protegido · disponible si no hay cambios posteriores';
		return 'No disponible';
	}

	function dateLabel( value ) {
		if ( ! value ) return '—';
		const parsed = new Date( value );
		if ( Number.isNaN( parsed.getTime() ) ) return value;
		return parsed.toLocaleString();
	}

	function targetLabel( item ) {
		if ( Number( item.target_id || 0 ) > 0 ) {
			return `#${ item.target_id }${ item.target_type ? ` · ${ item.target_type }` : '' }`;
		}
		return 'Sitio completo';
	}

	function scopeLabel( item ) {
		const parts = [];
		if ( Array.isArray( item.changed_fields ) && item.changed_fields.length ) parts.push( item.changed_fields.join( ', ' ) );
		if ( item.structured_model ) parts.push( `modelo ${ item.structured_model }` );
		if ( Number( item.planned_redirects || 0 ) > 0 ) parts.push( `${ item.planned_redirects } × 301` );
		return parts.length ? parts.join( ' · ' ) : '—';
	}

	function buildPanel() {
		const section = document.createElement( 'section' );
		section.className = 'seo-geo-manager-admin__panel';
		section.setAttribute( 'data-seo-geo-operation-history', '' );

		const heading = document.createElement( 'div' );
		heading.className = 'seo-geo-manager-admin__panel-heading';
		const copy = document.createElement( 'div' );
		copy.appendChild( text( 'p', 'Operation evidence', 'seo-geo-manager-admin__eyebrow' ) );
		copy.appendChild( text( 'h2', 'Historial de operaciones' ) );
		copy.appendChild( text( 'p', 'Registro acotado para verificar qué aplicó el Manager y si su rollback sigue protegido. No muestra contenido anterior, payloads ni fingerprints sensibles.', 'description' ) );
		heading.appendChild( copy );

		const refresh = text( 'button', 'Actualizar historial' );
		refresh.type = 'button';
		refresh.className = 'button button-secondary';
		refresh.setAttribute( 'data-seo-geo-operation-refresh', '' );
		heading.appendChild( refresh );
		section.appendChild( heading );

		const status = text( 'p', 'Cargando operaciones…', 'description' );
		status.setAttribute( 'data-seo-geo-operation-status', '' );
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		section.appendChild( status );

		const body = document.createElement( 'div' );
		body.setAttribute( 'data-seo-geo-operation-body', '' );
		section.appendChild( body );

		const raw = root.querySelector( '.seo-geo-manager-admin__raw' );
		if ( raw ) raw.before( section );
		else root.appendChild( section );

		return section;
	}

	function renderEmpty( body ) {
		body.replaceChildren( text( 'p', 'Todavía no hay operaciones indexadas por esta versión del Manager.', 'description' ) );
	}

	function renderRows( body, items ) {
		body.replaceChildren();
		const table = document.createElement( 'table' );
		table.className = 'widefat striped';
		const thead = document.createElement( 'thead' );
		const header = document.createElement( 'tr' );
		[ 'Fecha', 'Tipo', 'Estado', 'Destino', 'Alcance', 'Rollback', 'Operación' ].forEach( ( label ) => header.appendChild( text( 'th', label ) ) );
		thead.appendChild( header );
		table.appendChild( thead );

		const tbody = document.createElement( 'tbody' );
		items.forEach( ( item ) => {
			const row = document.createElement( 'tr' );
			row.appendChild( text( 'td', dateLabel( item.rolled_back_at_gmt || item.created_at_gmt ) ) );
			row.appendChild( text( 'td', typeLabel( item.operation_type ) ) );
			row.appendChild( text( 'td', statusLabel( item.status ) ) );
			row.appendChild( text( 'td', targetLabel( item ) ) );
			row.appendChild( text( 'td', scopeLabel( item ) ) );
			row.appendChild( text( 'td', rollbackLabel( item.rollback_state ) ) );
			const idCell = document.createElement( 'td' );
			idCell.appendChild( text( 'code', item.operation_id || '—' ) );
			row.appendChild( idCell );
			tbody.appendChild( row );
		} );
		table.appendChild( tbody );
		body.appendChild( table );
	}

	const panel = buildPanel();
	const body = panel.querySelector( '[data-seo-geo-operation-body]' );
	const status = panel.querySelector( '[data-seo-geo-operation-status]' );
	const refresh = panel.querySelector( '[data-seo-geo-operation-refresh]' );
	if ( ! body || ! status || ! refresh ) return;

	async function load() {
		refresh.disabled = true;
		status.textContent = 'Actualizando evidencia de operaciones…';
		try {
			const response = await window.wp.apiFetch( { path: '/seo-geo-manager/v1/operations?per_page=20' } );
			const items = Array.isArray( response.items ) ? response.items : [];
			if ( items.length ) renderRows( body, items );
			else renderEmpty( body );
			status.textContent = `${ items.length } operación(es) visible(s). El historial guarda solo metadatos de auditoría acotados.`;
		} catch ( error ) {
			status.textContent = error && error.message ? error.message : 'No se pudo cargar el historial de operaciones.';
		} finally {
			refresh.disabled = false;
		}
	}

	refresh.addEventListener( 'click', load );
	load();
}() );
