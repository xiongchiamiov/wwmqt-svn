<?php

/**
* @desc This block allows students to print quizzes in pdf format
*/
class block_webwork_printer extends block_base {
    
    /**
    * @desc Sets the title of the block and the current version.
    */
    function init() {
        $this->title = get_string('blockname','block_webwork_printer');
        $this->version = 2007100600;
    }
    /**
    * @desc Allows each instance of the block to have its own configuration settings.
    */
    function instance_allow_config() {
        return false;
    }
    
    /**
    * @desc Makes sure that the only place this block can be added is on course-view page. This insures one block per course.
    */
    function applicable_formats() {
        return array('all' => false, 'course-view' => true);
    }
    
    /**
    * @desc Prints the content of the block. Whether or not the course is connected to a moodle course.
    */
    function get_content() {
        //$this->content->text = "This block will eventually be able to print your WW Sets in PDF format."
        //return $this->content;
    }
}

?>
