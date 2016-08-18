<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
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
        ini_set('memory_limit', '2048M');
        $processor = Processor::getInstance();
        $processor->setOptionsCatalogs($input->getOption("processId"));
        $processor->startPdfGeneration($input->getOption("processId"));
    }
}
