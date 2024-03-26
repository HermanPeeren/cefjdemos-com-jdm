<?php

/**
 * @package     Jdocmanual
 * @subpackage  Cli
 *
 * @copyright   Copyright (C) 2003 Clifford E Ford. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Cefjdemos\Component\Jdocmanual\Administrator\Cli;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('JPATH_PLATFORM') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Build the Joomla help html files used by the proxy server.
 *
 * @since  1.0.0
 */
class Buildproxy
{
    /**
     * Path to local source of markdown files.
     *
     * @var     string
     * @since   1.0.0
     */
    protected $gfmfiles_path;

    /**
     * Regex pattern to select first GFM H1 (#) string.
     *
     * @var     string;
     * @since   1.0.0
     */
    protected $pattern1 = '/\n[#]([\w| ].*)/m';

    /**
     * Regex pattern to select Display title from GFM comment string.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $pattern2 = '/<!-- Filename:.*Display title:(.*)? -->/m';

    /**
     * Path fragment of manual to process.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $manualtodo;

    /**
     * Path fragment of language to process.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $languagetodo;

    /**
     * The top section of a Help page.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $top;

    /**
     * The bottom section of a Help page.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $bottom;

    /**
     * Content of menu index file.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $tmp;

    /**
     * Accumulate a summary to return to caller.
     *
     * @var     string
     * @since  1.0.0
     */
    protected $summary = '';

    /**
     * Entry point to convert md to html and save.
     *
     * @param   $manual     The path fragment of the manual to process.
     * @param   $language   The path fragment of the language to process.
     *
     * @return  $string     A message reporting the outcome.
     *
     * @since   1.0.0
     */
    public function go($manual, $language)
    {
        $time_start = microtime(true);

        $this->manualtodo = 'help';
        $this->languagetodo = $language;

        $memlimit = ini_get('memory_limit');
        ini_set("memory_limit", "2048M");
        $this->build();

        //ini_set("memory_limit", $memlimit);
        // Warning: Failed to set memory limit to 536870912 bytes
        //(Current memory usage is 1470103552 bytes) in [redacted]/Buildhtml.php on line 139

        $time_end = microtime(true);
        $execution_time = $time_end - $time_start;

        $this->summary.= 'Total Execution Time: ' . number_format($execution_time, 2) . ' Seconds' . "\n\n";
        return $this->summary;
    }

    /**
     * Cycle over manuals and languages to convert txt to html and save.
     * Skip all manuals except help.
     *
     * @return  $string     A message reporting the outcome.
     *
     * @since   1.0.0
     */
    protected function build()
    {
        // Get the data source path from the component parameters
        $this->gfmfiles_path = ComponentHelper::getComponent('com_jdocmanual')->getParams()->get('gfmfiles_path', ',');
        if (empty($this->gfmfiles_path)) {
            return "\nThe Markdown source could not be found: {$this->gfmfiles_path}. Set in Jdocmanual configuration.\n";
        }
        $this->settop();
        $this->setbottom();
        $counts = $this->html4lingo('help');
        foreach ($counts as $key=>$count) {
            $this->summary .= 'Language: ' . $key . ' Count: ' . $count . "\n";
        }
        // Copy the proxy files from components to to root
        $src = JPATH_ROOT . '/components/com_jdocmanual/proxy/';
        $dst = JPATH_ROOT . '/proxy/';
        File::copy($src . 'help.css', $dst . 'help.css');
        File::copy($src . 'index.php', $dst . 'index.php');

        return;
    }

    /**
     * Convert help md files to html and save.
     *
     * @param   $manual     The path fragment of the manual to process.
     * @param   $language   The path fragment of the language to process.
     *
     * @return  $int        Count of the number of files.
     *
     * @since   1.0.0
     */
    protected function html4lingo($manual)
    {
        $count = 0;
        $db = Factory::getContainer()->get('DatabaseDriver');

        // get the already converted html from the database
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('jdoc_key', 'language', 'heading', 'filename', 'display_title', 'html')))
        ->from('#__jdm_articles')
        ->where($db->quoteName('manual') . ' = ' . $db->quote('help'))
        ->order($db->quoteName(array('language', 'heading', 'filename')));
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        $key_index = "<?php\n\$key_index = [\n";

        $counts = [];
        foreach ($rows as $row) {
            $outfile = str_replace('.md', '.html', $row->filename);
            $html = $this->top;
            $html .= '<h1>' . $row->display_title . '</h1>';
            $html .= $row->html;
            $html .= $this->bottom;
            if (!is_dir(JPATH_ROOT . '/proxy/' . $row->language . '/' . $row->heading)) {
                mkdir(JPATH_ROOT . '/proxy/' . $row->language . '/' . $row->heading, 0755, true);
            }
            File::write(JPATH_ROOT . '/proxy/' . $row->language . '/' . $row->heading . '/' . $outfile, $html);
            if (isset($counts[$row->language])) {
                $counts[$row->language] += 1;
            } else {
                $counts[$row->language] = 1;
            }
            if ($row->language == 'en') {
                // A jdoc_key may contain single quotes
                $key = str_replace("'", "\'", $row->jdoc_key);
                $filename = str_replace('.md', '.html', $row->filename);
                $key_index .= "    '{$key}' => '{$row->heading}/{$filename}',\n";
            }
            // for testing - do one file from each language
            // return $counts;
        }

        // Need a final dummy key_index entry without a comma.
        $key_index .= "    'dummy' => 'dummy'\n];\n";

        // Write a new key-index.php file.
        File::write(JPATH_ROOT . '/proxy/key-index.php', $key_index);

        return $counts;
    }

    /**
     * Set the top part of a Help page.
     *
     * @return  $html   The required html code.
     *
     * @since   1.0.0
     */
    protected function settop()
    {
        $this->top = <<<EOF
<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Joomla! Help Screens</title>
        <link rel="stylesheet" href="https://help.joomla.org/media/css/help.css">
        <link rel="stylesheet" href="/proxy/help.css">
    </head>
    <body>
        <main>
            <a name="Top" id="Top"></a>
EOF;
    }

    /**
     * Set the bottom part of a Help page.
     *
     * @return  $html   The required html code.
     *
     * @since   1.0.0
     */
    protected function setbottom()
    {
        $this->bottom = <<<EOF
            <div id="footer-wrapper">
                <div id="license">
                    License: <a href="https://docs.joomla.org/JEDL" target="_blank">
                        Joomla! Electronic Documentation License
                    </a>
                </div>
                <div id="source-page">
                    Source page:
                    <a href="https://docs.joomla.org/Help5.x:Articles/en" target="_blank">
                        https://docs.joomla.org/Help5.x:Articles/en
                    </a>
                </div>
                <div id="copyright">
                    Copyright &copy; 2023 <a href="https://www.opensourcematters.org" target="_blank">
                    Open Source Matters, Inc.</a> All rights reserved.
                </div>
            </div>
            <hr/>
            <a href="#Top">Top</a>
        </main>
    </body>
</html>
EOF;
    }
}