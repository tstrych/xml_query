#!/usr/bin/env php5.6
<?php

class Query
{
    #params
    var $input;
    var $output;
    var $qf_query;
    var $n;
    var $root;
    var $in;
    var $input_data;
    var $output_file;
    var $root_data;
    var $qf_query_input;

    #query:
    var $select;

    var $from;
    var $fr_elem;
    var $fr_attribute;

    var $where;
    var $where_elem;
    var $where_attribute;
    var $where_operator;
    var $where_literal;
    var $where_not;

    var $limit;
}
//writes help to user
function help() {
    print "welcome in help:\n";
    print "this script, which processes XML based on SQL SELECT statement\n";
    print "you can see which parameters you can set:\n";
    print "--help \n";
    print "--input=filename\n";
    print "--output=filename\n";
    print "--query='for example: SELECT element FROM element.attribute WHERE element.attribute > 5 LIMIT 6'\n";
    print "--qf=filename\n";
    print "-n\n";
    print "--root=element\n";
    print "examples of using parameters in script:\n";
    print ">> --help  \n";
    print ">> --input=filename --output=filename --qf=filename -n \n";
    print ">> --input=filename --output=filename --query='sql command in xml' --root=element -n \n";
    }

//function parse arguments
//what was parsed save to object XQR
//argc to control how many  arguments was set up
function parse_arg($arguments, $argc, $XQR) {
    if ($argc == 1) {
        fwrite(STDERR, "for more information use name of the script with parameter --help \n");
        exit(4);
    }
    //counter checking count of loaded arguments
    $counter =1;
    //iterate through arguments
    foreach ($arguments as $argument) {
        if (preg_match("/--help/", $argument)) {
            if ($argc <> 2) {
                exit(1);
            }
            else {
                help();//writes help to standard output
                exit(0);
            }
        }
        if (preg_match("/--input=.+/", $argument)) {
            if (isset($XQR->input)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument\n");
                exit(1);
            }
            else {  //save input
                $XQR->input = true; //flag
                $XQR->input_data = substr($argument, 8); //filename
                $counter = $counter + 1;
            }
        }
        if (preg_match("/--output=.+/", $argument)) {
            if (isset($XQR->output)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument\n");
                exit(1);
            }
            else {  //save output
                $XQR->output = true;
                $XQR->output_file = substr($argument, 9);
                $counter = $counter + 1;
            }
        }
        if (preg_match("/^-n$/", $argument)) {
            if (isset($XQR->n)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument\n");
                exit(1);
            }
            else {  //save header
                $XQR->n = true;
                $counter = $counter + 1;
            }
        }
        if (preg_match("/--qf=.+/", $argument)) {
            if (isset($XQR->qf_query)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument or use qf and query parameters\n");
                exit(1);
            }
            else { //load and save query file
                $XQR->qf_query = true;
                $counter = $counter + 1;
                $qf_file = substr($argument, 5);
                if (file_exists($qf_file)) { //control file
                   if (($file = fopen($qf_file, 'r')) == false ) {
                       fwrite(STDERR, "query file doesn't exist\n");
                       exit(2);
                   }
                   else {
                       $XQR->qf_query_input = file_get_contents($qf_file); //load file from file
                       $XQR->qf_query_input = str_replace(array("\r", "\n", ), '', $XQR->qf_query_input);
                       //remove new lines and i forget what is \r use google
                       $XQR->qf_query_input = preg_replace('!\s+!', ' ', $XQR->qf_query_input);
                       //replace multi space to one space
                   }
                }
                else {
                    fwrite(STDERR, "query file doesn't exist\n");
                    exit(2);
                }
            }
        }
        if (preg_match("/--query=SELECT/", $argument)) {
            if (isset($XQR->qf_query)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument or use qf and query parameters\n");
                exit(1);
            }
            else { //load query from arguments
                $XQR->qf_query = true;
                $XQR->qf_query_input = substr($argument, 8);
                $counter = $counter + 1;
                $i = 2;
                $flag = 0;
                $connected = 0;
                $space = " ";
                foreach($arguments as $arg) {
                    if ($flag)
                        break;
                    //load until next argument will start with -- or -
                    if ($i > $counter) {
                        if(preg_match("/^--/", $arg)) {
                            $flag = 1;
                        }
                        elseif(preg_match("/^-.*/", $arg)) {
                            $flag = 1;
                        }
                        else {
                            $XQR->qf_query_input = "{$XQR->qf_query_input}{$space}{$arg}"; //concatenate string
                            $connected++;
                        }
                    }
                    $i++;
                }
                $counter = $counter + $connected;
            }
        }
        if (preg_match("/--root=.+/", $argument)) {
            if (isset($XQR->root)) { //check if argument was not set multiple time
                fwrite(STDERR, "set multiple times of equal argument\n");
                exit(1);
            }
            else { //load root
                $XQR->root = true;
                $counter = $counter + 1;
                $XQR->root_data = substr($argument, 7);
            }
        }
    }
    if ($counter != $argc) { //another argument that the script accepts
        fwrite(STDERR, "bad arguments\n");
        exit(1);
    }
}

//function return true if user does not used argument --input and read input from standard input
//using data from object XQR
function std_in($XQR){
    if (!$XQR->input and (empty($XQR->input_data))) {
        while (FALSE !== ($line = fgets(STDIN))) {
            $XQR->input_data = $XQR->input_data . $line;
        }
        $XQR->input = true; //input to true
        return true;
    }
    return false;
}

//function parse input from input file
//flag flag can be set to true only in function std_in
//using data from object XQR
function parse_input($XQR, $flag)
{
    if (!$flag) {
        $name_of_file = $XQR->input_data;
        if (file_exists($name_of_file)) {
            $handle = fopen($XQR->input_data, "r");
            if (!$handle){
                fwrite(STDERR, "can' t open file\n");
                exit(4);
            }
            $XQR->input_data = file_get_contents($name_of_file, true);
        }
        else {
            fwrite(STDERR, "input file doesn't exist\n");
            exit(2);
        }
    }
}

//output prints output (xml file) to file or on standard output
//using data from object XQR
//xml is file where is loaded output
function output($XQR,$xml){
    if (!$XQR->output) {
        fwrite( STDOUT, "$xml\n");
    }
    else {
        $file = fopen( $XQR->output_file, "w" );
        if (!$file) {
            fwrite(STDERR, "can't open output file\n");
            exit(3);
        }
        file_put_contents($XQR->output_file, $xml);
    }
}


//function returns true when is set input and query file or query load from arguments
//using data from object XQR
function good_format($XQR) {
    if ($XQR->input and $XQR->qf_query) {
        return true;
    }
    else {
        return false;
    }
}

//function parse query file or query load from arguments
//using data from object XQR
function parse_query_and_qf($XQR)
{
    //really long regex which tell you if format of query is right
    $regex = "\\s*SELECT\\s+([^ .]+)\\s+FROM(\\s+((?!LIMIT)(?!WHERE)([a-žA-Ž]+)?(\\.[a-žA-Ž]+)?|ROOT))?(\\s+WHERE\\s+((NOT\\s+)*(?(?=[a-žA-Ž]+\\s*([<>=]|CONTAINS))((?!FROM)(?!SELECT)(?!CONTAINS)(?!LIMIT)(?!WHERE)[a-žA-Ž]+)|([a-žA-Ž]*(\\.[a-žA-Ž]+)))(\\s+CONTAINS\\s*[\"']{1}[a-žA-Ž0-9]+[\"']{1}|\\s*[=><]{1}\\s*([+-]?[0-9]+|[\"']{1}[a-žA-Ž0-9]+[\"']{1}))))?(\\s+LIMIT\\s+([0-9]+))?";


    if (!preg_match("/^{$regex}$/", $XQR->qf_query_input)) {
        fwrite(STDERR, "invalid input of query\n");
        exit(80);
    }
    //parse select
    if (preg_match("/SELECT\\s+([^ .]+)/", $XQR->qf_query_input, $matches)) {
        if (isset($matches[1])) //match groups and  subgroups of regex
            $XQR->select = trim($matches[1]);
    }
    //parse from
    if (preg_match("/FROM(\\s+((?!LIMIT)(?!WHERE)([a-žA-Ž]+)?(\\.[a-žA-Ž]+)?|ROOT))?/", $XQR->qf_query_input, $matches)) {
        if (isset($matches[1])) //match groups and  subgroups of regex
            $XQR->from = trim($matches[1]);
            if (preg_match("/([a-žA-Ž]*)\\.*([a-žA-Ž]*)/", $XQR->from, $matches)) {
                if (!empty($matches[1]))
                    $XQR->fr_elem = trim($matches[1]);
                if (!empty($matches[2]))
                    $XQR->fr_attribute = trim($matches[2]);
            }
    }

    //parse where
    if (preg_match("/WHERE\\s+((NOT\\s+)*(?(?=[a-žA-Ž]+\\s*([<>=]|CONTAINS))((?!FROM)(?!SELECT)(?!CONTAINS)(?!LIMIT)(?!WHERE)[a-žA-Ž]+)|([a-žA-Ž]*(\\.[a-žA-Ž]+)))(\\s+CONTAINS\\s*[\"']{1}[a-žA-Ž0-9]+[\"']{1}|\\s*[=><]{1}\\s*([+-]?[0-9]+|[\"']{1}[a-žA-Ž0-9]+[\"']{1})))/", $XQR->qf_query_input, $matches)) {        if (isset($matches[1])) {
            $XQR->where = trim($matches[1]);
            if (preg_match("/.*NOT.*/", $XQR->where)) {
                $XQR->where_not = substr_count($XQR->where, "NOT") % 2; #1 if NOT is set 0 if NOT is unset, using modulo
            }
            $XQR->where = trim(str_replace("NOT", '', $XQR->where));
            if (preg_match("/([a-žA-Ž]*)\\.*([a-žA-Ž]*)((\\s+CONTAINS)\\s*[\"']{1}([a-žA-Ž0-9]+)[\"']{1}|\\s*([=><]{1})\\s*([+-]?[0-9]+|[\"']{1}[a-žA-Ž0-9]+[\"']{1}))/", $XQR->where, $matches)) {
                //again groups and subgroups to parse where
                if (!empty($matches[1]))
                    $XQR->where_elem = trim($matches[1]);
                if (!empty($matches[2]))
                    $XQR->where_attribute = trim($matches[2]);
                if (!empty($matches[4]))
                    $XQR->where_operator = trim($matches[4]);
                else
                    $XQR->where_operator = trim($matches[6]);
                if (!empty($matches[5]))
                    $XQR->where_literal = trim($matches[5]);
                else
                    $XQR->where_literal = trim($matches[7]);
            }
        }
    }
    //parse limit
    if (preg_match("/LIMIT\\s+([0-9]+)/", $XQR->qf_query_input, $matches))
        if (isset($matches[1]))
            $XQR->limit = trim($matches[1]);

    //really pretty print which will print your parsed query
//    print "------------------GOOD FORM-------------\n";
//    print " select:$XQR->select\n from:$XQR->from\n where:$XQR->where\n limit:$XQR->limit\n\n";
//    print "------------------FROM------------------\n";
//    print " from:$XQR->from\n from_element:$XQR->fr_elem\n from_attribute:$XQR->fr_attribute\n\n";
//    print "------------------WHERE-----------------\n";
//    print " elem:$XQR->where_elem\n attr:$XQR->where_attribute\n operator:$XQR->where_operator\n literal:$XQR->where_literal\n not:$XQR->where_not\n\n";
//    print "------------------LIMIT-----------------\n";
//    print " limit: $XQR->limit\n";
}


//function parse XML
//using data from object XQR
function parseXML($XQR)
{
    // dom document represents an entire HTML or XML document; serves as the root of the document tree.
    //we create new document and function append elements to xml file
    $new_xml = new DOMDocument("1.0", "UTF-8");
    // root condition , if root from arguments is not set function will remove root element later
    if ($XQR->root)
        $first_elem = $new_xml->createElement($XQR->root_data);
    else
        $first_elem = $new_xml->createElement("root");

    $new_xml->appendChild($first_elem);

    //load string to DOMDocument
    $xml_to_parse = new DOMDocument();
    $xml_to_parse->loadXML($XQR->input_data);

    //set FROM in query
    if (isset($XQR->from)) {
        if ($XQR->fr_elem == "ROOT" or empty($XQR->fr_elem))
            $fr_root = "*";
        else {
            $fr_root = $XQR->fr_elem;
        }
        //iterate through elements in root element
        foreach ($xml_to_parse->getElementsByTagName($fr_root) as $elem){
            //control if  element has set attribute or element
            if ((isset($XQR->fr_attribute) and $elem->hasAttribute($XQR->fr_attribute) or !isset($XQR->fr_attribute) and isset($XQR->fr_elem))){
                $elements = $elem->getElementsByTagName($XQR->select);
                if (isset($XQR->where))
                    filter_where($elements, $XQR); //parse where part
                append_elements($XQR->limit, $new_xml, $elements); //append the right elements
                break;
            }
        }
    }

    //save xml from DOM object to string
    $new_xml = $new_xml->saveXML();
    if ($XQR->n)    //remove from start header of XML which is automatically set
        $new_xml = ltrim(str_replace('<?xml version="1.0" encoding="UTF-8"?>', "", $new_xml));

    //and remove root element if was not set
    if (!$XQR->root){
        if (!($first_elem->hasChildNodes())) {
            $new_xml = ltrim(str_replace("<root/>", "", $new_xml));
        }
        else {
            $new_xml = str_replace("<root>", "", $new_xml);
            $new_xml = str_replace("</root>", "", $new_xml);
        }
    }
    //return output
    return $new_xml;

}

//limit of elements which can be on output
//xml file where the function append elements
//and elements which function append
function append_elements($limit, $xml, $elements){
    foreach ($elements as $elem) {
        $elem = $xml->importNode($elem, true);
        if (isset($limit) && $limit != 0) {
            $limit = $limit - 1;
            $xml->documentElement->appendChild($elem);
        }
        elseif (!isset($limit)) {
            $xml->documentElement->appendChild($elem);
        }
    }
}

//elements with ampersand because function change elements
//using data from object XQR
function filter_where(&$elements, $XQR)
{
    //where element or take from all elements because function try to find attribute
    if (isset($XQR->where_elem))
        $where_elem = $XQR->where_elem;
    else
        $where_elem = "*";

    //using down_to because function remove elements and no element can't be skipped
    for ($i = $elements->length - 1; $i >= 0; $i--) {
        $where_flag = false;
        $changed_elements = $elements->item($i)->getElementsByTagName($where_elem);
        /*iterate through elements trying to find condition and control
        if condition is meet and than set flag to not be removed*/
        foreach ($changed_elements as $ch) {
            if (!isset($XQR->where_attribute)) {
                if ($where_elem == $ch->tagName) {
                    //control right name of condition
                    $problem_solver = $ch->nodeValue;
                    //choose contains and find if element content it by regular expression
                    if ($XQR->where_operator == "CONTAINS" and preg_match("/.*{$XQR->where_literal}.*/", $problem_solver)) {
                        $where_flag = true;
                    }
                    //choose other relation operators as =>< and control if the elements meet condition
                    if ($XQR->where_operator != "CONTAINS") {
                        if (compare($XQR->where_operator, $problem_solver, $XQR->where_literal)) {
                            $where_flag = true;
                        }
                    }
                }
            }
            //do the same using attribute
            else {
                if ($ch->hasAttribute($XQR->where_attribute)) {
                    $problem_solver = $ch->getAttribute($XQR->where_attribute);
                    if ($XQR->where_operator == "CONTAINS" and preg_match("/.*{$XQR->where_literal}.*/", $problem_solver)) {
                        $where_flag = true;
                    }
                    if ($XQR->where_operator != "CONTAINS") {
                        if (compare($XQR->where_operator, $problem_solver, $XQR->where_literal)) {
                            $where_flag = true;
                        }
                    }
                }
            }
        }
        //need to be careful if element or attribute is same as select element, check if condition is right
        /*iterate through elements trying to find condition and control
        if condition is meet and than set flag to not be removed*/
        if (!$where_flag) {
            if (!isset($XQR->where_attribute)) {
                if ($where_elem == $elements->item($i)->tagName) {
                    //control right name of condition
                    $first_problem_solver = $elements->item($i)->nodeValue;
                    //choose contains and find if element content it by regular expression
                    if ($XQR->where_operator == "CONTAINS" and preg_match("/.*{$XQR->where_literal}.*/", $first_problem_solver)) {
                        $where_flag = true;
                    }
                    //choose other relation operators as =>< and control if the elements meet condition
                    if ($XQR->where_operator != "CONTAINS") {
                        if (compare($XQR->where_operator, $first_problem_solver, $XQR->where_literal)) {
                            $where_flag = true;
                        }
                    }
                }
            }
            //do the same using attribute
            else {
                if ($elements->item($i)->hasAttribute($XQR->where_attribute)) {
                    $first_problem_solver = $elements->item($i)->getAttribute($XQR->where_attribute);
                    if ($XQR->where_operator == "CONTAINS" and preg_match("/.*{$XQR->where_literal}.*/", $first_problem_solver)) {
                        $where_flag = true;
                    }
                    if ($XQR->where_operator != "CONTAINS") {
                        if (compare($XQR->where_operator, $first_problem_solver, $XQR->where_literal)) {
                            $where_flag = true;
                        }
                    }
                }
            }
        }

        //where NOT condition than negate flag which is set when the condition is right
        if (!$XQR->where_not) {
            //remove condition when the where flag is not set and not is not set
            if (!$where_flag) {
                    $elements->item($i)->parentNode->removeChild($elements->item($i));
                }
        }
        else {
            //remove condition when the where flag is set and not is set too
             if ($where_flag) {
                        $elements->item($i)->parentNode->removeChild($elements->item($i));
             }
        }
    }
}


//compare strings or numbers return result of condition
//what i compare is control with literal by operator
function compare($operator, $control, $literal){
    if (is_numeric($control) && is_numeric($literal))
        $control = floatval($control);
    else {
        $control = trim(str_replace(['"', '\''], '', $control));
        $literal = trim(str_replace(['"', '\''], '', $literal));
    }
    if ($operator == "<")
        return $control < $literal;
    elseif ($operator == ">")
        return $control > $literal;
    elseif ($operator == "=")
        return $control == $literal;
    else
        return false;
}

//MAIN - return 0 if everything goes well
$flag = false;
$arguments = $argv; //load arguments
unset($arguments[0]); //unset name of script everyone know his name is xqr.php

$XQR  =new Query(); //create object
parse_arg($arguments,$argc , $XQR); //parse each argument
$flag = std_in($XQR); //input from standard input
parse_input($XQR,$flag); //load input

//control good format
if (!good_format($XQR)) {
    fwrite(STDERR, ">>you need to use parameters input,output,qf or input,output,query \n>>for more information use --help \n");
    exit(1);
}
//parse query divide select, from, where, attributes, elements, literal, limit, root
parse_query_and_qf($XQR);

//create ,based on from and where select, append each suitable element
$new_xml = parseXML($XQR);

//give output to file or to standard output
output($XQR,$new_xml);

return 0;
?>