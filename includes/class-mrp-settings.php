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
	public static function get_sections() {
		return array(
			'mrp_section_content'    => array(
				'title'       => __( 'Content Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Choose the default text, metadata and visibility behavior for the related posts section.', 'manual-related-posts-pro' ),
			),
			'mrp_section_layout'     => array(
				'title'       => __( 'Layout Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Set the responsive structure and alignment used by new blocks before any per-block overrides.', 'manual-related-posts-pro' ),
			),
			'mrp_section_card'       => array(
				'title'       => __( 'Card Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Control the visual treatment of each related post card.', 'manual-related-posts-pro' ),
			),
			'mrp_section_image'      => array(
				'title'       => __( 'Image Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Define how featured images appear across the card grid.', 'manual-related-posts-pro' ),
			),
			'mrp_section_typography' => array(
				'title'       => __( 'Typography, Excerpt & Button Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Tune heading semantics, text styles, excerpt styles and button appearance.', 'manual-related-posts-pro' ),
			),
			'mrp_section_advanced'   => array(
				'title'       => __( 'Advanced Defaults', 'manual-related-posts-pro' ),
				'description' => __( 'Define link behavior and card interaction defaults for new blocks.', 'manual-related-posts-pro' ),
			),
		);
	}

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

		foreach ( self::get_sections() as $id => $section ) {
			add_settings_section( $id, $section['title'], '__return_false', 'mrp-settings' );
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

		$sections = self::get_sections();
		$fields   = self::get_settings_fields();
		?>
		<div class="wrap mrp-admin-page">
			<div class="mrp-admin-hero">
				<div>
					<h1><?php esc_html_e( 'Manual Related Posts', 'manual-related-posts-pro' ); ?></h1>
					<p><?php esc_html_e( 'Set polished global defaults for new blocks. Every block can still override these values individually inside Gutenberg.', 'manual-related-posts-pro' ); ?></p>
				</div>
				<div class="mrp-admin-hero__badge">
					<span><?php esc_html_e( 'Global Defaults', 'manual-related-posts-pro' ); ?></span>
				</div>
			</div>

			<form action="options.php" method="post" class="mrp-admin-layout">
				<?php settings_fields( 'mrp_settings_group' ); ?>

				<aside class="mrp-admin-sidebar">
					<div class="mrp-admin-sidebar__card">
						<h2><?php esc_html_e( 'Sections', 'manual-related-posts-pro' ); ?></h2>
						<nav class="mrp-admin-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'manual-related-posts-pro' ); ?>">
							<?php foreach ( $sections as $section_id => $section ) : ?>
								<a href="#<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $section['title'] ); ?></a>
							<?php endforeach; ?>
						</nav>
					</div>

					<div class="mrp-admin-sidebar__card">
						<h2><?php esc_html_e( 'How defaults work', 'manual-related-posts-pro' ); ?></h2>
						<ol>
							<li><?php esc_html_e( 'Block-level value', 'manual-related-posts-pro' ); ?></li>
							<li><?php esc_html_e( 'Plugin global default', 'manual-related-posts-pro' ); ?></li>
							<li><?php esc_html_e( 'Internal fallback', 'manual-related-posts-pro' ); ?></li>
						</ol>
					</div>
				</aside>

				<div class="mrp-admin-content">
					<?php foreach ( $sections as $section_id => $section ) : ?>
						<section id="<?php echo esc_attr( $section_id ); ?>" class="mrp-settings-card">
							<header class="mrp-settings-card__header">
								<h2><?php echo esc_html( $section['title'] ); ?></h2>
								<p><?php echo esc_html( $section['description'] ); ?></p>
							</header>
							<div class="mrp-settings-grid">
								<?php foreach ( $fields as $field ) : ?>
									<?php if ( $field['section'] !== $section_id ) : ?>
										<?php continue; ?>
									<?php endif; ?>
									<div class="mrp-setting-field mrp-setting-field--<?php echo esc_attr( $field['type'] ); ?>">
										<label class="mrp-setting-field__label" for="<?php echo esc_attr( 'mrp-setting-' . $field['key'] ); ?>">
											<?php echo esc_html( $field['label'] ); ?>
										</label>
										<div class="mrp-setting-field__control">
											<?php self::render_field( $field ); ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>

					<div class="mrp-admin-actions">
						<?php submit_button( __( 'Save Global Defaults', 'manual-related-posts-pro' ), 'primary', 'submit', false ); ?>
					</div>
				</div>
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
		$id      = 'mrp-setting-' . $key;

		switch ( $type ) {
			case 'text':
				printf( '<input type="text" id="%1$s" class="regular-text" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'number':
				printf(
					'<input type="number" id="%1$s" class="small-text" min="%2$d" max="%3$d" name="%4$s" value="%5$s" />',
					esc_attr( $id ),
					isset( $args['min'] ) ? (int) $args['min'] : 0,
					isset( $args['max'] ) ? (int) $args['max'] : 999,
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'color':
				printf(
					'<input type="text" id="%1$s" class="mrp-color-field" data-default-color="%2$s" name="%3$s" value="%4$s" />',
					esc_attr( $id ),
					esc_attr( (string) $value ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'checkbox':
				printf(
					'<input type="hidden" name="%2$s" value="0" /><label class="mrp-checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> <span>%4$s</span></label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html( $args['checkboxLabel'] ?? __( 'Enable', 'manual-related-posts-pro' ) )
				);
				break;
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
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

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="mrp-setting-field__description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	private static function get_settings_fields() {
		return array(
			array( 'key' => 'sectionTitle', 'label' => __( 'Default section title', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_content', 'description' => __( 'Appears when a block does not define its own section title.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'sectionSubtitle', 'label' => __( 'Default section subtitle', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_content', 'description' => __( 'Helpful for optional context beneath the main section heading.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'showExcerpt', 'label' => __( 'Show excerpt by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_content', 'description' => __( 'Enable excerpts for new blocks unless the editor overrides them.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'showDate', 'label' => __( 'Show date by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_content', 'description' => __( 'Controls whether new blocks display the post date unless overridden.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'showCategory', 'label' => __( 'Show category by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_content', 'description' => __( 'Controls whether new blocks display categories unless overridden.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'excerptLength', 'label' => __( 'Excerpt length', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_content', 'min' => 5, 'max' => 60, 'description' => __( 'Approximate word count used when no manual excerpt is available.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'columnsDesktop', 'label' => __( 'Desktop columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 6, 'description' => __( 'How many cards to show per row on large screens.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'columnsTablet', 'label' => __( 'Tablet columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 4, 'description' => __( 'Used for medium breakpoints and tablet layouts.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'columnsMobile', 'label' => __( 'Mobile columns', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 1, 'max' => 2, 'description' => __( 'Usually one column for readability on phones.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'gap', 'label' => __( 'Grid gap (px)', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_layout', 'min' => 0, 'max' => 80, 'description' => __( 'Spacing between cards in the grid.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'blockAlign', 'label' => __( 'Incomplete row alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_layout', 'description' => __( 'When the last row has fewer cards than the configured columns, align it left, center or right.', 'manual-related-posts-pro' ), 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'cardBackgroundColor', 'label' => __( 'Card background color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_card' ),
			array( 'key' => 'cardBorderColor', 'label' => __( 'Card border color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_card' ),
			array( 'key' => 'cardBorderWidth', 'label' => __( 'Card border width', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 8 ),
			array( 'key' => 'cardBorderRadius', 'label' => __( 'Card border radius', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'cardPadding', 'label' => __( 'Card padding', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_card', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'cardShadow', 'label' => __( 'Card shadow', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_card', 'options' => array( 'none' => __( 'None', 'manual-related-posts-pro' ), 'soft' => __( 'Soft', 'manual-related-posts-pro' ), 'medium' => __( 'Medium', 'manual-related-posts-pro' ), 'strong' => __( 'Strong', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'cardContentAlign', 'label' => __( 'Card content alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_card', 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'showImage', 'label' => __( 'Show featured image by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_image' ),
			array( 'key' => 'imageSize', 'label' => __( 'Image size', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_image', 'options' => array( 'thumbnail' => __( 'Thumbnail', 'manual-related-posts-pro' ), 'medium' => __( 'Medium', 'manual-related-posts-pro' ), 'large' => __( 'Large', 'manual-related-posts-pro' ), 'full' => __( 'Full', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'imageRatio', 'label' => __( 'Image aspect ratio', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_image', 'options' => array( 'auto' => __( 'Auto', 'manual-related-posts-pro' ), 'square' => __( 'Square', 'manual-related-posts-pro' ), 'landscape' => __( 'Landscape', 'manual-related-posts-pro' ), 'portrait' => __( 'Portrait', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'imageRadius', 'label' => __( 'Image corner radius', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_image', 'min' => 0, 'max' => 48 ),
			array( 'key' => 'imageHeight', 'label' => __( 'Fixed image height (px)', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_image', 'min' => 0, 'max' => 800, 'description' => __( 'Leave 0 to use the natural height implied by the aspect ratio.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'sectionTitleColor', 'label' => __( 'Section title color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'sectionTitleSize', 'label' => __( 'Section title size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 12, 'max' => 60 ),
			array( 'key' => 'sectionTitleMarginBottom', 'label' => __( 'Section title bottom margin', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 0, 'max' => 80 ),
			array( 'key' => 'sectionTitleTag', 'label' => __( 'Section title HTML tag', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'DIV' ) ),
			array( 'key' => 'sectionTitleWeight', 'label' => __( 'Section title weight', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ) ),
			array( 'key' => 'sectionTitleAlign', 'label' => __( 'Section title alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'sectionSubtitleColor', 'label' => __( 'Section subtitle color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'sectionSubtitleSize', 'label' => __( 'Section subtitle size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 10, 'max' => 40 ),
			array( 'key' => 'sectionSubtitleAlign', 'label' => __( 'Section subtitle alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'headingSpacing', 'label' => __( 'Spacing below heading (px)', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 0, 'max' => 80 ),
			array( 'key' => 'postTitleColor', 'label' => __( 'Card post title color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'postTitleSize', 'label' => __( 'Card post title size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 12, 'max' => 48 ),
			array( 'key' => 'postTitleMarginBottom', 'label' => __( 'Card post title bottom margin', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 0, 'max' => 80 ),
			array( 'key' => 'postTitleWeight', 'label' => __( 'Card post title weight', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800' ) ),
			array( 'key' => 'postTitleAlign', 'label' => __( 'Card post title alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'postTitleClamp', 'label' => __( 'Card post title line clamp', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 1, 'max' => 6 ),
			array( 'key' => 'excerptColor', 'label' => __( 'Excerpt color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'excerptSize', 'label' => __( 'Excerpt size', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 10, 'max' => 28 ),
			array( 'key' => 'showButton', 'label' => __( 'Show button by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_typography', 'description' => __( 'Controls whether new blocks display the read more button unless overridden.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'buttonText', 'label' => __( 'Default button text', 'manual-related-posts-pro' ), 'type' => 'text', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'buttonTextColor', 'label' => __( 'Button text color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'buttonBackgroundColor', 'label' => __( 'Button background color', 'manual-related-posts-pro' ), 'type' => 'color', 'section' => 'mrp_section_typography' ),
			array( 'key' => 'buttonRadius', 'label' => __( 'Button radius', 'manual-related-posts-pro' ), 'type' => 'number', 'section' => 'mrp_section_typography', 'min' => 0, 'max' => 999 ),
			array( 'key' => 'buttonAlign', 'label' => __( 'Button alignment', 'manual-related-posts-pro' ), 'type' => 'select', 'section' => 'mrp_section_typography', 'options' => array( 'left' => __( 'Left', 'manual-related-posts-pro' ), 'center' => __( 'Center', 'manual-related-posts-pro' ), 'right' => __( 'Right', 'manual-related-posts-pro' ) ) ),
			array( 'key' => 'fullCardLink', 'label' => __( 'Make entire card clickable by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_advanced', 'description' => __( 'Adds a full-card overlay link while preserving the optional button.', 'manual-related-posts-pro' ) ),
			array( 'key' => 'openInNewTab', 'label' => __( 'Open links in new tab by default', 'manual-related-posts-pro' ), 'type' => 'checkbox', 'section' => 'mrp_section_advanced' ),
		);
	}
}
