<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

require_once "xgettextCommon.php";
require_once "ods2csv.php";

class xgettextFamily extends xgettextCommon
{
    protected $outputFile = "messages";
    protected $inputFiles = array();
    protected $outputFiles = array();
    
    public static function getUsage()
    {
        global $argv;
        return sprintf("%s extract tranlation from family structure files\n
                %s -o <output directory> <input file> [<input file>...]\n
                If option has '-f-', files from stdin are used\n", $argv[0], $argv[0]);
    }
    /**
     * extractPOFromCSV from a CSV file and print it on standard output
     *
     * @param  string $fi file input path
     * @param string $podir Po directory
     * @return void
     */
    protected function extractPOFromCSV($fi, $podir)
    {
        $fdoc = fopen($fi, "r");
        if (!$fdoc) {
            throw new Exception("fam2po: Can't access file [$fi]");
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
                
                $data[0] = trim($this->getArrayIndexValue($data, 0));
                switch ($data[0]) {
                    case "BEGIN":
                        $famname = $this->getArrayIndexValue($data, 5);
                        $famtitle = $this->getArrayIndexValue($data, 2);
                        if ($famname) {
                            $oFileName = $podir . "/" . $famname . ".pot";
                            $this->outputFiles[] = $oFileName;
                            $podoc = fopen($oFileName, "w+");
                        } else {
                            $podoc = null;
                        }
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
                            throw new Exception("xgettextFamily: Can't create tempory family po file [$podir/$famname.pot]");
                        } else {
                            fwrite($podoc, $contentToWrite);
                            fclose($podoc);
                        }
                        $famname = "*******";
                        break;

                    case "ORDER":
                        $type = $this->getArrayIndexValue($data, 1);
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
                        $type = $this->getArrayIndexValue($data, 1);
                        if ($type === "CVDOC") {
                            $cvName = $this->getArrayIndexValue($data, 2);
                            if ($cvName && !is_numeric($cvName) && $cv_idview_index) {
                                $oFileName = $podir . "/" . $cvName . ".pot";
                                $cvdoc = fopen($oFileName, "w+");
                                
                                $this->outputFiles[] = $oFileName;
                                if (!$cvdoc) {
                                    throw new Exception("fam2po: Can't create tempory CV po file [$podir/$cvName.pot]");
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
                                $tcv_idview = explode('\n', $this->getArrayIndexValue($data, $cv_idview_index));
                                $tcv_lview = explode('\n', $this->getArrayIndexValue($data, $cv_lview_index));
                                $tcv_menu = explode('\n', $this->getArrayIndexValue($data, $cv_menu_index));
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
                        $contentToWrite.= "msgid \"" . $famname . "#" . strtolower($this->getArrayIndexValue($data, 1)) . "\"\n";
                        $contentToWrite.= "msgstr \"" . $this->getArrayIndexValue($data, 3) . "\"\n\n";
                        // Enum ----------------------------------------------
                        $type = $this->getArrayIndexValue($data, 6);
                        if ($type == "enum" || $type == "enumlist") {
                            $d = str_replace('\,', '\#', $this->getArrayIndexValue($data, 12));
                            $tenum = explode(",", $d);
                            foreach ($tenum as $ve) {
                                $d = str_replace('\#', ',', $ve);
                                $enumValues = explode("|", $d);
                                $contentToWrite.= "#, fuzzy, ($fi)\n";
                                $contentToWrite.= "msgid \"" . $famname . "#" . strtolower($this->getArrayIndexValue($data, 1)) . "#" . (str_replace('\\', '', $this->getArrayIndexValue($enumValues, 0))) . "\"\n";
                                $contentToWrite.= "msgstr \"" . (str_replace('\\', '', $this->getArrayIndexValue($enumValues, 1))) . "\"\n\n";
                            }
                        }
                        // Options ----------------------------------------------
                        $options = $this->getArrayIndexValue($data, 15);
                        $options = explode("|", $options);
                        foreach ($options as $currentOption) {
                            $currentOption = explode("=", $currentOption);
                            $currentOptionKey = $this->getArrayIndexValue($currentOption, 0);
                            $currentOptionValue = $this->getArrayIndexValue($currentOption, 1);
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
                                    $contentToWrite.= "msgid \"" . $famname . "#" . strtolower($this->getArrayIndexValue($data, 1)) . "#" . strtolower($currentOptionKey) . "\"\n";
                                    $contentToWrite.= "msgstr \"" . $currentOptionValue . "\"\n\n";
                            }
                        }
                    }
                }
            }
        }
        
        protected function getArrayIndexValue(&$array, $index)
        {
            return isset($array[$index]) ? $array[$index] : "";
        }
        
        public function getOutputFile()
        {
            return implode(", ", $this->outputFiles);
        }
        
        public function extract()
        {
            if (!is_dir($this->outputFile)) {
                throw new Exception(sprintf('output option "%s" must be a directory', $this->outputFile));
            }
            
            $convert = new Ods2Csv();
            foreach ($this->inputFiles as $familyFile) {
                if (empty($familyFile)) {
                    continue;
                }
                $err = "";
                try {
                    $familyFilePathInfo = pathinfo($familyFile);
                    if (file_exists($familyFile)) {
                        if (isset($familyFilePathInfo['extension'])) {
                            switch ($familyFilePathInfo['extension']) {
                                case 'ods':
                                    $csvfile = $familyFile . ".csv";
                                    $convert->convertOds2csv($familyFile, $csvfile);
                                    if (file_exists($csvfile)) {
                                        $this->extractPOFromCSV($csvfile, $this->outputFile);
                                    } else {
                                        throw new Exception("Unable to generate CSV from " . $familyFile);
                                    }
                                    unlink($csvfile);
                                    break;

                                case 'csv':
                                    $this->extractPOFromCSV($familyFile, $this->outputFile);
                                    break;

                                default:
                                    $err = ($familyFile . " has an unknown extension, skipping it.");
                            }
                        } else {
                            $err = ($familyFile . " has no extension, skipping it.");
                        }
                    } else {
                        throw new Exception("Can't access file " . $familyFile);
                    }
                }
                catch(Exception $e) {
                    $err.= $e->getMessage() . " " . $e->getFile() . " line (" . $e->getLine() . ")\n";
                }
                if ($err) {
                    throw new Exception($err);
                }
            }
        }
    }
    
    