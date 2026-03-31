<?php
/**
 * Shared helpers.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Helpers {
	const OPTION_KEY = 'mrp_settings';

	public static function get_hardcoded_defaults() {
		return array(
			'sectionTitle'          => __( 'Related Posts', 'manual-related-posts-pro' ),
			'sectionSubtitle'       => '',
			'sectionTitleColor'     => '#111827',
			'sectionTitleSize'      => 28,
			'sectionTitleMarginBottom' => 0,
			'sectionTitleTag'       => 'h2',
			'sectionTitleWeight'    => '700',
			'sectionTitleAlign'     => 'left',
			'sectionSubtitleColor'  => '#4b5563',
			'sectionSubtitleSize'   => 16,
			'sectionSubtitleAlign'  => 'left',
			'headingSpacing'        => 20,
			'columnsDesktop'        => 3,
			'columnsTablet'         => 2,
			'columnsMobile'         => 1,
			'gap'                   => 24,
			'cardBackgroundColor'   => '#ffffff',
			'cardBorderColor'       => '#e5e7eb',
			'cardBorderWidth'       => 1,
			'cardBorderRadius'      => 16,
			'cardPadding'           => 20,
			'cardShadow'            => 'soft',
			'cardContentAlign'      => 'left',
			'showImage'             => true,
			'imageSize'             => 'large',
			'imageRatio'            => 'landscape',
			'imageRadius'           => 12,
			'imageHeight'           => 0,
			'postTitleColor'        => '#111827',
			'postTitleSize'         => 20,
			'postTitleMarginBottom' => 12,
			'postTitleWeight'       => '700',
			'postTitleAlign'        => 'left',
			'postTitleClamp'        => 3,
			'showExcerpt'           => true,
			'excerptLength'         => 22,
			'excerptColor'          => '#4b5563',
			'excerptSize'           => 15,
			'showDate'              => false,
			'showCategory'          => false,
			'showButton'            => true,
			'buttonText'            => __( 'Read more', 'manual-related-posts-pro' ),
			'buttonTextColor'       => '#ffffff',
			'buttonBackgroundColor' => '#111827',
			'buttonRadius'          => 999,
			'buttonAlign'           => 'left',
			'fullCardLink'          => true,
			'openInNewTab'          => false,
			'blockAlign'            => 'left',
		);
	}

	public static function get_setting_schema() {
		return array(
			'sectionTitle'          => 'string',
			'sectionSubtitle'       => 'string',
			'sectionTitleColor'     => 'color',
			'sectionTitleSize'      => 'int',
			'sectionTitleMarginBottom' => 'int',
			'sectionTitleTag'       => 'heading_tag',
			'sectionTitleWeight'    => 'font_weight',
			'sectionTitleAlign'     => 'align',
			'sectionSubtitleColor'  => 'color',
			'sectionSubtitleSize'   => 'int',
			'sectionSubtitleAlign'  => 'align',
			'headingSpacing'        => 'int',
			'columnsDesktop'        => 'int',
			'columnsTablet'         => 'int',
			'columnsMobile'         => 'int',
			'gap'                   => 'int',
			'cardBackgroundColor'   => 'color',
			'cardBorderColor'       => 'color',
			'cardBorderWidth'       => 'int',
			'cardBorderRadius'      => 'int',
			'cardPadding'           => 'int',
			'cardShadow'            => 'shadow',
			'cardContentAlign'      => 'align',
			'showImage'             => 'bool',
			'imageSize'             => 'image_size',
			'imageRatio'            => 'image_ratio',
			'imageRadius'           => 'int',
			'imageHeight'           => 'int',
			'postTitleColor'        => 'color',
			'postTitleSize'         => 'int',
			'postTitleMarginBottom' => 'int',
			'postTitleWeight'       => 'font_weight',
			'postTitleAlign'        => 'align',
			'postTitleClamp'        => 'int',
			'showExcerpt'           => 'bool',
			'excerptLength'         => 'int',
			'excerptColor'          => 'color',
			'excerptSize'           => 'int',
			'showDate'              => 'bool',
			'showCategory'          => 'bool',
			'showButton'            => 'bool',
			'buttonText'            => 'string',
			'buttonTextColor'       => 'color',
			'buttonBackgroundColor' => 'color',
			'buttonRadius'          => 'int',
			'buttonAlign'           => 'align',
			'fullCardLink'          => 'bool',
			'openInNewTab'          => 'bool',
			'blockAlign'            => 'layout_align',
		);
	}

	public static function resolve_settings( $attributes ) {
		$defaults       = self::get_hardcoded_defaults();
		$global_options = self::get_plugin_settings();
		$resolved       = array();

		foreach ( self::get_setting_schema() as $key => $type ) {
			$attribute_value = $attributes[ $key ] ?? null;
			$global_value    = $global_options[ $key ] ?? null;
			$value           = self::has_meaningful_value( $attribute_value ) ? $attribute_value : $global_value;

			if ( ! self::has_meaningful_value( $value ) ) {
				$value = $defaults[ $key ] ?? null;
			}

			$resolved[ $key ] = self::sanitize_setting_value( $type, $value, $defaults[ $key ] ?? null );
		}

		return $resolved;
	}

	public static function get_plugin_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$clean  = array();

		foreach ( self::get_setting_schema() as $key => $type ) {
			if ( array_key_exists( $key, $stored ) ) {
				$clean[ $key ] = self::sanitize_setting_value( $type, $stored[ $key ], null );
			}
		}

		return $clean;
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( self::get_setting_schema() as $key => $type ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = self::sanitize_setting_value( $type, $input[ $key ], null );
			}
		}

		return $clean;
	}

	public static function sanitize_block_attributes( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$clean      = array();
		$selected   = $attributes['selectedPosts'] ?? array();

		$clean['selectedPosts'] = is_array( $selected )
			? array_values( array_unique( array_filter( array_map( 'absint', $selected ) ) ) )
			: array();

		foreach ( self::get_setting_schema() as $key => $type ) {
			if ( array_key_exists( $key, $attributes ) ) {
				$clean[ $key ] = self::sanitize_setting_value( $type, $attributes[ $key ], null );
			}
		}

		return $clean;
	}

	public static function build_wrapper_style( $settings ) {
		$shadow_map = array(
			'none'   => 'none',
			'soft'   => '0 12px 28px rgba(17, 24, 39, 0.08)',
			'medium' => '0 16px 36px rgba(17, 24, 39, 0.12)',
			'strong' => '0 20px 44px rgba(17, 24, 39, 0.16)',
		);
		$ratio_map  = array(
			'auto'      => 'auto',
			'square'    => '1 / 1',
			'landscape' => '16 / 9',
			'portrait'  => '4 / 5',
		);
		$flex_align_map = array(
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		);

		$vars = array(
			'--mrp-columns-mobile'    => max( 1, absint( $settings['columnsMobile'] ) ),
			'--mrp-columns-tablet'    => max( 1, absint( $settings['columnsTablet'] ) ),
			'--mrp-columns-desktop'   => max( 1, absint( $settings['columnsDesktop'] ) ),
			'--mrp-gap'               => absint( $settings['gap'] ) . 'px',
			'--mrp-heading-spacing'   => absint( $settings['headingSpacing'] ) . 'px',
			'--mrp-heading-color'     => $settings['sectionTitleColor'],
			'--mrp-heading-size'      => absint( $settings['sectionTitleSize'] ) . 'px',
			'--mrp-heading-weight'    => $settings['sectionTitleWeight'],
			'--mrp-heading-align'     => $settings['sectionTitleAlign'],
			'--mrp-subtitle-color'    => $settings['sectionSubtitleColor'],
			'--mrp-subtitle-size'     => absint( $settings['sectionSubtitleSize'] ) . 'px',
			'--mrp-subtitle-align'    => $settings['sectionSubtitleAlign'],
			'--mrp-card-bg'           => $settings['cardBackgroundColor'],
			'--mrp-card-border-color' => $settings['cardBorderColor'],
			'--mrp-card-border-width' => absint( $settings['cardBorderWidth'] ) . 'px',
			'--mrp-card-radius'       => absint( $settings['cardBorderRadius'] ) . 'px',
			'--mrp-card-padding'      => absint( $settings['cardPadding'] ) . 'px',
			'--mrp-card-shadow'       => $shadow_map[ $settings['cardShadow'] ] ?? $shadow_map['soft'],
			'--mrp-card-text-align'   => $settings['cardContentAlign'],
			'--mrp-image-radius'      => absint( $settings['imageRadius'] ) . 'px',
			'--mrp-image-ratio'       => $ratio_map[ $settings['imageRatio'] ] ?? $ratio_map['landscape'],
			'--mrp-image-height'      => absint( $settings['imageHeight'] ) > 0 ? absint( $settings['imageHeight'] ) . 'px' : 'auto',
			'--mrp-post-title-color'  => $settings['postTitleColor'],
			'--mrp-post-title-size'   => absint( $settings['postTitleSize'] ) . 'px',
			'--mrp-post-title-weight' => $settings['postTitleWeight'],
			'--mrp-post-title-align'  => $settings['postTitleAlign'],
			'--mrp-post-title-clamp'  => max( 1, absint( $settings['postTitleClamp'] ) ),
			'--mrp-excerpt-color'     => $settings['excerptColor'],
			'--mrp-excerpt-size'      => absint( $settings['excerptSize'] ) . 'px',
			'--mrp-button-text-color' => $settings['buttonTextColor'],
			'--mrp-button-bg'         => $settings['buttonBackgroundColor'],
			'--mrp-button-radius'     => absint( $settings['buttonRadius'] ) . 'px',
			'--mrp-button-align'      => $flex_align_map[ $settings['buttonAlign'] ] ?? 'flex-start',
			'--mrp-grid-justify'      => $flex_align_map[ $settings['blockAlign'] ] ?? 'flex-start',
		);

		$parts = array();
		foreach ( $vars as $name => $value ) {
			$parts[] = sprintf( '%s:%s', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return implode( ';', $parts );
	}

	public static function get_allowed_post_types() {
		$post_types = get_post_types(
			array(
				'public'              => true,
				'show_in_rest'        => true,
				'exclude_from_search' => false,
			),
			'names'
		);

		unset( $post_types['attachment'] );

		return array_values( $post_types );
	}

	private static function has_meaningful_value( $value ) {
		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		return null !== $value && '' !== $value;
	}

	public static function sanitize_setting_value( $type, $value, $fallback ) {
		switch ( $type ) {
			case 'string':
				return sanitize_text_field( (string) $value );
			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return $color ? $color : $fallback;
			case 'int':
				return max( 0, absint( $value ) );
			case 'bool':
				return rest_sanitize_boolean( $value );
			case 'align':
				return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : $fallback;
			case 'layout_align':
				return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : $fallback;
			case 'font_weight':
				return in_array( (string) $value, array( '400', '500', '600', '700', '800' ), true ) ? (string) $value : $fallback;
			case 'heading_tag':
				return in_array( $value, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $value : $fallback;
			case 'shadow':
				return in_array( $value, array( 'none', 'soft', 'medium', 'strong' ), true ) ? $value : $fallback;
			case 'image_size':
				return in_array( $value, array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $value : $fallback;
			case 'image_ratio':
				return in_array( $value, array( 'auto', 'square', 'landscape', 'portrait' ), true ) ? $value : $fallback;
			default:
				return $fallback;
		}
	}
}
