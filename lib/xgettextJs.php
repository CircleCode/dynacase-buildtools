<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

require_once "xgettextCommon.php";
class xgettextJs extends xgettextCommon
{
    public function extract()
    {
        $potFile = $this->outputFile;

        if(count($this->inputFiles)){
            $cmd = sprintf('xgettext \
                  --language=c \
                  --sort-output \
                  --from-code=utf-8 \
                  --no-location \
                  --add-comments=_COMMENT \
                  --keyword=_:1 \
                 %s -o %s %s ', $this->getXoptions() , $potFile, '"' . implode('" "', $this->inputFiles) . '"');
            
            self::mySystem($cmd);
        }
    }
}

