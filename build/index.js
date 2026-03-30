( function ( blocks, blockEditor, components, element, i18n, apiFetch ) {
	var __ = i18n.__;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var Button = components.Button;
	var Spinner = components.Spinner;
	var Notice = components.Notice;
	var ColorPalette = components.ColorPalette;
	var BaseControl = components.BaseControl;
	var Placeholder = components.Placeholder;

	var DEFAULTS = window.mrpBlockData && window.mrpBlockData.defaults ? window.mrpBlockData.defaults : {};
	var REST_URL = window.mrpBlockData && window.mrpBlockData.restUrl ? window.mrpBlockData.restUrl : '';
	var COLORS = [
		{ color: '#111827', name: 'Ink' },
		{ color: '#ffffff', name: 'White' },
		{ color: '#f9fafb', name: 'Slate 50' },
		{ color: '#e5e7eb', name: 'Slate 200' },
		{ color: '#4b5563', name: 'Slate 600' },
		{ color: '#0f766e', name: 'Teal' },
		{ color: '#1d4ed8', name: 'Blue' }
	];

	function getSetting( attributes, key ) {
		var value = attributes[ key ];
		if ( value === undefined || value === null || value === '' || value === 0 ) {
			return DEFAULTS[ key ];
		}
		return value;
	}

	function updateOne( setAttributes, key, value ) {
		var next = {};
		next[ key ] = value;
		setAttributes( next );
	}

	function fetchPosts( params ) {
		return apiFetch( {
			url: REST_URL + '?' + new window.URLSearchParams( params ).toString(),
			headers: { 'X-WP-Nonce': window.mrpBlockData.nonce }
		} );
	}

	function ColorControl( props ) {
		return el( BaseControl, { label: props.label }, el( ColorPalette, {
			colors: COLORS,
			value: props.value || undefined,
			onChange: function ( next ) {
				props.onChange( next || '' );
			}
		} ) );
	}

	function SearchResults( props ) {
		if ( ! props.results.length ) {
			return null;
		}

		return el( 'div', { className: 'mrp-search-results' }, props.results.map( function ( post ) {
			var isSelected = props.selectedIds.indexOf( post.id ) !== -1;
			return el( 'button', {
				key: post.id,
				type: 'button',
				className: 'mrp-search-result' + ( isSelected ? ' is-selected' : '' ),
				onClick: function () {
					if ( ! isSelected ) {
						props.onAdd( post );
					}
				},
				disabled: isSelected
			},
			el( 'span', { className: 'mrp-search-result__title' }, post.title ),
			el( 'span', { className: 'mrp-search-result__meta' }, post.type + ' • ' + post.date )
			);
		} ) );
	}

	function SelectedPosts( props ) {
		if ( ! props.posts.length ) {
			return el( Notice, { status: 'info', isDismissible: false }, __( 'Select posts to build the related posts grid.', 'manual-related-posts-pro' ) );
		}

		return el( 'div', { className: 'mrp-selected-posts' }, props.posts.map( function ( post, index ) {
			return el( 'div', { key: post.id, className: 'mrp-selected-post' },
				post.image ? el( 'img', { className: 'mrp-selected-post__image', src: post.image, alt: '' } ) : el( 'div', { className: 'mrp-selected-post__image mrp-selected-post__image--empty' } ),
				el( 'div', { className: 'mrp-selected-post__content' },
					el( 'strong', null, post.title ),
					el( 'div', { className: 'mrp-selected-post__meta' }, post.type + ' • ' + post.date )
				),
				el( 'div', { className: 'mrp-selected-post__actions' },
					el( Button, { icon: 'arrow-up-alt2', label: __( 'Move up', 'manual-related-posts-pro' ), onClick: function () { props.onMove( index, -1 ); }, disabled: index === 0 } ),
					el( Button, { icon: 'arrow-down-alt2', label: __( 'Move down', 'manual-related-posts-pro' ), onClick: function () { props.onMove( index, 1 ); }, disabled: index === props.posts.length - 1 } ),
					el( Button, { icon: 'trash', label: __( 'Remove', 'manual-related-posts-pro' ), isDestructive: true, onClick: function () { props.onRemove( post.id ); } } )
				)
			);
		} ) );
	}

	blocks.registerBlockType( 'manual-related-posts-pro/manual-related-posts', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var selectedIds = attributes.selectedPosts || [];
			var blockProps = useBlockProps( { className: 'mrp-editor-wrapper' } );
			var searchState = useState( '' );
			var search = searchState[0];
			var setSearch = searchState[1];
			var resultsState = useState( [] );
			var results = resultsState[0];
			var setResults = resultsState[1];
			var postsState = useState( [] );
			var posts = postsState[0];
			var setPosts = postsState[1];
			var loadingState = useState( false );
			var isLoading = loadingState[0];
			var setLoading = loadingState[1];
			var errorState = useState( '' );
			var error = errorState[0];
			var setError = errorState[1];

			useEffect( function () {
				if ( ! selectedIds.length ) {
					setPosts( [] );
					return;
				}
				setLoading( true );
				fetchPosts( { ids: selectedIds } ).then( function ( response ) {
					setPosts( response.selected || [] );
				} ).catch( function () {
					setError( __( 'Unable to load selected posts.', 'manual-related-posts-pro' ) );
				} ).finally( function () {
					setLoading( false );
				} );
			}, [ JSON.stringify( selectedIds ) ] );

			useEffect( function () {
				if ( ! search || search.length < 2 ) {
					setResults( [] );
					return;
				}
				var timer = window.setTimeout( function () {
					setLoading( true );
					fetchPosts( { search: search } ).then( function ( response ) {
						setResults( response.results || [] );
					} ).catch( function () {
						setError( __( 'Unable to search posts right now.', 'manual-related-posts-pro' ) );
					} ).finally( function () {
						setLoading( false );
					} );
				}, 250 );
				return function () { window.clearTimeout( timer ); };
			}, [ search ] );

			function addPost( post ) {
				if ( selectedIds.indexOf( post.id ) !== -1 ) {
					return;
				}
				setAttributes( { selectedPosts: selectedIds.concat( [ post.id ] ) } );
				setPosts( posts.concat( [ post ] ) );
			}

			function removePost( id ) {
				setAttributes( { selectedPosts: selectedIds.filter( function ( current ) { return current !== id; } ) } );
				setPosts( posts.filter( function ( post ) { return post.id !== id; } ) );
			}

			function reorderPosts( fromIndex, toIndex ) {
				if ( fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || toIndex >= selectedIds.length ) {
					return;
				}
				var nextIds = selectedIds.slice();
				var nextPosts = posts.slice();
				var movedId = nextIds.splice( fromIndex, 1 )[0];
				var movedPost = nextPosts.splice( fromIndex, 1 )[0];
				nextIds.splice( toIndex, 0, movedId );
				nextPosts.splice( toIndex, 0, movedPost );
				setAttributes( { selectedPosts: nextIds } );
				setPosts( nextPosts );
			}

			function movePost( index, offset ) {
				var target = index + offset;
				if ( target < 0 || target >= selectedIds.length ) {
					return;
				}
				reorderPosts( index, target );
			}

			var previewStyle = {
				'--mrp-heading-spacing': getSetting( attributes, 'headingSpacing' ) + 'px',
				'--mrp-heading-color': getSetting( attributes, 'sectionTitleColor' ),
				'--mrp-heading-size': getSetting( attributes, 'sectionTitleSize' ) + 'px',
				'--mrp-heading-weight': getSetting( attributes, 'sectionTitleWeight' ),
				'--mrp-heading-align': getSetting( attributes, 'sectionTitleAlign' ),
				'--mrp-subtitle-color': getSetting( attributes, 'sectionSubtitleColor' ),
				'--mrp-subtitle-size': getSetting( attributes, 'sectionSubtitleSize' ) + 'px',
				'--mrp-card-bg': getSetting( attributes, 'cardBackgroundColor' ),
				'--mrp-card-border-color': getSetting( attributes, 'cardBorderColor' ),
				'--mrp-card-border-width': getSetting( attributes, 'cardBorderWidth' ) + 'px',
				'--mrp-card-radius': getSetting( attributes, 'cardBorderRadius' ) + 'px',
				'--mrp-post-title-color': getSetting( attributes, 'postTitleColor' ),
				'--mrp-post-title-size': getSetting( attributes, 'postTitleSize' ) + 'px',
				'--mrp-post-title-weight': getSetting( attributes, 'postTitleWeight' ),
				'--mrp-excerpt-color': getSetting( attributes, 'excerptColor' ),
				'--mrp-excerpt-size': getSetting( attributes, 'excerptSize' ) + 'px',
				'--mrp-button-text-color': getSetting( attributes, 'buttonTextColor' ),
				'--mrp-button-bg': getSetting( attributes, 'buttonBackgroundColor' ),
				'--mrp-button-radius': getSetting( attributes, 'buttonRadius' ) + 'px'
			};

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Content', 'manual-related-posts-pro' ), initialOpen: true },
						el( TextControl, { label: __( 'Section title', 'manual-related-posts-pro' ), value: attributes.sectionTitle || '', onChange: function ( value ) { updateOne( setAttributes, 'sectionTitle', value ); } } ),
						el( TextareaControl, { label: __( 'Section subtitle', 'manual-related-posts-pro' ), value: attributes.sectionSubtitle || '', onChange: function ( value ) { updateOne( setAttributes, 'sectionSubtitle', value ); } } ),
						el( ToggleControl, { label: __( 'Show excerpt', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'showExcerpt' ), onChange: function ( value ) { updateOne( setAttributes, 'showExcerpt', value ); } } ),
						el( ToggleControl, { label: __( 'Show date', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'showDate' ), onChange: function ( value ) { updateOne( setAttributes, 'showDate', value ); } } ),
						el( ToggleControl, { label: __( 'Show category', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'showCategory' ), onChange: function ( value ) { updateOne( setAttributes, 'showCategory', value ); } } )
					),
					el( PanelBody, { title: __( 'Section Header', 'manual-related-posts-pro' ), initialOpen: false },
						el( ColorControl, { label: __( 'Title color', 'manual-related-posts-pro' ), value: attributes.sectionTitleColor || '', onChange: function ( value ) { updateOne( setAttributes, 'sectionTitleColor', value ); } } ),
						el( RangeControl, { label: __( 'Title size', 'manual-related-posts-pro' ), value: attributes.sectionTitleSize || DEFAULTS.sectionTitleSize, onChange: function ( value ) { updateOne( setAttributes, 'sectionTitleSize', value ); }, min: 12, max: 60 } ),
						el( SelectControl, { label: __( 'Title weight', 'manual-related-posts-pro' ), value: attributes.sectionTitleWeight || DEFAULTS.sectionTitleWeight, options: [ { label: '400', value: '400' }, { label: '500', value: '500' }, { label: '600', value: '600' }, { label: '700', value: '700' }, { label: '800', value: '800' } ], onChange: function ( value ) { updateOne( setAttributes, 'sectionTitleWeight', value ); } } ),
						el( SelectControl, { label: __( 'Title alignment', 'manual-related-posts-pro' ), value: attributes.sectionTitleAlign || DEFAULTS.sectionTitleAlign, options: [ { label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' }, { label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' }, { label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' } ], onChange: function ( value ) { updateOne( setAttributes, 'sectionTitleAlign', value ); } } ),
						el( ColorControl, { label: __( 'Subtitle color', 'manual-related-posts-pro' ), value: attributes.sectionSubtitleColor || '', onChange: function ( value ) { updateOne( setAttributes, 'sectionSubtitleColor', value ); } } ),
						el( RangeControl, { label: __( 'Subtitle size', 'manual-related-posts-pro' ), value: attributes.sectionSubtitleSize || DEFAULTS.sectionSubtitleSize, onChange: function ( value ) { updateOne( setAttributes, 'sectionSubtitleSize', value ); }, min: 10, max: 40 } ),
						el( RangeControl, { label: __( 'Spacing below heading', 'manual-related-posts-pro' ), value: attributes.headingSpacing || DEFAULTS.headingSpacing, onChange: function ( value ) { updateOne( setAttributes, 'headingSpacing', value ); }, min: 0, max: 60 } )
					),
					el( PanelBody, { title: __( 'Layout', 'manual-related-posts-pro' ), initialOpen: false },
						el( RangeControl, { label: __( 'Desktop columns', 'manual-related-posts-pro' ), value: attributes.columnsDesktop || DEFAULTS.columnsDesktop, onChange: function ( value ) { updateOne( setAttributes, 'columnsDesktop', value ); }, min: 1, max: 6 } ),
						el( RangeControl, { label: __( 'Tablet columns', 'manual-related-posts-pro' ), value: attributes.columnsTablet || DEFAULTS.columnsTablet, onChange: function ( value ) { updateOne( setAttributes, 'columnsTablet', value ); }, min: 1, max: 4 } ),
						el( RangeControl, { label: __( 'Mobile columns', 'manual-related-posts-pro' ), value: attributes.columnsMobile || DEFAULTS.columnsMobile, onChange: function ( value ) { updateOne( setAttributes, 'columnsMobile', value ); }, min: 1, max: 2 } ),
						el( RangeControl, { label: __( 'Gap', 'manual-related-posts-pro' ), value: attributes.gap || DEFAULTS.gap, onChange: function ( value ) { updateOne( setAttributes, 'gap', value ); }, min: 0, max: 60 } )
					),
					el( PanelBody, { title: __( 'Card', 'manual-related-posts-pro' ), initialOpen: false },
						el( ColorControl, { label: __( 'Background color', 'manual-related-posts-pro' ), value: attributes.cardBackgroundColor || '', onChange: function ( value ) { updateOne( setAttributes, 'cardBackgroundColor', value ); } } ),
						el( ColorControl, { label: __( 'Border color', 'manual-related-posts-pro' ), value: attributes.cardBorderColor || '', onChange: function ( value ) { updateOne( setAttributes, 'cardBorderColor', value ); } } ),
						el( RangeControl, { label: __( 'Border width', 'manual-related-posts-pro' ), value: attributes.cardBorderWidth || DEFAULTS.cardBorderWidth, onChange: function ( value ) { updateOne( setAttributes, 'cardBorderWidth', value ); }, min: 0, max: 8 } ),
						el( RangeControl, { label: __( 'Border radius', 'manual-related-posts-pro' ), value: attributes.cardBorderRadius || DEFAULTS.cardBorderRadius, onChange: function ( value ) { updateOne( setAttributes, 'cardBorderRadius', value ); }, min: 0, max: 40 } ),
						el( RangeControl, { label: __( 'Padding', 'manual-related-posts-pro' ), value: attributes.cardPadding || DEFAULTS.cardPadding, onChange: function ( value ) { updateOne( setAttributes, 'cardPadding', value ); }, min: 0, max: 48 } ),
						el( SelectControl, { label: __( 'Shadow', 'manual-related-posts-pro' ), value: attributes.cardShadow || DEFAULTS.cardShadow, options: [ { label: __( 'None', 'manual-related-posts-pro' ), value: 'none' }, { label: __( 'Soft', 'manual-related-posts-pro' ), value: 'soft' }, { label: __( 'Medium', 'manual-related-posts-pro' ), value: 'medium' }, { label: __( 'Strong', 'manual-related-posts-pro' ), value: 'strong' } ], onChange: function ( value ) { updateOne( setAttributes, 'cardShadow', value ); } } )
					),
					el( PanelBody, { title: __( 'Image', 'manual-related-posts-pro' ), initialOpen: false },
						el( ToggleControl, { label: __( 'Show featured image', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'showImage' ), onChange: function ( value ) { updateOne( setAttributes, 'showImage', value ); } } ),
						el( SelectControl, { label: __( 'Image size', 'manual-related-posts-pro' ), value: attributes.imageSize || DEFAULTS.imageSize, options: [ { label: __( 'Thumbnail', 'manual-related-posts-pro' ), value: 'thumbnail' }, { label: __( 'Medium', 'manual-related-posts-pro' ), value: 'medium' }, { label: __( 'Large', 'manual-related-posts-pro' ), value: 'large' }, { label: __( 'Full', 'manual-related-posts-pro' ), value: 'full' } ], onChange: function ( value ) { updateOne( setAttributes, 'imageSize', value ); } } ),
						el( SelectControl, { label: __( 'Image ratio', 'manual-related-posts-pro' ), value: attributes.imageRatio || DEFAULTS.imageRatio, options: [ { label: __( 'Auto', 'manual-related-posts-pro' ), value: 'auto' }, { label: __( 'Square', 'manual-related-posts-pro' ), value: 'square' }, { label: __( 'Landscape', 'manual-related-posts-pro' ), value: 'landscape' }, { label: __( 'Portrait', 'manual-related-posts-pro' ), value: 'portrait' } ], onChange: function ( value ) { updateOne( setAttributes, 'imageRatio', value ); } } ),
						el( RangeControl, { label: __( 'Image corner radius', 'manual-related-posts-pro' ), value: attributes.imageRadius || DEFAULTS.imageRadius, onChange: function ( value ) { updateOne( setAttributes, 'imageRadius', value ); }, min: 0, max: 40 } )
					),
					el( PanelBody, { title: __( 'Post Title', 'manual-related-posts-pro' ), initialOpen: false },
						el( ColorControl, { label: __( 'Title color', 'manual-related-posts-pro' ), value: attributes.postTitleColor || '', onChange: function ( value ) { updateOne( setAttributes, 'postTitleColor', value ); } } ),
						el( RangeControl, { label: __( 'Title size', 'manual-related-posts-pro' ), value: attributes.postTitleSize || DEFAULTS.postTitleSize, onChange: function ( value ) { updateOne( setAttributes, 'postTitleSize', value ); }, min: 12, max: 40 } ),
						el( SelectControl, { label: __( 'Title weight', 'manual-related-posts-pro' ), value: attributes.postTitleWeight || DEFAULTS.postTitleWeight, options: [ { label: '400', value: '400' }, { label: '500', value: '500' }, { label: '600', value: '600' }, { label: '700', value: '700' }, { label: '800', value: '800' } ], onChange: function ( value ) { updateOne( setAttributes, 'postTitleWeight', value ); } } ),
						el( RangeControl, { label: __( 'Title line clamp', 'manual-related-posts-pro' ), value: attributes.postTitleClamp || DEFAULTS.postTitleClamp, onChange: function ( value ) { updateOne( setAttributes, 'postTitleClamp', value ); }, min: 1, max: 5 } )
					),
					el( PanelBody, { title: __( 'Excerpt / Meta', 'manual-related-posts-pro' ), initialOpen: false },
						el( RangeControl, { label: __( 'Excerpt length', 'manual-related-posts-pro' ), value: attributes.excerptLength || DEFAULTS.excerptLength, onChange: function ( value ) { updateOne( setAttributes, 'excerptLength', value ); }, min: 5, max: 60 } ),
						el( ColorControl, { label: __( 'Excerpt color', 'manual-related-posts-pro' ), value: attributes.excerptColor || '', onChange: function ( value ) { updateOne( setAttributes, 'excerptColor', value ); } } ),
						el( RangeControl, { label: __( 'Excerpt size', 'manual-related-posts-pro' ), value: attributes.excerptSize || DEFAULTS.excerptSize, onChange: function ( value ) { updateOne( setAttributes, 'excerptSize', value ); }, min: 10, max: 28 } )
					),
					el( PanelBody, { title: __( 'Button', 'manual-related-posts-pro' ), initialOpen: false },
						el( ToggleControl, { label: __( 'Show button', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'showButton' ), onChange: function ( value ) { updateOne( setAttributes, 'showButton', value ); } } ),
						el( TextControl, { label: __( 'Button text', 'manual-related-posts-pro' ), value: attributes.buttonText || '', onChange: function ( value ) { updateOne( setAttributes, 'buttonText', value ); } } ),
						el( ColorControl, { label: __( 'Button text color', 'manual-related-posts-pro' ), value: attributes.buttonTextColor || '', onChange: function ( value ) { updateOne( setAttributes, 'buttonTextColor', value ); } } ),
						el( ColorControl, { label: __( 'Button background color', 'manual-related-posts-pro' ), value: attributes.buttonBackgroundColor || '', onChange: function ( value ) { updateOne( setAttributes, 'buttonBackgroundColor', value ); } } ),
						el( RangeControl, { label: __( 'Button radius', 'manual-related-posts-pro' ), value: attributes.buttonRadius || DEFAULTS.buttonRadius, onChange: function ( value ) { updateOne( setAttributes, 'buttonRadius', value ); }, min: 0, max: 999 } )
					),
					el( PanelBody, { title: __( 'Advanced', 'manual-related-posts-pro' ), initialOpen: false },
						el( ToggleControl, { label: __( 'Make entire card clickable', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'fullCardLink' ), onChange: function ( value ) { updateOne( setAttributes, 'fullCardLink', value ); } } ),
						el( ToggleControl, { label: __( 'Open links in new tab', 'manual-related-posts-pro' ), checked: !! getSetting( attributes, 'openInNewTab' ), onChange: function ( value ) { updateOne( setAttributes, 'openInNewTab', value ); } } )
					)
				),
				el( 'div', blockProps,
					el( Placeholder, { icon: 'admin-links', label: __( 'Manual Related Posts', 'manual-related-posts-pro' ), instructions: __( 'Search and select the posts you want to show in this block.', 'manual-related-posts-pro' ) },
						error ? el( Notice, { status: 'error', onRemove: function () { setError( '' ); } }, error ) : null,
						el( TextControl, { label: __( 'Search posts by title', 'manual-related-posts-pro' ), value: search, onChange: setSearch, placeholder: __( 'Start typing to find posts...', 'manual-related-posts-pro' ) } ),
						isLoading ? el( Spinner, null ) : null,
						search && ! isLoading && results.length === 0 ? el( Notice, { status: 'info', isDismissible: false }, __( 'No matching posts found.', 'manual-related-posts-pro' ) ) : null,
						el( SearchResults, { results: results, selectedIds: selectedIds, onAdd: addPost } )
					),
					el( 'div', { className: 'mrp-editor-preview', style: previewStyle },
						el( 'div', { className: 'mrp-block-header' },
							el( 'h2', { className: 'mrp-block-heading' }, attributes.sectionTitle || DEFAULTS.sectionTitle ),
							( attributes.sectionSubtitle || DEFAULTS.sectionSubtitle ) ? el( 'p', { className: 'mrp-block-subtitle' }, attributes.sectionSubtitle || DEFAULTS.sectionSubtitle ) : null
						),
						el( SelectedPosts, { posts: posts, onMove: movePost, onRemove: removePost } )
					)
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.apiFetch );