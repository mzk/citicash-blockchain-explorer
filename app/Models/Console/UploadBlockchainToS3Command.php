<?php declare(strict_types = 1);

namespace App\Models\Console;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Tracy\Debugger;

class UploadBlockchainToS3Command extends BaseCommand
{

	use LockableTrait;

	/**
	 * @var string
	 */
	private $s3Key;

	/**
	 * @var string
	 */
	private $s3Secret;

	/**
	 * @var string
	 */
	private $citicashIoServer;

	/**
	 * @var OutputInterface
	 */
	private $output;

	/**
	 * @var string
	 */
	private $outputBlockchainFileName;

	public function __construct(string $s3Key, string $s3Secret, string $citicashIoServer, string $outputBlockchainFileName)
	{
		parent::__construct(null);
		$this->s3Key = $s3Key;
		$this->s3Secret = $s3Secret;
		$this->citicashIoServer = $citicashIoServer;
		$this->outputBlockchainFileName = $outputBlockchainFileName;
	}

	protected function configure(): void
	{
		parent::configure();
		$this->setName('upload-to-s3');
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');

			return 1;
		}

		$this->output = $output;

		$command = \sprintf('/home/ubuntu/mounted2/citicash-blockchain-export --data-dir /home/ubuntu/mounted2/.citicash --output-file %s', $this->outputBlockchainFileName);
		$this->runProcess($command);

		$md5 = \hash_file('md5', $this->outputBlockchainFileName);
		$sha256 = \hash_file('sha256', $this->outputBlockchainFileName);
		$output->writeln(\sprintf('computed md5 is %s', $md5));
		$output->writeln(\sprintf('computed sha256 is %s', $sha256));

		$result = \file_put_contents('/var/www/blockchain-explorer/www/blockchain.raw.md5sum.txt', $md5 . '  /home/ubuntu/mounted2/blockchain.raw.tmp'); //tmp fix

		if ($result === false) {
			$output->writeln('fail in write md5sum');

			return 1;
		}

		$s3Client = new S3Client(
			[
				'credentials' => [
					'key' => $this->s3Key,
					'secret' => $this->s3Secret,
				],
				'region' => 'eu-west-2',
				'version' => 'latest',
			]
		);

		$uploader = new MultipartUploader($s3Client, $this->outputBlockchainFileName, [
			'bucket' => 'citicashblockchain',
			'key' => 'blockchain.raw',
			'Content-MD5' => \base64_encode($md5),
			'SourceFile' => $this->outputBlockchainFileName,
			'ContentSHA256' => $sha256,
			'Metadata' => [
				'Content-MD5' => \base64_encode($md5),
			],
		]);

		try {
			$result = $uploader->upload();
			$output->writeln(\sprintf('Upload complete: %s', $result['ObjectURL']));

			$output->writeln('scp');
			$copyToAnotherCommand = \sprintf('scp /var/www/blockchain-explorer/www/blockchain.raw.md5sum.txt %s:/home/ubuntu/blockchain.raw.md5sum.txt', $this->citicashIoServer);
			$this->runProcess($copyToAnotherCommand);

		} catch (MultipartUploadException $e) {
			Debugger::log($e);
			$output->writeln($e->getMessage());
		}

		$this->release();

		return 0;
	}

	private function runProcess(string $command): void
	{
		$this->output->writeln($command);
		$process = Process::fromShellCommandline($command);
		$process->setTimeout(120);
		$process->run();

		if ($process->getExitCode() !== 0) {
			$this->output->writeln($process->getErrorOutput());

			die($process->getExitCode());
		}
	}
}
