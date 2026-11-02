/**
 * Selected posts card list with move up/down and remove actions.
 */
import { createElement as el } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';

export default function SelectedPostsList( { posts, onMove, onRemove, mode } ) {
	if ( ! posts || ! posts.length ) {
		const emptyMessage = mode === 'series'
			? __( 'Select an existing chapter to automatically link to its series, or select previous chapters to start a new series.', 'manual-related-posts-pro' )
			: __( 'Select posts to build the related posts grid.', 'manual-related-posts-pro' );

		return el( Notice, { status: 'info', isDismissible: false }, emptyMessage );
	}

	return el(
		'div',
		{ className: 'mrp-selected-posts' },
		posts.map( ( post, index ) =>
			el(
				'div',
				{ key: post.id, className: 'mrp-selected-post' },
				post.image
					? el( 'img', { className: 'mrp-selected-post__image', src: post.image, alt: '' } )
					: el( 'div', { className: 'mrp-selected-post__image mrp-selected-post__image--empty' } ),
				el(
					'div',
					{ className: 'mrp-selected-post__content' },
					el( 'strong', null, post.title ),
					el(
						'div',
						{ className: 'mrp-selected-post__meta' },
						post.type + ' • ' + post.date +
						( post.episodeNumber ? ' • ' + __( 'Ep.', 'manual-related-posts-pro' ) + ' ' + post.episodeNumber : '' ) +
						( post.series ? ' • ' + post.series.name : '' )
					)
				),
				el(
					'div',
					{ className: 'mrp-selected-post__actions' },
					el( Button, {
						icon: 'arrow-up-alt2',
						label: __( 'Move up', 'manual-related-posts-pro' ),
						onClick: () => onMove( index, -1 ),
						disabled: index === 0,
					} ),
					el( Button, {
						icon: 'arrow-down-alt2',
						label: __( 'Move down', 'manual-related-posts-pro' ),
						onClick: () => onMove( index, 1 ),
						disabled: index === posts.length - 1,
					} ),
					el( Button, {
						icon: 'trash',
						label: __( 'Remove', 'manual-related-posts-pro' ),
						isDestructive: true,
						onClick: () => onRemove( post.id ),
					} )
				)
			)
		)
	);
}
