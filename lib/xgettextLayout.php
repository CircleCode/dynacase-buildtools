<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

require_once "xgettextCommon.php";
class xgettextLayout extends xgettextCommon
{
    
    public function extract()
    {
        $potFile = $this->outputFile;
        $phpFile = $potFile . "_.php";
        file_put_contents($phpFile, '<?php' . "\n");
        foreach ($this->inputFiles as $layoutFile) {
            $layoutFile = trim($layoutFile);
            if (!$layoutFile) {
                continue;
            }
            $ext = '';
            if (strrpos($layoutFile, '.') > 0) {
                $ext = substr($layoutFile, strrpos($layoutFile, '.') + 1);
            }
            if ($ext == 'odt') {
                $tmpDir = $temp_file = sprintf("%s/%s", sys_get_temp_dir() , uniqid('po'));
                $cmd = sprintf('mkdir %s && unzip -d "%s" "%s" content.xml meta.xml && cat %s/meta.xml >> %s/content.xml', $tmpDir, $tmpDir, $layoutFile, $tmpDir, $tmpDir);
                self::mySystem($cmd);
                $layoutFile = sprintf("%s/content.xml", $tmpDir);
            } else {
                $tmpDir = '';
            }
            
            $cmd = sprintf('perl -ne \'print "\$a=gettext(\\"$1\\");\n" while(m/\[TEXT:([^]]+)]/g)\' %s >> %s', $layoutFile, $phpFile);
            self::mySystem($cmd);
            $cmd = sprintf('perl -ne \'print "\$a=pgettext(\\"$2\\", \\"$1\\");\n" while(m/\[TEXT\((.+)\):([^]]+)]/g)\' %s >> %s', $layoutFile, $phpFile);
            self::mySystem($cmd);
            
            if ($tmpDir) {
                $cmd = sprintf('\rm -r "%s"', $tmpDir);
                self::mySystem($cmd);
            }
        }
        
        $cmd = sprintf('xgettext \
            --language=PHP \
            --sort-output \
            --from-code=utf-8 \
            --no-location \
            --keyword=pgettext:1,2c %s -o %s %s \
            && rm %s', $this->getXoptions() , $potFile, $phpFile, $phpFile);
        
        self::mySystem($cmd);
    }
}

