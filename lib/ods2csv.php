<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

class Ods2Csv
{
    
    const ALTSEPCHAR = ' --- ';
    const SEPCHAR = ';';
    
    private $rows = array();
    private $nrows = 0;
    private $ncol = 0;
    private $celldata = '';
    private $colrepeat = false;
    private $inrow = false;
    private $incell = false;
    /**
     * Take an ODS file and produce one CSV
     *
     * @param  string $odsfile path to ODS file
     * @param  string $csvfile path to CSV output file
     * @throws Exception
     * @return void
     */
    public function convertOds2Csv($odsfile, $csvfile)
    {
        if ($odsfile === "" or !file_exists($odsfile) or $csvfile === "") {
            throw new Exception("ODS convert needs an ODS path and a CSV path");
        }
        
        $this->rows = array();
        $this->nrows = 0;
        $this->ncol = 0;
        $this->colrepeat = false;
        $this->inrow = false;
        $this->incell = false;
        
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
    protected function ods2content($odsfile)
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
    protected function xmlcontent2csv($xmlcontent)
    {
        
        $xml_parser = xml_parser_create();
        // Use case handling $map_array
        xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, true);
        xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_element_handler($xml_parser, array(
            $this,
            "startElement"
        ) , array(
            $this,
            "endElement"
        ));
        xml_set_character_data_handler($xml_parser, array(
            $this,
            "characterData"
        ));
        
        if (!xml_parse($xml_parser, $xmlcontent)) {
            throw new Exception(sprintf("Unable to parse XML : %s line %d", xml_error_string(xml_get_error_code($xml_parser)) , xml_get_current_line_number($xml_parser)));
        }
        $fcsv = "";
        xml_parser_free($xml_parser);
        foreach ($this->rows as $row) {
            $fcsv.= implode(self::SEPCHAR, $row) . "\n";
        }
        return $fcsv;
    }
    /* Handling method for XML parser*/
    protected function startElement(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $name, $attrs)
    {
        
        if ($name == "TABLE:TABLE-ROW") {
            $this->inrow = true;
            if (isset($this->rows[$this->nrows])) {
                // fill empty cells
                $idx = 0;
                /** @noinspection PhpUnusedLocalVariableInspection */
                foreach ($this->rows[$this->nrows] as & $v) {
                    if (!isset($this->rows[$this->nrows][$idx])) {
                        $this->rows[$this->nrows][$idx] = '';
                    }
                    $idx++;
                }
                ksort($this->rows[$this->nrows], SORT_NUMERIC);
            }
            $this->nrows++;
            $this->ncol = 0;
            $this->rows[$this->nrows] = array();
        }
        
        if ($name == "TABLE:TABLE-CELL") {
            $this->incell = true;
            $this->celldata = "";
            if (!empty($attrs["TABLE:NUMBER-COLUMNS-REPEATED"])) {
                $this->colrepeat = intval($attrs["TABLE:NUMBER-COLUMNS-REPEATED"]);
            }
        }
        if ($name == "TEXT:P") {
            if (isset($this->rows[$this->nrows][$this->ncol])) {
                if (strlen($this->rows[$this->nrows][$this->ncol]) > 0) {
                    $this->rows[$this->nrows][$this->ncol].= '\n';
                }
            }
        }
    }
    
    protected function endElement(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $name)
    {
        
        if ($name == "TABLE:TABLE-ROW") {
            // Remove trailing empty cells
            $i = $this->ncol - 1;
            while ($i >= 0) {
                if (strlen($this->rows[$this->nrows][$i]) > 0) {
                    break;
                }
                $i--;
            }
            array_splice($this->rows[$this->nrows], $i + 1);
            $this->inrow = false;
        }
        
        if ($name == "TABLE:TABLE-CELL") {
            $this->incell = false;
            
            $this->rows[$this->nrows][$this->ncol] = $this->celldata;
            
            if ($this->colrepeat > 1) {
                $rval = $this->rows[$this->nrows][$this->ncol];
                for ($i = 1; $i < $this->colrepeat; $i++) {
                    $this->ncol++;
                    $this->rows[$this->nrows][$this->ncol] = $rval;
                }
            }
            $this->ncol++;
            $this->colrepeat = 0;
        }
    }
    
    protected function characterData(
    /** @noinspection PhpUnusedParameterInspection */
    $parser, $data)
    {
        
        if ($this->inrow && $this->incell) {
            $this->celldata.= preg_replace('/^\s*[\r\n]\s*$/ms', '', str_replace(self::SEPCHAR, self::ALTSEPCHAR, $data));
        }
    }
    
    protected function seemsODS($filename)
    {
        if (preg_match('/\.ods$/', $filename)) return true;
        $sys = trim(shell_exec(sprintf("file -bi %s", escapeshellarg($filename))));
        if ($sys == "application/x-zip") return true;
        if ($sys == "application/zip") return true;
        if ($sys == "application/vnd.oasis.opendocument.spreadsheet") return true;
        return false;
    }
}

