<?php
/**
 * The editing form code for this question type.
 *
 * @copyright &copy; 2008 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
 **/
 
 
require_once("$CFG->dirroot/question/type/webwork/config.php");
require_once("$CFG->dirroot/question/type/webwork/lib/question.php");
require_once("$CFG->dirroot/question/type/webwork/lib/questionfactory.php");
require_once("$CFG->dirroot/question/type/edit_question_form.php");
require_once("$CFG->dirroot/backup/lib.php");

/**
 * webwork editing form definition.
 * 
 * See http://docs.moodle.org/en/Development:lib/formslib.php for information
 * about the Moodle forms library, which is based on the HTML Quickform PEAR library.
 */
class question_edit_webwork_form extends question_edit_form {
 
    /**
    * @desc This function is overriding the normal form definition for a question creation
    */
    function definition() {
        global $COURSE, $CFG;

        $qtype = $this->qtype();
        $langfile = "qtype_$qtype";

        $mform =& $this->_form;

        // Standard fields at the start of the form.
        
        //General Header
        $mform->addElement('header', 'generalheader', get_string("general", 'form'));
        
        //Question Category
        $mform->addElement('questioncategory', 'category', get_string('category', 'quiz'), null,
                array('courseid' => $COURSE->id, 'published' => true, 'only_editable' => true));
                
        //Question Name
        $mform->addElement('text', 'name', get_string('questionname', 'quiz'),
                array('size' => 50));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        
        //Default Grade
        $mform->addElement('text', 'defaultgrade', get_string('defaultgrade', 'quiz'),
                array('size' => 3));
        $mform->setType('defaultgrade', PARAM_INT);
        $mform->setDefault('defaultgrade', 1);
        $mform->addRule('defaultgrade', null, 'required', null, 'client');
        
        //Penalty
        $mform->addElement('text', 'penalty', get_string('penaltyfactor', 'quiz'),
                array('size' => 3));
        $mform->setType('penalty', PARAM_NUMBER);
        $mform->addRule('penalty', null, 'required', null, 'client');
        $mform->setHelpButton('penalty', array('penalty', get_string('penalty', 'quiz'), 'quiz'));
        $mform->setDefault('penalty', 0.1);
        
        //General Feedback
        $mform->addElement('htmleditor', 'generalfeedback', get_string('generalfeedback', 'quiz'),
                array('rows' => 10, 'course' => $COURSE->id));
        $mform->setType('generalfeedback', PARAM_RAW);
        $mform->setHelpButton('generalfeedback', array('generalfeedback', get_string('generalfeedback', 'quiz'), 'quiz'));

        // Any questiontype specific fields.
        $this->definition_inner($mform);

        // Standard fields at the end of the form.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_ALPHA);

        $mform->addElement('hidden', 'inpopup');
        $mform->setType('inpopup', PARAM_INT);

        $mform->addElement('hidden', 'versioning');
        $mform->setType('versioning', PARAM_BOOL);

        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        if (!empty($this->question->id)) {
            $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('makecopy', 'quiz'));
        }
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
    
    function definition_inner(&$mform) {    
        //CODE HEADER
        $mform->addElement('header', 'codeheader', get_string("edit_codeheader", 'qtype_webwork'));
        
        //CODE DISPLAY
        $mform->addElement('static', 'questiontext', get_string('questiontext', 'quiz'));
        $mform->setHelpButton('questiontext', array(array('questiontext', get_string('questiontext', 'quiz'), 'quiz'), 'richtext'), false, 'editorhelpbutton');

        
        //CODE
        $mform->addElement('textarea', 'code', get_string('edit_code', 'qtype_webwork'),
                array('rows' => 20,'cols' => 60));
        $mform->setType('code', PARAM_RAW);
        $mform->setHelpButton('code', array('code', get_string('edit_code', 'qtype_webwork'), 'webwork'));
        
        //CODECHECK (legacy dont want to change db so this stays till 1.9)
        $mform->addElement('hidden','codecheck');
        $mform->setType('codecheck', PARAM_INT);
        $mform->setDefault('codecheck', 1);
        
        //STOREKEY
        $mform->addElement('hidden', 'storekey');
        $mform->setType('storekey', PARAM_INT);
        $mform->setDefault('storekey', WebworkQuestionFactory::MakeNewKey());
        
    } 
    /**
    * @desc Sets the data in the question object based on old form data. Do some tricks to get file managers to work.
    */
    function set_data($question) {
        if(isset($this->question->webwork)) {
            $wwquestion = $this->question->webwork;
            //set fields to old values
            $question->code = $wwquestion->getCodeText();
            $question->codecheck = $wwquestion->getCodeCheck();
        }
        parent::set_data($question);   
    }
    
    /**
    * @desc Validates that the question is without PG errors as mandated by codecheck level.
    * @param $data array The form data that needs to be validated.
    * @return array An array of errors indexed by field.
    */
    function validation($data) {
        //init
        $errors = array();
        //build dataobject
        $dataobject = new stdClass;
        $dataobject->code = $data['code'];
        $dataobject->codecheck = $data['codecheck'];
        
        //attempt to find an ID (if updating)
        if(isset($this->question->webwork)) {
            $dataobject->id = $this->question->webwork->getId();
        }
        
        //try and build the question object
        try {
            if(isset($this->_form->_submitValues['makecopy'])) {
                $wwquestion = WebworkQuestionFactory::CreateFormCopy($dataobject);
            } else {
                if(isset($dataobject->id)) {
                    $wwquestion = WebworkQuestionFactory::CreateFormUpdate($dataobject);
                } else {
                    $wwquestion = WebworkQuestionFactory::CreateFormNew($dataobject);
                }
            }
            WebworkQuestionFactory::Store($data['storekey'],$wwquestion);
        } catch(Exception $e) {
            $errors['code'] = $e->getMessage();
        }
        return $errors;
    }

    function qtype() {
        return 'webwork';
    }
}
?>
