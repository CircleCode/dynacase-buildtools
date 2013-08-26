#!/usr/bin/env php
<?php

class Ods2Csv
{


    const ALTSEPCHAR = ' --- ';
    const SEPCHAR = ';';

    /**
     * Take an ODS file and produce one CSV
     *
     * @param  string $odsfile path to ODS file
     * @param  string $csvfile path to CSV output file
     * @throws Exception
     * @return void
     */
    public function convertOds2csv($odsfile, $csvfile)
    {
        if ($odsfile === "" or !file_exists($odsfile) or $csvfile === "") {
            throw new Exception("ODS convert needs an ODS path and a CSV path");
        }

        $content = $this->ods2content($odsfile);
        $csv = $this->xmlcontent2csv($content);
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
        xml_set_element_handler($xml_parser, array($this, "startElement"), array($this, "endElement"));
        xml_set_character_data_handler($xml_parser, array($this, "characterData"));

        if (!xml_parse($xml_parser, $xmlcontent)) {
            throw new Exception(sprintf("Unable to parse XML : %s line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
        }
        $fcsv = "";
        xml_parser_free($xml_parser);
        foreach ($rows as $row) {
            $fcsv .= implode(self::SEPCHAR, $row) . "\n";
        }
        return $fcsv;
    }

    /* Handling method for XML parser*/
    public static function startElement(
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
                    $rows[$nrow][$ncol] .= '\n';
                }
            }
        }
    }

    public static function  endElement(
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

    public static function characterData(
        /** @noinspection PhpUnusedParameterInspection */
        $parser, $data)
    {
        global $inrow, $incell, $celldata;
        if ($inrow && $incell) {
            $celldata .= preg_replace('/^\s*[\r\n]\s*$/ms', '', str_replace(self::SEPCHAR, self::ALTSEPCHAR, $data));
        }
    }

    public function seemsODS($filename)
    {
        if (preg_match('/\.ods$/', $filename)) return true;
        $sys = trim(shell_exec(sprintf("file -bi %s", escapeshellarg($filename))));
        if ($sys == "application/x-zip") return true;
        if ($sys == "application/vnd.oasis.opendocument.spreadsheet") return true;
        return false;
    }
}

class GenerateStub
{

    protected $files = array();

    public $content = array();
    public $attr = array();

    public function addFileToExamine($file)
    {
        $this->files[] = $file;
    }


    public function getSignifiantContent($file)
    {
        $convert = new Ods2Csv();
        if ($convert->seemsODS($file)) {
            $csvFile = tempnam("/tmp", "FOO");
            $convert->convertOds2csv($file, $csvFile);
            $needUnlink = $csvFile;

        } else {
            $csvFile = $file;
            $needUnlink = false;


        }
        $famName = $className = $fromName = $famId = $famTitle = $name='';
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                switch ($data[0]) {
                    case "BEGIN":
                        $famName = strtolower($data[5]);
                        if (isset($this->content[$famName])) {

                            $className = $this->content[$famName]["className"];
                            $fromName = $this->content[$famName]["fromName"];
                            $famId = $this->content[$famName]["id"];
                            $name = $this->content[$famName]["name"];
                            if ($data[4] && $data[4] != '-') {
                                $className = $data[4];
                            }
                            if ($data[1] && $data[1] != '-') {
                                $fromName = ($data[1] == '--') ? '' : $data[1];
                            }
                            if ($data[3] && $data[3] != '-') {
                                $famId = $data[3];
                            }
                            if ($data[2] && $data[2] != '-') {
                                $famTitle = $data[2];
                            }
                        } else {

                            $className = $data[4];
                            $fromName = ($data[1] == '--') ? '' : $data[1];
                            $famId = $data[3];
                            $famTitle = $data[2];
                            $name = $data[5];
                        }
                        $this->attr[$famName]=array();
                        break;
                    case 'CLASS';
                        $className = $data[1];
                        break;
                    case 'PARAM':
                    case 'ATTR':
                        $attrid = strtolower($data[1]);
                        $this->attr[$famName][$attrid] = array(
                            "id" => $attrid,
                            "type" => $data[6],
                            "label" => $data[3],
                            "famName" => $famName);
                        break;
                    case 'END';
                        $this->content[$famName] = array(
                            "famName" => $famName,
                            "name" => $name,
                            "className" => $className,
                            "id" => $famId,
                            "title" => $famTitle,
                            "fromName" => $fromName);
                        break;
                }

            }
        }
        fclose($handle);
        if ($needUnlink) {
            unlink($needUnlink);
        }
        $this->completeContent();
    }

    protected function completeContent()
    {
        foreach ($this->content as $k => $info) {
            $fromName = $info["fromName"];
            if ($fromName and is_numeric($fromName)) {
                foreach ($this->content as $famName => $info2) {
                    if ($info2["id"] == $fromName) {
                        $this->content[$k]["fromName"] = $famName;
                    }
                }
            }
        }
    }

    public function generateStubFile()
    {
        $phpContent = "<?php\n";
        $phpContent .= "namespace Dcp\\Family {\n";
        foreach ($this->content as $famId => $famInfo) {
            $phpContent .= "\t" . $this->getPhpPart($famInfo) . "\n";
        }
        $phpContent .= "}\n";
        return $phpContent;
    }

    protected function getPhpPart(array $info)
    {
        $famName = sprintf('%s', ucfirst(strtolower($info["famName"])));
        if ($info["className"]) {
            $parentClass = '\\' . $info["className"];
        } elseif ($info["fromName"]) {
            $parentClass = sprintf('%s', ucfirst(strtolower($info["fromName"])));
        } else {
            $parentClass = 'Document';
        }
        $comment = sprintf('/** %s  */', $info["title"]);
        $template = sprintf('class %s extends %s { const familyName="%s";}', $famName, $parentClass, $info["name"]);
        return $comment . "\n\t" . $template;

    }

    public function generateStubAttrFile()
    {

        $phpContent = "namespace Dcp\\AttributeIdentifiers {\n";
        foreach ($this->attr as $famName => $attrInfo) {
            $phpContent .= "\t" . $this->getPhpAttrPart($famName, $attrInfo) . "\n";
        }
        $phpContent .= "}\n";
        return $phpContent;
    }

    protected function getPhpAttrPart($famName, array $info)
    {
        $famInfo = $this->content[$famName];
        if ($famInfo["fromName"]) {
            $parentClass = sprintf('%s', ucfirst(strtolower($famInfo["fromName"])));
        } else {
            $parentClass = '';
        }
        $comment = sprintf('/** %s  */', $famInfo["title"]);
        if ($parentClass) {
            $template = sprintf("class %s extends %s {\n", ucwords($famName), $parentClass);
        } else {
            $template = sprintf("class %s {\n", ucwords($famName));

        }
        foreach ($info as $attrid => $attrInfo) {
            $template .= sprintf("\t\t/** [%s] %s */\n", str_replace('*',' ', $attrInfo["type"]),str_replace('*', ' ',$attrInfo["label"]));
            $template .= sprintf("\t\tconst %s='%s';\n", $attrInfo["id"], $attrInfo["id"]);
        }
        $template .= "\t}";
        return $comment . "\n\t" . $template;
    }
}

$s = new GenerateStub();
foreach ($argv as $k => $aFile) {
    if ($k > 0) {
        $s->getSignifiantContent($aFile);
    }
}
print($s->generateStubFile());
print($s->generateStubAttrFile());
