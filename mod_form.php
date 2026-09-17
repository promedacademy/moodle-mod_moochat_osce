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
 * Activity settings form for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_moochat_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // ---------------------------------------------------------------
        // General section.
        // ---------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name.
        $mform->addElement('text', 'name', get_string('chatname', 'moochat'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction.
        $this->standard_intro_elements();

        // Display mode.
        $displayoptions = [
            0 => get_string('display_page',   'moochat'),
            1 => get_string('display_inline', 'moochat'),
        ];
        $mform->addElement('select', 'display', get_string('display', 'moochat'), $displayoptions);
        $mform->addHelpButton('display', 'display', 'moochat');
        $mform->setDefault('display', 0);

        // Chat size.
        $sizeoptions = [
            'small'  => get_string('chatsize_small',  'moochat'),
            'medium' => get_string('chatsize_medium', 'moochat'),
            'large'  => get_string('chatsize_large',  'moochat'),
        ];
        $mform->addElement('select', 'chatsize', get_string('chatsize', 'moochat'), $sizeoptions);
        $mform->addHelpButton('chatsize', 'chatsize', 'moochat');
        $mform->setDefault('chatsize', 'medium');

        // Include section content.
        $mform->addElement('advcheckbox', 'include_section_content', get_string('include_section_content', 'moochat'));
        $mform->addHelpButton('include_section_content', 'include_section_content', 'moochat');
        $mform->setDefault('include_section_content', 0);

        $warninghtml = '<div class="alert alert-warning">' . get_string('include_section_content_warning', 'moochat') . '</div>';
        $mform->addElement('static', 'include_section_content_warning', '', $warninghtml);
        $mform->hideIf('include_section_content_warning', 'include_section_content');

        // Include hidden content.
        $mform->addElement('advcheckbox', 'include_hidden_content', get_string('include_hidden_content', 'moochat'));
        $mform->addHelpButton('include_hidden_content', 'include_hidden_content', 'moochat');
        $mform->setDefault('include_hidden_content', 0);
        $mform->hideIf('include_hidden_content', 'include_section_content');

        // Avatar Image Upload.
        $mform->addElement('filemanager', 'avatar', get_string('avatar', 'moochat'), null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]);
        $mform->addHelpButton('avatar', 'avatar', 'moochat');

        // Avatar Size.
        $sizes = ['48' => 'Small (48x48)', '64' => 'Medium (64x64)', '96' => 'Large (96x96)', '128' => 'Extra Large (128x128)'];
        $mform->addElement('select', 'avatarsize', get_string('avatarsize', 'moochat'), $sizes);
        $mform->setDefault('avatarsize', '64');
        $mform->addHelpButton('avatarsize', 'avatarsize', 'moochat');

        // System Prompt.
        $mform->addElement('editor', 'systemprompt_editor', get_string('systemprompt', 'moochat'), ['rows' => 10], ['maxfiles' => 0]);
        $mform->setType('systemprompt_editor', PARAM_RAW);
        $mform->addHelpButton('systemprompt_editor', 'systemprompt', 'moochat');

        // ---------------------------------------------------------------
        // Course Content for AI section.
        // ---------------------------------------------------------------
        $mform->addElement('header', 'contentheader', get_string('contentheader', 'moochat'));
        $mform->setExpanded('contentheader', true);

        // File upload — same options as avatar but allow 5 files and any type
        // (type filtering caused upload hangs on some servers).
        $mform->addElement('filemanager', 'contentfiles', get_string('contentfiles', 'moochat'), null,
            ['subdirs' => 0, 'maxfiles' => 5, 'accepted_types' => '*']);
        $mform->addHelpButton('contentfiles', 'contentfiles', 'moochat');

        // Only use uploaded content checkbox.
        $mform->addElement('advcheckbox', 'content_restrict', get_string('content_restrict', 'moochat'));
        $mform->addHelpButton('content_restrict', 'content_restrict', 'moochat');
        $mform->setDefault('content_restrict', 0);

        // ---------------------------------------------------------------
        // Learning Objectives & Grading section.
        // ---------------------------------------------------------------
        $mform->addElement('header', 'objectivesheader', get_string('objectivesheader', 'moochat'));
        $mform->setExpanded('objectivesheader', true);

        $mform->addElement('textarea', 'objectives', get_string('objectives', 'moochat'), ['rows' => 8, 'cols' => 60]);
        $mform->setType('objectives', PARAM_TEXT);
        $mform->addHelpButton('objectives', 'objectives', 'moochat');
        $mform->setDefault('objectives', '');

        $hinthtml = '<div class="alert alert-info">' . get_string('objectiveshint', 'moochat') . '</div>';
        $mform->addElement('static', 'objectiveshint', '', $hinthtml);

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade[modgrade_type]', 'none');

        $mform->addElement('advcheckbox','osce_mode','OSCE mode');

        $mform->addHelpButton('osce_mode','osce_mode','moochat');

        $mform->setDefault('osce_mode',0);
        
        $mform->addElement('select','sessiontimelimit','OSCE session time limit',[0   => 'No time limit',180 => '3 minutes',240 => '4 minutes',300 => '5 minutes',360 => '6 minutes',420 => '7 minutes',600 => '10 minutes',]);
        
        $mform->addHelpButton('sessiontimelimit','sessiontimelimit','moochat');
        
        $mform->setDefault('sessiontimelimit',300);
        
        $mform->hideIf('sessiontimelimit','osce_mode','eq',0);
        
        
        // ---------------------------------------------------------------
        // Rate Limiting section.
        // ---------------------------------------------------------------
        $mform->addElement('header', 'ratelimitheader', get_string('ratelimiting', 'moochat'));

        $mform->addElement('advcheckbox', 'ratelimit_enable', get_string('ratelimit_enable', 'moochat'));
        $mform->addHelpButton('ratelimit_enable', 'ratelimit_enable', 'moochat');
        $mform->setDefault('ratelimit_enable', 0);

        $periods = ['hour' => get_string('period_hour', 'moochat'), 'day' => get_string('period_day', 'moochat')];
        $mform->addElement('select', 'ratelimit_period', get_string('ratelimit_period', 'moochat'), $periods);
        $mform->setDefault('ratelimit_period', 'day');
        $mform->addHelpButton('ratelimit_period', 'ratelimit_period', 'moochat');
        $mform->hideIf('ratelimit_period', 'ratelimit_enable');

        $mform->addElement('text', 'ratelimit_count', get_string('ratelimit_count', 'moochat'));
        $mform->setType('ratelimit_count', PARAM_INT);
        $mform->setDefault('ratelimit_count', 10);
        $mform->addHelpButton('ratelimit_count', 'ratelimit_count', 'moochat');
        $mform->hideIf('ratelimit_count', 'ratelimit_enable');

        // ---------------------------------------------------------------
        // Advanced Settings section.
        // ---------------------------------------------------------------
        $mform->addElement('header', 'advancedheader', get_string('advancedsettings', 'moochat'));
        $mform->setExpanded('advancedheader', false);

        $mform->addElement('text', 'maxmessages', get_string('maxmessages', 'moochat'));
        $mform->setType('maxmessages', PARAM_INT);
        $mform->setDefault('maxmessages', 20);
        $mform->addHelpButton('maxmessages', 'maxmessages', 'moochat');

        $temperatures = [
            '0.1' => '0.1 - Very Focused',
            '0.3' => '0.3 - Focused',
            '0.5' => '0.5 - Balanced',
            '0.7' => '0.7 - Creative',
            '0.9' => '0.9 - Very Creative',
        ];
        $mform->addElement('select', 'temperature', get_string('temperature', 'moochat'), $temperatures);
        $mform->setDefault('temperature', '0.7');
        $mform->addHelpButton('temperature', 'temperature', 'moochat');

        // ---------------------------------------------------------------
        // Completion rules — must come before standard_coursemodule_elements().
        // ---------------------------------------------------------------
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add custom completion rules.
     *
     * Called automatically by standard_coursemodule_elements().
     *
     * @return array Element names added (used by Moodle to show/hide them).
     */
    public function add_completion_rules() {
        $mform  = $this->_form;
        $suffix = $this->get_suffix();

        // Minimum interactions rule.
        $group = [];
        $completionmessagesel = 'completionmessages' . $suffix;
        $group[] = $mform->createElement(
            'advcheckbox',
            $completionmessagesel,
            '',
            get_string('completionmessages', 'moochat')
        );
        $completionmessagescountel = 'completionmessagescount' . $suffix;
        $group[] = $mform->createElement(
            'text',
            $completionmessagescountel,
            '',
            ['size' => 3]
        );
        $group[] = $mform->createElement(
            'static',
            'completionmessageslabel' . $suffix,
            '',
            get_string('completionmessages_label', 'moochat')
        );

        $groupname = 'completionmessagesgroup' . $suffix;
        $mform->addGroup($group, $groupname, get_string('completionmessages', 'moochat'), ' ', false);
        $mform->setType($completionmessagescountel, PARAM_INT);
        $mform->setDefault($completionmessagescountel, 5);
        $mform->hideIf($completionmessagescountel, $completionmessagesel);
        $mform->disabledIf($completionmessagescountel, $completionmessagesel);

        return [$groupname];
    }

    /**
     * Returns true if any custom completion rule is enabled.
     *
     * @param array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionmessages' . $suffix]);
    }

    /**
     * Pre-process form data to load the completionmessages count into the group field.
     */
    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        $suffix = $this->get_suffix();

        // Map the stored completionmessages integer into the count sub-field.
        if (isset($default_values['completionmessages']) && $default_values['completionmessages'] > 0) {
            $default_values['completionmessages' . $suffix]      = 1;
            $default_values['completionmessagescount' . $suffix] = $default_values['completionmessages'];
        } else {
            $default_values['completionmessages' . $suffix]      = 0;
            $default_values['completionmessagescount' . $suffix] = 5;
        }
        
        // Prepare system prompt editor.
        $default_values['systemprompt_editor'] = [
            'text'   => $default_values['systemprompt'] ?? get_string('defaultprompt', 'moochat'),
            'format' => FORMAT_HTML,
        ];

        // Prepare file manager for avatar.
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('avatar');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_moochat',
                'avatar', 0, ['subdirs' => false, 'maxfiles' => 1]);
            $default_values['avatar'] = $draftitemid;
        }

        // Prepare file manager for content files.
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('contentfiles');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_moochat',
                'contentfiles', 0, ['subdirs' => false, 'maxfiles' => 5]);
            $default_values['contentfiles'] = $draftitemid;
        }
    }

    /**
     * Post-process submitted form data.
     *
     * Collapses the completionmessages group fields into a single integer
     * stored in $data->completionmessages (0 = disabled, N = threshold).
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        $suffix = $this->get_suffix();
        $enabled = !empty($data->{'completionmessages' . $suffix});
        $count   = isset($data->{'completionmessagescount' . $suffix})
            ? (int) $data->{'completionmessagescount' . $suffix}
            : 5;

        // Store 0 when disabled, otherwise store the count (minimum 1).
        $data->completionmessages = $enabled ? max(1, $count) : 0;
        
        // Save system prompt — strip HTML tags before storing so the AI gets clean text.
        if (isset($data->systemprompt_editor['text'])) {
            $data->systemprompt = strip_tags($data->systemprompt_editor['text']);
        }

        // Handle avatar file upload.
        if (!empty($data->avatar)) {
            file_save_draft_area_files($data->avatar, $this->context->id, 'mod_moochat',
                'avatar', 0, ['subdirs' => false, 'maxfiles' => 1]);
        }

        // Handle content files upload.
        if (!empty($data->contentfiles)) {
            file_save_draft_area_files($data->contentfiles, $this->context->id, 'mod_moochat',
                'contentfiles', 0, ['subdirs' => false, 'maxfiles' => 5]);
        }
    }
}
