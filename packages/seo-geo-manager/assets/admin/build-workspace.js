( function () {
	'use strict';

	const config = window.SeoGeoManagerDashboard || {};
	const root = document.getElementById( 'seo-geo-manager-dashboard' );
	const workspace = root ? root.querySelector( '[data-seo-geo-build-workspace]' ) : null;
	if ( ! root || ! workspace || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const modelsBox = workspace.querySelector( '[data-seo-geo-build-models]' );
	const editorBox = workspace.querySelector( '[data-seo-geo-build-editor]' );
	const statusBox = workspace.querySelector( '[data-seo-geo-build-status]' );
	const refreshButton = workspace.querySelector( '[data-seo-geo-build-refresh]' );
	const previewButton = workspace.querySelector( '[data-seo-geo-build-preview]' );
	const applyButton = workspace.querySelector( '[data-seo-geo-build-apply]' );
	const previewBox = workspace.querySelector( '[data-seo-geo-build-preview-result]' );
	const verificationBox = workspace.querySelector( '[data-seo-geo-build-verification]' );
	const publishedBox = workspace.querySelector( '[data-seo-geo-build-published]' );
	const publishedInput = workspace.querySelector( '[data-seo-geo-build-allow-published]' );
	const modelTitle = workspace.querySelector( '[data-seo-geo-build-model-title]' );
	const modelMeta = workspace.querySelector( '[data-seo-geo-build-model-meta]' );
	const slotsBox = workspace.querySelector( '[data-seo-geo-build-slots]' );

	const object = ( value ) => value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	const array = ( value ) => Array.isArray( value ) ? value : [];

	let currentModel = null;
	let previewState = null;

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function text( tag, value, className ) {
		const node = document.createElement( tag );
		node.textContent = String( value ?? '' );
		if ( className ) {
			node.className = className;
		}
		return node;
	}

	function setStatus( message, type = 'info' ) {
		if ( ! statusBox ) return;
		statusBox.className = `notice inline seo-geo-manager-admin__notice notice-${ type }`;
		clear( statusBox );
		statusBox.appendChild( text( 'p', message ) );
	}

	function setEditorEnabled( enabled ) {
		if ( editorBox ) editorBox.hidden = ! enabled;
	}

	function resetPreview() {
		previewState = null;
		if ( applyButton ) applyButton.disabled = true;
		if ( previewBox ) {
			clear( previewBox );
			previewBox.appendChild( text( 'p', 'Haz una vista previa antes de aplicar cambios.', 'description' ) );
		}
	}

	function valueEqual( left, right ) {
		return JSON.stringify( left ?? null ) === JSON.stringify( right ?? null );
	}

	function modelResource( model ) {
		return object( object( model ).mapping ).resource || null;
	}

	function slotInputValue( slot ) {
		if ( 'link' === slot.type ) {
			const value = object( slot.value );
			return { text: String( value.text || '' ), url: String( value.url || '' ) };
		}
		return String( slot.value ?? '' );
	}

	function renderModels( payload ) {
		clear( modelsBox );
		const models = array( object( payload ).models );
		if ( ! models.length ) {
			modelsBox.appendChild( text( 'p', 'El Theme activo no expone modelos semánticos editables.', 'description' ) );
			return;
		}

		models.forEach( ( model ) => {
			const card = document.createElement( 'button' );
			card.type = 'button';
			card.className = 'seo-geo-manager-build-model';
			card.dataset.modelId = String( model.model_id || '' );
			const resource = object( model.resource );
			card.appendChild( text( 'strong', model.page_key || model.model_id || 'Modelo Theme' ) );
			card.appendChild( text( 'span', resource.title || ( model.mapped ? 'Página enlazada' : 'Sin página enlazada' ) ) );
			card.appendChild( text( 'small', `${ model.slot_count || 0 } campos · ${ model.required_slot_count || 0 } obligatorios` ) );
			if ( ! model.mapped ) card.disabled = true;
			card.addEventListener( 'click', () => loadModel( model.model_id ) );
			modelsBox.appendChild( card );
		} );
	}

	function renderVerification( slots ) {
		clear( verificationBox );
		const groups = [];
		array( slots ).forEach( ( slot ) => {
			if ( slot.requires_verification && slot.verification_group && ! groups.includes( slot.verification_group ) ) {
				groups.push( slot.verification_group );
			}
		} );
		if ( ! groups.length ) {
			verificationBox.hidden = true;
			return;
		}
		verificationBox.hidden = false;
		verificationBox.appendChild( text( 'strong', 'Verificación de evidencia' ) );
		verificationBox.appendChild( text( 'p', 'Marca únicamente grupos cuyo contenido hayas comprobado. El Manager bloqueará campos que requieran evidencia sin esta confirmación.', 'description' ) );
		groups.forEach( ( group ) => {
			const label = document.createElement( 'label' );
			label.className = 'seo-geo-manager-build-verification';
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.value = group;
			input.dataset.verificationGroup = group;
			input.addEventListener( 'change', resetPreview );
			label.appendChild( input );
			label.appendChild( text( 'span', group ) );
			verificationBox.appendChild( label );
		} );
	}

	function renderSlots( slots ) {
		clear( slotsBox );
		array( slots ).forEach( ( slot ) => {
			const row = document.createElement( 'div' );
			row.className = 'seo-geo-manager-build-slot';
			row.dataset.slotId = String( slot.id || '' );
			row.dataset.slotType = String( slot.type || 'text' );
			row.dataset.originalValue = JSON.stringify( slotInputValue( slot ) );

			const heading = document.createElement( 'div' );
			heading.className = 'seo-geo-manager-build-slot__heading';
			heading.appendChild( text( 'strong', slot.id || 'campo' ) );
			const badges = document.createElement( 'span' );
			badges.className = 'seo-geo-manager-build-slot__badges';
			if ( slot.required ) badges.appendChild( text( 'small', 'Obligatorio', 'seo-geo-manager-admin__badge' ) );
			if ( slot.requires_verification ) badges.appendChild( text( 'small', 'Evidencia', 'seo-geo-manager-admin__badge' ) );
			heading.appendChild( badges );
			row.appendChild( heading );

			const editable = true === slot.writable && Number( slot.marker_count || 0 ) === 1;
			if ( 'link' === slot.type ) {
				const value = slotInputValue( slot );
				const linkGrid = document.createElement( 'div' );
				linkGrid.className = 'seo-geo-manager-build-slot__link';
				const linkText = document.createElement( 'input' );
				linkText.type = 'text';
				linkText.value = value.text;
				linkText.placeholder = 'Texto del enlace';
				linkText.dataset.slotPart = 'text';
				linkText.disabled = ! editable;
				const linkUrl = document.createElement( 'input' );
				linkUrl.type = 'url';
				linkUrl.value = value.url;
				linkUrl.placeholder = 'https://… o ruta interna';
				linkUrl.dataset.slotPart = 'url';
				linkUrl.disabled = ! editable;
				linkText.addEventListener( 'input', resetPreview );
				linkUrl.addEventListener( 'input', resetPreview );
				linkGrid.appendChild( linkText );
				linkGrid.appendChild( linkUrl );
				row.appendChild( linkGrid );
			} else {
				const input = document.createElement( 'textarea' );
				input.rows = 3;
				input.value = slotInputValue( slot );
				input.dataset.slotPart = 'value';
				input.disabled = ! editable;
				input.addEventListener( 'input', resetPreview );
				row.appendChild( input );
			}

			if ( ! editable ) {
				row.appendChild( text( 'p', 'Este campo no es editable en el estado actual del Theme o su marcador no es único.', 'description' ) );
			}
			slotsBox.appendChild( row );
		} );
	}

	function renderModel( payload ) {
		currentModel = object( payload );
		resetPreview();
		const model = object( currentModel.model );
		const mapping = object( currentModel.mapping );
		const resource = object( mapping.resource );
		modelTitle.textContent = resource.title || model.page_key || model.model_id || 'Modelo Theme';
		modelMeta.textContent = `${ model.model_id || 'modelo' } · ${ resource.status || 'sin estado' } · #${ resource.id || '—' }`;
		renderSlots( currentModel.slots );
		renderVerification( currentModel.slots );
		if ( publishedBox ) publishedBox.hidden = 'publish' !== resource.status;
		if ( publishedInput ) {
			publishedInput.checked = false;
			publishedInput.addEventListener( 'change', resetPreview, { once: true } );
		}
		setEditorEnabled( true );
	}

	function collectSlots() {
		const changes = {};
		workspace.querySelectorAll( '[data-seo-geo-build-slots] [data-slot-id]' ).forEach( ( row ) => {
			const original = JSON.parse( row.dataset.originalValue || 'null' );
			let value;
			if ( 'link' === row.dataset.slotType ) {
				value = {
					text: row.querySelector( '[data-slot-part="text"]' )?.value || '',
					url: row.querySelector( '[data-slot-part="url"]' )?.value || '',
				};
			} else {
				value = row.querySelector( '[data-slot-part="value"]' )?.value || '';
			}
			if ( ! valueEqual( original, value ) ) {
				changes[ row.dataset.slotId ] = value;
			}
		} );
		return changes;
	}

	function verifiedGroups() {
		return Array.from( workspace.querySelectorAll( '[data-verification-group]:checked' ) ).map( ( node ) => node.value );
	}

	function basePayload() {
		const model = object( currentModel?.model );
		const resource = object( object( currentModel?.mapping ).resource );
		return {
			schema_version: 1,
			target: {
				id: Number( resource.id || 0 ),
				expected_fingerprint: String( resource.fingerprint || '' ),
			},
			model_id: String( model.model_id || '' ),
			slots: collectSlots(),
			verified_groups: verifiedGroups(),
			allow_published_target: Boolean( publishedInput?.checked ),
			verify_rendered: true,
		};
	}

	function renderPreview( result ) {
		clear( previewBox );
		const slots = object( result.slots );
		const names = Object.keys( slots );
		if ( ! names.length ) {
			previewBox.appendChild( text( 'p', 'La vista previa no contiene cambios.', 'description' ) );
			return;
		}
		previewBox.appendChild( text( 'strong', `Vista previa: ${ names.length } campo(s)` ) );
		const list = document.createElement( 'div' );
		list.className = 'seo-geo-manager-build-diff';
		names.forEach( ( name ) => {
			const diff = object( slots[ name ] );
			const item = document.createElement( 'article' );
			item.appendChild( text( 'strong', name ) );
			item.appendChild( text( 'pre', JSON.stringify( { from: diff.from, to: diff.to }, null, 2 ) ) );
			list.appendChild( item );
		} );
		previewBox.appendChild( list );
	}

	function idempotencyKey() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return `manager-ui-${ window.crypto.randomUUID() }`;
		}
		return `manager-ui-${ Date.now() }-${ Math.random().toString( 16 ).slice( 2 ) }`;
	}

	async function loadModels() {
		setStatus( 'Cargando modelos semánticos del Theme…' );
		setEditorEnabled( false );
		try {
			const data = await window.wp.apiFetch( { path: config.themeModelsPath || '/seo-geo-manager/v1/theme/models' } );
			renderModels( object( data ) );
			setStatus( `Modelos cargados: ${ Number( data.model_count || array( data.models ).length ) }. Selecciona una página para construirla.`, 'success' );
		} catch ( error ) {
			clear( modelsBox );
			modelsBox.appendChild( text( 'p', error?.message || 'No se pudieron cargar los modelos Theme.', 'description' ) );
			setStatus( error?.message || 'No se pudieron cargar los modelos Theme.', 'error' );
		}
	}

	async function loadModel( modelId ) {
		if ( ! modelId ) return;
		setStatus( `Cargando ${ modelId }…` );
		try {
			const base = config.themeModelsPath || '/seo-geo-manager/v1/theme/models';
			const data = await window.wp.apiFetch( { path: `${ base }/${ encodeURIComponent( modelId ) }` } );
			renderModel( data );
			setStatus( 'Modelo listo. Edita los campos y ejecuta Vista previa antes de aplicar.', 'success' );
		} catch ( error ) {
			setEditorEnabled( false );
			setStatus( error?.message || 'No se pudo abrir el modelo Theme.', 'error' );
		}
	}

	async function preview() {
		if ( ! currentModel ) return;
		const payload = basePayload();
		if ( ! Object.keys( payload.slots ).length ) {
			setStatus( 'No has modificado ningún campo.', 'info' );
			return;
		}
		previewButton.disabled = true;
		setStatus( 'Generando vista previa segura…' );
		try {
			const result = await window.wp.apiFetch( {
				path: config.themeStructuredPreviewPath || '/seo-geo-manager/v1/theme/structured/preview',
				method: 'POST',
				data: payload,
			} );
			previewState = { payload, result: object( result ) };
			renderPreview( result );
			applyButton.disabled = true !== result.has_changes;
			setStatus( true === result.has_changes ? 'Vista previa correcta. Revisa el diff y aplica solo si es el cambio esperado.' : 'No hay cambios efectivos que aplicar.', true === result.has_changes ? 'success' : 'info' );
		} catch ( error ) {
			resetPreview();
			setStatus( error?.message || 'La vista previa ha sido rechazada por el Manager.', 'error' );
		} finally {
			previewButton.disabled = false;
		}
	}

	async function apply() {
		if ( ! previewState ) return;
		applyButton.disabled = true;
		previewButton.disabled = true;
		setStatus( 'Aplicando y verificando el cambio…' );
		try {
			const environment = object( previewState.result.environment );
			const payload = {
				...previewState.payload,
				idempotency_key: idempotencyKey(),
				environment_fingerprint: String( environment.fingerprint || '' ),
			};
			const result = await window.wp.apiFetch( {
				path: config.themeStructuredApplyPath || '/seo-geo-manager/v1/theme/structured/apply',
				method: 'POST',
				data: payload,
			} );
			const operationId = result.operation_id ? ` · Operación ${ result.operation_id }` : '';
			setStatus( `Cambio aplicado y verificado${ operationId }.`, 'success' );
			previewState = null;
			await loadModel( object( currentModel.model ).model_id );
		} catch ( error ) {
			setStatus( error?.message || 'El Manager rechazó la aplicación del cambio.', 'error' );
		} finally {
			previewButton.disabled = false;
			applyButton.disabled = true;
		}
	}

	refreshButton?.addEventListener( 'click', loadModels );
	previewButton?.addEventListener( 'click', preview );
	applyButton?.addEventListener( 'click', apply );
	loadModels();
}() );
