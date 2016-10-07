<?php
/**
 * Active Publishing
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2014-2016 Active Publishing http://www.activepublishing.fr
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License version 3 (GPLv3)
 */
namespace ActiveWireframe\Pimcore\Console\Command;

use ActiveWireframe\Pimcore\Web2Print\Processor;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Web2PrintPdfCreationCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('web2printActivePublishing:pdf-creation')
            ->setDescription('Start pdf creation')
            ->addOption(
                'processId', 'p',
                InputOption::VALUE_REQUIRED,
                "process-id with pdf creation definitions"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Processor::getInstance()->startPdfGeneration($input->getOption("processId"));
    }
}
