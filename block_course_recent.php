<?php
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
 * Recent courses block main class.
 *
 * @package   blocks-course_recent
 * @copyright 2010 Remote Learner - http://www.remote-learner.net/
 * @author    Akin Delamarre <adelamarre@remote-learner.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class block_course_recent extends block_list {
    function init() {
        $this->title   = get_string('course_recent', 'block_course_recent');
        $this->version = 2010071300;
    }

    function get_content() {
        global $CFG, $USER, $COURSE;

        require_once($CFG->dirroot.'/blocks/course_recent/lib.php');

        if ($this->content !== NULL) {
          return $this->content;
        }

        $this->content         =  new stdClass;
        $this->content->items  = array();
        $this->content->icons  = array();
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        $maximum = isset($CFG->block_course_recent_default) ? $CFG->block_course_recent_default : DEFAULT_MAX;

        $userlimit = get_field('block_course_recent', 'userlimit', 'userid', $USER->id);

        // Override the global setting if the user limit is set
        if (!empty($userlimit)) {
            $maximum = $userlimit;
        }

        // Make sure the maximum record number is within the acceptible range.
        if (LOWER_LIMIT > $maximum) {
            $maximum = LOWER_LIMIT;
        } elseif (UPPER_LIMIT < $maximum) {
            $maximum = UPPER_LIMIT;
        }

        // Set flag to check user's role on the course
        $checkrole = !empty($CFG->block_course_recent_musthaverole);

        // Get a list of all courses that have been viewed by the user.
        if (!$checkrole) {
            $sql = "SELECT DISTINCT(logs.course)
                    FROM (
                        SELECT l.course, l.time
                        FROM {$CFG->prefix}log l
                        WHERE l.userid = 2
                        AND l.course NOT IN(0, 1)
                        AND l.action = 'view'
                        ORDER BY l.time DESC
                    ) AS logs";
        } else {
            $sql = "SELECT DISTINCT(logs.course)
                    FROM (
                        SELECT l.course, l.time
                        FROM {$CFG->prefix}log l
                        INNER JOIN {$CFG->prefix}context ctx ON l.course = ctx.instanceid
                        INNER JOIN {$CFG->prefix}role_assignments ra ON ra.contextid = ctx.id
                        WHERE l.userid = 2
                        AND l.course NOT IN(0, 1)
                        AND ctx.contextlevel = " . CONTEXT_COURSE . "
                        AND ra.userid = l.userid
                        AND l.action = 'view'
                        ORDER BY l.time DESC
                    ) AS logs";
        }

        $records = get_records_sql($sql, 0, $maximum);

        if (empty($records)) {
            $records = array();
        }

        $i = 1;

        // Set flag to display hidden courses
        $context    = get_context_instance(CONTEXT_SYSTEM);
        $showhidden = has_capability('moodle/course:viewhiddencourses', $context, $USER->id);

        // Set flag to true by defafult
        $showcourse = true;

        $icon  = '<img src="' . $CFG->pixpath . '/i/course.gif" class="icon" alt="' .
                 get_string('coursecategory') . '" />';

        // Create links for each course that was viewed by the user
        foreach ($records as $key => $record) {
            $visible = get_field('course', 'visible', 'id', $record->course);

            $class = ($visible) ? 'visible' : 'notvisible';

            if ($visible or $showhidden) {
                // Get a list or courses where the user has the student role
                $fullname = get_field('course', 'fullname', 'id', $record->course);
                $this->content->items[] = '<a class="' . $class . '" href="'. $CFG->wwwroot .'/course/view.php?id=' .
                                          $record->course . '">' . $fullname . '</a>';
                $this->content->icons[] = $icon;
            }
        }

        $context = get_context_instance(CONTEXT_BLOCK, $this->instance->id);

        if (has_capability('block/course_recent:changelimit', $context, $USER->id)) {
            $this->content->footer = '<a href="' . $CFG->wwwroot.'/blocks/course_recent/usersettings.php?' .
                                     'courseid='.$COURSE->id . '">' . get_string('settings', 'block_course_recent') .
                                     '</a>';
        }

        return $this->content;
    }

    function has_config() {
        return true;
    }
}

?>