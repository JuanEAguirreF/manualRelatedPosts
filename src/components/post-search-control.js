/**
 * Post search input and search results list.
 */
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextControl, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function PostSearchControl( { restUrl, nonce, selectedIds, onAdd } ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( ! search || search.length < 2 ) {
			setResults( [] );
			return;
		}

		const timer = setTimeout( () => {
			setIsLoading( true );
			setError( '' );

			apiFetch( {
				url: restUrl + '?' + new URLSearchParams( { search } ).toString(),
				headers: { 'X-WP-Nonce': nonce },
			} )
				.then( ( response ) => {
					setResults( response.results || [] );
				} )
				.catch( () => {
					setError( __( 'Unable to search posts right now.', 'manual-related-posts-pro' ) );
				} )
				.finally( () => {
					setIsLoading( false );
				} );
		}, 250 );

		return () => clearTimeout( timer );
	}, [ search, restUrl, nonce ] );

	return el(
		'div',
		{ className: 'mrp-search-control' },
		error && el( Notice, { status: 'error', onRemove: () => setError( '' ) }, error ),
		el( TextControl, {
			label: __( 'Search posts by title', 'manual-related-posts-pro' ),
			value: search,
			onChange: setSearch,
			placeholder: __( 'Start typing to find posts...', 'manual-related-posts-pro' ),
		} ),
		isLoading && el( Spinner, null ),
		search && ! isLoading && results.length === 0 &&
			el( Notice, { status: 'info', isDismissible: false }, __( 'No matching posts found.', 'manual-related-posts-pro' ) ),
		results.length > 0 &&
			el(
				'div',
				{ className: 'mrp-search-results' },
				results.map( ( post ) => {
					const isSelected = selectedIds.indexOf( post.id ) !== -1;
					return el(
						'button',
						{
							key: post.id,
							type: 'button',
							className: 'mrp-search-result' + ( isSelected ? ' is-selected' : '' ),
							onClick: () => {
								if ( ! isSelected ) {
									onAdd( post );
								}
							},
							disabled: isSelected,
						},
						el( 'span', { className: 'mrp-search-result__title' }, post.title ),
						el(
							'span',
							{ className: 'mrp-search-result__meta' },
							post.type + ' • ' + post.date + ( post.series ? ' [' + post.series.name + ']' : '' )
						)
					);
				} )
			)
	);
}
