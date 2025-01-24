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
 * Local configuration web services lib.
 *
 * @package     local_configws
 * @copyright   2024 Softhouse
 * @author      Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by pluginfile, to download user generated reports via selected dataformat.
 * Generated reports can also be downloaded via webservice/pluginfile.
 *
 * Example url for download:
 * /pluginfile/<contextid>/local_configws/download/<serviceid>/?userid=value
 * Example url for download via WS:
 * /webservice/pluginfile/<contextid>/local_configws/download/<serviceid>/?token=<wstoken>&userid=value
 *
 * Exits if the required permissions are not satisfied.
 *
 * @param stdClass $course course object
 * @param stdClass $cm
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return void
 */
function local_configws_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): void {
    global $CFG, $DB;

    if ($context->id != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea != 'download') {
        return false;
    }

    $serviceid = (int)array_shift($args);
    $userid = optional_param('userid', 0, PARAM_INT);
    $user = $DB->get_record('user', ['id' => $userid], '*');

    $service = $DB->get_record('external_services', ['id' => $serviceid], '*', MUST_EXIST);
    $servicefunctions = $DB->get_records_menu('external_services_functions',
        ['externalserviceid' => $service->id], 'id', 'id, functionname');

    $serviceinfo = new stdClass();
    $serviceinfo->serviceid = $serviceid;
    if (!empty($user)) {
        $serviceinfo->userid = $userid;
        $serviceinfo->username = $user->username;
        $serviceinfo->email = $user->email;
    }

    $serviceinfo->name = $service->name;
    $serviceinfo->enabled = $service->enabled;
    $serviceinfo->downloadfiles = $service->downloadfiles;
    $serviceinfo->uploadfiles = $service->uploadfiles;
    $serviceinfo->requiredcapability = $service->requiredcapability;
    $serviceinfo->functions = array_values($servicefunctions);
    $shortname = strtolower($serviceinfo->name);
    $shortname = preg_replace('/[^a-z0-9]/', '', $shortname);
    $servicejson = json_encode($serviceinfo);

    $filename = 'confiws-'. $user->username . '-' . $shortname . '.json';
    $context = context_system::instance();
    $fs = get_file_storage();
    $fr = array(
        'contextid' => $context->id,
        'component' => 'local_configws',
        'filearea' => 'download',
        'itemid' => $serviceid,
        'filepath' => '/',
        'filename' => $filename,
    );
    $file = $fs->get_file($fr['contextid'], $fr['component'], $fr['filearea'], $fr['itemid'], $fr['filepath'], $fr['filename']);
    if ($file) {
        $file->delete();
    }
    // We create the file with the latest service info.
    $file = $fs->create_file_from_string($fr, $servicejson);

    send_stored_file($file, 0, 0, true);
}
