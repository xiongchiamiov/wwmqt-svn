<?php
/**
 * The WebworkClient Class.
 * 
 * @copyright &copy; 2008 Matthew Leventi
 * @author mleventi@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package webwork_qtype
**/


/**
* @desc Singleton class that contains function for communication to the WeBWorK Question Server.
*/
class WebworkClient {
    
    /**
    * @desc Gets a single instance of WebworkClient. Constructs the instance if it doesn't exist.
    * @return WebworkClient instance.
    */
    public static function Get() {
        static $client = null;
        if ($client == null) {
            $client = new WebworkClient();
        }
        return $client;
    }
    
    private $_client = null;
    
    /**
    * @desc Private Constructor that creates a PHP::SOAP client.
    */
    private function __construct()
    {
        $this->_client = new SoapClient(WWQUESTION_WSDL);
    }
    
    /**
    * @desc Calls the server function renderProblem.
    * @param object $env The problem environment.
    * @param string $code The base64 encoded PG code.
    * @param array $files An array of base64 encoded file names.
    * @return mixed The results of the server call.
    */
    public function renderProblem($env,$code,$files=array()) {
        $problem = new stdClass;
        $problem->code = $code;
        $problem->env = $env;
        $problem->files = $files;
        
        try {
            $results = $this->_client->renderProblem($problem);
        } catch (SoapFault $exception) {
            print_error('error_soap','qtype_webwork',$exception);
            return false;
        }
        return $results;
        
    }
    
    /**
    * @desc Calls the server function renderProblem.
    * @param object $env The problem environment.
    * @param string $code The base64 encoded PG code.
    * @param array $files An array of base64 encoded file names.
    * @param array $answers The array of answers.
    * @return mixed The results of the server call.
    */
    public function renderProblemAndCheck($env,$code,$files=array(),$answers) {
        $problem = new stdClass;
        $problem->code = $code;
        $problem->env = $env;
        $problem->files = $files;
        
        try {
            $response = $this->_client->renderProblemAndCheck($problem,$answers);
        } catch (SoapFault $exception) {
            print_error('error_soap','qtype_webwork',$exception);
            return false;
        }
        return $response;
    }
    
    /**
    * @desc Calls the server function generateProblem.
    * @param integer $trials The number of trials to generate.
    * @param object $env The problem environment.
    * @param string $code The base64 encoded PG code.
    * @param array $files An array of base64 encoded file names.
    * @return mixed The results of the server call.
    */
    public function generateProblem($trials,$env,$code,$files=array()) {
        $problem = array();
        $problem['code'] = $code;
        $problem['env'] = $env;
        $problem['files']= $files;
        $params = array();
        $params['trials'] = $trials;
        $params['problem'] = $problem;
        $params['id'] = 0;
        try {
            $results = $this->_client->generateProblem($params);
        } catch (SoapFault $exception) {
            print_error('error_soap','qtype_webwork',$exception);
            return false;
        }
        return $results; 
    }
    
    
    

}

?>
