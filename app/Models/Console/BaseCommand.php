<?php declare(strict_types = 1);

namespace App\Models\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command
{

	/**
	 * @var float
	 */
	protected $commandStartTime;

	protected function initialize(InputInterface $input, OutputInterface $output): void
	{
		parent::initialize($input, $output); // TODO: Change the autogenerated stub
		$this->commandStartTime = \microtime(true);
	}

	protected function stillRunningInfo(OutputInterface $output): void
	{
		$memoryUsage = (int)(\memory_get_usage(true) / 1024 / 1024);
		$memoryLimit = \ini_get('memory_limit');
		$elapsedTime = \microtime(true) - $this->commandStartTime;
		$msg = \sprintf(
			'Command %s running %.3fs, memory: %s/%s.',
			$this->getName(),
			$elapsedTime,
			$memoryUsage,
			$memoryLimit
		);
		$output->writeln('<info>' . $msg . '</info>');
	}

	public function run(InputInterface $input, OutputInterface $output): int
	{
		$code = parent::run($input, $output);

		$output->writeln('');
		$arguments = \implode(' ', \array_slice($_SERVER['argv'], 2));

		$memoryUsage = (int)(\memory_get_usage(true) / 1024 / 1024);
		$peakMemoryUsage = (int)(\memory_get_peak_usage(true) / 1024 / 1024);
		$memoryLimit = \ini_get('memory_limit');
		$elapsedTime = \microtime(true) - $this->commandStartTime;
		$msg = \sprintf(
			'Command %s %s finished in %.3fs, memory: %s/%s. Peak: %sM',
			$this->getName(),
			$arguments,
			$elapsedTime,
			$memoryUsage,
			$memoryLimit,
			$peakMemoryUsage
		);
		$output->writeln('<info>' . $msg . '</info>');

		return $code;
	}
}