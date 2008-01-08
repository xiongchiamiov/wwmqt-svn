<?php
/**
 * The format file for the webwork question importer.
 * 
 * @copyright &copy; 2007 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/


define('WWQUESTION_OPTIONS_FILE','OPTIONS.conf');
define('WWQUESTION_REQUEST_SIZE',1);

require_once("$CFG->libdir/xmlize.php");
require_once("$CFG->dirroot/question/type/webwork/lib/locallib.php");
require_once("$CFG->dirroot/question/type/webwork/lib/question.php");
require_once("$CFG->dirroot/mod/quiz/lib.php");
require_once("$CFG->dirroot/mod/quiz/editlib.php");
require_once("$CFG->dirroot/course/lib.php");

//require_once("$CFG->libdir/filelib.php");
//require_once("$CFG->libdir/setuplib.php");

/**
* @desc This class provides the ability to mass import webwork questions into moodle.
*/
class qformat_webwork extends qformat_default {
    
    /**
    * @desc Question Data
    */
    var $questions;
    var $sets;
    var $tempdirs;
    var $counter;
    var $optionstack;
    var $quizmodnum;
    
//////////////////////////////////////////////////////////////////////////////////
//IMPORTER REQUISITE FUNCTIONS
//////////////////////////////////////////////////////////////////////////////////

    /**
    * @desc Providing Import Ability?
    * @return bool true
    */
    function provide_import() {
        return true;
    }
    
    /**
    * @desc Providing Export Ability?
    * @return bool true
    */
    function provide_export() {
        return false;
    }
    
    /**
    * @desc Processes a zip file into an object containing the import data.
    * @param $string filename The zip file to be processed.
    * @return object The data.
    */
    function readdata($filename) {
        global $CFG;
        set_time_limit(0);
        echo "Beginning Processing Stage<br>";
        echo "Extracting '$filename'...<br>";
        flush();
        
        $unique_code = time();
        $temp_dir = $CFG->dataroot."/temp/webworkquiz_import/".$unique_code;
        $this->temp_dir = $temp_dir;
        //failures we cannot handle
        if (!$this->check_and_create_import_dir($unique_code)) {
            error("Could not create temporary directory");
            return;
        }
        if(!is_readable($filename)) {
            error ("Could not read uploaded file");
            return;
        }
        if (!copy($filename, "$temp_dir/webwork.zip")) {
            error("Could not copy backup file");
            return;
        }
        if(!unzip_file("$temp_dir/webwork.zip", '', false)) {
            print "filename: $filename<br />tempdir: $temp_dir <br />";
            error("Could not unzip file.");
            return;   
        }
        
        $this->questions = array();
        $this->sets = array();
        $this->tempdirs = array();
        $this->counter = 0;
        
        echo "Done<br><br>";
        flush();
        
        $this->init_options();
        //recurse through categories
        $this->process_category_directory($temp_dir,"Default");
        
        echo "Processing Complete<br><br>";
        flush();
        
        return $this->questions;
    }
    
    /**
    * @desc Takes the data in the questions array and creates derivations for it. Returns the questions that passed codecheck
    * @param array $data The questions to be added.
    * @return array The questions that passed the codecheck.
    */
    function readquestions($data) {
        echo "Starting Import Section...<br>";
        flush();
        
        $correctdata = array();
        $errors = array();
        
        //this is where we load question requests
        
        $start = time();
        $count = 1;
        $total = count($this->questions);
        echo "We have $total questions and categories to generate!<br>";
        flush();
        foreach($this->questions as $question) {
            if(isset($question->qtype) && ($question->qtype == 'webwork')) {
                echo "Question #$count ($question->name) ... ";
                flush();
                $qstart = time();
                $wwquestion = WebworkQuestionFactory::Import($question);
                $ret = $wwquestion->createDerivations();
                $qend = time();
                $qdiff = $qend-$qstart;
                echo "Generated ~($qdiff secs)<br>";
                flush();
                if((!$ret) || (!isset($question->code))) {
                    $errors[$question->name] = $wwquestion->getErrorMsg();
                    $wwquestion->removeDir();
                } else {
                    WebworkQuestionFactory::Holder($wwquestion->getPath(),$wwquestion);
                    $question->filepath = $wwquestion->getPath();
                    array_push($correctdata,$question);
                }
                $count++;
            } elseif ((isset($question->qtype)) && ($question->qtype == 'category')) {
                //category addition
                echo "Category Added $question->category <br>";
                flush();
                array_push($correctdata,$question);
            } else {
                echo "Unused File<br>";
                flush();
            }
        }
        $end = time();
        $diff = $end - $start;
        echo "Finished Generation, total time ~($diff secs)<br><br>";
        flush();
        if(count($errors) > 0) {
            echo "<br>Found " . count($errors) . " Question(s) with Errors:<br>";
            foreach($errors as $name => $error) {
                echo "Name: " . $name . "<br>";
                echo "Error:" . $error . "<br><br>";
            }
        }
        echo "Done<br>";
        echo "Importing " . count($correctdata) . " Questions and Categories...<br>";
        flush();
        return $correctdata;
    }
    
    /**
    * @desc Does post processing after the import completes.
    * @return boolean true
    */
    function importpostprocess() {
        echo "Finished Import of Questions<br>";
        echo "Starting Import of Definitions<br>";
        flush();
        $this->insert_set_definitions();
        echo "Finished Import of Definitions<br>";
        flush();
        //need to clean up temporary directories
        /*fulldelete($this->tempdirs);
        foreach($this->tempdirs as $tempdir) {
            fulldelete($tempdir);
        }*/
        echo "Complete!<br>";
        flush();
        return true; 
    }
    
    /**
    * @desc Returns a string describing the question object
    * @param object $question The question object.
    * @return string.
    */
    function format_question_text($question) {
        return $question->name . ' -- Seed: ' . $question->seed . ' -- Trials:' . $question->trials;
    }
    
//////////////////////////////////////////////////////////////////////////////////
//IMPORTER PROCESS FUNCTIONS (file content --> arrays)
//////////////////////////////////////////////////////////////////////////////////

    /**
    * @desc Processes a category directory recursively down the tree.
    * @param string $dir The directory to process.
    * @param object The data from the directory (recursive)
    */
    function process_category_directory($dir,$categoryname) {
        
        echo "Processing Directory...<br>";
        flush();
        
        //add trailing slash
        if ($dir[strlen($dir)-1] != '/') {
            $dir .= '/';
        }
        //is this a directory
        if (!is_dir($dir)) {
            return;
        }
        
        //add this category
        $category = new stdClass;
        $category->qtype = 'category';
        $category->category = $categoryname;
        $this->questions[] = $category;        
        //data arrays
        $categorydirs = array();
        $questionfiles = array();
        $setfiles = array();
        $questiondirs = array();
        $otherfiles = array();
        
        //categorize the stuff in this directory into arrays
        $dir_handle = opendir($dir);
        while ($filename = readdir($dir_handle)) {
            if (!in_array($filename, array('.','..','CVS','.svn'))) {
                
                $filepath = $dir . $filename;
                $type = filetype($filepath);
                
                if($type == 'dir') {
                    if($this->is_question_dir($filename)) {
                        array_push($questiondirs,$filename);
                    } else {
                        array_push($categorydirs,$filename);
                    }
                } elseif ($type == 'file') {
                    if ((is_file($filepath)) && (is_readable($filepath))) {
                        if($this->is_problem_file($filename)) {
                            array_push($questionfiles,$filename);
                        } elseif($this->is_set_file($filename)) {
                            array_push($setfiles,$filename);
                        }
                    }
                } else {
                    array_push($otherfiles,$filename);
                }
            }
        }
        
        echo "---Loaded Files<br>";
        flush();
        
        //check for options file
        $optionsfile = $this->process_options($dir);
        
        //deal with the question files
        echo "---Processing Simple Questions: ";
        flush();
        foreach($questionfiles as $questionfile) {
            $codefile = $dir . $questionfile;
            $this->process_question($codefile);
            echo ".";
            flush();
        }
        echo "<br>---Done Processing Simple Questions<br>";
        
        //deal with the question dirs
        /*echo "---Processing Complex Questions: ";
        flush();
        foreach($questiondirs as $questiondir) {
            $filepath = $dir . $questiondir;
            //find the requisite file
            $tempname = substr($questiondir,2) . '.pg';
            $neededfile = $filepath . '/' . $tempname;
            if(file_exists($neededfile)) {
                $codefile = $neededfile;
                $this->process_question($codefile,$filepath);
                echo ".";
                flush();
            } else {
                echo "<br>Error: Cannot find the code file:'$neededfile'<br>";
                flush();                
            }
        }
        echo "<br>---Done Processing Complex Questions<br>";*/
        flush();
        
        //deal with the set definition files
        echo "---Processing Set Definitions: ";
        flush();
        foreach($setfiles as $setfilename) {
            $thefile = $dir . '/'. $setfilename;
            $this->process_set_definition($thefile);
            echo ".";
            flush();
            
        }
        echo "<br>---Done Processing Set Definitions<br>";
        flush();
        
        echo "Done with Directory<br>";
        
        //deal with nested CAT directories
        foreach($categorydirs as $categorydir) {
            $categorypath = $dir . $categorydir;
            $this->process_category_directory($categorypath,$categoryname.'/'.$categorydir);
        }
        
        $options = array_pop($this->optionstack);
        if(strstr($dir,$options['DIR']) == false) {
            echo "Removing Options File<br>";   
        } else {
            array_push($this->optionstack,$options);
        }   
    }
    
    /**
    * @desc Adds a question from a pg file into the questions array.
    * @param string $name The name of the question.
    * @param string $codefile The code file of the problem.
    * @param string $filepath The possible path of the directory of supporting question files
    * @return bool true
    */
    function process_question($codefile,$filepath=NULL) {
        //load in options (overwrites shallow option files with deeper ones) (inefficient)
        $name = $this->filepath_to_name($codefile);
        foreach($this->optionstack as $options) {
            if(isset($options['SEED'])) {
                $seed = $options['SEED'];
            }
            if(isset($options['TRIALS'])) {
                $trials = $options['TRIALS'];
            }
            if(isset($options['CODECHECK'])) {
                $codecheck = $options['CODECHECK'];
            }
            if(isset($options['SEED'])) {
                $seed = $options['SEED'];
            }
            if(isset($options['NAME'])) {
                $name = $options['NAME'];
            }
            if(isset($options['CACHE'])) {
                $cache = $options['CACHE'];
            }
        }
        $question = $this->defaultquestion();
        $question->name = $this->strip_file_extension($name);
        $question->qtype = 'webwork';
        $question->code = base64_encode((file_get_contents($codefile)));
        $question->codecheck = $codecheck;
        $question->seed = $seed;
        $question->trials = $trials;
        $question->grading = 0;
        $question->cache = $cache;
        $question->filepath = $filepath;
        //create the question object
        $this->questions[] = $question;
        return true;
    }
    
    /**
    * @desc Processes a set definition file into the sets array.
    * @param string $name The name of the set.
    * @param string $filepath The path to the set file.
    */
    function process_set_definition($filepath) {
        $contents = file_get_contents($filepath);
        $name = $this->filepath_to_name($filepath);
        $lines = explode("\n",$contents);
        $setoptions = array();
        $setoptions['name'] = $name;
        $onproblems = false;
        foreach($lines as $line) {
            $templine = trim($line);
            if($onproblems == false) {
                //normal config options
                $parts = explode("=",$templine);
                if(count($parts) == 2) {
                    $firstpart = trim($parts[0]);
                    $secondpart = trim($parts[1]);
                    if($secondpart != "") {
                        switch($firstpart) {
                            case 'openDate':
                            case 'dueDate':
                            case 'answerDate':
                                $secondpart =  str_replace("at","",$secondpart);
                                $setoptions[$firstpart] = strtotime($secondpart);
                                break;
                            case 'problemList':
                                $onproblems = true;
                                $setoptions['problemList'] = array();
                                break;
                            default:
                                $setoptions[$firstpart] = $secondpart;
                                break;
                        }
                    } else {
                        if($firstpart == 'problemList') {
                            $onproblems = true;
                            $setoptions['problemList'] = array();
                        }
                    }  
                }
            } else {
                //dealing with problem list
                if($templine != "") {
                    $parts = explode(",",$templine);
                    if(count($parts) > 0) {
                        $question = array();
                        $question['file'] = trim($parts[0]);
                        if(count($parts) > 1) {
                            $question['weight'] = trim($parts[1]);
                        }
                        if(count($parts) > 2) {
                            $question['maxattempts'] = trim($parts[2]);
                        }
                        $setoptions['problemList'][] = $question;
                    }
                }
                
            }
        }
        $this->sets[] = $setoptions;
        return true;
        
    }
    
    /**
    * @desc Checks if an options file exists in the current directory and processes it.
    * @param string $dir The directory to check in.
    * @return bool (true if optionsfile exists otherwise false)
    */
    function process_options($dir) {
        $possiblefile = $dir  .'/' . WWQUESTION_OPTIONS_FILE;
        if(!file_exists($possiblefile)) {
            return false;
        }
        
        $contents = file_get_contents($possiblefile);
        $lines = explode("\n",$contents);
        $options = array();
        foreach($lines as $line) {
            $templine = trim($line);
            $parts = explode("=",$templine);
            if(count($parts) == 2) {
                $firstpart = trim($parts[0]);
                $secondpart = trim($parts[1]);
                $options[$firstpart] = $secondpart;
            }
        }
        $options['DIR'] = $dir;
        array_push($this->optionstack,$options);
        echo "---Applying Custom Options<br>";
        flush();
        return true;
    }

//////////////////////////////////////////////////////////////////////////////////
//IMPORTER INSERT FUNCTIONS (arrays --> DB or moodledata)
//////////////////////////////////////////////////////////////////////////////////

    /**
    * @desc Read in the sets definition options and turn them into quizzes.
    * @return bool depending on error.
    */
    function insert_set_definitions() {
        global $COURSE;
        //find out the quiz module num
        $this->quizmodnum = get_field('modules','id','name','quiz');
        if($this->quizmodnum == false) {
            echo "Error: Module Quiz does not appear to be installed<br>";
            return false;
        }
        /*if (!course_allowed_module($COURSE->id,$mod->modulename)) {
            echo "Error: This module ($mod->modulename) has been disabled for this particular course<br>";
            return false;
        }*/
        $needrebuild = false;
        foreach($this->sets as $set) {
            $this->insert_set_definition($set);
            $needrebuild = true;
        }
        if($needrebuild) {
            rebuild_course_cache($COURSE->id);
        }
        return true;
    }
    
    /**
    * @desc Read in the set and turn it into a quiz.
    * @param array $set The set data.
    * @return bool
    */
    function insert_set_definition($set) {
        global $COURSE;
        //create new quiz object
        $quiz = new stdClass;
        $quiz->name = $set['name'];
        if(isset($set['openDate'])) {
            $quiz->timeopen = $set['openDate'];
        }
        if(isset($set['dueDate'])) {
            $quiz->timeclose = $set['dueDate'];
        }
        $quiz->intro = "WeBWorK Problem Set";
        $quiz->timelimitenable = 0;
        $quiz->quizpassword = "";
        $quiz->course = $COURSE->id;
        $quiz->adaptive = 1;
        $quiz->attemptonlast = 1;
        $quiz->shuffleanswers = 1;
        $quiz->feedbackboundarycount = -1;
        //review variables
        $quiz->responsesimmediately = 1;
        $quiz->responsesopen = 1;
        $quiz->responsesclosed = 1;
        $quiz->scoreimmediately = 1;
        $quiz->scoreopen = 1;
        $quiz->scoreclosed = 1;
        $quiz->feedbackimmediately = 1;
        $quiz->feedbackopen = 1;
        $quiz->feedbackclosed = 1;
        $quiz->answersimmediately = 1;
        $quiz->answersopen = 1;
        $quiz->answersclosed = 1;
        $quiz->solutionsimmediately = 1;
        $quiz->solutionsopen = 1;
        $quiz->solutionsclosed = 1;
        $quiz->generalfeedbackimmediately = 1;
        $quiz->generalfeedbackopen = 1;
        $quiz->generalfeedbackclosed = 1;
        $quiz->questionsperpage = 1;
        $quiz->grade = 10; 
        
        
        $result = quiz_add_instance($quiz);
        if($result == false) {
            echo "Error on DB addition<br>";
            return false;
        }
        $quizid = $result;
        
        $numquestions = $this->insert_set_definition_questions($set['problemList'],$quizid);
        $quiz->sumgrades = $numquestions;
        $quiz->id = $quizid;
        $quiz->questions = get_field('quiz','questions','id',$quizid);
        update_record('quiz',$quiz);
        echo "Created Quiz (".$quiz->name.")<br>";
        flush();
        
        //Creating module
        $mod = new stdClass;
        $mod->course = $COURSE->id;
        $mod->section = 1;
        $mod->module = $this->quizmodnum;
        $mod->instance = $quizid;
        $mod->modulename = 'quiz';
        
        if (!isset($mod->name) || trim($mod->name) == '') {
            $mod->name = get_string("modulename", $mod->modulename);
        }
        if (!isset($mod->groupmode)) { // to deal with pre-1.5 modules
            $mod->groupmode = $COURSE->groupmode;  /// Default groupmode the same as course
        }
        if (! $mod->coursemodule = add_course_module($mod) ) {
            echo ("Error: Could not add a new course module<br>");
            return false;
        }
        if (! $sectionid = add_mod_to_section($mod) ) {
            echo ("Error: Could not add the new course module to that section<br>");
            return false;
        }
        if (! set_field("course_modules", "section", $sectionid, "id", $mod->coursemodule)) {
            echo ("Error: Could not update the course module with the correct section<br>");
            return false;
        }
        if (!isset($mod->visible)) {   // We get the section's visible field status
            $mod->visible = get_field("course_sections","visible","id",$sectionid);
        }
        // make sure visibility is set correctly (in particular in calendar)
        set_coursemodule_visible($mod->coursemodule, $mod->visible);
        return true;
    }
    
        
    /**
    * @desc Takes in an array of questins and adds them to a quiz
    * @param array $questions An array of the question data to add.
    * @param integer $quizid The ID of the quiz.
    * @return bool.
    */
    function insert_set_definition_questions($questions,$quizid) {
        global $COURSE;
        //partial quiz object
        $quizinfo = new stdClass;
        $quizinfo->grades = array();
        $quizinfo->questionsperpage = 1;
        $quizinfo->questions = "";
        $quizinfo->instance = $quizid;
        $quizinfo->id = $quizid;
        //loop to find questions
        $count = 0;
        foreach($questions as $question) {
            //get question name
            $filepath = $question['file'];
            $name = $this->filepath_to_name($filepath);
            $parts = explode('/',$filepath);
            $partcount = count($parts);
            if($partcount >= 2) {
                $categoryname = $parts[$partcount-2];
                //try first with course constraint
                $result = get_field('question_categories','id','name',$categoryname,'course',$COURSE->id);
                if($result == false) {
                    $result = get_field('question_categories','id','name',$categoryname);
                    if($result == false) {
                        echo "The category '$categoryname' was not found for question '$name'. - You will have to manually add this question.<br>";
                        continue;
                    } else {
                        $categoryid = $result;
                    }
                } else {
                    $categoryid = $result;
                }
                $result = get_field('question','id','category',$categoryid,'name',$name);
            }
            $result = get_field('question','id','name',$name);
            if($result != false) {
                echo "Added Question '$name' to quiz.<br>";
                quiz_add_quiz_question($result,$quizinfo);
                $count++;
            } else {
                echo "A question was not found: '$name' - You will have to manually add it.<br>";
            }
        }
        return $count;
    }

//////////////////////////////////////////////////////////////////////////////////
//IMPORTER UTIL FUNCTIONS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Creates the needed temp directory to unzip the uploaded file into
    * @param string $unique_code A unique code takked to the end of the path
    * @return bool status
    */
    function check_and_create_import_dir($unique_code) {
        global $CFG; 
        $status = $this->check_dir_exists($CFG->dataroot."/temp",true);
        if ($status) {
            $status = $this->check_dir_exists($CFG->dataroot."/temp/webworkquiz_import",true);
        }
        if ($status) {
            $status = $this->check_dir_exists($CFG->dataroot."/temp/webworkquiz_import/".$unique_code,true);
        }
        return $status;
    }
    
    /**
    * @desc Checks if a directory exists and optionally creates it.
    */
    function check_dir_exists($dir,$create=false) {
        global $CFG; 
        $status = true;
        if(!is_dir($dir)) {
            if (!$create) {
                $status = false;
            } else {
                umask(0000);
                $status = mkdir ($dir,$CFG->directorypermissions);
            }
        }
        return $status;
    }
    
    /**
    * @desc Creates the initial options for questions.
    * @return bool true
    */
    function init_options() {
        echo "Initializing Options<br>";
        
        $options = array();
        $options['SEED'] = 0;
        $options['TRIALS'] = 0;
        $options['CODECHECK'] = 4;
        $options['CACHE'] = 0;
        $options['DIR'] = '/';
        
        echo "---Default Cache: " . $options['CACHE'] ." <br>";
        echo "---Default Seed: " . $options['SEED'] . " <br>";
        echo "---Default Trials: " . $options['TRIALS'] ." <br>";
        echo "---Default Codecheck: " . $options['CODECHECK'] . " <br>";
        
        $this->optionstack = array();
        array_push($this->optionstack,$options);
        
        echo "Done<br><br>";
        flush();
        return true;
    }
       
    /**
    * @desc Takes a full filepath and returns a name by striping the file extension and all characters before the last '/'
    * @param string $filepath The filepath to process.
    * @return string The new name.
    */
    function filepath_to_name($filepath) {
        $result = strrpos($filepath,"/");
        if($result != false) {
            $filepath = substr($filepath,$result+1);
        }
        return $this->strip_file_extension($filepath);
    }
    
    /**
    * @desc Strips a filename of its extension.
    * @param string $filename The filename to process.
    * @return string The new name.
    */
    function strip_file_extension($filename) {
        $result = strrpos($filename,'.');
        if($result == false) {
            return $filename;
        }
        return substr($filename,0,$result);
    }
    
    /**
    * @desc Determines if a filename ends in .pg
    * @param string $filename The file.
    * @return bool
    */
    function is_problem_file($filename) {
        $len = strlen($filename);
        if(($len > 3) && ($filename[$len-1] == 'g') && ($filename[$len-2] == 'p') && ($filename[$len-3] == '.')) {
            return true;
        }
        return false;
    }
    
    /**
    * @desc Determines if a directory starts with Q_
    * @param string $dirname The directory.
    * @return bool
    */
    function is_question_dir($dirname) {
        $len = strlen($dirname);
        if(($len > 2) && ($dirname[0] == 'Q') && ($dirname[1] == '_')) {
            return true;
        }
        return false;
    }
    
    /**
    * @desc Determines if a filename ends in .def
    * @param string $filename The filename.
    * @return bool.
    */
    function is_set_file($filename) {
        $len = strlen($filename);
        if(($len > 4) && ($filename[$len-1] == 'f') && ($filename[$len-2] == 'e') && ($filename[$len-3] == 'd') && ($filename[$len-4] == '.')) {
            return true;
        }
        return false;
    }
    
    
        
    
    

    
    
    
    
}
?>