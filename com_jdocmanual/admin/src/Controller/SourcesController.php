<?php

/**
 * @package     Jdocmanual
 * @subpackage  Administrator
 *
 * @copyright   (C) 2023 Clifford E Ford. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Cefjdemos\Component\Jdocmanual\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Cefjdemos\Component\Jdocmanual\Administrator\Cli\Buildarticles;
use Cefjdemos\Component\Jdocmanual\Administrator\Cli\Buildmenus;
use Cefjdemos\Component\Jdocmanual\Administrator\Cli\Buildproxy;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for a single source
 *
 * @since  1.6
 */
class SourcesController extends AdminController
{
    protected $text_prefix = 'COM_JDOCMANUAL_SOURCES';

    /**
     * Update the article html for the selected manual and language.
     * This function updates all of the articles (ToDo: selected article).
     *
     * @return  $void
     *
     * @since   1.0.0
     */
    public function buildhtml()
    {
        $manual = $this->app->input->get('manual', '', 'string');
        $language = $this->app->input->get('language', '', 'string');

        if (!empty($manual)) {
            $ba = new Buildarticles();
			$summary = "Building Articles\n";
            $summary .= $ba->go($manual, $language);
            $this->app->enqueueMessage(nl2br($summary, true));

			$this->buildmenus();

			if ($manual == 'help') {
				$this->buildproxy();
			}
        }
        $this->setRedirect(Route::_('index.php?option=com_jdocmanual&view=sources', false));
    }

    /**
     * Update the menus html for the selected manual.
     *
     * @return  $void
     *
     * @since   1.0.0
     */
    public function buildmenus()
    {
		$manual = $this->app->input->get('manual', '', 'string');
        $language = $this->app->input->get('language', '', 'string');

		if (!empty($manual)) {
            $bm = new Buildmenus();
			$summary = "Building Menus\n";
            $summary .= $bm->go($manual, $language);
            $this->app->enqueueMessage(nl2br($summary, true));
        }
        $this->setRedirect(Route::_('index.php?option=com_jdocmanual&view=sources', false));
    }

    /**
     * Update the proxy html for the help manual.
     *
     * @return  $void
     *
     * @since   1.0.0
     */
    public function buildproxy()
    {
		$manual = $this->app->input->get('manual', '', 'string');
        $language = $this->app->input->get('language', '', 'string');

		$summary = "Building Proxy\n";

        if (!empty($manual) && $manual == 'help') {
            $bp = new Buildproxy();
            $summary .= $bp->go($manual, $language);
        } else {
            $summary .= 'Select the <strong>help</strong> manual to build the proxy server';
        }
        $this->app->enqueueMessage(nl2br($summary, true));
        $this->setRedirect(Route::_('index.php?option=com_jdocmanual&view=sources', false));
    }
}