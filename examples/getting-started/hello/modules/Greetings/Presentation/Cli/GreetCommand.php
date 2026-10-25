<?php

declare(strict_types=1);

namespace Modules\Greetings\Presentation\Cli;

use Modules\Greetings\Application\Greeter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zana\Console\Command;

#[AsCommand(name: 'greet', description: 'Print a greeting')]
final class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Who to greet');
    }

    public function handle(Greeter $greeter, InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln($greeter->greet(is_string($name) ? $name : null));

        return self::SUCCESS;
    }
}
