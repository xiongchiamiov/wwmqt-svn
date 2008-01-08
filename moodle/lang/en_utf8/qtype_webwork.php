<?php // $Id: qtype_webwork.php,v 1.1 2007/09/11 22:52:59 mleventi Exp $
/**
 * The language strings for the qtype_webwork question type.
 *    
 * @copyright &copy; 2006 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_webwork
 */

 
//module name
$string['webwork'] = 'WeBWorK';

//form editing
$string['editingwebwork'] = 'Editing WeBWorK';
$string['edit_codeheader'] = 'WeBWorK Code';
$string['edit_fileheader'] = 'WeBWorK Files';
$string['edit_optionheader'] = 'WeBWorK Options';
    $string['edit_cache'] = 'Cache';
    $string['edit_cache0'] = 'Off';
$string['edit_cache1'] = 'On';
$string['edit_codecheck'] = 'Code Checking';
    $string['edit_codecheck0'] = 'Turn Off (unadvised)';
    $string['edit_codecheck1'] = 'Reject Problem Seeds w/ Errors (silent)';
    $string['edit_codecheck2'] = 'Reject Question if any Errors exist (strict)';
    $string['edit_codecheck3'] = 'Reject Problem Seeds w/ Errors or Warnings (silent)';
    $string['edit_codecheck4'] = 'Reject Question if any Errors or Warnings exist (strict)';
$string['edit_codefile'] = 'Question File';
$string['edit_addcodefiles'] = 'Add More Question Files';
$string['edit_code'] = 'Code';
$string['edit_seed'] = 'Seed';
$string['edit_trials'] = 'Trials';

//errors
$string['error_question_id'] = 'The parent question ID was not set.';
$string['error_question_id_no_child'] = 'There is no record of a webwork question with a parent ID:';
$string['error_no_filepath'] = 'No filepath key was found.';
$string['error_no_filepath_record'] = 'No record was found for the filepath key:';
$string['error_db_failure'] = 'Failed to change the Database.';
$string['error_no_wwquestion'] = 'The WeBWorK Question object was not found.';
$string['error_no_seed'] = 'No Seed Found.';
$string['error_no_derivation_id'] = 'No derivation ID found.';
$string['error_no_derivation'] = 'Derivation was not found.';
$string['error_failed_pick_random_question'] = 'Failed to pick a random WeBWorK question seed.';
$string['error_soap'] = 'Error in Communication with the Server Details:';

?>
