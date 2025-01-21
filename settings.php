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
 * Settings for local_altai.
 *
 * @package     local_configws
 * @copyright   2024 Softhouse
 * @author      Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

if ($hassiteconfig) {
    $pluginname = 'local_configws';

    $ADMIN->add(
        'localplugins',
        new admin_category(
            $pluginname,
            new lang_string('pluginname', $pluginname)
        )
    );


    // Add autoconfig page.
    $urleventtypes = new moodle_url(
        '/local/configws/autoconfig.php',
        []
    );
    $ADMIN->add(
        $pluginname,
        new admin_externalpage(
            'local_configws_autoconfig',
            new lang_string('autoconfig', $pluginname),
            $urleventtypes->out(false)
        )
    );
}
