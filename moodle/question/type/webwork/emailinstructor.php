<?php // $Id: preview.php,v 1.13.2.6 2008-01-24 15:06:46 tjhunt Exp $
/**
* This page displays the form to send a question to the instructor.
*
* The preview uses the option settings from the activity within which the question
* is previewed or the default settings if no activity is specified. The question session
* information is stored in the session as an array of subsequent states rather
* than in the database.
*
* TODO: make this work with activities other than quiz
*
* @version $Id: preview.php,v 1.13.2.6 2008-01-24 15:06:46 tjhunt Exp $
* @author Alex Smith as part of the Serving Mathematics project
*         {@link http://maths.york.ac.uk/serving_maths}
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package question
*/

    require_once("../../../config.php");
    require_once($CFG->libdir.'/questionlib.php');
    require_once($CFG->libdir.'/weblib.php');
    require_once($CFG->dirroot.'/mod/quiz/locallib.php'); // We really want to get rid of this

    $id = required_param('id', PARAM_INT);        // question id
    $seed = required_param('seed', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
    $message = optional_param('message', '', PARAM_RAW);
    $send = optional_param('send',0,PARAM_INT);

    require_login();
    
    if ($send) {
        $link = $CFG->wwwroot . '/question/type/webwork/instructorpreview.php?id=' . $id . '&amp;seed=' . $seed . '&amp;uid='.$USER->id;
        $info = "WeBWorK Problem Email<br>Problem Link: <a href='".$link."'>View Problem</a><br>";
        $info .= "From: " . $USER->firstname . ' ' . $USER->lastname;
        $info .= "<br><br>";
        $message = $info . $message;
        $msghtml = $message;
        $msgtext = html_to_text($message);
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $sentsomewhere = false;
        if ($users = get_users_by_capability($context, 'moodle/course:viewcoursegrades')) {
            foreach ($users as $user) {
                if(email_to_user($user,$USER,'WeBWorK Problem',$msgtext,$msghtml,NULL,NULL,$USER->email,$USER->email,$USER->firstname . ' ' . $USER->lastname)) {
                    $sentsomewhere = true;
                }
            }
        }
        if($sentsomewhere) {
            echo 'Email was sent<br><br>';
            echo '<BUTTON onclick="window.close();">Close</BUTTON>';
            exit(1);
        } else {
            print_error('error_send_email','qtype_webwork');
        }
    }
    
    // Load the question information
    if (!$question = get_record('question', 'id', $id)) {
        error('Could not load question');
    }
    
    // Load the question type specific information
    if (!get_question_options($question)) {
        error(get_string('newattemptfail', 'quiz'));
    }

    $strpreview = "Email Instructor";
    
    print_header($strpreview);
    print_heading($strpreview);
    
    //create default state
    $state = new stdClass;
    $state->responses['seed'] = $seed;
    $state->event = 0;
    
    $option = new stdClass;
    $option->readonly = true;
    echo "<b>Question</b><br>";
    $QTYPES['webwork']->print_question_formulation_and_controls($question,$state,NULL,$option);
    
    
    echo "<br><br>\n";
    echo "<form method=\"post\" action=\"emailinstructor.php\">\n";
    echo "<b>Message</b><br>";
    print_textarea(true,15,30,NULL,NULL,'message','');
    
    echo "<br><br>";
    echo '<input type="hidden" name="id" value="'.$id.'"/>';
    echo '<input type="hidden" name="seed" value="'.$seed.'"/>';
    echo '<input type="hidden" name="courseid" value="'.$courseid.'"/>';
    echo '<input type="hidden" name="send" value="1"/>';
    echo '<input type="submit" value="Send Email" />';
    echo "</form>";
    use_html_editor('message');
    print_footer();
?>