#!/usr/bin/env perl

use Cwd;

sub promptUser {

   #-------------------------------------------------------------------#
   #  two possible input arguments - $promptString, and $defaultValue  #
   #  make the input arguments local variables.                        #
   #-------------------------------------------------------------------#

   local($promptString,$defaultValue) = @_;

   #-------------------------------------------------------------------#
   #  if there is a default value, use the first print statement; if   #
   #  no default is provided, print the second string.                 #
   #-------------------------------------------------------------------#

   if ($defaultValue) {
      print $promptString, "[", $defaultValue, "]: ";
   } else {
      print $promptString, ": ";
   }

   $| = 1;               # force a flush after our print
   $_ = <STDIN>;         # get the input from STDIN (presumably the keyboard)


   #------------------------------------------------------------------#
   # remove the newline character from the end of the input the user  #
   # gave us.                                                         #
   #------------------------------------------------------------------#

   chomp;

   #-----------------------------------------------------------------#
   #  if we had a $default value, and the user gave us input, then   #
   #  return the input; if we had a default, and they gave us no     #
   #  no input, return the $defaultValue.                            #
   #                                                                 #
   #  if we did not have a default value, then just return whatever  #
   #  the user gave us.  if they just hit the <enter> key,           #
   #  the calling routine will have to deal with that.               #
   #-----------------------------------------------------------------#

   if ("$defaultValue") {
      return $_ ? $_ : $defaultValue;    # return $_ if it has a value
   } else {
      return $_;
   }
}
print "##########################################\n";
print "#WeBWorK Moodle Question Type (WWMQT)    #\n";
print "##########################################\n";

#Continue?
print "This script will setup the WWMQT.\n";
$continue = promptUser('Continue','y');
if($continue ne "y") {
    exit;
}
print "\n";

#Program Root
my $path = $0;
$path =~ s|[^/]*$||;
$path = Cwd::abs_path($path);
$path = $path .  '/../../';
$path = Cwd::abs_path($path);
print "Enter the root directory where the WWMQT module is located. \n";
print "Examples: '/tmp/wwmqt' , '/home/you/tmp/wwmqt'\n";
$wwquestionRoot = promptUser('',$path);
print "\n";

#Moodle Root
print "Enter the root directory where Moodle is installed. \n";
print "Example: '/var/www/moodle' \n";
$moodleRoot = promptUser('');

#WSDL Root Directory
print "Enter the WSDL Path given in the WWQS setup. \n";
print "Example: http://myserver/problemserver_files/WSDL.wsdl\n";
$wsdlPath = promptUser('');

#Writing Configuration File
open(INPUT2, "<config.php.base");
$content = "";
while(<INPUT2>)
{
    my($line) = $_;
    $content .= $line;
}
close INPUT2;

$content =~ s/MARKER_FOR_WSDL/$wsdlPath/;
open(OUTP3, ">config.php") or die("Cannot open file 'config.php' for writing.\n");
print OUTP3 $content;
close OUTP3;

system("mv config.php $wwquestionRoot/moodle/question/type/webwork/config.php");
print "config.php file generated.\n";

#File Moving/Linking
$files = promptUser('Would you like me to place the files into proper directories (y,n)','y');
if($files eq 'y') {
   $action = 'cp -R ';
   #wipe existing directories
   system("rm -rf $moodleRoot/question/type/webwork");
   system("rm -rf $moodleRoot/blocks/webwork_printer");
   system("rm -rf $moodleRoot/question/format/webwork");
   system("rm -rf $moodleRoot/lang/en_utf8/help/webwork");
   #new ones
   system("cp -R $wwquestionRoot/moodle/question/type/webwork " .$moodleRoot . '/question/type/');
   system("cp -R $wwquestionRoot/moodle/blocks/webwork_printer " .$moodleRoot . '/blocks/');
   system("cp -R $wwquestionRoot/moodle/question/format/webwork " .$moodleRoot . '/question/format/');
   system($action . "$wwquestionRoot/moodle/lang/en_utf8/block_webwork_printer.php " . $moodleRoot . '/lang/en_utf8/block_webwork_printer.php');
   system($action . "$wwquestionRoot/moodle/lang/en_utf8/qtype_webwork.php " . $moodleRoot . '/lang/en_utf8/qtype_webwork.php');
   system($action . "$wwquestionRoot/moodle/lang/en_utf8/help/quiz/webwork.html " . $moodleRoot . '/lang/en_utf8/help/quiz/webwork.html');
   system($action . "$wwquestionRoot/moodle/lang/en_utf8/help/webwork " . $moodleRoot . '/lang/en_utf8/help/');
}

print "Setup Successful!\n";

1;
