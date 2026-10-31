<?php
/**
 * Beaver Builder module: GitHub Widget. Each module on a page is a separate widget.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The module class. Rendering is shared with the shortcode through gw_render().
 */
class GW_BB_Module extends FLBuilderModule {

	/**
	 * Register the module's name, category and folder.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'GitHub Widget', 'git-widget' ),
				'description'     => __( 'Your GitHub profile, contributions, charts and achievements.', 'git-widget' ),
				'category'        => __( 'Git Widget', 'git-widget' ),
				'dir'             => GW_DIR . 'modules/gw-github/',
				'url'             => GW_URL . 'modules/gw-github/',
				'partial_refresh' => true,
			)
		);
	}

	/**
	 * Turn Beaver Builder settings into gw_render() options.
	 *
	 * @param object $settings Module settings.
	 * @return array
	 */
	public static function to_atts( $settings ) {
		$s        = (array) $settings;
		$sections = $s['sections'] ?? array( 'profile' );
		$accent   = (string) ( $s['accent'] ?? '' );
		return array(
			'sections'  => implode( ',', (array) $sections ),
			'user'      => $s['user'] ?? '',
			'year'      => $s['year'] ?? '',
			'years'     => $s['years'] ?? 6,
			'accent'    => '' === $accent ? '#2da44e' : '#' . ltrim( $accent, '#' ),
			'theme'     => $s['theme'] ?? 'auto',
			'layout'    => $s['layout'] ?? 'stack',
			'title'     => $s['title'] ?? '',
			'show_link' => $s['show_link'] ?? 'yes',
		);
	}
}

$gw_years = array( '' => __( 'Last 12 months', 'git-widget' ) );
for ( $gw_y = (int) gmdate( 'Y' ); $gw_y >= 2008; $gw_y-- ) {
	$gw_years[ (string) $gw_y ] = (string) $gw_y;
}

FLBuilder::register_module(
	'GW_BB_Module',
	array(
		'content' => array(
			'title'    => __( 'Content', 'git-widget' ),
			'sections' => array(
				'what' => array(
					'title'  => __( 'What to show', 'git-widget' ),
					'fields' => array(
						'sections' => array(
							'type'         => 'select',
							'label'        => __( 'Sections', 'git-widget' ),
							'multi-select' => true,
							'options'      => gw_sections(),
							'default'      => array( 'profile' ),
							'help'         => __( 'Hold Ctrl or Cmd to pick several. They appear in this order. Add another GitHub Widget module to show other sections elsewhere on the page.', 'git-widget' ),
						),
						'user'     => array(
							'type'        => 'text',
							'label'       => __( 'GitHub username', 'git-widget' ),
							'placeholder' => gw_settings()['username'],
							'help'        => __( 'Leave empty to use the username from Settings > Git Widget.', 'git-widget' ),
						),
						'title'    => array(
							'type'  => 'text',
							'label' => __( 'Heading (optional)', 'git-widget' ),
						),
					),
				),
				'period' => array(
					'title'  => __( 'Time period', 'git-widget' ),
					'fields' => array(
						'year'  => array(
							'type'    => 'select',
							'label'   => __( 'Year', 'git-widget' ),
							'options' => $gw_years,
							'help'    => __( 'Used by the calendar, overview, contribution types and monthly chart.', 'git-widget' ),
						),
						'years' => array(
							'type'    => 'unit',
							'label'   => __( 'Years in the yearly chart', 'git-widget' ),
							'default' => 6,
							'slider'  => array( 'min' => 1, 'max' => 20, 'step' => 1 ),
						),
					),
				),
			),
		),
		'style'   => array(
			'title'    => __( 'Style', 'git-widget' ),
			'sections' => array(
				'look' => array(
					'title'  => __( 'Look', 'git-widget' ),
					'fields' => array(
						'accent'    => array(
							'type'       => 'color',
							'label'      => __( 'Accent colour', 'git-widget' ),
							'default'    => '2da44e',
							'show_reset' => true,
						),
						'theme'     => array(
							'type'    => 'select',
							'label'   => __( 'Background', 'git-widget' ),
							'default' => 'auto',
							'options' => array(
								'auto'  => __( 'Match the page', 'git-widget' ),
								'light' => __( 'Light card', 'git-widget' ),
								'dark'  => __( 'Dark card', 'git-widget' ),
							),
						),
						'layout'    => array(
							'type'    => 'select',
							'label'   => __( 'Layout', 'git-widget' ),
							'default' => 'stack',
							'options' => array(
								'stack' => __( 'Stacked', 'git-widget' ),
								'grid'  => __( 'Side by side when there is room', 'git-widget' ),
							),
						),
						'show_link' => array(
							'type'    => 'select',
							'label'   => __( '"View on GitHub" link', 'git-widget' ),
							'default' => 'yes',
							'options' => array(
								'yes' => __( 'Show', 'git-widget' ),
								'no'  => __( 'Hide', 'git-widget' ),
							),
						),
					),
				),
			),
		),
	)
);
