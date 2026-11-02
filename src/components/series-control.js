/**
 * Series detection, conflict safeguards, and episode settings control.
 */
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PanelBody, SelectControl, TextControl, ToggleControl, Notice, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function SeriesControl( {
	attributes,
	setAttributes,
	seriesCheckUrl,
	unlinkSeriesUrl,
	relinkSeriesUrl,
	nonce,
	postId,
} ) {
	const { mode = 'classic', episodeNumber = '', seriesTitle = '', isReciprocal = false, selectedPosts = [], relinkConfirmed = false } = attributes;
	const [ seriesInfo, setSeriesInfo ] = useState( null );
	const [ isChecking, setIsChecking ] = useState( false );
	const [ isUnlinking, setIsUnlinking ] = useState( false );
	const [ isRelinking, setIsRelinking ] = useState( false );
	const [ unlinkMessage, setUnlinkMessage ] = useState( '' );

	// Single-use confirmation safeguard: reset relinkConfirmed whenever selection or mode changes!
	useEffect( () => {
		if ( relinkConfirmed ) {
			setAttributes( { relinkConfirmed: false } );
		}
	}, [ JSON.stringify( selectedPosts ), mode ] );

	useEffect( () => {
		if ( mode !== 'series' || ! selectedPosts.length || ! seriesCheckUrl ) {
			setSeriesInfo( null );
			return;
		}

		setIsChecking( true );
		apiFetch( {
			url: seriesCheckUrl,
			method: 'POST',
			headers: { 'X-WP-Nonce': nonce },
			data: { ids: selectedPosts, postId: postId },
		} )
			.then( ( data ) => {
				setSeriesInfo( data );
			} )
			.catch( () => {
				setSeriesInfo( null );
			} )
			.finally( () => {
				setIsChecking( false );
			} );
	}, [ mode, JSON.stringify( selectedPosts ), seriesCheckUrl, nonce, postId ] );

	const handleRelink = () => {
		if ( ! relinkSeriesUrl || ! seriesInfo || ! seriesInfo.seriesList || ! seriesInfo.seriesList[0] ) {
			setAttributes( { relinkConfirmed: true } );
			return;
		}

		setIsRelinking( true );
		const unlinkedIds = ( seriesInfo.unlinkedPosts || [] ).map( ( p ) => p.id );

		apiFetch( {
			url: relinkSeriesUrl,
			method: 'POST',
			headers: { 'X-WP-Nonce': nonce },
			data: {
				ids: unlinkedIds,
				series_id: seriesInfo.seriesList[0].id,
			},
		} )
			.then( () => {
				setAttributes( { relinkConfirmed: true } );
				// Refresh series detection so unlinked warning clears cleanly
				setIsChecking( true );
				apiFetch( {
					url: seriesCheckUrl,
					method: 'POST',
					headers: { 'X-WP-Nonce': nonce },
					data: { ids: selectedPosts, postId: postId },
				} )
					.then( ( data ) => setSeriesInfo( data ) )
					.finally( () => setIsChecking( false ) );
			} )
			.catch( () => {
				setAttributes( { relinkConfirmed: true } );
			} )
			.finally( () => {
				setIsRelinking( false );
			} );
	};

	const handleUnlink = () => {
		if ( ! unlinkSeriesUrl || ! postId ) {
			return;
		}

		setIsUnlinking( true );
		setUnlinkMessage( '' );

		apiFetch( {
			url: unlinkSeriesUrl,
			method: 'POST',
			headers: { 'X-WP-Nonce': nonce },
			data: { id: postId },
		} )
			.then( ( res ) => {
				setUnlinkMessage( res.message || __( 'Post unlinked from series.', 'manual-related-posts-pro' ) );
				setSeriesInfo( null );
				setAttributes( {
					mode: 'classic',
					episodeNumber: '',
					relinkConfirmed: false,
				} );
			} )
			.catch( () => {
				setUnlinkMessage( __( 'Failed to unlink post from series.', 'manual-related-posts-pro' ) );
			} )
			.finally( () => {
				setIsUnlinking( false );
			} );
	};

	const modeOptions = [
		{ label: __( 'Classic manual selection', 'manual-related-posts-pro' ), value: 'classic' },
		{ label: __( 'Same series (Chapters)', 'manual-related-posts-pro' ), value: 'series' },
		{ label: __( 'Related content (Spin-offs & Sequels)', 'manual-related-posts-pro' ), value: 'spinoff' },
	];

	return el(
		PanelBody,
		{ title: __( 'Relationship Mode', 'manual-related-posts-pro' ), initialOpen: true },
		el( SelectControl, {
			label: __( 'Select relationship type', 'manual-related-posts-pro' ),
			value: mode,
			options: modeOptions,
			help: mode === 'series'
				? __( 'Posts in the same series belong to a shared group. Previous chapters will automatically show this chapter without editing them.', 'manual-related-posts-pro' )
				: mode === 'spinoff'
				? __( 'Independent relationships. Spin-offs never merge series or affect chapter numbering.', 'manual-related-posts-pro' )
				: __( 'Standard manual post selection without series propagation.', 'manual-related-posts-pro' ),
			onChange: ( nextMode ) => setAttributes( { mode: nextMode } ),
		} ),

		mode === 'series' && el(
			'div',
			{ className: 'mrp-series-settings' },
			seriesInfo && seriesInfo.lastSyncError && el(
				Notice,
				{ status: 'error', isDismissible: true },
				el( 'strong', null, __( 'Error al sincronizar la serie:', 'manual-related-posts-pro' ) ),
				el( 'p', { style: { margin: '4px 0 0 0' } }, seriesInfo.lastSyncError.message || seriesInfo.lastSyncError.error )
			),

			isChecking && el( Notice, { status: 'info', isDismissible: false }, __( 'Checking series membership...', 'manual-related-posts-pro' ) ),

			seriesInfo && seriesInfo.detected && ! seriesInfo.hasConflict && el(
				Notice,
				{ status: 'success', isDismissible: false },
				__( 'Series detected:', 'manual-related-posts-pro' ) + ' ' +
				( seriesInfo.seriesList[0] ? seriesInfo.seriesList[0].name : '' ) + ' ' +
				( seriesInfo.members ? '(' + seriesInfo.members.length + ' ' + __( 'chapters linked', 'manual-related-posts-pro' ) + ')' : '' )
			),

			seriesInfo && seriesInfo.hasConflict && el(
				'div',
				{ className: 'mrp-conflict-warning' },
				el(
					Notice,
					{ status: 'error', isDismissible: false },
					el( 'strong', null, __( 'Conflict: Multiple distinct series detected!', 'manual-related-posts-pro' ) ),
					el( 'p', null, __( 'Selected posts belong to different series groups. To prevent data corruption, merging distinct series is blocked. Please deselect chapters from one of the series.', 'manual-related-posts-pro' ) )
				)
			),

			seriesInfo && seriesInfo.unlinkedPosts && seriesInfo.unlinkedPosts.length > 0 && el(
				Notice,
				{ status: 'warning', isDismissible: false },
				el( 'p', null,
					__( 'Uno o más capítulos seleccionados fueron desvinculados previamente de esta serie: ', 'manual-related-posts-pro' ),
					el( 'strong', null, seriesInfo.unlinkedPosts.map( ( p ) => p.title || ( '#' + p.id ) ).join( ', ' ) )
				),
				relinkConfirmed ? el(
					'p',
					{ style: { color: '#008a20', fontWeight: 'bold', margin: '4px 0 0 0' } },
					__( '✓ Reincorporación confirmada. Se reincorporarán al guardar.', 'manual-related-posts-pro' )
				) : el(
					Button,
					{
						variant: 'primary',
						isSmall: true,
						isBusy: isRelinking,
						disabled: isRelinking,
						style: { marginTop: '8px' },
						onClick: handleRelink,
					},
					__( 'Reincorporar a la serie', 'manual-related-posts-pro' )
				)
			),

			el( TextControl, {
				label: __( 'Current post episode number', 'manual-related-posts-pro' ),
				value: episodeNumber,
				placeholder: __( 'e.g. 7 or 7.5', 'manual-related-posts-pro' ),
				help: __( 'Used to order chapters within this series (1, 2, 3...). If blank, post date is used.', 'manual-related-posts-pro' ),
				onChange: ( val ) => setAttributes( { episodeNumber: val } ),
			} ),

			el( TextControl, {
				label: __( 'Series group name', 'manual-related-posts-pro' ),
				value: seriesTitle,
				placeholder: __( 'Leave blank to use post title', 'manual-related-posts-pro' ),
				help: __( 'Optional custom name when creating a new series group.', 'manual-related-posts-pro' ),
				onChange: ( val ) => setAttributes( { seriesTitle: val } ),
			} ),

			postId && el(
				'div',
				{ className: 'mrp-unlink-action', style: { marginTop: '12px', borderTop: '1px solid #e5e7eb', paddingTop: '12px' } },
				unlinkMessage && el( Notice, { status: 'info', onRemove: () => setUnlinkMessage( '' ) }, unlinkMessage ),
				el( Button, {
					variant: 'secondary',
					isDestructive: true,
					isBusy: isUnlinking,
					disabled: isUnlinking,
					onClick: handleUnlink,
				}, __( 'Unlink this post from series', 'manual-related-posts-pro' ) )
			)
		),

		mode === 'spinoff' && el(
			'div',
			{ className: 'mrp-spinoff-settings' },
			el(
				Notice,
				{ status: 'info', isDismissible: false },
				__( 'Spin-offs remain independent. Linking a spin-off does not merge series chapters.', 'manual-related-posts-pro' )
			),
			el( ToggleControl, {
				label: __( 'Reciprocal relationship', 'manual-related-posts-pro' ),
				checked: !! isReciprocal,
				help: __( 'If enabled, this post will also appear in the related posts section of the selected posts without merging series.', 'manual-related-posts-pro' ),
				onChange: ( val ) => setAttributes( { isReciprocal: val } ),
			} )
		)
	);
}
