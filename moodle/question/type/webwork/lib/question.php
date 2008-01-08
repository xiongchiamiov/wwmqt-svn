<?php
/**
 * The WebworkQuestion Class.
 * 
 * @copyright &copy; 2007 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/

require_once("$CFG->dirroot/question/type/webwork/config.php");
require_once("$CFG->dirroot/question/type/webwork/lib/client.php");
require_once("$CFG->dirroot/question/type/webwork/lib/htmlparser.php");


/**
* @desc Defines the different states a question can be created under. Used to determine if we should create directories etc.
*/
define('WWQUESTION_CODECHECK_OFF',          0);
define('WWQUESTION_CODECHECK_ERRORS',       1);
define('WWQUESTION_CODECHECK_ALL',          2);
define('WWQUESTION_CODECHECK_ERRORS_MANY',  3);
define('WWQUESTION_CODECHECK_ALL_MANY',     4);

/**
* @desc The WeBWorKQuestion class
*/
class WebworkQuestion {
    
    /**
    * @desc The derivation used by this question.
    */
    private $_derivation;
    
    /**
    * @desc Object holding the same fields as the DB.
    */
    private $_data;
    
    /**
    * @desc Sets up the default problem environment that gets passed to the server.
    * @return object The problem environment.
    */
    public static function DefaultEnvironment() {
        global $USER;
        $env = new stdClass;
        $env->psvn = "MoodleSet";
        $env->psvnNumber = $env->psvn;
        $env->probNum = "MoodleProblemNum";
        $env->questionNumber = $env->probNum;
        $env->fileName = "MoodleProblemTemplate";
        $env->probFileName = $env->fileName;
        $env->problemSeed = "0";
        $env->displayMode = "HTML_dpng";
        $env->languageMode = $env->displayMode;
        $env->outputMode = $env->displayMode;
        $env->formattedOpenDate = "MoodleOpenDate";
        $env->openDate = "10";
        $env->formattedDueDate = "MoodleOpenDate";
        $env->dueDate = "11";
        $env->formattedAnswerDate = "MoodleAnswerDate";
        $env->answerDate = "12";
        $env->numOfAttempts = "3";
        $env->problemValue = "";
        $env->sectionName = "Default Profs Name";
        $env->sectionNumber = $env->sectionName;
        $env->recitationName = "Default TAs Name";
        $env->recitationNumber = $env->recitationName;
        $env->setNumber = "Default Set";
        $env->studentLogin = $USER->username;
        $env->studentName = $USER->firstname . " " . $USER->lastname;
        $env->studentNID = $USER->username;
        $env->ANSWER_PREFIX = "Moodle";
        return $env;
    }
    

//////////////////////////////////////////////////////////////////////////////////
//MAIN FUNCTIONS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Constructor for a question. Sets the data and creates a path to a directory.
    * @param object $dataobject The object that will go into the db.
    * @param object $derivation A derivation of the question.
    */
    public function WebworkQuestion($dataobject,$derivation=null) {
        $this->_data = $dataobject;
        $this->_derivation = $derivation;    
    }
    
    /**
    * @desc Generates the HTML for a particular question.
    * @param integer $seed The seed of the question.
    * @param array $answers An array of answers that needs to be rendered.
    * @param object $event The event object.
    * @return string The HTML question representation.
    */
    public function render($seed,$answers,$event) {
        if(!isset($this->_derivation)) {
            $client = WebworkClient::Get();
            $env = WebworkQuestion::DefaultEnvironment();
            $env->problemSeed = $seed;
            $result = $client->renderProblem($env,$this->_data->code);
            $derivation = new stdClass;
            $derivation->html = base64_decode($result->output);
            $derivation->seed = $result->seed;
            $this->_derivation = $derivation;
        }
        
        $newanswers = array();
        foreach($answers as $answer) {
            $newanswers[$answer->field] = $answer;
        }
        $answers = $newanswers;
        
        $showpartialanswers = $this->_data->grading;
        $questionhtml = "";
        $parser = new HtmlParser($this->_derivation->html);
        $currentselect = "";
        while($parser->parse()) {
            //change some attributes of html tags for moodle compliance
            if ($parser->iNodeType == NODE_TYPE_ELEMENT) {
                $nodename = $parser->iNodeName;
                if(isset($parser->iNodeAttributes['name'])) {
                    $name = $parser->iNodeAttributes['name'];
                }
                //handle generic change of node's attribute name
                if(($nodename == "INPUT") || ($nodename == "SELECT") || ($nodename == "TEXTAREA")) {
                    $parser->iNodeAttributes['name'] = 'resp' . $this->_data->question . '_' . $name;
                    if(($event == QUESTION_EVENTGRADE) && (isset($answers[$name]))) {
                        if($showpartialanswers) {
                            if(isset($parser->iNodeAttributes['class'])) {
                                $class = $parser->iNodeAttributes['class'];
                            } else {
                                $class = "";
                            }
                            $parser->iNodeAttributes['class'] = $class . ' ' . question_get_feedback_class($answers[$name]->score);
                        }
                    }
                    if((!strstr($name,'previous')) && (isset($answers[$name]))) {
                        
                    }
                }
                //handle specific change
                if($nodename == "INPUT") {
                    //put submitted value into field
                    if(isset($answers[$name])) {
                        $parser->iNodeAttributes['value'] = $answers[$name]->answer;
                    }
                } else if($nodename == "SELECT") {
                    $currentselect = $name;    
                } else if($nodename == "OPTION") {
                    if($parser->iNodeAttributes['value'] == $answers[$currentselect]->answer)
                        $parser->iNodeAttributes['selected'] = '1';
                } else if($nodename == "TEXTAREA") {
                }
            }
            $questionhtml .= $parser->printTag();
        }
        return $questionhtml;
    }
    
    /**
    * @desc Grades a particular question state.
    * @param object $state The state to grade.
    * @return true.
    */    
    public function grade(&$state) {
        $seed = $state->responses['seed'];
        $answers = array();
        if((isset($state->responses['answers'])) && (is_array($state->responses['answers']))) {
            foreach($state->responses['answers'] as $answerobj) {
                if((is_string($answerobj->field)) && (is_string($answerobj->answer))) {
                    array_push($answers, array('field' => $answerobj->field, 'answer'=> $answerobj->answer));
                }
            }
        }
        if((isset($state->responses)) && (is_array($state->responses))) {
            foreach($state->responses as $key => $value) {
                if((is_string($key)) && (is_string($value))) {
                    array_push($answers, array('field' => $key,'answer'=>$value));
                }
            }
        }
        
        $client = WebworkClient::Get();
        $env = WebworkQuestion::DefaultEnvironment();
        $env->problemSeed = $seed;
        
        $results = $client->renderProblemAndCheck($env,$this->_data->code,array(),$answers);
        
        //process the question
        $question = $results->problem;
        $derivation = new stdClass;
        $derivation->seed = $question->seed;
        $derivation->html = base64_decode($question->output);
        $this->_derivation = $derivation;
        
        //assign a grade
        $answers = $results->answers;
        $state->raw_grade = $this->processAnswers($answers);

        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;
        
        //put the responses into the state to remember
        if(!is_array($state->responses)) {
            $state->responses = array();
        }
        $state->responses['answers'] = $answers;
        return true;
    }
    
    /**
    * @desc Saves a question into the database.
    * @return true.
    */
    public function save() {
        if(isset($this->_data->id)) {
            $this->update();
        } else {
            $this->insert();
        }
        return true;
    }
    
    /**
    * @desc Updates the wwquestion record in the database.
    * @throws Exception on DB error.
    */
    protected function update() {
        $dbresult = update_record('question_webwork',$this->_data);
        if(!$dbresult) {
            throw new Exception();
        }
    }
    
    /**
    * @desc Inserts the wwquestion record into the database. Fills in the new database ID.
    * @throws Exception on DB error.
    */
    protected function insert() {
        $dbresult = insert_record('question_webwork',$this->_data);
        if($dbresult) {
            $this->_data->id = $dbresult;
        } else {
            throw new Exception();
        }
    }
    
    /**
    * @desc Does basic processing on the answers back from the server.
    * @param array $answers The answers recieved.
    * @return integer The grade.
    */
    protected function processAnswers(&$answers) {
        $total = 0;   
        for($i=0;$i<count($answers);$i++) {
            $answers[$i]->preview = base64_decode($answers[$i]->preview);
            $total += $answers[$i]->score;            
        }
        return $total / $i;
    }    
     
//////////////////////////////////////////////////////////////////////////////////
//SETTERS
//////////////////////////////////////////////////////////////////////////////////
    
    public function setParent($id) {
        $this->_data->question = $id;
    }

//////////////////////////////////////////////////////////////////////////////////
//GETS
//////////////////////////////////////////////////////////////////////////////////

    
    public function getId() {
        return $this->_data->id;
    }
    
    public function getQuestion() {
        return $this->_data->question;
    }
    
    public function getCode() {
        return $this->_data->code;
    }
    
    public function getCodeText() {
        return base64_decode($this->_data->code);
    }
    
    public function getCodeCheck() {
        return $this->_data->codecheck;
    }
    
    public function getGrading() {
        return $this->_data->grading;
    }

//////////////////////////////////////////////////////////////////////////////////
//REMOVERS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Removes a wwquestion object.
    */
    public function remove() {
        delete_records('question_webwork','id',$this->_data->id);
    }
    
    
}

?>