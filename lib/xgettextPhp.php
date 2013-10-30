<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

require_once "xgettextCommon.php";
class xgettextPhp extends xgettextCommon
{
    protected $tokenFile = array();
    protected function extractExtraLabel($inputFile)
    {
        $this->tokenFile = token_get_all(file_get_contents($inputFile));
        // extract searchLabel comment
        $filteredLabel = preg_filter("/.*@(searchLabel)\\s+([^\\n]+)\\n.*/s", "\\2", array_map(function ($t)
        {
            return $t[1];
        }
        , array_filter($this->tokenFile, function ($t)
        {
            return ($t[0] === T_DOC_COMMENT);
        })));
        
        return $filteredLabel;
    }
    protected function extractSharp()
    {
        // extract searchLabel comment
        $filteredLabel = array();
        // extract sharp comment
        $filteredSharp = array_filter($this->tokenFile, function ($t)
        {
            return ($t[0] === T_COMMENT && $t[1][0] === '#');
        });
        foreach ($filteredSharp as $sharpComment) {
            $sharpComment[1][0] = ' ';
            if (preg_match_all('/\sN?_\("([^\)]+)"\)/', $sharpComment[1], $matches)) {
                foreach ($matches[1] as $m) {
                    $filteredLabel[] = $m;
                }
            }
        }
        return $filteredLabel;
    }
    
    public function extract()
    {
        $potFile = $this->outputFile;
        $phpFile = $potFile . "_searchlabel_.php";
        $searchLabel = array();
        $sharpLabel = array();
        foreach ($this->inputFiles as $k => $phpInputFile) {
            $phpInputFile = trim($phpInputFile);
            if (!$phpInputFile) {
                unset($this->inputFiles[$k]);
                continue;
            }
            $labels = $this->extractExtraLabel($phpInputFile);
            $searchLabel = array_merge($searchLabel, $labels);
            $labels = $this->extractSharp();
            $sharpLabel = array_merge($sharpLabel, $labels);
        }
        $searchPhp = "<?php\n";
        foreach ($searchLabel as $label) {
            $searchPhp.= sprintf("\n// _COMMENT Search Label\n");
            $searchPhp.= sprintf('$a=_("%s");', preg_replace('/"/', '\"', $label));
        }
        foreach ($sharpLabel as $label) {
            $searchPhp.= sprintf("\n// _COMMENT Sharp Label\n");
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

