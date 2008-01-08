<?php
/**
 * The WebworkQuestionFactory Class.
 * 
 * @copyright &copy; 2007 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/

/**
* @desc Factory that creates WebworkQuestion objects in different ways.
*/
class WebworkQuestionFactory {
    
//////////////////////////////////////////////////////////////////////////////////
//OBJECT HOLDER FUNCTIONS
//////////////////////////////////////////////////////////////////////////////////

    /**
    * @desc Holds the WebworkQuestions between event API calls.
    */
    protected static $_library;
    
    protected static $_currentkey;
    
    /**
    * @desc Stores a WebworkQuestion object into the library.
    * @param string $key The key of the object.
    * @param WebworkQuestion $wwquestion The object to be stored
    */
    public static function Store($key,$wwquestion) {
        if(!isset(WebworkQuestionFactory::$_library)) {
            WebworkQuestionFactory::$_library = array();
        }
        WebworkQuestionFactory::$_library[$key] = $wwquestion;
    }
    
    /**
    * @desc Retrieves a WebworkQuestion object from the library.
    * @param string $key The key to access the object.
    * @return WebworkQuestion The object out of the library.
    * @throws Exception when the object was not found.
    */
    public static function Retrieve($key) {
        if(!isset(WebworkQuestionFactory::$_library)) {
            WebworkQuestionFactory::$_library = array();
        }
        if(isset(WebworkQuestionFactory::$_library[$key])) {
            return WebworkQuestionFactory::$_library[$key];
        }
        throw new Exception();
    }
    
    /**
    * @desc Makes a new Store Key. This attaches a submitted forms data to the db insert event.
    * @return string The store key.
    */
    public static function MakeNewKey() {
       if(!isset(WebworkQuestionFactory::$_currentkey)) {
            WebworkQuestionFactory::$_currentkey = 46;
       }
       WebworkQuestionFactory::$_currentkey++;
       return WebworkQuestionFactory::$_currentkey;
    }
    
    /**
    * @desc Gets the current Store Key.
    * @return string The store key.
    */
    public static function GetCurrentKey() {
        return WebworkQuestionFactory::$_currentkey;  
    }

//////////////////////////////////////////////////////////////////////////////////
//LOADERS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Loads a WebworkQuestion out of the db based on its Id.
    * @param integer $wwquestionid The Id.
    * @throws Exception if the $wwquestionid was not found.
    * @return WebworkQuestion object.
    */
    public static function Load($wwquestionid) {
        $record = get_record('question_webwork','id',$wwquestionid);
        if(!$record) {
            throw new Exception();
        }
        return new WebworkQuestion($record);
    }
    
    /**
    * @desc Loads a WebworkQuestion out of the db based on a question id.
    * @param integer $questionid The Id.
    * @throws Exception if the $questionid was not found.
    * @return WebworkQuestion object.
    */
    public static function LoadByParent($questionid) {
        $record = get_record('question_webwork','question',$questionid);
        if(!$record) {
            throw new Exception();
        }
        return new WebworkQuestion($record);
    }
    
    
//////////////////////////////////////////////////////////////////////////////////
//FORM CREATORS
//////////////////////////////////////////////////////////////////////////////////
    
    /**
    * @desc Creates a WebworkQuestion object based on form update data.
    * @param object $formdata The form data.
    * @return WebworkQuestion object.
    */
    public static function CreateFormUpdate($formdata) {
        return WebworkQuestionFactory::CreateFromForm($formdata);
    }
    
    /**
    * @desc Creates a WebworkQuestion object based on form copy data (create as a new question).
    * @param object $formdata The form data.
    * @return WebworkQuestion object.
    */
    public static function CreateFormCopy($formdata) {
        unset($formdata->id);
        return WebworkQuestionFactory::CreateFromForm($formdata);
    }
    
    /**
    * @desc Creates a WebworkQuestion object based on new form data.
    * @param object $formdata The form data.
    * @return WebworkQuestion object.
    */
    public static function CreateFormNew($formdata) {
        return WebworkQuestionFactory::CreateFromForm($formdata);
    }
    
//////////////////////////////////////////////////////////////////////////////////
//IMPORTER CREATORS
//////////////////////////////////////////////////////////////////////////////////

    /**
    * @desc Creates a WebworkQuestion from data given by the importer.
    * @param string $code The PG code.
    * @param string $codecheck The codecheck level.
    * @return WebworkQuestion object.
    */
    public static function Import($code,$codecheck) {
        $output = WebworkQuestionFactory::CodeCheck($code,array(),$codecheck);
        $importdata = new stdClass;
        $importdata->codecheck = $codecheck;
        $importdata->code = $code;
        $importdata->grading = $output->grading;
        $wwquestion = new WebworkQuestion($importdata,$output->derivation);   
        return $wwquestion;
    }
    
//////////////////////////////////////////////////////////////////////////////////
//UTILS
//////////////////////////////////////////////////////////////////////////////////
    
    public static function CreateFromForm($formdata) {
        WebworkQuestionFactory::ParseFormData($formdata);
        $output = WebworkQuestionFactory::CodeCheck($formdata->code,array(),$formdata->codecheck);
        $formdata->grading = $output->grading;
        $wwquestion = new WebworkQuestion($formdata,$output->derivation);
        return $wwquestion;
    }
    
    /**
    * @desc Strips slashes and encodes the PG code out of the form.
    * @param object $formdata The form data.
    * @return true.
    */
    public static function ParseFormData(&$formdata) {
        $formdata->code = base64_encode(stripslashes($formdata->code));
        return true;
    }
    
    public function CodeCheck($code,$files,$codecheck) {
        $client = WebworkClient::Get();
        $env = WebworkQuestion::DefaultEnvironment();
        if(($codecheck == WWQUESTION_CODECHECK_ERRORS_MANY) || ($codecheck == WWQUESTION_CODECHECK_ALL_MANY)) {
            $versions = 10;
        } else {
            $versions = 1;
        }
        $results = $client->generateProblem($versions,$env,$code,$files);
        //init error arrays
        $errorresults = array();
        $noerrorresults = array();
        $warningresults = array();
        $goodresults = array();
    
        //pick up derivations with errors
        foreach($results as $record) {
            if((isset($record->errors)) && ($record->errors != '') && ($record->errors != null)) {
                array_push($errorresults,$record);
            } else {
                array_push($noerrorresults,$record);
            }
        }
        //pick up derivations with warnings
        foreach($noerrorresults as $record) {
            if((isset($record->warnings)) && ($record->warnings != '') && ($record->warnings != null)) {
                array_push($warningresults,$record);
            } else {
                array_push($goodresults,$record);
            }
        }
        $valid = false;
        switch($codecheck) {
            //No code check
            case 0:
                $useableresults = $results;
                $valid = true;
                break;
            //reject seeds with errors
            case 1:
                if(count($noerrorresults) > 0) {
                    $useableresults = $noerrorresults;
                    $valid = true;
                }
                break;
            //reject if errors
            case 2:
                if(count($noerrorresults) == count($results)) {
                    $useableresults = $results;
                    $valid = true;
                }
                break;
            //reject seeds with errors or warnings
            case 3:
                if(count($goodresults) > 0) {
                    $useableresults = $goodresults;
                    $valid = true;
                }
                break;
            //reject if errors or warnings
            case 4:
                if(count($goodresults) == count($results)) {
                    $useableresults = $goodresults;
                    $valid = true;
                }
                break;
        }
        if($valid) {
            $result = $useableresults[0];
            $output = new stdClass;
            $output->valid = 1;
            $output->grading = $result->grading;
            
            $derivation = new stdClass;
            $derivation->html = $result->output;
            $derivation->seed = $result->seed;
            $output->derivation = $derivation;
        } else {
            //NOW WE ARE INVALID going to throwup
            //start the output
            $errormsgs = array();
            $warningmsgs = array();
            //ERRORS
            foreach($errorresults as $record) {
                $found = 0;
                $candidate = $record->errors . "<br>";
                $candidateseed = $record->seed;
                for($i=0;$i<count($errormsgs);$i++) {
                    if($candidate == $errormsgs[$i]->errors) {
                        $found = 1;
                        $errormsgs[$i]->seeds[] = $candidateseed;
                    }
                }
                if($found == 0) {
                    //new error message
                    $msg = new stdClass;
                    $msg->errors = $candidate;
                    $msg->seeds = array();
                    $msg->seeds[] = $candidateseed;
                    $errormsgs[] = $msg;
                }
            }
            //WARNINGS
            foreach($warningresults as $record) {
                $found = 0;
                $candidate = $record->warnings . "<br>";
                $candidateseed = $record->seed;
                for($i=0;$i<count($warningmsgs);$i++) {
                    if((isset($warningmsgs[$i]->errors))&&($candidate == $warningmsgs[$i]->errors)) {
                        $found = 1;
                        $warningmsgs[$i]->seeds[] = $candidateseed;
                    }
                }
                if($found == 0) {
                    //new error message
                    $msg = new stdClass;
                    $msg->warnings = $candidate;
                    $msg->seeds = array();
                    $msg->seeds[] = $candidateseed;
                    $warningmsgs[] = $msg;
                }
                
            }
            $finalmsg = "Errors in PG Code on: " . count($errorresults) . " out of " . count($results) . " seeds tried:<br>";
            //construct error statement
            $counter = 1;
            foreach($errormsgs as $msg) {
                $finalmsg .= "$counter) ";
                $finalmsg .= "Seeds (";
                foreach ($msg->seeds as $seed) {
                    $finalmsg .= $seed . " ";
                }
                $finalmsg .= ") gave Errors:" . $msg->errors . "<br><br>";
                $counter++;
            }
            $finalmsg .= "Warnings in PG Code on: " . count($warningresults) . " out of " . count($results) . " seeds tried:<br>";
            $counter = 1;
            foreach($warningmsgs as $msg) {
                $finalmsg .= "$counter) ";
                $finalmsg .= "Seeds (";
                foreach ($msg->seeds as $seed) {
                    $finalmsg .= $seed . " ";
                }
                $finalmsg .= ") gave Warnings:" . $msg->warnings . "<br><br>";
                $counter++;
            }
            throw new Exception($finalmsg);
        }
        return $output;   
    }
}
    
?>