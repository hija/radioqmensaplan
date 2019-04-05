<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              hilko.eu
 * @since             1.0.0
 * @package           Radioqmensaplan
 *
 * @wordpress-plugin
 * Plugin Name:       Radio Q Mensaplan
 * Plugin URI:        https://github.com/hija/radioqmensaplan
 * Description:       Generiert dynamisch einen Mensaplan für die Radio Q Homepage.
 * Version:           1.0.0
 * Author:            Hilko Janßen
 * Author URI:        hilko.eu
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       radioqmensaplan
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function radioqmensaplan_activation() {
    if (! wp_next_scheduled ( 'radioq_mensaplan_download' )) {
			wp_schedule_event(time(), 'hourly', 'radioq_mensaplan_download');
    }
}

function radioqmensaplan_deactivation() {
	wp_clear_scheduled_hook('radioq_mensaplan_download');
}

function radioq_mensaplan_download() {
	write_log('Downloading current mensaplans.');
	$mensen = array(
		'Mensa am Aasee' => 'https://mensa.chrk.de/openmensa/mensa_aasee.xml',
		'Mensa am Ring' => 'https://mensa.chrk.de/openmensa/mensa_am_ring.xml',
		'Mensa Da Vinci' => 'https://mensa.chrk.de/openmensa/mensa_da_vinci.xml',
		'Mensa Steinfurt' => 'https://mensa.chrk.de/openmensa/mensa_steinfurt.xml',
		'Mensa Bispinghof' => 'https://mensa.chrk.de/openmensa/mensa_bispinghof.xml'
	);

	// Download to tmp directory
	foreach($mensen as $mensaurl){
		$filename = basename($mensaurl);
		file_put_contents(plugin_dir_path( __FILE__ ) . 'tmp/' . $filename, file_get_contents($mensaurl));
	}
}

function get_meal_closest_to_date($xml, $date){
	$days = $xml->canteen->day;

	foreach($days as $day){
		$datum = strtotime($day['date']);
		if ($datum == $date){
			if (isset($day->closed)){
				continue; // Don't show closed menues
			}
			return $day;
		}else if($datum > $date){
			return $day;
		}
	}
}

function show_mensaplan($attr){

	$attr = shortcode_atts( array(
		'mensa' => 'aasee',
	), $attr, 'mensaplan' );

	$mensa_to_file = array(
		'aasee' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_aasee.xml',
		'am_aasee' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_aasee.xml',
		'ring' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_am_ring.xml',
		'am_ring' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_am_ring.xml',
		'bispinghof' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_bispinghof.xml',
		'am_bispinghof' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_bispinghof.xml',
		'da_vinci' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_da_vinci.xml',
		'davinci' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_da_vinci.xml',
		'steinfurt' => plugin_dir_path( __FILE__ ) . 'tmp/mensa_steinfurt.xml',
	);

	if (!array_key_exists($attr['mensa'], $mensa_to_file)){
		return 'Mensa ' . esc_html($attr['mensa']) . ' does not exist!';
	}

	// Read in mensafile
	$xml = simplexml_load_file($mensa_to_file[$attr['mensa']]);
	//$datemeals = get_meal_closest_to_date($xml, strtotime('2019-04-19'));
	$datemeals = get_meal_closest_to_date($xml, strtotime('Y-m-d'));

	$output = <<<EOT
<table>
<tr>
<th style="width:75%"> Gericht </th>
<th style="width:15%"> Hinweis </th>
<th style="width:10%"> Preis für Studierende </th>
</tr>
EOT;

	if (is_null($datemeals)){
		return 'Error: Data could not be loaded successful.';
	}

	$meals = $datemeals->category;
	foreach($meals as $meal) {
		$mealdata = $meal->meal;
		$mealname = $mealdata->name[0];
		$mealinfo = !empty($mealdata->note) ? $mealdata->note[0] : '';
		$mealprice = $mealdata->price[0];

		if (mb_strlen($mealname) < 2 || mb_substr($mealname, 0, mb_strlen('x mit')) == 'x mit'){
			// Skip bullshit
			continue;
		}

		$output .= '<tr><td>';
		$output .= esc_html($mealname);
		$output .= '</td>';


		$output .= '<td>';
		$output .= esc_html($mealinfo);
		$output .= '</td>';


		$output .= '<td>';
		$output .= esc_html($mealprice);
		$output .= '</td></tr>';
	}

	$output .= '</table>';
	$output .= '<h6> (Für Datum: ' . $datemeals['date'] . ') </h6>';
	return $output;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'RADIOQMENSAPLAN_VERSION', '1.0.0' );

register_activation_hook(__FILE__, 'radioqmensaplan_activation');
register_deactivation_hook(__FILE__, 'radioqmensaplan_deactivation');

add_action('radioq_mensaplan_download', 'radioq_mensaplan_download');
add_shortcode('mensaplan', 'show_mensaplan');


if (!function_exists('write_log')) {
    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}
