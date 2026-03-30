<?php
/**
 * Plugin settings page.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Settings {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_settings() {
		register_setting(
			'mrp_settings_group',
			MRP_Helpers::OPTION_KEY,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( 'MRP_Helpers', 'sanitize_settings' ),
				'default'           => MRP_Helpers::get_hardcoded_defaults(),
			)
		);

		$sections = array(
			'mrp_section_content'    => __( 'Content Defaults', 'manual-related-posts-pro' ),
			'mrp_section_layout'     => __( 'Layout Defaults', 'manual-related-posts-pro' ),
			'mrp_section_card'       => __( 'Card Defaults', 'manual-related-posts-pro' ),
			'mrp_section_image'      => __( 'Image Defaults', 'manual-related-posts-pro' ),
			'mrp_section_typography' => __( 'Typography & Button Defaults', 'manual-related-posts-pro' ),
		);

		foreach ( $sections as $id => $title ) {
			add_settings_section( $id, $title, '__return_false', 'mrp-settings' );
		}

		foreach ( self::get_settings_fields() as $field ) {
			add_settings_field(
				$field['key'],
				$field['label'],
				array( __CLASS__, 'render_field' ),
				'mrp-settings',
				$field['section'],
				$field
			);
		}
	}

	public static function register_menu() {
		add_options_page(
			__( 'Manual Related Posts', 'manual-related-posts-pro' ),
			__( 'Manual Related Posts', 'manual-related-posts-pro' ),
			'manage_options',
			'mrp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manual Related Posts', 'manual-related-posts-pro' ); ?></h1>
			<p><?php esc_html_e( 'These values act as global defaults. Any block can override them individually.', 'manual-related-posts-pro' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'mrp_settings_group' );
				do_settings_sections( 'mrp-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_field( $args ) {
		$options = MRP_Helpers::get_plugin_settings();
		$key     = $args['key'];
		$type    = $args['type'];
		$value   = $options[ $key ] ?? MRP_Helpers::get_hardcoded_defaults()[ $key ] ?? '';
		$name    = MRP_Helpers::OPTION_KEY . '[' . $key . ']';

		switch ( $type ) {
			case 'text':
				printf( '<input type="text" class="regular-text" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'number':
				printf(
					'<input type="number" class="small-text" min="%1$d" max="%2$d" name="%3$s" value="%4$s" />',
					isset( $args['min'] ) ? (int) $args['min'] : 0,
					isset( $args['max'] ) ? (int) $args['max'] : 999,
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'color':
				printf(
					'<input type="text" class="mrp-color-field" data-default-color="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( (string) $value ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html( $args['checkboxLabel'] ?? __( 'Enable', 'manual-related-posts-pro' ) )
				);
				break;
			case 'select':
				printf( '<select name="%1$s">', esc_attr( $name ) );
				foreach ( $args['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;
		}
	}

	private static function get_settings_fields() {
		return array(
			array( 'key' => 'sectionTitle', 'label' => __( 'Default section title', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_content' ),
			array( 'key' => 'sectionSubtitle', 'label' => __( 'Default section subtitle', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_content' ),
			array( 'key' => 'showExcerpt', 'label' => __( 'Show excerpt by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_content' ),
			array( 'key' => 'excerptLength', 'label' => __( 'Excerpt length', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_content', 'min' => 5, 'max' => 60 ),
			array( 'key' => 'columnsDesktop', 'label' => __( 'Desktop columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 6 ),
			array( 'key' => 'columnsTablet', 'label' => __( 'Tablet columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 4 ),
			array( 'key' => 'columnsMobile', 'label' => __( 'Mobile columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 2 ),
			array( 'key' => 'gap', 'label' => __( 'Grid gap (px)', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 0, 'max' => 80 ),
			array( 'key' => 'cardBackgroundColor', 'label' => __( 'Card background color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_card' ),
			array( 'key' => 'cardBorderColor', 'label' => __( 'Card border color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_card' ),
			array( 'key' => 'cardBorderWidth', 'label' => __( 'Card border width', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 8 ),
			array( 'key' => 'cardBorderRadius', 'label' => __( 'Card border radius', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'cardPadding', 'label' => __( 'Card padding', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'cardShadow', 'label' => __( 'Card shadow', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_card', 'options' => array( 'none' => __( 'None', 'manual-related-posts-pro' ), 'soft' => __( 'Soft', 'manual-related-posts-pro' ), 'medium' => __( 'Medium', 'manual-related-posts-pro' ), 'strong' => __( 'Strong', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'showImage', 'label' => __( 'Show featured image by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_image' ),
			array( 'key' => 'imageRatio', 'label' => __( 'Image aspect ratio', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_image', 'options' => array( 'auto' => __( 'Auto', 'manual-related-posts-pro' ), 'square' => __( 'Square', 'manual-related-posts-pro' ), 'landscape' => __( 'Landscape', 'manual-related-posts-pro' ), 'portrait' => __( 'Portrait', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'imageRadius', 'label' => __( 'Image corner radius', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_image', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'sectionTitleColor', 'label' => __( 'Section title color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'sectionTitleSize', 'label' => __( 'Section title size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 12, 'max' => 60 ),
			array( 'key' => 'postTitleColor', 'label' => __( 'Card post title color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'postTitleSize', 'label' => __( 'Card post title size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 12, 'max' => 48 ),
			array( 'key' => 'buttonText', 'label' => __( 'Default button text', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'buttonTextColor', 'label' => __( 'Button text color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'buttonBackgroundColor', 'label' => __( 'Button background color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
		);
	}
}