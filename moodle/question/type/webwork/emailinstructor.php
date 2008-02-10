<?php // $Id: preview.php,v 1.13.2.6 2008-01-24 15:06:46 tjhunt Exp $
/**
 * The email instructor form and page.
 *
 * @copyright &copy; 2008 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
 **/

    require_once("../../../config.php");
    require_once($CFG->libdir.'/questionlib.php');
    require_once($CFG->libdir.'/weblib.php');
    require_once($CFG->dirroot.'/mod/quiz/locallib.php'); // We really want to get rid of this

    $id = required_param('id', PARAM_INT);
    $seed = required_param('seed', PARAM_INT);
    $quizid = required_param('quizid', PARAM_INT);
    $message = optional_param('message', '', PARAM_RAW);
    $send = optional_param('send',0,PARAM_INT);

    require_login();
    
    // Load the question information
    if (!$question = get_record('question', 'id', $id)) {
        print_error('error_question_id','qtype_webwork');
    }
    
    // Load the question type specific information
    if (!get_question_options($question)) {
        print_error('error_question_id_no_child','qtype_webwork');
    }
    
    // Load the quiz information
    if(!$quiz = get_record('quiz','id',$quizid)) {
        error('Invalid Quiz ID');
    }
    
    $courseid = $quiz->course;
    
    // Load the course information
    if(!$course = get_record('course','id',$courseid)) {
        error('Invalid Course ID');
    }
    
    // Find where the question is in the quiz
    $questionorder = explode(',',$quiz->questions);
    $count = 1;
    for($i=0;$i<$questionorder;$i++) {
        if($questionorder[$i] != 0) {
            if($questionorder[$i] == $id) {
                break;
            } else {
                $count++;
            }
        }
    }
    $questioninquiz = $count;
    
    //Send the email
    if ($send) {
        $link = $CFG->wwwroot . '/question/type/webwork/instructorpreview.php?';
        $link .= 'id=' . $id;
        $link .= '&amp;seed=' . $seed;
        $link .= '&amp;uid='.$USER->id;
        $link .= '&amp;quizid='.$quizid;
        
        $info =  "<h3>".get_string('wwquestion','qtype_webwork')."</h3>";
        $info .= "<table>";
        $info .= "<tr><td><b>Course</b></td><td>" . $course->fullname . "</td></tr>";
        $info .= "<tr><td><b>Quiz</b></td><td>" . $quiz->name . "</td></tr>";
        $info .= "<tr><td><b>Student</b></td><td>" . $USER->firstname . ' ' . $USER->lastname . "</td></tr>";
        $info .= "<tr><td><b>Question #</b></td><td>". $questioninquiz . "</td></tr>";
        $info .= "<tr><td><b>Link to Question<b></td><td><a href='".$link."'>".get_string('viewquestion','qtype_webwork')."</a></td></tr>";
        $info .= "</table>";
        
        $info .= "";
        $info .= "<br><br>";
        $message = $info . $message;
        $msghtml = $message;
        $msgtext = html_to_text($message);
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $sentsomewhere = false;
        if ($users = get_users_by_capability($context, 'moodle/course:viewcoursegrades')) {
            foreach ($users as $user) {
                if(email_to_user($user,$USER,get_string('wwquestion','qtype_webwork'),$msgtext,$msghtml,NULL,NULL,$USER->email,$USER->email,$USER->firstname . ' ' . $USER->lastname)) {
                    $sentsomewhere = true;
                }
            }
        }
        if($sentsomewhere) {
            echo get_string('emailconfirm','qtype_webwork').'<br><br>';
            echo '<BUTTON onclick="window.close();">Close</BUTTON>';
            exit(1);
        } else {
            print_error('error_send_email','qtype_webwork');
        }
    }
    
    

    $strpreview = get_string('emailinstructor','qtype_webwork');
    
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
    echo '<input type="hidden" name="quizid" value="'.$quizid.'"/>';
    echo '<input type="hidden" name="send" value="1"/>';
    echo '<input type="submit" value="Send Email" />';
    echo "</form>";
    use_html_editor('message');
    print_footer();
?>