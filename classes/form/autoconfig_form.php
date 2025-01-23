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

namespace local_configws\form;

defined('MOODLE_INTERNAL') || die('Forbidden.');

require_once("$CFG->libdir/formslib.php");
use core_form\dynamic_form;

/**
 * Form class for filtering the events.
 *
 * @package   local_configws
 * @author    Oscar Nadjar <Oscar.nadjar@moodle.com>
 * @copyright 2024 Softhouse
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autoconfig_form extends dynamic_form {

    /**
     * Gets context for submission
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Reviews caps. for context.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Processes the form submission.
     * @return mixed Response to be serialized and handled by amd.
     */
    public function process_dynamic_submission(): array {
        global $DB;
        $data = $this->get_data();

        $lowercasews = strtolower($data->webservicename);
        $shortname = preg_replace('/[^a-z0-9]/', '', $lowercasews);
        $user = $data->user;
        $webservice = $data->webservice;
        $webservicename = $data->webservicename;
        $rolename = $data->webservicename;
        $roleshortname = $shortname;
        $wsshortname = $shortname;
        $capability = $data->capability;
        $enabled = !empty($data->enabled) ? 1 : 0;
        $candownload = !empty($data->candownload) ? 1 : 0;
        $canupload = !empty($data->canupload) ? 1 : 0;
        $functions = $data->functions;

        if (empty($webservice) || $webservice == 'new') {
            if (!$DB->record_exists('external_services', ['shortname' => $wsshortname])) {
                $wsobject = new \stdClass();
                $wsobject->shortname = $wsshortname;
                $wsobject->name = $webservicename;
                $wsobject->restrictedusers = 0;
                $wsobject->enabled = $enabled;
                $wsobject->downloadfiles = $candownload;
                $wsobject->uploadfiles = $canupload;
                $wsobject->timecreated = time();
                $webserviceid = $DB->insert_record('external_services', $wsobject);
            } else {
                $webserviceid = $DB->get_field('external_services', 'id', ['shortname' => $wsshortname]);
            }
        } else {
            $wsobject = $DB->get_record('external_services', ['id' => $webservice]);
            $wsobject->shortname = $wsshortname;
            $wsobject->name = $webservicename;
            $wsobject->enabled = $enabled;
            $wsobject->downloadfiles = $candownload;
            $wsobject->uploadfiles = $canupload;
            $wsobject->timemodified = time();
            $DB->update_record('external_services', $wsobject);
            $webserviceid = $webservice;
        }

        if (!$DB->record_exists('external_services_users', ['userid' => $user, 'externalserviceid' => $webserviceid])) {
            $DB->insert_record('external_services_users', ['userid' => $user, 'externalserviceid' => $webserviceid]);
        }

        if (!$DB->record_exists('role', ['shortname' => $roleshortname])) {
            $roleid = create_role($rolename, $shortname, '');
        } else {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
        }
        $context = \context_system::instance();
        role_assign($roleid, $user, $context->id);

        if (!empty($capabilities)) {
            $webserviceobj = $DB->get_record('external_services', ['id' => $webserviceid]);
            $capabilities = array_keys($capabilities);
            $context = \context_system::instance();
            $webserviceobj->requiredcapability = $capability;
            $DB->update_record('external_services', $webserviceobj);
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        }

        // It's easier to delete all functions and reinsert them than to check which ones to delete and which ones to insert.
        if (!empty($functions)) {
            $DB->delete_records('external_services_functions', ['externalserviceid' => $webserviceid]);
        }
        foreach ($functions as $function) {
            $funtionaname = $DB->get_field('external_functions', 'name', ['id' => $function]);
            if (!$DB->record_exists('external_services_functions',
                ['externalserviceid' => $webserviceid, 'functionname' => $funtionaname])) {
                $DB->insert_record('external_services_functions',
                ['externalserviceid' => $webserviceid, 'functionname' => $funtionaname]);
            }
        }
        $token = $DB->get_field('external_tokens', 'token', ['userid' => $user, 'externalserviceid' => $webserviceid]);
        if (empty($token)) {
            $token = \core_external\util::generate_token(
                EXTERNAL_TOKEN_PERMANENT,
                \core_external\util::get_service_by_id($webserviceid),
                $user,
                \context_system::instance(),
                0,
                '',
                ''
            );
        }

        return [ 'success' => true ];
    }

    /**
     * Sets the data to the dynamic form from the JS passed args.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $isjson = $this->optional_param('isjson', null, PARAM_INT);
        $webservice = $this->optional_param('webservice', null, PARAM_ALPHANUM);
        $defaultvalues = [];
        if (!empty($isjson)) {
            $useremail = $this->optional_param('email', null, PARAM_TEXT);
            $user = $this->optional_param('userid', null, PARAM_INT);
            $webservicename = $this->optional_param('name', null, PARAM_TEXT);
            $capability = $this->optional_param('requiredcapability', null, PARAM_TEXT);
            $enabled = $this->optional_param('enabled', null, PARAM_INT);
            $candownload = $this->optional_param('downloadfiles', null, PARAM_INT);
            $canupload = $this->optional_param('uploadfiles', null, PARAM_INT);
            $functions = $this->optional_param('functions', null, PARAM_TEXT);
            if (empty($useremail)) {
                $defaultvalues['user'] = $user;
            } else {
                $user = $DB->get_field('user', 'id', ['email' => $useremail]);
                $defaultvalues['user'] = $user;
            }
            $defaultvalues['webservice'] = $webservice;

            if ($webservice == 'new') {
                if (!empty($webservicename)) {
                    $defaultvalues['webservicename'] = $webservicename;
                }
                if (!empty($capability)) {
                    $capbid = $DB->get_field('capabilities', 'id', ['name' => $capability]);
                    $defaultvalues['capability'] = $capbid;
                }
                if (!empty($enabled)) {
                    $defaultvalues['enabled'] = $enabled;
                }
                if (!empty($candownload)) {
                    $defaultvalues['candownload'] = $candownload;
                }
                if (!empty($canupload)) {
                    $defaultvalues['canupload'] = $canupload;
                }
                if (!empty($functions)) {
                    $explodedfunctions = explode(',', $functions);
                    $functionsids = '';
                    foreach ($explodedfunctions as $function) {
                        $function = $DB->get_field('external_functions', 'id', ['name' => $function]);
                        $functionsids .= $function . ',';
                    }
                    $functionsids = rtrim($functionsids, ',');
                    $defaultvalues['functions'] = $functionsids;
                }
            }
        } else {
            $user = $this->optional_param('user', null, PARAM_INT);
            $defaultvalues['user'] = $user;
            $defaultvalues['webservice'] = $webservice;
        }
        $this->set_data($defaultvalues);
    }

    /**
     * Gets the URL for the dynamic form submission.
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/customws/autoconfig.php');
    }

    /**
     * Form definition.
     */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('button', 'jsonload', get_string('loadjson', 'local_configws'));
        $mform->addElement('button', 'jsonsave', get_string('savejson', 'local_configws'));

        $mform->addElement('hidden', 'disablealwaystrue', 1);
        $mform->setType('disablealwaystrue', PARAM_INT);

        $users = [0 => get_string('select')];
        $users += $DB->get_records_menu('user', [], '', "id, concat(firstname,' ',lastname)");
        $mform->addElement('autocomplete', 'user', get_string('user'), $users);

        $selecteduser = $this->optional_param('user', 0, PARAM_INT);
        $mform->setDefault('user', $selecteduser);
        $wsoptions = [0 => get_string('select'), 'new' => get_string('new')];
        if ($selecteduser) {
            $userwsids = $DB->get_records('external_services_users',
                ['userid' => $selecteduser], '', 'id, externalserviceid as id');
            foreach ($userwsids as $ws) {
                $ws = $DB->get_record('external_services', ['id' => $ws->id]);
                $wsoptions[$ws->id] = $ws->name;
            }
        }

        $selectedws = $this->optional_param('webservice', 0, PARAM_INT);
        if ($selectedws) {
            $wsinfo = $DB->get_record('external_services', ['id' => $selectedws]);
        }
        $mform->addElement('select', 'webservice', get_string('webservice', 'local_configws'), $wsoptions);
        $mform->disabledIf('webservice', 'user', 'eq', 0);

        $mform->addElement('text', 'webservicename', get_string('webservicename', 'local_configws'), []);
        $mform->setType('webservicename', PARAM_TEXT);
        $mform->disabledIf('webservicename', 'webservice', 'noteq', 'new');
        $mform->hideIf('webservicename', 'webservice', 'eq', 0);
        $mform->setDefault('webservicename', $wsinfo->name ?? '');

        $mform->addElement('text', 'wsshortname', get_string('wsshortname', 'local_configws'), []);
        $mform->setType('wsshortname', PARAM_TEXT);
        $mform->hideIf('wsshortname', 'webservice', 'eq', 0);
        $mform->hideIf('wsshortname', 'webservice', 'eq', 'new');
        $mform->disabledIf('wsshortname', 'webservice', 'noteq', value: -1);
        $mform->setDefault('wsshortname', $wsinfo->shortname ?? '');

        $token = $DB->get_field('external_tokens', 'token', ['userid' => $selecteduser, 'externalserviceid' => $selectedws]);
        $mform->addElement('text', 'token', get_string('token', 'local_configws'));
        $mform->setType('token', PARAM_ALPHANUM);
        $mform->hideIf('token', 'webservice', 'eq', 'new');
        $mform->hideIf('token', 'webservice', 'eq', 0);
        $mform->setDefault('token', $token);

        $mform->disabledIf('token', 'disablealwaystrue', 'eq', 1);

        $mform->addElement('text', 'rolename', get_string('rolename', 'local_configws'));
        $mform->setType('rolename', PARAM_TEXT);
        $mform->hideIf('rolename', 'webservice', 'eq', 0);
        $mform->hideIf('rolename', 'webservice', 'eq', 'new');
        $mform->setDefault('rolename', $wsinfo->name ?? '');
        $mform->disabledIf('rolename', 'webservice', 'noteq',  -1);

        $mform->addElement('text', 'roleshortname', get_string('roleshortname', 'local_configws'));
        $mform->setType('roleshortname', PARAM_ALPHANUMEXT);
        $mform->hideIf('roleshortname', 'webservice', 'eq', 0);
        $mform->hideIf('roleshortname', 'webservice', 'eq', 'new');

        $mform->setDefault('roleshortname', $wsinfo->shortname ?? '');
        $mform->disabledIf('roleshortname', 'webservice', 'noteq', -1);

        $capoptions = $DB->get_records_menu('capabilities', [], '', "id, name");
        $mform->addElement('autocomplete', 'capability', get_string('capabilities', 'local_configws'),
            $capoptions, ['multiple' => false]);
        $mform->setDefault('capability', $wsinfo->requiredcapability ?? '');
        $mform->hideIf('capability', 'webservice', 'eq', value: 0);

        $mform->addElement('checkbox', 'enabled', get_string('enabled', 'local_configws'));
        $mform->setDefault('enabled', $wsinfo->enabled ?? 0);
        $mform->hideIf('enabled', 'webservice', 'eq', 0);

        $mform->addElement('checkbox', 'candownload', get_string('candownload', 'local_configws'));
        $mform->setDefault('candownload', $wsinfo->downloadfiles ?? 0);
        $mform->hideIf('candownload', 'webservice', 'eq', 0);

        $mform->addElement('checkbox', 'canupload', get_string('canupload', 'local_configws'));
        $mform->setDefault('canupload', $wsinfo->uploadfiles ?? 0);
        $mform->hideIf('canupload', 'webservice', 'eq', 0);

        $functionoptions = $DB->get_records_menu('external_functions', [], '', "id, name");
        $wsdefaultfunctions = '';
        $mform->addElement('autocomplete', 'functions', get_string('functions', 'local_configws'),
            $functionoptions, ['multiple' => true]);
        if ($selectedws) {
            $wsfunctions = $DB->get_records_menu('external_services_functions', ['externalserviceid' => $selectedws],
                '', 'id, functionname');
            if (!empty($wsfunctions)) {
                foreach ($wsfunctions as $function) {
                    if (!empty($wsdefaultfunctions)) {
                        $wsdefaultfunctions .= ', ';
                    }
                    $functionid = $DB->get_field('external_functions', 'id', ['name' => $function]);
                    $wsdefaultfunctions = $wsdefaultfunctions . $functionid;
                }
            }
        }
        $mform->setDefault('functions', $wsdefaultfunctions);
        $mform->hideIf('functions', 'webservice', 'eq', 0);
        $mform->hideIf('jsonsave', 'webservice', 'eq', 0);
        $mform->hideIf('jsonsave', 'webservice', 'eq', 'new');
    }

    /**
     * Form validation.
     * @param array $data The form data.
     * @param array $files The form files.
     * @return array The validated data.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty($data['user'])) {
            $errors['user'] = get_string('required');
        }
        if (empty($data['webservice'])) {
            $errors['webservice'] = get_string('required');
        }
        if (empty($data['webservicename'])) {
            $errors['webservicename'] = get_string('required');
        }
        return $errors;
    }
}
