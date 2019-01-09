<?php declare(strict_types = 1);

namespace App\Models\Console;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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

		$md5 = \md5_file($this->outputBlockchainFileName);
		$sha256 = \hash_file('sha256', $this->outputBlockchainFileName);
		$output->writeln(\sprintf('computed md5 is %s', $md5));
		$output->writeln(\sprintf('computed sha256 is %s', $sha256));

		$result = \file_put_contents('/home/ubuntu/mounted2/blockchain.raw.md5sum.txt', $md5);
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
			'Bucket' => 'citicashblockchain',
			'Key' => 'blockchain2.raw',
			'Content-MD5' => \base64_encode($md5),
		]);

		try {
			$result = $uploader->upload();
			$output->writeln(\sprintf('Upload complete: %s', $result['ObjectURL']));
		} catch (MultipartUploadException $e) {
			$output->writeln($e->getMessage());
		}

		$output->writeln('scp');
		$copyToAnotherCommand = \sprintf('scp /home/ubuntu/mounted2/blockchain.raw.md5sum.txt %s:/home/ubuntu/blockchain.raw.md5sum.txt', $this->citicashIoServer);
		$this->runProcess($copyToAnotherCommand);

		$this->release();

		return 0;
	}

	private function runProcess(string $command): void
	{
		$this->output->writeln($command);
		$process = Process::fromShellCommandline($command);
		$process->run();

		if ($process->getExitCode() !== 0) {
			$this->output->writeln($process->getErrorOutput());

			die($process->getExitCode());
		}
	}
}
