<?php
/**
 * The question type class for the webwork question type.
 *
 * @copyright &copy; 2007 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/


require_once("$CFG->dirroot/question/type/webwork/config.php");
require_once("$CFG->dirroot/question/type/webwork/lib/questionfactory.php");

require_once("$CFG->dirroot/question/type/questiontype.php");
require_once("$CFG->dirroot/backup/lib.php");


/**
 * The webwork question class
 *
 * Allows webwork questions to be used in Moodle through a new question type.
 */
class webwork_qtype extends default_questiontype {
    
    //////////////////////////////////////////////////////////////////
    // Functions overriding default_questiontype functions
    //////////////////////////////////////////////////////////////////
    
    /**
    * @desc Required function that names the question type.
    * @return string webwork.
    */
    function name() {
        return 'webwork';
    }
    
    /**
    * @desc Gives the label in the Create Question dropdown.
    * @return string WeBWorK
    */
    function menu_name() {
        return 'WeBWorK';
    }
    
    /**
     * @desc Retrieves information out of question_webwork table and puts it into question object.
     * @return boolean to indicate success of failure.
     */
    function get_question_options(&$question) {
        //check if we have a question id.
        if(!isset($question->id)) {
            print_error('error_question_id','qtype_webwork');
            return false;
        }
        //check if we have a webwork question for this...
        try {
            $wwquestion = WebworkQuestionFactory::LoadByParent($question->id);
            $question->webwork = $wwquestion;
        } catch(Exception $e) {
            print_error('error_question_id_no_child','qtype_webwork');
        }        
        return true;
    }
    
    /**
    * @desc Saves the question object. Created to make some initial checks so we don't mess up the DB badly if stuff goes wrong.
    * @param $question object The question object holding new data.
    * @param $form object The form entries.
    * @param $course object The course object.
    */
    function save_question($question, $form, $course) {
        //check if we have a filepath key
        if(isset($form->filepath)) {
            $path = $form->filepath;
        } elseif(isset($question->filepath)) {
            $path = $question->filepath;
        } else {
            print_error('error_no_filepath','qtype_webwork');
            return false;
        }
        //check if we have a record
        try {
            $wwquestion = WebworkQuestionFactory::Holder($path);
            $form->questiontext = base64_decode($wwquestion->render(0,array(),0));
        } catch(Exception $e) {
            print_error('error_no_filepath_record','qtype_webwork');
        }
        
        //call parent
        return parent::save_question($question,$form,$course);
    }
    
    /**
     * @desc Saves the webwork question code and default seed setting into question_webwork. Will recreate all corresponding derived questions.
     * @param $question object The question object holding new data.
     * @return boolean to indicate success of failure.
     */
    function save_question_options($question) {
        //check if we have a filepath key and and a parent id.
        if(!isset($question->id)) {
            print_error('error_question_id','qtype_webwork');
            return false;
        }
        if(!isset($question->filepath)) {
            print_error('error_no_filepath','qtype_webwork');
            return false;
        }
        //attempt retrieval of the record for filepath key
        try {
            $wwquestion = WebworkQuestionFactory::Holder($question->filepath);
        } catch(Exception $e) {
            print_error('error_no_filepath_record','qtype_webwork');
        }
        //set the parent question
        $wwquestion->setParent($question->id);
        //save
        try {
            $result = $wwquestion->save();
        } catch(Exception $e) {
            print_error('error_db_failure','qtype_webwork');
        }
        return true;
    }
    
    /**
    * @desc Creates an empty response before a student answers a question. This contains the possibly randomized seed for that particular student. Sticky seeds are created here.
    * @param $question object The question object.
    * @param $state object The state object.
    * @param $cmoptions object The cmoptions containing the course ID
    * @param $attempt id The attempt ID.
    * @return bool true. 
    */
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        $state->responses['seed'] = rand(1,10000);
        return true;
    }
    
    /**
     * @desc Deletes question from the question_webwork table
     * @param integer $questionid The question being deleted
     * @return boolean to indicate success of failure.
     */
    function delete_question($questionid) {
        try {
            $wwquestion = WebworkQuestionFactory::LoadByParent($questionid);
            $wwquestion->remove();
        } catch(Exception $e) {
            print_error('error_question_id_no_child','qtype_webwork');
        }
        return true;
    }
    
    /**
    * @desc Decodes and unserializes a students response into the response array carried by state
    * @param $question object The question object.
    * @param $state object The state that needs to be restored.
    * @return bool true.
    */
    function restore_session_and_responses(&$question, &$state) {
        $serializedresponse = $state->responses[''];
        $serializedresponse = base64_decode($serializedresponse);
        $responses = unserialize($serializedresponse);
        $state->responses = $responses;
        return true;
    }
    
    /**
    * @desc Serialize, encodes and inserts a students response into the question_states table.
    * @param $question object The question object for the session.
    * @param $state object The state to save.
    * @return true, or error on db change.
    */
    function save_session_and_responses(&$question, &$state) {
        $responses = $state->responses;
        $serialized = serialize($responses);
        $serialized = base64_encode($serialized);
        return set_field('question_states', 'answer', $serialized, 'id', $state->id);
    }
    
    /**
    * @desc Prints the question. Calls question_webwork_derived, and prints out the html associated with derivedid.
    * @param $question object The question object to print.
    * @param $state object The state of the responses for the question.
    * @param $cmoptions object Options containing course ID.
    * @param $options object
    */
    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG,$USER;
        
        //find webworkquestion object
        $wwquestion = $question->webwork;
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        
        //find seed
        if(!isset($state->responses['seed'])) {
            print_error('error_no_seed','qtype_webwork');
            return false;
        }
        
        //find answers
        if(isset($state->responses['answers'])) {
            $answers = $state->responses['answers'];
        } else {
            $answers = array();
        }
        
        $seed = $state->responses['seed'];
        $event = $state->event;
        
        $questionhtml = $wwquestion->render($seed,$answers,$event);
        $showPartiallyCorrectAnswers = $wwquestion->getGrading();
        $qid = $wwquestion->getQuestion();
        
        include("$CFG->dirroot/question/type/webwork/display.html");
        flush();
    }
    
    /**
    * @desc Assigns a grade for a student response. Currently a percentage right/total questions. Calls the Webwork Server to evaluate answers.
    * @param $question object The question to grade.
    * @param $state object The response to the question.
    * @param $cmoptions object ...
    * @return boolean true.
    */
    function grade_responses(&$question, &$state, $cmoptions) {
        global $CFG,$USER;
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        $wwquestion = $question->webwork;
        $wwquestion->grade($state);
        // Apply the penalty for this attempt
        $state->penalty = $question->penalty * $question->maxgrade;
        
        return true;
    }
    
    /**
    * @desc Comparison of two student responses for the same question. Checks based on seed equality, and response equality.
    * Perhaps we could add check on evaluated answer (depends on whether the server is called before this function)
    * @param $question object The question object to compare.
    * @param $state object The first response.
    * @param $teststate object The second response.
    * @return boolean, Returns true if the state are equal | false if not.
    */
    function compare_responses($question, $state, $teststate) {        
        if(sizeof($state->responses) != sizeof($teststate->responses)) {
            return false;
        }
        //check values are equal
        foreach($state->responses as $key => $value) {
            if($value != $teststate->responses[$key]) {
                return false;
            }
        }
        return true;
    }
    
    /**
    * @desc Gets the correct answers from the SOAP server for the seed in state. Places them into the state->responses array.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return object Object containing the seed,derivedid, and answers.
    */
    function get_correct_responses(&$question, &$state) {
        
        if(!isset($question->webwork)) {
            print_error('error_no_wwquestion','qtype_webwork');
            return false;
        }
        $wwquestion = $question->webwork;
        
        //find seed
        if(!isset($state->responses['seed'])) {
            print_error('error_no_seed','qtype_webwork');
            return false;
        }
        $seed = $state->responses['seed'];
        $wwquestion->grade($state);
        
        $state->raw_grade = 1;
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;
        $state->penalty = 0;
        
        $ret = array();
        $ret['seed'] = $seed;
        $ret['answers'] = array();
        for($i=0;$i<count($state->responses['answers']);$i++) {
            $ret['answers'][$i]->answer = $state->responses['answers'][$i]->correct;
            $ret['answers'][$i]->answer_msg = "";
            $ret['answers'][$i]->score = '1';
            $ret['answers'][$i]->evaluated = "";
            $ret['answers'][$i]->preview = "";
            $ret['answers'][$i]->field = $state->responses['answers'][$i]->field;
        }
        return $ret;
    }
    
    /**
    * @desc Enumerates the pictures for a response.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return array HTML code with <img> tag for each picture.
    */
    function get_actual_response($question, $state) {
        $temp = '';
        $i = 1;
        foreach($state->responses['answers'] as $key => $value) {
            $responses[] = "$i) " . $value->answer;
            $i++;
        }
        return $responses;
    }
    
    /**
    * @desc Prints a summary of a response.
    * @param $question object The question object.
    * @param $state object The state object.
    * @return string HTML.
    */
    function response_summary($question, $state, $length=80) {
        // This should almost certainly be overridden
        $responses = $this->get_actual_response($question, $state);
        if (empty($responses) || !is_array($responses)) {
            $responses = array();
        }
        if (is_array($responses)) {
            $responses = implode('<br/><br/>', $responses);
        }
        return $responses;
    }
    
    /**
    * Changes all states for the given attempts over to a new question
    *
    * This is used by the versioning code if the teacher requests that a question
    * gets replaced by the new version. In order for the attempts to be regraded
    * properly all data in the states referring to the old question need to be
    * changed to refer to the new version instead. In particular for question types
    * that use the answers table the answers belonging to the old question have to
    * be changed to those belonging to the new version.
    *
    * @param integer $oldquestionid  The id of the old question
    * @param object $newquestion    The new question
    * @param array  $attempts       An array of all attempt objects in whose states
    *                               replacement should take place
    */
    function replace_question_in_attempts($oldquestionid, $newquestion, $attempts) {
        echo 'Not yet implemented';
        return;
    }
        
       
    
    /**
    * Renders the question for printing and returns the LaTeX source produced
    *
    * This function should render the question suitable for a printed problem
    * or solution sheet in LaTeX and return the rendered output.
    * @return string          The LaTeX output.
    * @param object $question The question to be rendered. Question type
    *                         specific information is included.
    * @param object $state    The state to render the question in. The
    *                         question type specific information is also
    *                         included.
    * @param object $cmoptions
    * @param string $type     Indicates if the question or the solution is to be
    *                         rendered with the values 'question' and
    *                         'solution'.
    */
    function get_texsource(&$question, &$state, $cmoptions, $type) {
        // The default implementation simply returns a string stating that
        // the question is only available online.
        return get_string('onlineonly', 'texsheet');
    }
    
    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {

        $status = true;

        $webworks = get_records('question_webwork', 'question', $question, 'id');
        //If there are webworks
        if ($webworks) {
            //Print webworks header
            //Iterate over each webwork
            foreach ($webworks as $webwork) {
                
                $status = fwrite ($bf,start_tag("WEBWORK",$level,true));
                
                fwrite ($bf,full_tag("CODE",$level+1,false,$webwork->code));
                fwrite ($bf,full_tag("SEED",$level+1,false,$webwork->seed));
                fwrite ($bf,full_tag("TRIALS",$level+1,false,$webwork->trials));
                
                $webworksderived = get_records('question_webwork_derived','question_webwork',$webwork->id);
                if($webworksderived) {
                    $status = fwrite ($bf,start_tag("WEBWORKDERIVED",$level+1,true));
                    foreach ($webworksderived as $webworkderived) {
                        fwrite ($bf,full_tag("ID",$level+2,false,$webworkderived->id));
                        fwrite ($bf,full_tag("QUESTION_WEBWORK",$level+2,false,$webworkderived->question_webwork));
                        fwrite ($bf,full_tag("HTML",$level+2,false,$webworkderived->html));
                        fwrite ($bf,full_tag("SEED",$level+2,false,$webworkderived->seed));
                    }
                    $status = fwrite ($bf,end_tag("WEBWORKDERIVED",$level+1,true));
                }
                $status = fwrite ($bf,end_tag("WEBWORK",$level,true));
            }
            //Print webworks footer
            //Now print question_webwork
            $status = question_backup_answers($bf,$preferences,$question);
        }
        return $status;
    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
     function restore($old_question_id,$new_question_id,$info,$restore) {

        $status = true;

        //Get the webworks array
        $webworks = $info['#']['WEBWORK'];

        //Iterate over webworks
        for($i = 0; $i < sizeof($webworks); $i++) {
            $webwork_info = $webworks[$i];

            //Now, build the question_webwork record structure
            $webwork = new stdClass;
            $webwork->question = $new_question_id;
            $webwork->code = backup_todb($webwork_info['#']['CODE']['0']['#']);
            $webwork->seed = backup_todb($webwork_info['#']['SEED']['0']['#']);
            $webwork->trials = backup_todb($webwork_info['#']['TRIALS']['0']['#']);

            //The structure is equal to the db, so insert the question_shortanswer
            $newid = insert_record("question_webwork",$webwork);
            
            $webworksderived = $webwork_info['#']['WEBWORKDERIVED'];
            for($j=0; $j < sizeof($webworksderived); $j++) {
                $webworkderived_info = $webworksderived[$j];
                
                $webworkderived = new stdClass;
                $webworkderived->question_webwork = $newid;
                $webworkderived->html = backup_todb($webworkderived_info['#']['HTML']['0']['#']);
                $webworkderived->seed = backup_todb($webworkderived_info['#']['SEED']['0']['#']);
                
                $newidderived = insert_record("question_webwork_derived",$webworkderived);
                if (!$newidderived) {
                    $status = false;
                }
            }

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }
        return $status;
    }
}

// Register this question type with the system.
question_register_questiontype(new webwork_qtype());
?>
