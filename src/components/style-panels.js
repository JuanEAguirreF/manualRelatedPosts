/**
 * Style and typography inspector panels for Manual Related Posts Pro.
 */
import { createElement as el } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	RangeControl,
	SelectControl,
	ToggleControl,
	ColorPalette,
	BaseControl,
} from '@wordpress/components';

const COLORS = [
	{ color: '#111827', name: 'Ink' },
	{ color: '#ffffff', name: 'White' },
	{ color: '#f9fafb', name: 'Slate 50' },
	{ color: '#e5e7eb', name: 'Slate 200' },
	{ color: '#4b5563', name: 'Slate 600' },
	{ color: '#0f766e', name: 'Teal' },
	{ color: '#1d4ed8', name: 'Blue' },
];

function ColorControl( { label, value, onChange } ) {
	return el(
		BaseControl,
		{ label },
		el( ColorPalette, {
			colors: COLORS,
			value: value || undefined,
			onChange: ( next ) => onChange( next || '' ),
		} )
	);
}

export default function StylePanels( { attributes, setAttributes, getSetting, defaults } ) {
	const updateOne = ( key, value ) => {
		const next = {};
		next[ key ] = value;
		setAttributes( next );
	};

	return [
		el(
			PanelBody,
			{ key: 'content', title: __( 'Content', 'manual-related-posts-pro' ), initialOpen: false },
			el( TextControl, {
				label: __( 'Section title', 'manual-related-posts-pro' ),
				value: attributes.sectionTitle || '',
				onChange: ( val ) => updateOne( 'sectionTitle', val ),
			} ),
			el( TextareaControl, {
				label: __( 'Section subtitle', 'manual-related-posts-pro' ),
				value: attributes.sectionSubtitle || '',
				onChange: ( val ) => updateOne( 'sectionSubtitle', val ),
			} ),
			el( ToggleControl, {
				label: __( 'Show excerpt', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'showExcerpt' ),
				onChange: ( val ) => updateOne( 'showExcerpt', val ),
			} ),
			el( ToggleControl, {
				label: __( 'Show date', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'showDate' ),
				onChange: ( val ) => updateOne( 'showDate', val ),
			} ),
			el( ToggleControl, {
				label: __( 'Show category', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'showCategory' ),
				onChange: ( val ) => updateOne( 'showCategory', val ),
			} )
		),

		el(
			PanelBody,
			{ key: 'header', title: __( 'Section Header', 'manual-related-posts-pro' ), initialOpen: false },
			el( ColorControl, {
				label: __( 'Title color', 'manual-related-posts-pro' ),
				value: attributes.sectionTitleColor || '',
				onChange: ( val ) => updateOne( 'sectionTitleColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Title size', 'manual-related-posts-pro' ),
				value: attributes.sectionTitleSize || defaults.sectionTitleSize,
				onChange: ( val ) => updateOne( 'sectionTitleSize', val ),
				min: 12,
				max: 60,
			} ),
			el( RangeControl, {
				label: __( 'Title bottom margin', 'manual-related-posts-pro' ),
				value: getSetting( attributes, 'sectionTitleMarginBottom' ),
				onChange: ( val ) => updateOne( 'sectionTitleMarginBottom', val ),
				min: 0,
				max: 80,
			} ),
			el( SelectControl, {
				label: __( 'Title tag', 'manual-related-posts-pro' ),
				value: attributes.sectionTitleTag || defaults.sectionTitleTag,
				options: [
					{ label: 'H1', value: 'h1' },
					{ label: 'H2', value: 'h2' },
					{ label: 'H3', value: 'h3' },
					{ label: 'H4', value: 'h4' },
					{ label: 'H5', value: 'h5' },
					{ label: 'H6', value: 'h6' },
					{ label: 'DIV', value: 'div' },
				],
				onChange: ( val ) => updateOne( 'sectionTitleTag', val ),
			} ),
			el( SelectControl, {
				label: __( 'Title weight', 'manual-related-posts-pro' ),
				value: attributes.sectionTitleWeight || defaults.sectionTitleWeight,
				options: [
					{ label: '400', value: '400' },
					{ label: '500', value: '500' },
					{ label: '600', value: '600' },
					{ label: '700', value: '700' },
					{ label: '800', value: '800' },
				],
				onChange: ( val ) => updateOne( 'sectionTitleWeight', val ),
			} ),
			el( SelectControl, {
				label: __( 'Title alignment', 'manual-related-posts-pro' ),
				value: attributes.sectionTitleAlign || defaults.sectionTitleAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'sectionTitleAlign', val ),
			} ),
			el( ColorControl, {
				label: __( 'Subtitle color', 'manual-related-posts-pro' ),
				value: attributes.sectionSubtitleColor || '',
				onChange: ( val ) => updateOne( 'sectionSubtitleColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Subtitle size', 'manual-related-posts-pro' ),
				value: attributes.sectionSubtitleSize || defaults.sectionSubtitleSize,
				onChange: ( val ) => updateOne( 'sectionSubtitleSize', val ),
				min: 10,
				max: 40,
			} ),
			el( SelectControl, {
				label: __( 'Subtitle alignment', 'manual-related-posts-pro' ),
				value: attributes.sectionSubtitleAlign || defaults.sectionSubtitleAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'sectionSubtitleAlign', val ),
			} ),
			el( RangeControl, {
				label: __( 'Spacing below heading', 'manual-related-posts-pro' ),
				value: attributes.headingSpacing || defaults.headingSpacing,
				onChange: ( val ) => updateOne( 'headingSpacing', val ),
				min: 0,
				max: 60,
			} )
		),

		el(
			PanelBody,
			{ key: 'layout', title: __( 'Layout', 'manual-related-posts-pro' ), initialOpen: false },
			el( RangeControl, {
				label: __( 'Desktop columns', 'manual-related-posts-pro' ),
				value: attributes.columnsDesktop || defaults.columnsDesktop,
				onChange: ( val ) => updateOne( 'columnsDesktop', val ),
				min: 1,
				max: 6,
			} ),
			el( RangeControl, {
				label: __( 'Tablet columns', 'manual-related-posts-pro' ),
				value: attributes.columnsTablet || defaults.columnsTablet,
				onChange: ( val ) => updateOne( 'columnsTablet', val ),
				min: 1,
				max: 4,
			} ),
			el( RangeControl, {
				label: __( 'Mobile columns', 'manual-related-posts-pro' ),
				value: attributes.columnsMobile || defaults.columnsMobile,
				onChange: ( val ) => updateOne( 'columnsMobile', val ),
				min: 1,
				max: 2,
			} ),
			el( RangeControl, {
				label: __( 'Gap', 'manual-related-posts-pro' ),
				value: attributes.gap || defaults.gap,
				onChange: ( val ) => updateOne( 'gap', val ),
				min: 0,
				max: 60,
			} ),
			el( SelectControl, {
				label: __( 'Incomplete row alignment', 'manual-related-posts-pro' ),
				value: attributes.blockAlign || defaults.blockAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'blockAlign', val ),
			} )
		),

		el(
			PanelBody,
			{ key: 'card', title: __( 'Card', 'manual-related-posts-pro' ), initialOpen: false },
			el( ColorControl, {
				label: __( 'Background color', 'manual-related-posts-pro' ),
				value: attributes.cardBackgroundColor || '',
				onChange: ( val ) => updateOne( 'cardBackgroundColor', val ),
			} ),
			el( ColorControl, {
				label: __( 'Border color', 'manual-related-posts-pro' ),
				value: attributes.cardBorderColor || '',
				onChange: ( val ) => updateOne( 'cardBorderColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Border width', 'manual-related-posts-pro' ),
				value: attributes.cardBorderWidth || defaults.cardBorderWidth,
				onChange: ( val ) => updateOne( 'cardBorderWidth', val ),
				min: 0,
				max: 8,
			} ),
			el( RangeControl, {
				label: __( 'Border radius', 'manual-related-posts-pro' ),
				value: attributes.cardBorderRadius || defaults.cardBorderRadius,
				onChange: ( val ) => updateOne( 'cardBorderRadius', val ),
				min: 0,
				max: 40,
			} ),
			el( RangeControl, {
				label: __( 'Padding', 'manual-related-posts-pro' ),
				value: attributes.cardPadding || defaults.cardPadding,
				onChange: ( val ) => updateOne( 'cardPadding', val ),
				min: 0,
				max: 48,
			} ),
			el( SelectControl, {
				label: __( 'Shadow', 'manual-related-posts-pro' ),
				value: attributes.cardShadow || defaults.cardShadow,
				options: [
					{ label: __( 'None', 'manual-related-posts-pro' ), value: 'none' },
					{ label: __( 'Soft', 'manual-related-posts-pro' ), value: 'soft' },
					{ label: __( 'Medium', 'manual-related-posts-pro' ), value: 'medium' },
					{ label: __( 'Strong', 'manual-related-posts-pro' ), value: 'strong' },
				],
				onChange: ( val ) => updateOne( 'cardShadow', val ),
			} ),
			el( SelectControl, {
				label: __( 'Content alignment', 'manual-related-posts-pro' ),
				value: attributes.cardContentAlign || defaults.cardContentAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'cardContentAlign', val ),
			} )
		),

		el(
			PanelBody,
			{ key: 'image', title: __( 'Image', 'manual-related-posts-pro' ), initialOpen: false },
			el( ToggleControl, {
				label: __( 'Show featured image', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'showImage' ),
				onChange: ( val ) => updateOne( 'showImage', val ),
			} ),
			el( SelectControl, {
				label: __( 'Image size', 'manual-related-posts-pro' ),
				value: attributes.imageSize || defaults.imageSize,
				options: [
					{ label: __( 'Thumbnail', 'manual-related-posts-pro' ), value: 'thumbnail' },
					{ label: __( 'Medium', 'manual-related-posts-pro' ), value: 'medium' },
					{ label: __( 'Large', 'manual-related-posts-pro' ), value: 'large' },
					{ label: __( 'Full', 'manual-related-posts-pro' ), value: 'full' },
				],
				onChange: ( val ) => updateOne( 'imageSize', val ),
			} ),
			el( SelectControl, {
				label: __( 'Image ratio', 'manual-related-posts-pro' ),
				value: attributes.imageRatio || defaults.imageRatio,
				options: [
					{ label: __( 'Auto', 'manual-related-posts-pro' ), value: 'auto' },
					{ label: __( 'Square', 'manual-related-posts-pro' ), value: 'square' },
					{ label: __( 'Landscape', 'manual-related-posts-pro' ), value: 'landscape' },
					{ label: __( 'Portrait', 'manual-related-posts-pro' ), value: 'portrait' },
				],
				onChange: ( val ) => updateOne( 'imageRatio', val ),
			} ),
			el( RangeControl, {
				label: __( 'Image corner radius', 'manual-related-posts-pro' ),
				value: attributes.imageRadius || defaults.imageRadius,
				onChange: ( val ) => updateOne( 'imageRadius', val ),
				min: 0,
				max: 40,
			} ),
			el( RangeControl, {
				label: __( 'Fixed image height', 'manual-related-posts-pro' ),
				value: attributes.imageHeight || defaults.imageHeight,
				onChange: ( val ) => updateOne( 'imageHeight', val ),
				min: 0,
				max: 800,
			} )
		),

		el(
			PanelBody,
			{ key: 'postTitle', title: __( 'Post Title', 'manual-related-posts-pro' ), initialOpen: false },
			el( ColorControl, {
				label: __( 'Title color', 'manual-related-posts-pro' ),
				value: attributes.postTitleColor || '',
				onChange: ( val ) => updateOne( 'postTitleColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Title size', 'manual-related-posts-pro' ),
				value: attributes.postTitleSize || defaults.postTitleSize,
				onChange: ( val ) => updateOne( 'postTitleSize', val ),
				min: 12,
				max: 40,
			} ),
			el( RangeControl, {
				label: __( 'Title bottom margin', 'manual-related-posts-pro' ),
				value: getSetting( attributes, 'postTitleMarginBottom' ),
				onChange: ( val ) => updateOne( 'postTitleMarginBottom', val ),
				min: 0,
				max: 80,
			} ),
			el( SelectControl, {
				label: __( 'Title weight', 'manual-related-posts-pro' ),
				value: attributes.postTitleWeight || defaults.postTitleWeight,
				options: [
					{ label: '400', value: '400' },
					{ label: '500', value: '500' },
					{ label: '600', value: '600' },
					{ label: '700', value: '700' },
					{ label: '800', value: '800' },
				],
				onChange: ( val ) => updateOne( 'postTitleWeight', val ),
			} ),
			el( SelectControl, {
				label: __( 'Title alignment', 'manual-related-posts-pro' ),
				value: attributes.postTitleAlign || defaults.postTitleAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'postTitleAlign', val ),
			} ),
			el( RangeControl, {
				label: __( 'Title line clamp', 'manual-related-posts-pro' ),
				value: attributes.postTitleClamp || defaults.postTitleClamp,
				onChange: ( val ) => updateOne( 'postTitleClamp', val ),
				min: 1,
				max: 5,
			} )
		),

		el(
			PanelBody,
			{ key: 'excerptMeta', title: __( 'Excerpt / Meta', 'manual-related-posts-pro' ), initialOpen: false },
			el( RangeControl, {
				label: __( 'Excerpt length', 'manual-related-posts-pro' ),
				value: attributes.excerptLength || defaults.excerptLength,
				onChange: ( val ) => updateOne( 'excerptLength', val ),
				min: 5,
				max: 60,
			} ),
			el( ColorControl, {
				label: __( 'Excerpt color', 'manual-related-posts-pro' ),
				value: attributes.excerptColor || '',
				onChange: ( val ) => updateOne( 'excerptColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Excerpt size', 'manual-related-posts-pro' ),
				value: attributes.excerptSize || defaults.excerptSize,
				onChange: ( val ) => updateOne( 'excerptSize', val ),
				min: 10,
				max: 28,
			} )
		),

		el(
			PanelBody,
			{ key: 'button', title: __( 'Button', 'manual-related-posts-pro' ), initialOpen: false },
			el( ToggleControl, {
				label: __( 'Show button', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'showButton' ),
				onChange: ( val ) => updateOne( 'showButton', val ),
			} ),
			el( TextControl, {
				label: __( 'Button text', 'manual-related-posts-pro' ),
				value: attributes.buttonText || '',
				onChange: ( val ) => updateOne( 'buttonText', val ),
			} ),
			el( ColorControl, {
				label: __( 'Button text color', 'manual-related-posts-pro' ),
				value: attributes.buttonTextColor || '',
				onChange: ( val ) => updateOne( 'buttonTextColor', val ),
			} ),
			el( ColorControl, {
				label: __( 'Button background color', 'manual-related-posts-pro' ),
				value: attributes.buttonBackgroundColor || '',
				onChange: ( val ) => updateOne( 'buttonBackgroundColor', val ),
			} ),
			el( RangeControl, {
				label: __( 'Button radius', 'manual-related-posts-pro' ),
				value: attributes.buttonRadius || defaults.buttonRadius,
				onChange: ( val ) => updateOne( 'buttonRadius', val ),
				min: 0,
				max: 999,
			} ),
			el( SelectControl, {
				label: __( 'Button alignment', 'manual-related-posts-pro' ),
				value: attributes.buttonAlign || defaults.buttonAlign,
				options: [
					{ label: __( 'Left', 'manual-related-posts-pro' ), value: 'left' },
					{ label: __( 'Center', 'manual-related-posts-pro' ), value: 'center' },
					{ label: __( 'Right', 'manual-related-posts-pro' ), value: 'right' },
				],
				onChange: ( val ) => updateOne( 'buttonAlign', val ),
			} )
		),

		el(
			PanelBody,
			{ key: 'advanced', title: __( 'Advanced', 'manual-related-posts-pro' ), initialOpen: false },
			el( ToggleControl, {
				label: __( 'Make entire card clickable', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'fullCardLink' ),
				onChange: ( val ) => updateOne( 'fullCardLink', val ),
			} ),
			el( ToggleControl, {
				label: __( 'Open links in new tab', 'manual-related-posts-pro' ),
				checked: !! getSetting( attributes, 'openInNewTab' ),
				onChange: ( val ) => updateOne( 'openInNewTab', val ),
			} )
		),
	];
}
