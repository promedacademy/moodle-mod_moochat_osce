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
 * Upgrade script for mod_moochat
 *
 * @package    mod_moochat
 * @copyright  2026 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_moochat_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // -----------------------------------------------------------------
    // 2026021801 - Add moochat_conversations table.
    // -----------------------------------------------------------------
    if ($oldversion < 2026021801) {
        $table = new xmldb_table('moochat_conversations');
        $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('moochatid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role',        XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message',     XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('moochatid', XMLDB_KEY_FOREIGN,  ['moochatid'], 'moochat', ['id']);
        $table->add_key('userid',    XMLDB_KEY_FOREIGN,  ['userid'],    'user',    ['id']);
        $table->add_index('moochatid-userid', XMLDB_INDEX_NOTUNIQUE, ['moochatid', 'userid']);
        $table->add_index('timecreated',      XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026021801, 'moochat');
    }

    // -----------------------------------------------------------------
    // 2026041600 - Add grading (grade, objectives) and objective results.
    // -----------------------------------------------------------------
    if ($oldversion < 2026041600) {

        $table = new xmldb_table('moochat');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'model');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('objectives', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $ortable = new xmldb_table('moochat_objective_results');
        $ortable->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $ortable->add_field('moochatid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $ortable->add_field('userid',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $ortable->add_field('objectiveindex',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $ortable->add_field('met',             XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '0');
        $ortable->add_field('timechecked',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $ortable->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
        $ortable->add_key('moochatid', XMLDB_KEY_FOREIGN, ['moochatid'], 'moochat', ['id']);
        $ortable->add_key('userid',    XMLDB_KEY_FOREIGN, ['userid'],    'user',    ['id']);
        $ortable->add_index('moochatid-userid',                XMLDB_INDEX_NOTUNIQUE, ['moochatid', 'userid']);
        $ortable->add_index('moochatid-userid-objectiveindex', XMLDB_INDEX_UNIQUE,    ['moochatid', 'userid', 'objectiveindex']);
        if (!$dbman->table_exists($ortable)) {
            $dbman->create_table($ortable);
        }

        upgrade_mod_savepoint(true, 2026041600, 'moochat');
    }

    // -----------------------------------------------------------------
    // 2026041601 - Add sessionid to conversations and objective_results.
    // -----------------------------------------------------------------
    if ($oldversion < 2026041601) {

        $table = new xmldb_table('moochat_conversations');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('moochat_objective_results');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('moochat_objective_results');
        $index = new xmldb_index('moochatid-userid-objectiveindex', XMLDB_INDEX_UNIQUE, ['moochatid', 'userid', 'objectiveindex']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $newindex = new xmldb_index('moochatid-userid-session-obj', XMLDB_INDEX_UNIQUE, ['moochatid', 'userid', 'sessionid', 'objectiveindex']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        $table = new xmldb_table('moochat');
        $field = new xmldb_field('pointsperobj', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'objectives');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041601, 'moochat');
    }

    // -----------------------------------------------------------------
    // 2026041602 - Add content_restrict field.
    //              Allows teacher to upload PDF/text files and restrict
    //              the AI to only answer from that content.
    // -----------------------------------------------------------------
    if ($oldversion < 2026041602) {

        $table = new xmldb_table('moochat');
        $field = new xmldb_field('content_restrict', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'pointsperobj');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041602, 'moochat');
    }
    
    // -----------------------------------------------------------------
    // 2026041603 - Add completionmessages field.
    //              Stores the minimum number of student messages required
    //              for the activity to be marked complete (0 = disabled).
    // -----------------------------------------------------------------
    if ($oldversion < 2026041603) {

        $table = new xmldb_table('moochat');
        $field = new xmldb_field('completionmessages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'content_restrict');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026041603, 'moochat');
    }

    // -----------------------------------------------------------------
    // 2026091700 - Add OSCE mode and session time limit.
    // -----------------------------------------------------------------
    if ($oldversion < 2026091700) {
    
        $table = new xmldb_table('moochat');
    
        $field = new xmldb_field(
            'osce_mode',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionmessages'
        );
    
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        $field = new xmldb_field(
            'sessiontimelimit',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '300',
            'osce_mode'
        );
    
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        upgrade_mod_savepoint(
            true,
            2026091700,
            'moochat'
        );
    }
    return true;

    return true;
}
