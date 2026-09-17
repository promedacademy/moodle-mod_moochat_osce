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
 * Library functions for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add moochat instance.
 */
function moochat_add_instance($moochat) {
    global $DB;
    $moochat->timecreated  = time();
    $moochat->timemodified = time();
    if (empty($moochat->grade)) {
        $moochat->grade = 0;
    }
    if (!isset($moochat->objectives)) {
        $moochat->objectives = '';
    }
    if (!isset($moochat->content_restrict)) {
        $moochat->content_restrict = 0;
    }
    if (!isset($moochat->completionmessages)) {
        $moochat->completionmessages = 0;
    }

    if (!isset($moochat->osce_mode)) {
    $moochat->osce_mode = 0;
    }

    if (!isset($moochat->sessiontimelimit)) {
    $moochat->sessiontimelimit = 300;
    }
    
    $moochat->id = $DB->insert_record('moochat', $moochat);
    moochat_grade_item_update($moochat);
    return $moochat->id;
    
}

/**
 * Update moochat instance.
 */
function moochat_update_instance($moochat) {
    global $DB;
    $moochat->timemodified = time();
    $moochat->id           = $moochat->instance;
    if (empty($moochat->grade)) {
        $moochat->grade = 0;
    }
    if (!isset($moochat->objectives)) {
        $moochat->objectives = '';
    }
    if (!isset($moochat->content_restrict)) {
        $moochat->content_restrict = 0;
    }
    if (!isset($moochat->completionmessages)) {
        $moochat->completionmessages = 0;
    }

    if (!isset($moochat->osce_mode)) {
    $moochat->osce_mode = 0;
    }

    if (!isset($moochat->sessiontimelimit)) {
    $moochat->sessiontimelimit = 300;
    }
    $result = $DB->update_record('moochat', $moochat);
    if ($moochat->grade == 0) {
        moochat_grade_item_delete($moochat);
    } else {
        moochat_grade_item_update($moochat);
    }
    return $result;
}

/**
 * Delete moochat instance.
 */
function moochat_delete_instance($id) {
    global $DB;
    if (!$moochat = $DB->get_record('moochat', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('moochat_usage',              ['moochatid' => $id]);
    $DB->delete_records('moochat_conversations',       ['moochatid' => $id]);
    $DB->delete_records('moochat_objective_results',   ['moochatid' => $id]);
    $DB->delete_records('moochat',                     ['id'        => $id]);
    moochat_grade_item_delete($moochat);
    return true;
}

/**
 * Reset course user data.
 */
function moochat_reset_userdata($data) {
    global $DB;
    $status       = [];
    $componentstr = get_string('modulenameplural', 'moochat');
    if (!empty($data->reset_moochat_conversations)) {
        $moochats = $DB->get_records('moochat', ['course' => $data->courseid]);
        foreach ($moochats as $moochat) {
            $DB->delete_records('moochat_conversations',     ['moochatid' => $moochat->id]);
            $DB->delete_records('moochat_usage',             ['moochatid' => $moochat->id]);
            $DB->delete_records('moochat_objective_results', ['moochatid' => $moochat->id]);
        }
        $status[] = ['component' => $componentstr, 'item' => get_string('viewconversations', 'moochat'), 'error' => false];
    }
    if (!empty($data->reset_moochat_grades)) {
        $moochats = $DB->get_records('moochat', ['course' => $data->courseid]);
        foreach ($moochats as $moochat) {
            moochat_grade_item_update($moochat, 'reset');
        }
        $status[] = ['component' => $componentstr, 'item' => get_string('grades'), 'error' => false];
    }
    return $status;
}

/**
 * Define reset form options.
 */
function moochat_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'moochatheader', get_string('modulenameplural', 'moochat'));
    $mform->addElement('checkbox', 'reset_moochat_conversations', get_string('viewconversations', 'moochat'));
    $mform->addElement('checkbox', 'reset_moochat_grades', get_string('grades'));
}

/**
 * Default reset form values.
 */
function moochat_reset_course_form_defaults($course) {
    return ['reset_moochat_conversations' => 1, 'reset_moochat_grades' => 0];
}

/**
 * Supported features.
 */
function moochat_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPS:                  return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        default:                              return null;
    }
}

/**
 * Create or update the gradebook grade item.
 */
function moochat_grade_item_update($moochat, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $moochat->name,
        'idnumber' => isset($moochat->cmidnumber) ? $moochat->cmidnumber : '',
    ];

    if (!isset($moochat->grade) || $moochat->grade == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else if ($moochat->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $moochat->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$moochat->grade;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/moochat', $moochat->course, 'mod', 'moochat', $moochat->id, 0, $grades, $params);
}

/**
 * Delete the gradebook grade item.
 */
function moochat_grade_item_delete($moochat) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/moochat', $moochat->course, 'mod', 'moochat', $moochat->id, 0, null, ['deleted' => 1]);
}

/**
 * Update a student grade in the gradebook.
 */
function moochat_update_grade($moochat, $userid, $rawgrade) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if ($moochat->grade == 0) {
        return;
    }
    $grade           = new stdClass();
    $grade->userid   = $userid;
    $grade->rawgrade = $rawgrade;
    moochat_grade_item_update($moochat, $grade);
}

/**
 * Return grades for given user or all users (used by gradebook).
 */
function moochat_get_user_grades($moochat, $userid = 0) {
    global $DB;
    $sql  = 'SELECT DISTINCT userid FROM {moochat_objective_results} WHERE moochatid = :moochatid';
    $args = ['moochatid' => $moochat->id];
    if ($userid) {
        $sql  .= ' AND userid = :userid';
        $args['userid'] = $userid;
    }
    $userids = $DB->get_fieldset_sql($sql, $args);
    $grades  = [];
    foreach ($userids as $uid) {
        $g = moochat_calculate_grade($moochat, $uid);
        if ($g) {
            $grades[$uid] = $g;
        }
    }
    return $grades;
}

/**
 * Calculate a user's best-session grade.
 *
 * Supports weighted OSCE objectives.
 */
function moochat_calculate_grade($moochat, $userid) {
    global $DB;

    if (
        empty($moochat->objectives) ||
        $moochat->grade == 0
    ) {
        return null;
    }

    $objectives = moochat_parse_objectives(
        $moochat->objectives,
        $moochat->pointsperobj ?? 1
    );

    if (empty($objectives)) {
        return null;
    }

    $records = $DB->get_records(
        'moochat_objective_results',
        [
            'moochatid' => $moochat->id,
            'userid' => $userid,
            'met' => 1,
        ]
    );

    $sessions = [];

    foreach ($records as $record) {

        if (!isset($sessions[$record->sessionid])) {
            $sessions[$record->sessionid] = [];
        }

        $sessions[$record->sessionid][
            (int)$record->objectiveindex
        ] = true;
    }

    $bestscore = 0;

    foreach ($sessions as $sessionid => $met) {

        $metweight = 0;

        foreach ($met as $index => $unused) {

            if (isset($objectives[$index])) {
                $metweight +=
                    (float)$objectives[$index]['weight'];
            }
        }

        $score = moochat_normalize_objective_score(
            $metweight,
            $objectives,
            $moochat->grade
        );

        $bestscore = max($bestscore, $score);
    }

    $grade = new stdClass();
    $grade->userid = $userid;
    $grade->rawgrade = $bestscore;

    return $grade;
}

/**
 * Parse learning objectives.
 *
 * Supported formats:
 *
 * [5] Professional introduction
 * [10] Presenting complaint
 *
 * or legacy:
 *
 * Professional introduction
 * Presenting complaint
 *
 * Legacy objectives use pointsperobj.
 *
 * @param string $objectivesraw
 * @param int $defaultweight
 * @return array
 */
function moochat_parse_objectives(
    $objectivesraw,
    $defaultweight = 1
) {

    if (empty($objectivesraw)) {
        return [];
    }

    $lines = preg_split(
        '/\r?\n/',
        $objectivesraw
    );

    $out = [];

    foreach ($lines as $line) {

        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $weight = (float)$defaultweight;
        $text = $line;

        /*
         * Weighted syntax:
         *
         * [5] Professional introduction
         */
        if (
            preg_match(
                '/^\[\s*(\d+(?:\.\d+)?)\s*\]\s*(.+)$/',
                $line,
                $matches
            )
        ) {

            $weight = (float)$matches[1];
            $text = trim($matches[2]);
        }

        /*
         * Never allow zero/negative objective weights.
         */
        if ($weight <= 0) {
            $weight = 1;
        }

        $out[] = [
            'text' => $text,
            'weight' => $weight,
        ];
    }

    return $out;
}

/**
 * Extract text from all uploaded content files for this activity.
 *
 * Supports PDF (via pdftotext or smalot parser) and plain text (.txt).
 * Returns the combined text of all uploaded files, or empty string if none.
 *
 * @param int    $contextid  The module context ID
 * @param object $moochat    The moochat instance record
 * @return string            Combined extracted text from all uploaded files
 */
function moochat_get_uploaded_content($contextid, $moochat) {
    global $CFG;

    $fs      = get_file_storage();
    $files   = $fs->get_area_files($contextid, 'mod_moochat', 'contentfiles', 0, 'filename', false);

    if (empty($files)) {
        return '';
    }

    $content = '';

    foreach ($files as $file) {
        $mimetype = $file->get_mimetype();
        $fileext  = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $filename = $file->get_filename();

        if ($mimetype === 'application/pdf' || $fileext === 'pdf') {
            // Extract PDF text — try pdftotext first, fall back to smalot parser.
            $tmpfile = tempnam(sys_get_temp_dir(), 'moochat_pdf_') . '.pdf';
            $file->copy_content_to($tmpfile);
            $pdftext   = '';
            $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null'));
            if (!empty($pdftotext)) {
                $pdftext = shell_exec('pdftotext ' . escapeshellarg($tmpfile) . ' -');
            }
            if (empty($pdftext)) {
                $autoload = $CFG->dirroot . '/mod/moochat/vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once($autoload);
                    try {
                        $parser  = new \Smalot\PdfParser\Parser();
                        $pdf     = $parser->parseFile($tmpfile);
                        $pdftext = $pdf->getText();
                    } catch (\Exception $e) {
                        error_log('MooChat: Error parsing PDF ' . $filename . ': ' . $e->getMessage());
                    }
                }
            }
            if (!empty($pdftext)) {
                $content .= "\n--- " . $filename . " ---\n" . trim($pdftext) . "\n";
            }
            unlink($tmpfile);

        } else if ($fileext === 'txt' || strpos($mimetype, 'text/') === 0) {
            // Plain text — read directly.
            $text = $file->get_content();
            if (!empty($text)) {
                $content .= "\n--- " . $filename . " ---\n" . trim($text) . "\n";
            }
        }
    }

    return $content;
}

/**
 * Serve files from the moochat file areas.
 */
function mod_moochat_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Allowed file areas.
    if ($filearea !== 'avatar' && $filearea !== 'contentfiles') {
        return false;
    }

    require_login($course, false, $cm);

    // Content files are teacher-uploaded — only teachers can download them directly.
    if ($filearea === 'contentfiles') {
        require_capability('mod/moochat:viewhistory', $context);
    }

    $fs       = get_file_storage();
    $filename = array_pop($args);
    $filepath = '/';
    $file     = $fs->get_file($context->id, 'mod_moochat', $filearea, 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Return the content for inline display on the course page.
 */
function moochat_get_coursemodule_info($coursemodule) {
    global $DB, $PAGE;
    $moochat    = $DB->get_record('moochat', ['id' => $coursemodule->instance], '*', MUST_EXIST);
    $info       = new cached_cm_info();
    $info->name = $moochat->name;

    // Store custom completion rule data so get_custom_rule_descriptions() can read it.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionmessages'] = $moochat->completionmessages;
    }

    if ($moochat->display == 1) {
        $context   = context_module::instance($coursemodule->id);
        $avatarurl = null;
        $fs        = get_file_storage();
        $files     = $fs->get_area_files($context->id, 'mod_moochat', 'avatar', 0, 'filename', false);
        if (!empty($files)) {
            $file      = reset($files);
            $avatarurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(),
                $file->get_filearea(), $file->get_itemid(),
                $file->get_filepath(), $file->get_filename()
            );
        }
        $chatinterface = new \mod_moochat\output\chat_interface($moochat, $avatarurl);
        $renderer      = $PAGE->get_renderer('mod_moochat');
        $info->content = $renderer->render_chat_interface($chatinterface);
    }
    return $info;
}

/**
 * Callback to add JS when course page loads (inline display).
 */
function moochat_cm_info_view(cm_info $cm) {
    global $PAGE, $DB;
    $moochat = $DB->get_record('moochat', ['id' => $cm->instance]);
    if ($moochat && $moochat->display == 1) {
        $PAGE->requires->js_call_amd('mod_moochat/chat', 'init', [$moochat->id]);
    }
}

/**
 * Extract text content from the current section for the AI context.
 */
function moochat_get_section_content($courseid, $sectionnum, $includehidden = false) {
    global $DB, $CFG;

    $content = "\n\n=== COURSE SECTION CONTENT ===\n\n";
    $modinfo = get_fast_modinfo($courseid);
    $section = $modinfo->get_section_info($sectionnum);
    if (empty($section->sequence)) {
        return '';
    }
    $cms = explode(',', $section->sequence);

    foreach ($cms as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        if (!$includehidden && !$cm->uservisible) {
            continue;
        }
        $content .= "\n--- " . format_string($cm->name) . " ---\n";

        switch ($cm->modname) {
            case 'page':
                if ($page = $DB->get_record('page', ['id' => $cm->instance])) {
                    $content .= strip_tags($page->content) . "\n";
                }
                break;
            case 'book':
                if ($book = $DB->get_record('book', ['id' => $cm->instance])) {
                    $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum');
                    foreach ($chapters as $chapter) {
                        $content .= "Chapter: " . format_string($chapter->title) . "\n";
                        $content .= strip_tags($chapter->content) . "\n";
                    }
                }
                break;
            case 'label':
                if ($label = $DB->get_record('label', ['id' => $cm->instance])) {
                    $content .= strip_tags($label->intro) . "\n";
                }
                break;
            case 'assign':
                if ($assign = $DB->get_record('assign', ['id' => $cm->instance])) {
                    $content .= "Assignment Description: " . strip_tags($assign->intro) . "\n";
                }
                break;
            case 'url':
                if ($url = $DB->get_record('url', ['id' => $cm->instance])) {
                    $content .= "URL: " . $url->externalurl . "\n";
                    if ($url->intro) {
                        $content .= strip_tags($url->intro) . "\n";
                    }
                }
                break;
            case 'glossary':
                if ($glossary = $DB->get_record('glossary', ['id' => $cm->instance])) {
                    $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossary->id]);
                    $content .= "Glossary Entries:\n";
                    foreach ($entries as $entry) {
                        $content .= "- " . format_string($entry->concept) . ": " . strip_tags($entry->definition) . "\n";
                    }
                }
                break;
            case 'resource':
                $fs      = get_file_storage();
                $context = context_module::instance($cmid);
                $files   = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
                foreach ($files as $file) {
                    $mimetype = $file->get_mimetype();
                    $fileext  = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                    if ($mimetype === 'application/pdf' || $fileext === 'pdf') {
                        $tmpfile   = tempnam(sys_get_temp_dir(), 'moochat_pdf_') . '.pdf';
                        $file->copy_content_to($tmpfile);
                        $pdftext   = '';
                        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null'));
                        if (!empty($pdftotext)) {
                            $pdftext = shell_exec('pdftotext ' . escapeshellarg($tmpfile) . ' -');
                        }
                        if (empty($pdftext)) {
                            $autoload = $CFG->dirroot . '/mod/moochat/vendor/autoload.php';
                            if (file_exists($autoload)) {
                                require_once($autoload);
                                try {
                                    $parser  = new \Smalot\PdfParser\Parser();
                                    $pdf     = $parser->parseFile($tmpfile);
                                    $pdftext = $pdf->getText();
                                } catch (\Exception $e) {
                                    error_log('MooChat: Error parsing PDF: ' . $e->getMessage());
                                }
                            }
                        }
                        if (!empty($pdftext)) {
                            $content .= $pdftext . "\n";
                        }
                        unlink($tmpfile);
                    } else if (
                        $mimetype === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ||
                        $fileext  === 'pptx'
                    ) {
                        $tmpfile    = tempnam(sys_get_temp_dir(), 'moochat_pptx_') . '.pptx';
                        $file->copy_content_to($tmpfile);
                        $zip = new \ZipArchive();
                        if ($zip->open($tmpfile) === true) {
                            $slide_number = 1;
                            $doc = new \DOMDocument();
                            while (($idx = $zip->locateName('ppt/slides/slide' . $slide_number . '.xml')) !== false) {
                                $doc->loadXML($zip->getFromIndex($idx),
                                    LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                                $content .= strip_tags($doc->saveXML()) . "\n";
                                $slide_number++;
                            }
                            $zip->close();
                        }
                        unlink($tmpfile);
                    } else if (strpos($mimetype, 'text/') === 0) {
                        $content .= strip_tags($file->get_content()) . "\n";
                    }
                }
                break;
        }
    }

    $content .= "\n=== END COURSE SECTION CONTENT ===\n\n";
    return $content;
}


/**
 * Return total objective weight.
 */
function moochat_total_objective_weight($objectives) {

    $total = 0;

    foreach ($objectives as $objective) {
        $total += (float)$objective['weight'];
    }

    return $total;
}


/**
 * Convert achieved objective weight into the activity grade.
 *
 * Example:
 *
 * Total weights = 100
 * Achieved = 75
 * Grade = 100
 *
 * Result = 75.
 */
function moochat_normalize_objective_score(
    $achievedweight,
    $objectives,
    $grademax
) {

    $totalweight =
        moochat_total_objective_weight(
            $objectives
        );

    if (
        $totalweight <= 0 ||
        $grademax <= 0
    ) {
        return 0;
    }

    return round(
        ($achievedweight / $totalweight) * $grademax,
        2
    );
}


/**
 * Calculate current session score.
 */
function moochat_calculate_session_score(
    $moochat,
    $objectives,
    $userid,
    $sessionid
) {

    global $DB;

    if (
        empty($sessionid) ||
        empty($objectives)
    ) {
        return 0;
    }

    $records = $DB->get_records(
        'moochat_objective_results',
        [
            'moochatid' => $moochat->id,
            'userid' => $userid,
            'sessionid' => $sessionid,
            'met' => 1,
        ]
    );

    $achievedweight = 0;

    foreach ($records as $record) {

        $index =
            (int)$record->objectiveindex;

        if (isset($objectives[$index])) {

            $achievedweight +=
                (float)$objectives[$index]['weight'];
        }
    }

    return moochat_normalize_objective_score(
        $achievedweight,
        $objectives,
        $moochat->grade
    );
}
