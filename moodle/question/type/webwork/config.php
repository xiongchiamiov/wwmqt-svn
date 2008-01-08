<?php
/**
 * The configuration file for WeBWorK Questions.
 *
 * @copyright &copy; 2008 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/

//Path to the WSDL file on the Webwork Server
define('WWQUESTION_WSDL','http://question.webwork.rochester.edu/problemserver_files/WSDL.wsdl');

error_reporting(E_ALL);
ini_set("soap.wsdl_cache_enabled", 1);
?>

