<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make',
    description: 'Scaffold app artifacts (controller, model, migration, portal, …)',
    aliases: ['make:scaffold'],
)]
final class MakeCommand extends Command
{
    use RunsForApp;

    private const TYPES = [
        'controller',
        'model',
        'migration',
        'patch',
        'portal',
        'form-request',
        'seeder',
        'test',
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Artifact type: ' . implode(', ', self::TYPES))
            ->addArgument('name', InputArgument::REQUIRED, 'Class or file name')
            ->addOption('service', 's', InputOption::VALUE_REQUIRED, 'Portal service class (portal only)')
            ->addOption('unit', 'u', InputOption::VALUE_NONE, 'Create unit test (test only)')
            ->addOption('feature', null, InputOption::VALUE_NONE, 'Create feature test (test only)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing test file (test only)')
            ->setHelp(
                <<<'HELP'
Create files inside the current single-app project (no package argument needed).

Examples:
  pinx make controller ProductController
  pinx make model ProductModel
  pinx make migration create_products_table
  pinx make patch fix_user_roles
  pinx make portal ShopService
  pinx make form-request StoreProductRequest
  pinx make seeder DemoSeeder
  pinx make test ProductTest --feature
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $type = strtolower((string) $input->getArgument('type'));
        $name = $this->normalizeName($type, (string) $input->getArgument('name'));

        if (!in_array($type, self::TYPES, true)) {
            $io->error('Unknown type "' . $type . '". Use: ' . implode(', ', self::TYPES));

            return Command::INVALID;
        }

        $args = $this->buildPincoreArgs($context, $type, $name, $input);

        $io->note('Creating ' . $type . ' for ' . $context->package);

        return $this->runPincore($context, $args, $output);
    }

    /**
     * @return list<string>
     */
    private function buildPincoreArgs(AppContext $context, string $type, string $name, InputInterface $input): array
    {
        return match ($type) {
            'controller' => ['controller:create', $name, $context->package],
            'model' => ['model:create', $name, $context->package],
            'migration' => ['migrate:create', $name, $context->package],
            'patch' => ['patch:create', $name, $context->package],
            'portal' => $this->portalArgs($context, $name, $input),
            'form-request' => ['form-request:create', $name, $context->package],
            'seeder' => ['seeder:create', $name, $context->package],
            'test' => $this->testArgs($context, $name, $input),
        };
    }

    /**
     * @return list<string>
     */
    private function portalArgs(AppContext $context, string $name, InputInterface $input): array
    {
        $args = ['portal:create', $name, '-p', $context->package];
        $service = $input->getOption('service');

        if (is_string($service) && $service !== '') {
            $args[] = '--service=' . $service;
        }

        return $args;
    }

    /**
     * @return list<string>
     */
    private function testArgs(AppContext $context, string $name, InputInterface $input): array
    {
        return array_merge(
            ['test:create', $name, $context->package],
            $this->forwardOptions($input, ['unit', 'feature', 'force']),
        );
    }

    private function normalizeName(string $type, string $name): string
    {
        $name = trim($name);

        return match ($type) {
            'controller' => $this->stripSuffix($name, 'Controller'),
            'model' => $this->stripSuffix($name, 'Model'),
            'portal' => $name,
            'form-request' => $this->stripSuffix($name, 'Request'),
            'seeder' => $this->stripSuffix($name, 'Seeder'),
            'test' => $this->stripSuffix($name, 'Test'),
            default => $name,
        };
    }

    private function stripSuffix(string $name, string $suffix): string
    {
        if (str_ends_with($name, $suffix) && strlen($name) > strlen($suffix)) {
            return substr($name, 0, -strlen($suffix));
        }

        return $name;
    }
}
