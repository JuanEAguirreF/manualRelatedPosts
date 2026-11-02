/**
 * Block editor edit component for Manual Related Posts Pro 2.0.
 */
import { createElement as el, Fragment, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import PostSearchControl from './components/post-search-control';
import SelectedPostsList from './components/selected-posts-list';
import SeriesControl from './components/series-control';
import StylePanels from './components/style-panels';

const DEFAULTS = window.mrpBlockData && window.mrpBlockData.defaults ? window.mrpBlockData.defaults : {};
const REST_URL = window.mrpBlockData && window.mrpBlockData.restUrl ? window.mrpBlockData.restUrl : '';
const SERIES_CHECK_URL = window.mrpBlockData && window.mrpBlockData.seriesCheckUrl ? window.mrpBlockData.seriesCheckUrl : '';
const UNLINK_SERIES_URL = window.mrpBlockData && window.mrpBlockData.unlinkSeriesUrl ? window.mrpBlockData.unlinkSeriesUrl : '';
const RELINK_SERIES_URL = window.mrpBlockData && window.mrpBlockData.relinkSeriesUrl ? window.mrpBlockData.relinkSeriesUrl : '';
const NONCE = window.mrpBlockData && window.mrpBlockData.nonce ? window.mrpBlockData.nonce : '';

function getSetting( attributes, key ) {
	const value = attributes[ key ];
	if ( value === undefined || value === null || value === '' ) {
		return DEFAULTS[ key ];
	}
	return value;
}

export default function Edit( props ) {
	const { attributes, setAttributes } = props;
	const selectedIds = attributes.selectedPosts || [];
	const mode = attributes.mode || 'classic';
	const postId = props.context ? props.context.postId : null;
	const blockProps = useBlockProps( {
		className: 'mrp-editor-wrapper mrp-mode-' + mode,
	} );

	const [ posts, setPosts ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( ! selectedIds.length ) {
			setPosts( [] );
			return;
		}

		setIsLoading( true );
		apiFetch( {
			url: REST_URL + '?' + new URLSearchParams( { ids: selectedIds } ).toString(),
			headers: { 'X-WP-Nonce': NONCE },
		} )
			.then( ( response ) => {
				setPosts( response.selected || [] );
			} )
			.catch( () => {
				setError( __( 'Unable to load selected posts.', 'manual-related-posts-pro' ) );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ JSON.stringify( selectedIds ) ] );

	const addPost = ( post ) => {
		if ( selectedIds.indexOf( post.id ) !== -1 ) {
			return;
		}
		setAttributes( { selectedPosts: selectedIds.concat( [ post.id ] ) } );
		setPosts( posts.concat( [ post ] ) );
	};

	const removePost = ( id ) => {
		setAttributes( { selectedPosts: selectedIds.filter( ( current ) => current !== id ) } );
		setPosts( posts.filter( ( post ) => post.id !== id ) );
	};

	const reorderPosts = ( fromIndex, toIndex ) => {
		if ( fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || toIndex >= selectedIds.length ) {
			return;
		}
		const nextIds = selectedIds.slice();
		const nextPosts = posts.slice();
		const movedId = nextIds.splice( fromIndex, 1 )[ 0 ];
		const movedPost = nextPosts.splice( fromIndex, 1 )[ 0 ];
		nextIds.splice( toIndex, 0, movedId );
		nextPosts.splice( toIndex, 0, movedPost );
		setAttributes( { selectedPosts: nextIds } );
		setPosts( nextPosts );
	};

	const movePost = ( index, offset ) => {
		const target = index + offset;
		if ( target < 0 || target >= selectedIds.length ) {
			return;
		}
		reorderPosts( index, target );
	};

	const previewStyle = {
		'--mrp-heading-spacing': getSetting( attributes, 'headingSpacing' ) + 'px',
		'--mrp-heading-color': getSetting( attributes, 'sectionTitleColor' ),
		'--mrp-heading-size': getSetting( attributes, 'sectionTitleSize' ) + 'px',
		'--mrp-heading-title-gap': getSetting( attributes, 'sectionTitleMarginBottom' ) + 'px',
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
		'--mrp-post-title-margin-bottom': getSetting( attributes, 'postTitleMarginBottom' ) + 'px',
		'--mrp-post-title-weight': getSetting( attributes, 'postTitleWeight' ),
		'--mrp-excerpt-color': getSetting( attributes, 'excerptColor' ),
		'--mrp-excerpt-size': getSetting( attributes, 'excerptSize' ) + 'px',
		'--mrp-button-text-color': getSetting( attributes, 'buttonTextColor' ),
		'--mrp-button-bg': getSetting( attributes, 'buttonBackgroundColor' ),
		'--mrp-button-radius': getSetting( attributes, 'buttonRadius' ) + 'px',
	};

	const modeLabel = mode === 'series'
		? __( 'Same Series (Chapters)', 'manual-related-posts-pro' )
		: mode === 'spinoff'
		? __( 'Related Content (Spin-offs)', 'manual-related-posts-pro' )
		: __( 'Classic Manual Posts', 'manual-related-posts-pro' );

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el( SeriesControl, {
				attributes,
				setAttributes,
				seriesCheckUrl: SERIES_CHECK_URL,
				unlinkSeriesUrl: UNLINK_SERIES_URL,
				relinkSeriesUrl: RELINK_SERIES_URL,
				nonce: NONCE,
				postId,
			} ),
			el( StylePanels, {
				attributes,
				setAttributes,
				getSetting,
				defaults: DEFAULTS,
			} )
		),
		el(
			'div',
			blockProps,
			el(
				Placeholder,
				{
					icon: 'admin-links',
					label: __( 'Manual Related Posts', 'manual-related-posts-pro' ) + ' — ' + modeLabel,
					instructions: mode === 'series'
						? __( 'Select chapters of the same series. The plugin will link them bidirectionally and propagate automatically.', 'manual-related-posts-pro' )
						: mode === 'spinoff'
						? __( 'Select spin-offs or sequels. These will remain independent and never merge series chapters.', 'manual-related-posts-pro' )
						: __( 'Search and select posts to display in this block.', 'manual-related-posts-pro' ),
				},
				error && el( Notice, { status: 'error', onRemove: () => setError( '' ) }, error ),
				el( PostSearchControl, {
					restUrl: REST_URL,
					nonce: NONCE,
					selectedIds,
					onAdd: addPost,
				} )
			),
			el(
				'div',
				{ className: 'mrp-editor-preview', style: previewStyle },
				el(
					'div',
					{ className: 'mrp-block-header' },
					el(
						attributes.sectionTitleTag || DEFAULTS.sectionTitleTag || 'h2',
						{ className: 'mrp-block-heading' },
						attributes.sectionTitle || DEFAULTS.sectionTitle
					),
					( attributes.sectionSubtitle || DEFAULTS.sectionSubtitle ) &&
						el(
							'p',
							{ className: 'mrp-block-subtitle' },
							attributes.sectionSubtitle || DEFAULTS.sectionSubtitle
						)
				),
				el( SelectedPostsList, {
					posts,
					onMove: movePost,
					onRemove: removePost,
					mode,
				} )
			)
		)
	);
}
