( function () {
	'use strict';

	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const api = window.SeoGeoManagerOperationEvidence || {};
	if ( ! root || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const state = {
		items: [],
	};

	function el( tag, className, value ) {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== value ) {
			node.textContent = String( value );
		}
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function typeLabel( type ) {
		const labels = {
			'content-change': 'Contenido',
			'theme-structured-content': 'Contenido Theme',
			'seo-authority-change': 'Autoridad SEO',
			'contextual-link-change': 'Enlace contextual',
			'navigation-change': 'Navegación',
			'featured-media-change': 'Imagen destacada',
			'media-alt-change': 'ALT de imagen',
			'media-context-change': 'Contexto de imagen',
			'author-binding-change': 'Autor',
			'taxonomy-assignment-change': 'Taxonomía',
			'native-translation-group-change': 'Relación de idioma',
			'permalink-structure': 'Permalinks',
			'permalink-structure-with-redirects': 'Permalinks + 301',
		};
		return labels[ type ] || type || 'Operación';
	}

	function statusLabel( status ) {
		const labels = {
			applied: 'Aplicada',
			'rolled-back': 'Revertida',
			failed: 'Fallida',
			'verification-failed': 'Verificación fallida',
			'rendered-verification-failed': 'Frontend no verificado',
			'verification-failed-rolled-back': 'Fallo verificado · restaurada',
		};
		return labels[ status ] || status || 'Desconocido';
	}

	function statusClass( status ) {
		if ( 'rolled-back' === status || 'verification-failed-rolled-back' === status ) {
			return 'is-success';
		}
		if ( 'applied' === status ) {
			return '';
		}
		if ( String( status || '' ).includes( 'failed' ) ) {
			return 'is-error';
		}
		return 'is-warning';
	}

	function rollbackLabel( rollbackState ) {
		if ( 'completed' === rollbackState ) {
			return 'Rollback completado';
		}
		if ( 'guarded' === rollbackState ) {
			return 'Protegido';
		}
		return 'No disponible';
	}

	function evidence( item ) {
		const rendered = String( item.rendered_verification_status || '' );
		if ( 'passed' === rendered ) {
			return { label: 'Frontend verificado', className: 'is-success' };
		}
		if ( rendered ) {
			return { label: `Frontend: ${ rendered }`, className: 'is-error' };
		}
		if ( 'rolled-back' === item.status || 'completed' === item.rollback_state ) {
			return { label: 'Restauración registrada', className: 'is-success' };
		}
		if ( String( item.status || '' ).includes( 'failed' ) ) {
			return { label: 'Revisión requerida', className: 'is-error' };
		}
		return { label: 'Estado persistido · Verify no indexado', className: '' };
	}

	function dateLabel( value ) {
		if ( ! value ) {
			return '—';
		}
		const parsed = new Date( value );
		return Number.isNaN( parsed.getTime() ) ? String( value ) : parsed.toLocaleString();
	}

	function targetLabel( item ) {
		if ( Number( item.target_id || 0 ) > 0 ) {
			return `#${ item.target_id }${ item.target_type ? ` · ${ item.target_type }` : '' }`;
		}
		return 'Sitio completo';
	}

	function scopeLabel( item ) {
		const parts = [];
		if ( Array.isArray( item.changed_fields ) && item.changed_fields.length ) {
			parts.push( item.changed_fields.join( ', ' ) );
		}
		if ( item.structured_model ) {
			parts.push( `modelo ${ item.structured_model }` );
		}
		if ( Number( item.planned_redirects || 0 ) > 0 ) {
			parts.push( `${ item.planned_redirects } × 301` );
		}
		return parts.length ? parts.join( ' · ' ) : '—';
	}

	function anchor() {
		const selectors = [
			'[data-seo-geo-media-alt-workspace]',
			'[data-seo-geo-featured-media-workspace]',
			'[data-seo-geo-navigation-workspace]',
			'[data-seo-geo-contextual-links-workspace]',
			'[data-seo-geo-content-workspace]',
			'[data-seo-geo-build-workspace]',
		];
		for ( const selector of selectors ) {
			const match = root.querySelector( selector );
			if ( match ) {
				return match;
			}
		}
		return null;
	}

	const section = el( 'section', 'seo-geo-manager-admin__panel seo-geo-manager-operation-evidence' );
	section.dataset.seoGeoOperationEvidence = '';

	const heading = el( 'div', 'seo-geo-manager-admin__panel-heading' );
	const copy = el( 'div' );
	copy.appendChild( el( 'p', 'seo-geo-manager-admin__eyebrow', 'Evidence · Operations' ) );
	copy.appendChild( el( 'h2', '', 'Evidencia de operaciones' ) );
	copy.appendChild( el( 'p', 'description', 'Consulta qué aplicó el Manager, el estado persistido, la evidencia de frontend disponible y si el rollback sigue protegido. No expone payloads, contenido anterior, hashes ni fingerprints privados.' ) );
	heading.appendChild( copy );
	const refresh = el( 'button', 'button button-secondary', 'Actualizar evidencia' );
	refresh.type = 'button';
	refresh.dataset.operationEvidenceRefresh = '';
	heading.appendChild( refresh );
	section.appendChild( heading );

	const status = el( 'div', 'notice notice-info inline seo-geo-manager-admin__notice' );
	status.dataset.operationEvidenceStatus = '';
	status.setAttribute( 'role', 'status' );
	status.setAttribute( 'aria-live', 'polite' );
	status.appendChild( el( 'p', '', 'Cargando evidencia acotada…' ) );
	section.appendChild( status );

	const summary = el( 'div', 'seo-geo-manager-operation-evidence__summary' );
	summary.dataset.operationEvidenceSummary = '';
	section.appendChild( summary );

	const filters = el( 'div', 'seo-geo-manager-operation-evidence__filters' );
	const searchLabel = el( 'label', '', 'Buscar' );
	const search = document.createElement( 'input' );
	search.type = 'search';
	search.placeholder = 'Operación, destino o tipo';
	search.dataset.operationEvidenceSearch = '';
	searchLabel.appendChild( search );
	filters.appendChild( searchLabel );

	const typeLabelNode = el( 'label', '', 'Tipo' );
	const typeFilter = document.createElement( 'select' );
	typeFilter.dataset.operationEvidenceType = '';
	typeLabelNode.appendChild( typeFilter );
	filters.appendChild( typeLabelNode );

	const statusLabelNode = el( 'label', '', 'Estado' );
	const statusFilter = document.createElement( 'select' );
	statusFilter.dataset.operationEvidenceState = '';
	statusLabelNode.appendChild( statusFilter );
	filters.appendChild( statusLabelNode );

	const clearFilters = el( 'button', 'button', 'Limpiar filtros' );
	clearFilters.type = 'button';
	filters.appendChild( clearFilters );
	section.appendChild( filters );

	const body = el( 'div', 'seo-geo-manager-operation-evidence__table-wrap' );
	body.dataset.operationEvidenceBody = '';
	section.appendChild( body );

	const insertionAnchor = anchor();
	if ( insertionAnchor ) {
		insertionAnchor.insertAdjacentElement( 'afterend', section );
	} else {
		const technical = root.querySelector( '.seo-geo-manager-admin__technical' );
		if ( technical ) {
			technical.before( section );
		} else {
			root.appendChild( section );
		}
	}

	function setStatus( message, type = 'info' ) {
		status.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( status );
		status.appendChild( el( 'p', '', message ) );
	}

	function metric( label, value ) {
		const card = el( 'div', 'seo-geo-manager-operation-evidence__metric' );
		card.appendChild( el( 'span', '', label ) );
		card.appendChild( el( 'strong', '', String( value ) ) );
		return card;
	}

	function attention( item ) {
		const rendered = String( item.rendered_verification_status || '' );
		return String( item.status || '' ).includes( 'failed' ) || ( rendered && 'passed' !== rendered );
	}

	function renderSummary() {
		clear( summary );
		summary.appendChild( metric( 'Operaciones visibles', state.items.length ) );
		summary.appendChild( metric( 'Aplicadas', state.items.filter( ( item ) => 'applied' === item.status ).length ) );
		summary.appendChild( metric( 'Rollback protegido', state.items.filter( ( item ) => 'guarded' === item.rollback_state ).length ) );
		summary.appendChild( metric( 'Requieren atención', state.items.filter( attention ).length ) );
	}

	function option( value, label ) {
		const node = document.createElement( 'option' );
		node.value = value;
		node.textContent = label;
		return node;
	}

	function renderFilterOptions() {
		const selectedType = typeFilter.value;
		const selectedStatus = statusFilter.value;
		clear( typeFilter );
		clear( statusFilter );
		typeFilter.appendChild( option( '', 'Todos los tipos' ) );
		statusFilter.appendChild( option( '', 'Todos los estados' ) );

		const types = [ ...new Set( state.items.map( ( item ) => String( item.operation_type || '' ) ).filter( Boolean ) ) ].sort();
		const statuses = [ ...new Set( state.items.map( ( item ) => String( item.status || '' ) ).filter( Boolean ) ) ].sort();
		types.forEach( ( type ) => typeFilter.appendChild( option( type, typeLabel( type ) ) ) );
		statuses.forEach( ( itemStatus ) => statusFilter.appendChild( option( itemStatus, statusLabel( itemStatus ) ) ) );
		if ( types.includes( selectedType ) ) {
			typeFilter.value = selectedType;
		}
		if ( statuses.includes( selectedStatus ) ) {
			statusFilter.value = selectedStatus;
		}
	}

	function filteredItems() {
		const query = search.value.trim().toLowerCase();
		const wantedType = typeFilter.value;
		const wantedStatus = statusFilter.value;
		return state.items.filter( ( item ) => {
			if ( wantedType && item.operation_type !== wantedType ) {
				return false;
			}
			if ( wantedStatus && item.status !== wantedStatus ) {
				return false;
			}
			if ( ! query ) {
				return true;
			}
			const haystack = [
				item.operation_id,
				item.operation_type,
				typeLabel( item.operation_type ),
				item.status,
				statusLabel( item.status ),
				targetLabel( item ),
				scopeLabel( item ),
				item.environment_type,
			].join( ' ' ).toLowerCase();
			return haystack.includes( query );
		} );
	}

	function badge( label, className = '' ) {
		return el( 'span', `seo-geo-manager-operation-evidence__badge ${ className }`.trim(), label );
	}

	function renderEmpty( message ) {
		clear( body );
		const empty = el( 'div', 'seo-geo-manager-operation-evidence__empty' );
		empty.appendChild( el( 'p', 'description', message ) );
		body.appendChild( empty );
	}

	function copyOperationId( operationId ) {
		if ( ! operationId || ! navigator.clipboard || typeof navigator.clipboard.writeText !== 'function' ) {
			setStatus( 'No se pudo copiar el ID automáticamente; puedes seleccionarlo en la tabla.', 'warning' );
			return;
		}
		navigator.clipboard.writeText( operationId )
			.then( () => setStatus( `ID de operación ${ operationId } copiado.`, 'success' ) )
			.catch( () => setStatus( 'No se pudo copiar el ID automáticamente; puedes seleccionarlo en la tabla.', 'warning' ) );
	}

	function renderRows() {
		const items = filteredItems();
		if ( ! state.items.length ) {
			renderEmpty( 'Todavía no hay operaciones indexadas por el Manager.' );
			return;
		}
		if ( ! items.length ) {
			renderEmpty( 'No hay operaciones que coincidan con los filtros actuales.' );
			return;
		}

		clear( body );
		const table = document.createElement( 'table' );
		table.className = 'widefat striped seo-geo-manager-operation-evidence__table';
		const thead = document.createElement( 'thead' );
		const header = document.createElement( 'tr' );
		[ 'Fecha', 'Tipo', 'Estado', 'Destino', 'Alcance', 'Evidencia', 'Rollback', 'Entorno', 'Operación' ].forEach( ( label ) => header.appendChild( el( 'th', '', label ) ) );
		thead.appendChild( header );
		table.appendChild( thead );

		const tbody = document.createElement( 'tbody' );
		items.forEach( ( item ) => {
			const row = document.createElement( 'tr' );
			row.appendChild( el( 'td', '', dateLabel( item.rolled_back_at_gmt || item.created_at_gmt ) ) );
			row.appendChild( el( 'td', '', typeLabel( item.operation_type ) ) );
			const stateCell = document.createElement( 'td' );
			stateCell.appendChild( badge( statusLabel( item.status ), statusClass( item.status ) ) );
			row.appendChild( stateCell );
			row.appendChild( el( 'td', '', targetLabel( item ) ) );
			row.appendChild( el( 'td', '', scopeLabel( item ) ) );
			const evidenceCell = document.createElement( 'td' );
			const itemEvidence = evidence( item );
			evidenceCell.appendChild( badge( itemEvidence.label, itemEvidence.className ) );
			row.appendChild( evidenceCell );
			const rollbackCell = document.createElement( 'td' );
			rollbackCell.appendChild( badge( rollbackLabel( item.rollback_state ), 'guarded' === item.rollback_state ? 'is-warning' : ( 'completed' === item.rollback_state ? 'is-success' : '' ) ) );
			row.appendChild( rollbackCell );
			row.appendChild( el( 'td', '', item.environment_type || '—' ) );

			const operationCell = document.createElement( 'td' );
			const operationWrap = el( 'div', 'seo-geo-manager-operation-evidence__operation' );
			operationWrap.appendChild( el( 'code', '', item.operation_id || '—' ) );
			const copyButton = el( 'button', 'button button-small', 'Copiar ID' );
			copyButton.type = 'button';
			copyButton.disabled = ! item.operation_id;
			copyButton.addEventListener( 'click', () => copyOperationId( String( item.operation_id || '' ) ) );
			operationWrap.appendChild( copyButton );
			operationCell.appendChild( operationWrap );
			row.appendChild( operationCell );
			tbody.appendChild( row );
		} );
		table.appendChild( tbody );
		body.appendChild( table );
		setStatus( `${ items.length } de ${ state.items.length } operación(es) visibles. La evidencia mantiene payloads, valores anteriores y fingerprints fuera del navegador.`, 'success' );
	}

	async function load() {
		refresh.disabled = true;
		setStatus( 'Actualizando evidencia de operaciones…' );
		try {
			const response = await window.wp.apiFetch( { path: api.operationsPath || '/seo-geo-manager/v1/operations?per_page=50' } );
			if ( true !== response.privacy_safe || 'operation-history-summary' !== response.mode ) {
				throw new Error( 'El endpoint de evidencia no confirmó su contrato de privacidad.' );
			}
			state.items = Array.isArray( response.items ) ? response.items : [];
			renderSummary();
			renderFilterOptions();
			renderRows();
		} catch ( error ) {
			state.items = [];
			renderSummary();
			renderFilterOptions();
			renderEmpty( 'No se pudo cargar la evidencia de operaciones.' );
			setStatus( error?.message || 'No se pudo cargar la evidencia de operaciones.', 'error' );
		} finally {
			refresh.disabled = false;
		}
	}

	search.addEventListener( 'input', renderRows );
	typeFilter.addEventListener( 'change', renderRows );
	statusFilter.addEventListener( 'change', renderRows );
	clearFilters.addEventListener( 'click', () => {
		search.value = '';
		typeFilter.value = '';
		statusFilter.value = '';
		renderRows();
	} );
	refresh.addEventListener( 'click', load );
	load();
}() );
