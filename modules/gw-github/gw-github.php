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
			'types_style' => $s['types_style'] ?? 'axes',
			'bio_breaks'  => $s['bio_breaks'] ?? 'yes',
			'count'       => $s['count'] ?? 6,
			'metrics'       => self::metrics( $s ),
			'awesome_count' => $s['awesome_count'] ?? 6,
			'period'        => $s['period'] ?? '',
			'calendar_period' => $s['calendar_period'] ?? '',
			'calendar_picker' => $s['calendar_picker'] ?? 'off',
			'picker_position' => $s['picker_position'] ?? 'right',
		);
	}

	/**
	 * The overview metrics from the mode menu and the per-metric switches.
	 *
	 * @param array $s Module settings.
	 * @return string "awesome", or a comma-separated list of metric keys.
	 */
	public static function metrics( $s ) {
		if ( 'custom' !== ( $s['metrics_mode'] ?? 'awesome' ) ) {
			return 'awesome';
		}
		$on = array();
		foreach ( array_keys( gw_overview_metrics() ) as $m ) {
			if ( 'yes' === ( $s[ 'metric_' . $m ] ?? 'no' ) ) {
				$on[] = $m;
			}
		}
		return $on ? implode( ',', $on ) : 'awesome';
	}
}

/**
 * One On/Off switch per overview metric, for the "Choose my own" list.
 *
 * @return array Beaver Builder fields.
 */
function gw_bb_metric_switches() {
	$fields = array();
	foreach ( gw_overview_metrics() as $key => $label ) {
		$fields[ 'metric_' . $key ] = array(
			'type'    => 'button-group',
			'label'   => $label,
			'default' => in_array( $key, array( 'contributions', 'active_days', 'current_streak', 'longest_streak' ), true ) ? 'yes' : 'no',
			'options' => array(
				'yes' => __( 'On', 'git-widget' ),
				'no'  => __( 'Off', 'git-widget' ),
			),
		);
	}
	$fields['metrics_note'] = array(
		'type'    => 'raw',
		'content' => '<p style="margin:0;opacity:.8">' . esc_html__( 'Stars, forks, repositories, languages, followers, following, organisations and years on GitHub are always current. The rest follow the time frame.', 'git-widget' ) . '</p>',
	);
	return $fields;
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
						'show_link' => array(
							'type'    => 'button-group',
							'label'   => __( 'Show "View on GitHub" link', 'git-widget' ),
							'default' => 'yes',
							'options' => array(
								'yes' => __( 'On', 'git-widget' ),
								'no'  => __( 'Off', 'git-widget' ),
							),
						),
						'bio_breaks' => array(
							'type'    => 'select',
							'label'   => __( 'Underscores in bio', 'git-widget' ),
							'default' => 'yes',
							'options' => array(
								'yes' => __( 'Start a new line (__ or ____ = one line break)', 'git-widget' ),
								'no'  => __( 'Show as typed', 'git-widget' ),
							),
						),
					),
				),
				'credit' => array(
					'title'  => __( 'About', 'git-widget' ),
					'fields' => array(
						'credit' => array(
							'type'    => 'raw',
							'content' => '<p style="margin:0 0 .4em">Made by <a href="https://github.com/DannoPeters" target="_blank" rel="noopener">@DannoPeters</a> in 🇨🇦</p>'
								. '<p style="margin:0">Suggestions and bug reports are welcome on <a href="https://github.com/DannoPeters/WordPress_Git-Widget/issues" target="_blank" rel="noopener">GitHub</a> or at <a href="https://dannopeters.ca" target="_blank" rel="noopener">DannoPeters.ca</a>.</p>',
						),
					),
				),
				'overview' => array(
					'title'  => __( 'Activity overview', 'git-widget' ),
					'fields' => array(
						'metrics_mode'  => array(
							'type'    => 'select',
							'label'   => __( 'Which stats', 'git-widget' ),
							'default' => 'awesome',
							'options' => array(
								'awesome' => __( 'Awesome Mode: pick my most impressive stats', 'git-widget' ),
								'custom'  => __( 'Choose my own', 'git-widget' ),
							),
							'toggle'  => array(
								'awesome' => array( 'fields' => array( 'awesome_count' ) ),
								'custom'  => array( 'sections' => array( 'overview_metrics' ) ),
							),
							'help'    => __( 'Used when "Activity overview" is one of the sections.', 'git-widget' ),
						),
						'awesome_count' => array(
							'type'    => 'unit',
							'label'   => __( 'How many stats', 'git-widget' ),
							'default' => 6,
							'slider'  => array( 'min' => 2, 'max' => 12, 'step' => 1 ),
						),
						'period'        => array(
							'type'    => 'select',
							'label'   => __( 'Time frame', 'git-widget' ),
							'default' => '',
							'options' => array(
								''     => __( 'Last 12 months (or the year chosen below)', 'git-widget' ),
								'last' => __( 'Last 12 months', 'git-widget' ),
								'year' => __( 'The year chosen below', 'git-widget' ),
								'all'  => __( 'All time', 'git-widget' ),
							),
						),
					),
				),
				'overview_metrics' => array(
					'title'  => __( 'Activity overview: stats to show', 'git-widget' ),
					'fields' => gw_bb_metric_switches(),
				),
				'period' => array(
					'title'  => __( 'Time period', 'git-widget' ),
					'fields' => array(
						'calendar_period' => array(
							'type'    => 'select',
							'label'   => __( 'Contribution calendar shows', 'git-widget' ),
							'default' => '',
							'options' => array(
								''     => __( 'Last 12 months (or the year chosen below)', 'git-widget' ),
								'last' => __( 'Last 12 months', 'git-widget' ),
								'year' => __( 'The year chosen below', 'git-widget' ),
								'all'  => __( 'All time: one calendar per year', 'git-widget' ),
							),
						),
						'calendar_picker' => array(
							'type'    => 'select',
							'label'   => __( 'Let visitors pick the year', 'git-widget' ),
							'default' => 'off',
							'options' => array(
								'off'      => __( 'Off', 'git-widget' ),
								'dropdown' => __( 'Dropdown', 'git-widget' ),
								'list'     => __( 'List of years', 'git-widget' ),
							),
							'toggle'  => array( 'list' => array( 'fields' => array( 'picker_position' ) ) ),
							'help'    => __( 'Visitors can switch between the last 12 months, each year and all years. The setting above picks what shows first.', 'git-widget' ),
						),
						'picker_position' => array(
							'type'    => 'button-group',
							'label'   => __( 'Year list position', 'git-widget' ),
							'default' => 'right',
							'options' => array(
								'left'   => __( 'Left', 'git-widget' ),
								'right'  => __( 'Right', 'git-widget' ),
								'bottom' => __( 'Bottom', 'git-widget' ),
							),
						),
						'year'  => array(
							'type'    => 'select',
							'label'   => __( 'Year', 'git-widget' ),
							'options' => $gw_years,
							'help'    => __( 'Used by the calendar, overview, contribution types and monthly chart.', 'git-widget' ),
						),
						'count' => array(
							'type'    => 'unit',
							'label'   => __( 'Repositories / languages to list', 'git-widget' ),
							'default' => 6,
							'slider'  => array( 'min' => 1, 'max' => 12, 'step' => 1 ),
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
						'types_style' => array(
							'type'    => 'select',
							'label'   => __( 'Contribution types chart', 'git-widget' ),
							'default' => 'axes',
							'options' => array(
								'axes' => __( 'Cross chart (like GitHub)', 'git-widget' ),
								'bar'  => __( 'Bar with counts', 'git-widget' ),
							),
						),
					),
				),
			),
		),
	)
);
