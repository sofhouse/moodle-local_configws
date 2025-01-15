<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Autoconfig page.
 *
 * @package     local_confiws
 * @copyright   2024 Softhouse
 * @author      Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_configws\form\autoconfig_form;

// Requirements.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// External page setup.
admin_externalpage_setup('local_configws_autoconfig');

$PAGE->set_heading(get_string('autoconfig', 'local_configws'));

// Page configuration.
$baseurl = new moodle_url('/local/configws/autoconfig.php');

$autoconfigform = new autoconfig_form();

// Call AMD.
$PAGE->requires->js_call_amd(
    'local_configws/autoconfig',
    'init',
    [autoconfig_form::class]
);
// Render output.
echo $OUTPUT->header();
echo $OUTPUT->single_button('', get_string('autoconfig', 'local_configws'), '', ['data-action' => 'autoconfig-form']);
echo $OUTPUT->footer();
