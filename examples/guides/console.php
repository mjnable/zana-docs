<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zana\Console\Command;
use Zana\Console\ConsoleKernel;
use Zana\Console\ConsoleServiceProvider;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\ScopeMetadata;
use Zana\Core\Application;
use Zana\Testing\ConsoleTester;

final class Inventory
{
    /** @var array<string, int> */
    public array $stock = ['scale' => 4, 'tape' => 0];
}

// region: command
#[AsCommand(name: 'stock:check', description: 'Show the stock of a product')]
final class StockCheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('product', InputArgument::REQUIRED)
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when out of stock');
    }

    // handle() receives services from the command's scope, plus the input, output or a SymfonyStyle.
    public function handle(Inventory $inventory, ScopeMetadata $scope, InputInterface $input, SymfonyStyle $io): int
    {
        $product = (string) $input->getArgument('product');
        $count = $inventory->stock[$product] ?? 0;
        $io->writeln("{$product}: {$count} in stock (scope: {$scope->type}:{$scope->name})");

        return $count === 0 && $input->getOption('strict') ? self::FAILURE : self::SUCCESS;
    }
}
// endregion: command

// region: register
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-console')
    ->withProviders(ConsoleServiceProvider::class)
    ->withServices(static fn (ServiceCollectionInterface $s) => $s
        ->singleton(Inventory::class)
        ->singleton(StockCheckCommand::class)->tag(ConsoleKernel::TAG, StockCheckCommand::class))
    ->build();
// endregion: register

// region: run
// In bin/zana: exit(ConsoleKernel::runApplication($app));  Here, in memory:
$console = new ConsoleTester($app->container()->get(ConsoleKernel::class));

$ok = $console->run('stock:check scale');
echo trim($ok->output()), " [exit {$ok->exitCode()}]\n";

$out = $console->run('stock:check tape --strict');
echo trim($out->output()), " [exit {$out->exitCode()}]\n";
// endregion: run
