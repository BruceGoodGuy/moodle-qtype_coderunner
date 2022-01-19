<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/*
 * qtype_coderunner external file. Allows webservice access by authenticated
 * users to the sandbox server (usually Jobe).
 *
 * @package    qtype_coderunner
 * @category   external
 * @copyright  2021 Richard Lobb, The University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");


class qtype_coderunner_external extends external_api {

    /**
     * Returns description of method parameters. Used for validation.
     * @return external_function_parameters
     */
    public static function run_in_sandbox_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT,
                        'The Moodle course ID of the originating web page',
                        VALUE_REQUIRED),
                'sourcecode' => new external_value(PARAM_RAW,
                        'The source code to be run', VALUE_REQUIRED),
                'language' => new external_value(PARAM_TEXT,
                        'The computer language of the sourcecode', VALUE_DEFAULT, 'python3'),
                'stdin' => new external_value(PARAM_RAW,
                        'The standard input to use for the run', VALUE_DEFAULT, ''),
                'files' => new external_value(PARAM_RAW,
                        'A JSON object in which attributes are filenames and values file contents',
                        VALUE_DEFAULT, ''),
                'params' => new external_value(PARAM_TEXT,
                        'A JSON object defining any sandbox parameters',
                        VALUE_DEFAULT, '')
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function run_in_sandbox_returns() {
        return new external_value(PARAM_RAW, 'The JSON-encoded Jobe server run result');
    }

    /**
     * Run a job in the sandbox (Jobe).
     * @param string $sourcecode The source code to be run.
     * @param string $language The language of execution (default python3)
     * @param string $stdin The standard input for the run (default empty)
     * @param string $files A JSON object in which attributes are filenames and
     * attribute values are the corresponding file contents.
     * @param string $params A JSON-encoded string defining any required Jobe sandbox
     * parameters (cputime, memorylimit etc).
     * @return string JSON-encoded Jobe run-result object.
     * @throws qtype_coderunner_exception
     */
    public static function run_in_sandbox($courseid, $sourcecode, $language='python3',
            $stdin='', $files='', $params='') {
        global $USER;
        // First, see if the web service is enabled.
        if (!get_config('qtype_coderunner', 'wsenabled')) {
            throw new qtype_coderunner_exception(get_string('wsdisabled', 'qtype_coderunner'));
        }

        // Now check if the user has the capability (usually meaning is logged in and not a guest).
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        if (!has_capability('qtype/coderunner:sandboxwsaccess', $context, $USER->id)) {
            throw new qtype_coderunner_exception(get_string('wsnoaccess', 'qtype_coderunner'));
        }
        // Parameters validation.
        self::validate_parameters(self::run_in_sandbox_parameters(),
                array('courseid' => $courseid,
                      'sourcecode' => $sourcecode,
                      'language' => $language,
                      'stdin' => $stdin,
                      'files' => $files,
                      'params' => $params
                    ));
        $sandbox = qtype_coderunner_sandbox::get_best_sandbox($language);
        if ($sandbox === null) {
            throw new qtype_coderunner_exception("Language {$language} is not available on this system");
        }

        if (get_config('qtype_coderunner', 'wsloggingenabled')) {
            // Check if need to throttle this user, and if not allow the request and log it.
            $logmanager = get_log_manager();$logmanger = get_log_manager();
            $readers = $logmanger->get_readers('\core\log\sql_reader');
            $reader = reset($readers);
            $maxhourlyrate = intval(get_config('qtype_coderunner', 'wsmaxhourlyrate'));
            if ($maxhourlyrate > 0) {
                $hour_ago = strtotime('-1 hour');
                $select = "userid = :userid AND eventname = :eventname AND timecreated > :since";
                $log_params = array('userid' => $USER->id, 'since' => $hour_ago,
                    'eventname' => '\qtype_coderunner\event\sandbox_webservice_exec');
                $currentrate = $reader->get_events_select_count($select, $log_params);
                if ($currentrate >= $maxhourlyrate) {
                    throw new qtype_coderunner_exception(get_string('wssubmissionrateexceeded', 'qtype_coderunner'));
                }
            }

            $context = context_system::instance(); // The event is logged as a system event.
            $event = \qtype_coderunner\event\sandbox_webservice_exec::create([
                'contextid' => $context->id,
                'courseid' => $courseid]);
            $event->trigger();
        }

        try {
            $filesarray = $files ? json_decode($files, true) : null;
            $paramsarray = $params ? json_decode($params, true) : array();
            $maxcputime = intval(get_config('qtype_coderunner', 'wsmaxcputime'));  // Limit CPU time through this service.
            if (isset($paramsarray['cputime'])) {
                $paramsarray['cputime'] = min($paramsarray['cputime'], $maxcputime);
            } else {
                $paramsarray['cputime'] = $maxcputime;
            }
            $jobehostws = trim(get_config('qtype_coderunner', 'wsjobeserver'));
            if ($jobehostws !== '') {
                $paramsarray['jobeserver'] = $jobehostws;
            }
            $runresult = $sandbox->execute($sourcecode, $language, $stdin, $filesarray, $paramsarray);
        } catch (Exception $ex) {
            throw new qtype_coderunner_exception("Attempt to run job failed with error {$ex->message}");
        }
        $runresult->sandboxinfo = null; // Prevent leakage of info.
        return json_encode($runresult);
    }
}
