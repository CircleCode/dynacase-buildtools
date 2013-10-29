<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

require_once "xgettextCommon.php";
class xgettextPhp extends xgettextCommon
{
    
    protected function extractSearchLabel($inputFile)
    {
        $tokenFile = token_get_all(file_get_contents($inputFile));
        $filteredLabel = preg_filter("/.*@(searchLabel)\\s+([^\\n]+)\\n.*/s", "\\2", array_map(function ($t)
        {
            return $t[1];
        }
        , array_filter($tokenFile, function ($t)
        {
            return ($t[0] == T_DOC_COMMENT);
        })));
        return $filteredLabel;
    }
    
    public function extract()
    {
        $potFile = $this->outputFile;
        $phpFile = $potFile . "_searchlabel_.php";
        $searchLabel = array();
        foreach ($this->inputFiles as $k => $phpInputFile) {
            $phpInputFile = trim($phpInputFile);
            if (!$phpInputFile) {
                unset($this->inputFiles[$k]);
            }
            $labels = $this->extractSearchLabel($phpInputFile);
            $searchLabel = array_merge($searchLabel, $labels);
        }
        $searchPhp = "<?php\n";
        foreach ($searchLabel as $label) {
            $searchPhp.= sprintf("\n// _COMMENT Search Label\n");
            $searchPhp.= sprintf('$a=_("%s");', preg_replace('/"/', '\"', $label));
        }
        file_put_contents($phpFile, $searchPhp);
        $cmd = sprintf('xgettext \
              --language=PHP \
              --sort-output \
              --from-code=utf-8 \
              --no-location \
              --add-comments=_COMMENT \
              --keyword=___:1 \
              --keyword=___:1,2c \
              --keyword=n___:1,2 \
              --keyword=pgettext:1,2 \
              --keyword=n___:1,2,4c \
              --keyword=npgettext:1,2,4c \
              --keyword="N_"  \
              --keyword="text"  \
              --keyword="Text" \
             %s -o %s %s "%s" \
            && rm  "%s"', $this->getXoptions() , $potFile, '"' . implode('" "', $this->inputFiles) . '"', $phpFile, $phpFile);
        
        self::mySystem($cmd);
    }
}

