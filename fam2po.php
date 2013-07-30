<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

define("SEPCHAR", ';');
define("ALTSEPCHAR", ' --- ');

$inrow = false;
$incell = false;
$nrow = 0;
$ncol = 0;
$rows = array();
$colrepeat = 0;
$dbg = false;
$podir = $argv[1];

for ($i = 2; $i < count($argv); $i++) {
    $err = "";
    $familyFile = $argv[$i];
    debugMessage("Processing file " . $familyFile);
    try {
        $familyFilePathInfo = pathinfo($familyFile);
        if (file_exists($familyFile)) {
            if (isset($familyFilePathInfo['extension'])) {
                switch ($familyFilePathInfo['extension']) {
                    case 'ods':
                        debugMessage("  --- csv extraction");
                        $csvfile = $familyFile . ".csv";
                        ods2csv($familyFile, $csvfile);
                        if (file_exists($csvfile)) {
                            extractPOFromCSV($csvfile, $podir);
                        } else {
                            throw new Exception("Unable to generate CSV from " . $familyFile);
                        }
                        unlink($csvfile);
                        break;

                    case 'csv':
                        extractPOFromCSV($familyFile, $podir);
                        break;

                    default:
                        debugMessage($familyFile . " has an unknown extension, skipping it.");
                }
            } else {
                debugMessage($familyFile . " has no extension, skipping it.");
            }
        } else {
            throw new Exception("Can't access file " . $familyFile);
        }
    }
    catch(Exception $e) {
        $err.= $e->getMessage() . " " . $e->getFile() . " line (" . $e->getLine() . ")\n";
    }
    if ($err) {
        throw new Exception($e);
    }
}

function debugMessage($msg)
{
    global $dbg;
    if ($dbg) {
        error_log("fam2po: " . $msg);
    }
}
/**
 * extractPOFromCSV from a CSV file and print it on standard output
 *
 * @param  string $fi file input path
 * @param string $podir Po directory
 * @return void
 */
function extractPOFromCSV($fi, $podir)
{
    $fdoc = fopen($fi, "r");
    if (!$fdoc) {
        new Exception("fam2po: Can't access file [$fi]");
    } else {
        $podoc = null;
        $contentToWrite = "";
        $nline = - 1;
        $famname = "*******";
        $cv_idview_index = 0;
        $cv_lview_index = 0;
        $cv_menu_index = 0;
        $date = date("c");
        while (!feof($fdoc)) {
            
            $nline++;
            
            $buffer = rtrim(fgets($fdoc, 16384));
            $data = explode(";", $buffer);
            
            $num = count($data);
            if ($num < 1) {
                continue;
            }
            
            $data[0] = trim(getArrayIndexValue($data, 0));
            switch ($data[0]) {
                case "BEGIN":
                    $famname = getArrayIndexValue($data, 5);
                    $famtitle = getArrayIndexValue($data, 2);
                    if ($famname) $podoc = fopen($podir . "/" . $famname . ".pot", "w+");
                    $contentToWrite = "msgid \"\"\n";
                    $contentToWrite.= "msgstr \"\"\n";
                    $contentToWrite.= "\"Project-Id-Version: $famname \\n\"\n";
                    $contentToWrite.= "\"Report-Msgid-Bugs-To: \\n\"\n";
                    $contentToWrite.= "\"PO-Revision-Date: $date\\n\"\n";
                    $contentToWrite.= "\"Last-Translator: Automatically generated\\n\"\n";
                    $contentToWrite.= "\"Language-Team: none\\n\"\n";
                    $contentToWrite.= "\"MIME-Version: 1.0\\n\"\n";
                    $contentToWrite.= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
                    $contentToWrite.= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
                    $contentToWrite.= "\"Language: \\n\"\n\n";
                    $contentToWrite.= "#, fuzzy, ($fi)\n";
                    $contentToWrite.= "msgid \"" . $famname . "#title\"\n";
                    $contentToWrite.= "msgstr \"" . $famtitle . "\"\n\n";
                    break;

                case "END":
                    if (!$podoc) {
                        new Exception("fam2po: Can't create tempory family po file [$podir/$famname.pot]");
                    } else {
                        fwrite($podoc, $contentToWrite);
                        fclose($podoc);
                    }
                    $famname = "*******";
                    break;

                case "ORDER":
                    $type = getArrayIndexValue($data, 1);
                    $cv_idview_index = 0;
                    $cv_lview_index = 0;
                    $cv_menu_index = 0;
                    if ($type === "CVDOC") {
                        foreach ($data as $index => $value) {
                            if ($value === "cv_idview") $cv_idview_index = $index;
                            else if ($value === "cv_lview") $cv_lview_index = $index;
                            else if ($value === "cv_menu") $cv_menu_index = $index;
                            if ($cv_idview_index && $cv_lview_index && $cv_menu_index) break;
                        }
                    }
                    break;

                case "DOC":
                    $type = getArrayIndexValue($data, 1);
                    if ($type === "CVDOC") {
                        $cvName = getArrayIndexValue($data, 2);
                        if ($cvName && !is_numeric($cvName) && $cv_idview_index) {
                            $cvdoc = fopen($podir . "/" . $cvName . ".pot", "w+");
                            if (!$cvdoc) {
                                new Exception("fam2po: Can't create tempory CV po file [$podir/$cvName.pot]");
                            }
                            $cvContentToWrite = "msgid \"\"\n";
                            $cvContentToWrite.= "msgstr \"\"\n";
                            $cvContentToWrite.= "\"Project-Id-Version: $cvName \\n\"\n";
                            $cvContentToWrite.= "\"Report-Msgid-Bugs-To: \\n\"\n";
                            $cvContentToWrite.= "\"PO-Revision-Date: $date\\n\"\n";
                            $cvContentToWrite.= "\"Last-Translator: Automatically generated\\n\"\n";
                            $cvContentToWrite.= "\"Language-Team: none\\n\"\n";
                            $cvContentToWrite.= "\"MIME-Version: 1.0\\n\"\n";
                            $cvContentToWrite.= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
                            $cvContentToWrite.= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
                            $cvContentToWrite.= "\"Language: \\n\"\n\n";
                            $tcv_idview = explode('\n', getArrayIndexValue($data, $cv_idview_index));
                            $tcv_lview = explode('\n', getArrayIndexValue($data, $cv_lview_index));
                            $tcv_menu = explode('\n', getArrayIndexValue($data, $cv_menu_index));
                            foreach ($tcv_idview as $i => $id) {
                                if ($cv_lview_index && $tcv_lview[$i]) {
                                    $cvContentToWrite.= "#: $fi\n";
                                    $cvContentToWrite.= "#, fuzzy\n";
                                    $cvContentToWrite.= "msgid \"" . $cvName . "#label#" . $id . "\"\n";
                                    $cvContentToWrite.= "msgstr \"" . $tcv_lview[$i] . "\"\n\n";
                                }
                                if ($cv_menu_index && $tcv_menu[$i]) {
                                    $cvContentToWrite.= "#: $fi\n";
                                    $cvContentToWrite.= "#, fuzzy\n";
                                    $cvContentToWrite.= "msgid \"" . $cvName . "#menu#" . $id . "\"\n";
                                    $cvContentToWrite.= "msgstr \"" . $tcv_menu[$i] . "\"\n\n";
                                }
                            }
                            fwrite($cvdoc, $cvContentToWrite);
                            fclose($cvdoc);
                        }
                    }
                    break;

                case "ATTR":
                case "MODATTR":
                case "PARAM":
                case "OPTION":
		    $contentToWrite.= "#: $fi\n";
                    $contentToWrite.= "#, fuzzy, ($fi)\n";
                    $contentToWrite.= "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data, 1)) . "\"\n";
                    $contentToWrite.= "msgstr \"" . getArrayIndexValue($data, 3) . "\"\n\n";
                    // Enum ----------------------------------------------
                    $type = getArrayIndexValue($data, 6);
                    if ($type == "enum" || $type == "enumlist") {
                        $d = str_replace('\,', '\#', getArrayIndexValue($data, 12));
                        $tenum = explode(",", $d);
                        foreach ($tenum as $ve) {
                            $d = str_replace('\#', ',', $ve);
                            $enumValues = explode("|", $d);
                            $contentToWrite.= "#, fuzzy, ($fi)\n";
                            $contentToWrite.= "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data, 1)) . "#" . (str_replace('\\', '', getArrayIndexValue($enumValues, 0))) . "\"\n";
                            $contentToWrite.= "msgstr \"" . (str_replace('\\', '', getArrayIndexValue($enumValues, 1))) . "\"\n\n";
                        }
                    }
                    // Options ----------------------------------------------
                    $options = getArrayIndexValue($data, 15);
                    $options = explode("|", $options);
                    foreach ($options as $currentOption) {
                        $currentOption = explode("=", $currentOption);
                        $currentOptionKey = getArrayIndexValue($currentOption, 0);
                        $currentOptionValue = getArrayIndexValue($currentOption, 1);
                        switch (strtolower($currentOptionKey)) {
                            case "elabel":
                            case "ititle":
                            case "submenu":
                            case "ltitle":
                            case "eltitle":
                            case "elsymbol":
                            case "lsymbol":
                            case "showempty":
                                $contentToWrite.= "#, fuzzy, ($fi)\n";
                                $contentToWrite.= "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data, 1)) . "#" . strtolower($currentOptionKey) . "\"\n";
                                $contentToWrite.= "msgstr \"" . $currentOptionValue . "\"\n\n";
                        }
                    }
                }
            }        }
    }
    
    function getArrayIndexValue(&$array, $index)
    {
        return isset($array[$index]) ? $array[$index] : "";
    }
    /** Utilities function to produce a CSV from an ODS**/
    /**
     * Take an ODS file and produce one CSV
     *
     * @param  string $odsfile path to ODS file
     * @param  string $csvfile path to CSV output file
     * @throws Exception
     * @return void
     */
    function ods2csv($odsfile, $csvfile)
    {
        if ($odsfile === "" or !file_exists($odsfile) or $csvfile === "") {
            throw new Exception("ODS convert needs an ODS path and a CSV path");
        }
        
        $content = ods2content($odsfile);
        $csv = xmlcontent2csv($content);
        $isWrited = file_put_contents($csvfile, $csv);
        if ($isWrited === false) {
            throw new Exception(sprintf("Unable to convert ODS to CSV fo %s", $odsfile));
        }
    }
    /**
     * Extract content from an ods file
     *
     * @param  string $odsfile file path
     * @throws Exception
     * @return string
     */
    function ods2content($odsfile)
    {
        if (!file_exists($odsfile)) {
            throw new Exception("file $odsfile not found");
        }
        $cibledir = uniqid("/var/tmp/ods");
        
        $cmd = sprintf("unzip -j %s content.xml -d %s >/dev/null", $odsfile, $cibledir);
        system($cmd);
        
        $contentxml = $cibledir . "/content.xml";
        if (file_exists($contentxml)) {
            $content = file_get_contents($contentxml);
            unlink($contentxml);
        } else {
            throw new Exception("unable to extract $odsfile");
        }
        
        rmdir($cibledir);
        return $content;
    }
    /**
     * @param $xmlcontent
     *
     * @throws Exception
     * @return string
     */
    function xmlcontent2csv($xmlcontent)
    {
        global $rows;
        $xml_parser = xml_parser_create();
        // Use case handling $map_array
        xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, true);
        xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_element_handler($xml_parser, "startElement", "endElement");
        xml_set_character_data_handler($xml_parser, "characterData");
        
        if (!xml_parse($xml_parser, $xmlcontent)) {
            throw new Exception(sprintf("Unable to parse XML : %s line %d", xml_error_string(xml_get_error_code($xml_parser)) , xml_get_current_line_number($xml_parser)));
        }
        $fcsv = "";
        xml_parser_free($xml_parser);
        foreach ($rows as $row) {
            $fcsv.= implode(SEPCHAR, $row) . "\n";
        }
        return $fcsv;
    }
    /* Handling method for XML parser*/
    function startElement(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $name, $attrs)
    {
        global $rows, $nrow, $inrow, $incell, $ncol, $colrepeat, $celldata;
        if ($name == "TABLE:TABLE-ROW") {
            $inrow = true;
            if (isset($rows[$nrow])) {
                // fill empty cells
                $idx = 0;
                foreach ($rows[$nrow] as $k => $v) {
                    if (!isset($rows[$nrow][$idx])) {
                        $rows[$nrow][$idx] = '';
                    }
                    $idx++;
                }
                ksort($rows[$nrow], SORT_NUMERIC);
            }
            $nrow++;
            $ncol = 0;
            $rows[$nrow] = array();
        }
        
        if ($name == "TABLE:TABLE-CELL") {
            $incell = true;
            $celldata = "";
            if (!empty($attrs["TABLE:NUMBER-COLUMNS-REPEATED"])) {
                $colrepeat = intval($attrs["TABLE:NUMBER-COLUMNS-REPEATED"]);
            }
        }
        if ($name == "TEXT:P") {
            if (isset($rows[$nrow][$ncol])) {
                if (strlen($rows[$nrow][$ncol]) > 0) {
                    $rows[$nrow][$ncol].= '\n';
                }
            }
        }
    }
    
    function endElement(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $name)
    {
        global $rows, $nrow, $inrow, $incell, $ncol, $colrepeat, $celldata;
        if ($name == "TABLE:TABLE-ROW") {
            // Remove trailing empty cells
            $i = $ncol - 1;
            while ($i >= 0) {
                if (strlen($rows[$nrow][$i]) > 0) {
                    break;
                }
                $i--;
            }
            array_splice($rows[$nrow], $i + 1);
            $inrow = false;
        }
        
        if ($name == "TABLE:TABLE-CELL") {
            $incell = false;
            
            $rows[$nrow][$ncol] = $celldata;
            
            if ($colrepeat > 1) {
                $rval = $rows[$nrow][$ncol];
                for ($i = 1; $i < $colrepeat; $i++) {
                    $ncol++;
                    $rows[$nrow][$ncol] = $rval;
                }
            }
            $ncol++;
            $colrepeat = 0;
        }
    }
    
    function characterData(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $data)
    {
        global $inrow, $incell, $celldata;
        if ($inrow && $incell) {
            $celldata.= preg_replace('/^\s*[\r\n]\s*$/ms', '', str_replace(SEPCHAR, ALTSEPCHAR, $data));
        }
    }
    