<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

abstract class xgettextCommon
{
    protected $outputFile = "message.pot";
    protected $inputFiles = array();
    
    public static function mySystem($cmd)
    {
        //print "$cmd\n";
        exec($cmd, $out, $var);
        if ($var != 0) {
            throw new Exception("Exec : $cmd - " . print_r($out, true));
        }
    }
    
    public static function getUsage()
    {
        global $argv;
        return sprintf("%s extract tranlation from layout files\n
            %s -o <output pot file> <input file> [<input file>...]\n
            If option has '-f-',  '-', files from stdin are used\n", $argv[0], $argv[0]);
    }
    
    abstract public function extract();
    
    protected function getXOptions()
    {
        return implode(" ", $this->options);
    }
    
    function __construct()
    {
        global $argv;
        $this->options = array_slice($argv, 1);
        $options = getopt("o:f:x:", array(
            "output:",
            "files-from:",
            "exclude-file:"
        ));
        if (isset($options["o"]) || isset($options["output"])) {
            if (!empty($options["o"])) {
                $this->outputFile = $options["o"];
            } else {
                $this->outputFile = $options["output"];
            }
        }
        
        if ((isset($options["f"]) && $options["f"] == "-") || (isset($options["files-from"]) && $options["files-from"] == "-")) {
            $sFiles = file_get_contents('php://stdin');
            $this->inputFiles = preg_split('/[\s,]+/', $sFiles);
            foreach ($this->inputFiles as $k => $file) {
                if (trim($file) == "") {
                    unset($this->inputFiles[$k]);
                }
            }
            //@todo delete -f options
            $this->options = explode(' ', preg_replace('/\s+(-f\s*-)/', '', implode(' ', $this->options)));
            $this->options = explode(' ', preg_replace('/\s+(--files-from\s*=\s*-)/', '', implode(' ', $this->options)));
        } else {
            $lastIndex = count($argv) - 1;
            $lastOptionIndex = $lastIndex;
            while ($lastIndex > 0) {
                if (substr($argv[$lastIndex], 0, 1) == '-') {
                    $lastOptionIndex = $lastIndex;
                    break;
                }
                $lastIndex--;
            }
            $lastOpt = $argv[$lastOptionIndex];
            if ($lastOpt == "-o" || $lastOpt == "-x" || $lastOpt == "-output" || $lastOpt == "-exclude-file") {
                $this->inputFiles = array_slice($argv, $lastOptionIndex + 2);
                $this->options = array_slice($argv, 1, $lastOptionIndex + 1);
            } else {
                $this->inputFiles = array_slice($argv, $lastOptionIndex + 1);
                $this->options = array_slice($argv, 1, $lastOptionIndex);
            }
        }
    }
    public function getOutputFile()
    {
        return $this->outputFile;
    }
}

