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
 * A two column layout for the moove theme.
 *
 * @package   theme_moove
 * @copyright 2017 Willian Mano - http://conecti.me
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

user_preference_allow_ajax_update('drawer-open-nav', PARAM_ALPHA);
user_preference_allow_ajax_update('sidepre-open', PARAM_ALPHA);

require_once($CFG->libdir . '/behat/lib.php');

if (isloggedin()) {
    $navdraweropen = (get_user_preferences('drawer-open-nav', 'true') == 'true');
    $draweropenright = (get_user_preferences('sidepre-open', 'true') == 'true');
} else {
    $navdraweropen = false;
    $draweropenright = false;
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;

$postblockshtml = $OUTPUT->blocks('side-post');
$topblockshtml = $OUTPUT->blocks('top');

$extraclasses = [];
if ($navdraweropen) {
    $extraclasses[] = 'drawer-open-left';
}

if ($draweropenright && $hasblocks) {
    $extraclasses[] = 'drawer-open-right';
}

$coursepresentation = theme_moove_get_setting('coursepresentation');
if ($coursepresentation == 2) {
    $extraclasses[] = 'coursepresentation-cover';
}

$themesettings = get_config('theme_moove');

$customcourseheader = null;
$customcategorybanner = null;

if (property_exists($themesettings, 'custombanner') && $themesettings->custombanner) {

    global $DB, $course;

    if ($course instanceof stdClass) {
        $course = new core_course_list_element($course);
    }

    $category = $DB->get_record('course_categories', array('id' => $course->category));

    $customcategorybanner = null;
    $customcategories = explode("\n", $themesettings->customcategories);
    foreach ($customcategories as $customcategory) {
        $options = explode('|', $customcategory);

        if (count($options) != 2) {
            continue;
        }

        $parentid = $options[0];
        if ($category->id == $parentid || strpos($category->path, "/{$parentid}/") !== false) {
            $customcategorybanner = new \stdClass();
            $customcategorybanner->src = $options[1];
            $customcategorybanner->name = $category->name;
            break;
        }
    }

    if ($customcategorybanner) {

        $customcourseheader = new stdClass();

        if (strpos($course->fullname, '(') > 0) {
            $customcourseheader->coursename = trim(substr($course->fullname, 0, strpos($course->fullname, '(') - 1));
        } else {
            $customcourseheader->coursename = $course->fullname;
        }

        // Course instructors.
        if ($course->has_course_contacts()) {
            $instructors = $course->get_course_contacts();
            if ($instructors && count($instructors) > 0) {

                foreach ($instructors as $key => $instructor) {
                    $customcourseheader->teachername = $instructor['username'];

                    $url = $CFG->wwwroot . '/user/profile.php?id=' . $key;
                    $customcourseheader->teacherimage = \theme_moove\util\extras::get_user_picture(
                                                                        $DB->get_record('user', array('id' => $key)));

                    // Only show the first instructor.
                    break;
                }
            }
        }

        $customcourseheader->options = array();

        $context = context_course::instance($course->id);

        $options = explode("\n", $themesettings->custommenuoptions);
        foreach ($options as $option) {
            $one = explode('|', $option);

            if (count($one) != 6) {
                continue;
            }

            if ($one[0] == '*' || has_capability($one[0], $context)) {

                $item = new \stdClass();
                $item->target = trim($one[3]);
                $item->name = trim($one[4]);
                $item->cssclass = trim($one[5]);

                if ($one[1] == 'url') {
                    $url = str_replace('{courseid}', $course->id, $one[2]);

                    if (substr($url, 0, 1) == '/') {
                        $item->url = new \moodle_url($url);
                    } else {
                        $item->url = $url;
                    }
                } else if (substr($one[1], 0, 4) == 'mod_') {
                    $modulename = substr($one[1], 4, strlen($one[1]) - 4);

                    if (!empty($one[2])) {
                        $instance = $DB->get_records($modulename, array('course' => $course->id));

                        if (!$instance || count($instance) == 0) {
                            continue;
                        }

                        $instance = reset($instance);

                        if (empty($item->name) && property_exists($instance, 'name')) {
                            $item->name = $instance->name;
                        }

                        $cm = get_coursemodule_from_instance($modulename, $instance->id, $course->id);

//                         $sql = "SELECT cm.id
//                                     FROM {" . $modulename . "} AS i
//                                     INNER JOIN {modules} AS m ON m.name = :modulename
//                                     INNER JOIN {course_modules} AS cm ON cm.module = m.id AND cm.instance = :instanceid";
//
//                         $modinstance = $DB->get_record($sql, array('modulename' => $modulename, 'instanceid' => $instance->id));

                        $item->url = new \moodle_url('/mod/' . $modulename . '/view.php', array('id' => $cm->id));

                    } else {
                        $item->url = new \moodle_url('/mod/' . $modulename . '/index.php', array('id' => $course->id));
                    }
                }

                $customcourseheader->options[] = $item;

            }
        }
    }
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$regionmainsettingsmenu = $OUTPUT->region_main_settings_menu();
$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'sidepostblocks' => $postblockshtml,
    'topblocks' => $topblockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'hasdrawertoggle' => true,
    'navdraweropen' => $navdraweropen,
    'draweropenright' => $draweropenright,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'customcourseheader' => $customcourseheader,
    'customcategorybanner' => $customcategorybanner
];

// Improve boost navigation.
theme_moove_extend_flat_navigation($PAGE->flatnav);

$templatecontext['flatnavigation'] = $PAGE->flatnav;

$themesettings = new \theme_moove\util\theme_settings();

$templatecontext = array_merge($templatecontext, $themesettings->footer_items());

if (!$coursepresentation || $coursepresentation == 1) {
    echo $OUTPUT->render_from_template('theme_moove/course', $templatecontext);
} else if ($coursepresentation == 2) {
    echo $OUTPUT->render_from_template('theme_moove/course_cover', $templatecontext);
}
