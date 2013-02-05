<?php
define("SEPCHAR", ';');
define("ALTSEPCHAR", ' --- ');

$inrow = false;
$incell = false;
$nrow = 0;
$ncol = 0;
$rows = array();
$colrepeat = 0;
$dbg = false;

for ($i = 1; $i < count($argv); $i++) {
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
                            extractPOFromCSV($csvfile);
                        } else {
                            throw new Exception("Unable to generate CSV from " . $familyFile);
                        }
                        unlink($csvfile);
                        break;
                    case 'csv':
                        extractPOFromCSV($familyFile);
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
    catch (Exception $e) {
        $err .= $e->getMessage() . " " . $e->getFile() . " line (" . $e->getLine() . ")\n";
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
 * @return void
 */
function extractPOFromCSV($fi)
{
    $fdoc = fopen($fi, "r");
    if (!$fdoc) {
        new Exception("fam2po: Can't access file [$fi]");
    } else {
        $nline = -1;
        $famname = "*******";

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
                    echo "#, fuzzy, ($fi::$nline)\n";
                    echo "msgid \"" . $famname . "#title\"\n";
                    echo "msgstr \"" . $famtitle . "\"\n\n";
                    break;
                case "END":
                    $famname = "*******";
                    break;
                case "ATTR":
                case "MODATTR":
                case "PARAM":
                case "OPTION":
                    echo "#, fuzzy, ($fi::$nline)\n";
                    echo "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data,1)) . "\"\n";
                    echo "msgstr \"" . getArrayIndexValue($data, 3) . "\"\n\n";
                    // Enum ----------------------------------------------
                    $type = getArrayIndexValue($data, 6);
                    if ($type == "enum" || $type == "enumlist") {
                        $d = str_replace('\,', '\#', getArrayIndexValue($data, 12));
                        $tenum = explode(",", $d);
                        foreach ($tenum as $ve) {
                            $d = str_replace('\#', ',', $ve);
                            $enumValues = explode("|", $d);
                            echo "#, fuzzy, ($fi::$nline)\n";
                            echo "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data,1)) .
                                "#" . (str_replace('\\', '', getArrayIndexValue($enumValues,0))) . "\"\n";
                            echo "msgstr \"" . (str_replace('\\', '', getArrayIndexValue($enumValues,1))) . "\"\n\n";
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
                            case "showempty":
                                echo "#, fuzzy, ($fi::$nline)\n";
                                echo "msgid \"" . $famname . "#" . strtolower(getArrayIndexValue($data,1))
                                    . "#" . strtolower($currentOptionKey) . "\"\n";
                                echo "msgstr \"" . $currentOptionValue . "\"\n\n";
                        }
                    }
            }

        }
    }
}

function getArrayIndexValue(&$array, $index) {
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
        throw new Exception(sprintf("Unable to parse XML : %s line %d",
            xml_error_string(xml_get_error_code($xml_parser)),
            xml_get_current_line_number($xml_parser)));
    }
    $fcsv = "";
    xml_parser_free($xml_parser);
    foreach ($rows as $row) {
        $fcsv .= implode(SEPCHAR, $row) . "\n";
    }
    return $fcsv;
}

/* Handling method for XML parser*/
function startElement(/** @noinspection PhpUnusedParameterInspection */
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
                $rows[$nrow][$ncol] .= '\n';
            }
        }
    }
}

function endElement(/** @noinspection PhpUnusedParameterInspection */
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

function characterData(/** @noinspection PhpUnusedParameterInspection */
    $parser, $data)
{
    global $inrow, $incell, $celldata;
    if ($inrow && $incell) {
        $celldata .= preg_replace('/^\s*[\r\n]\s*$/ms', '', str_replace(SEPCHAR, ALTSEPCHAR, $data));
    }
}