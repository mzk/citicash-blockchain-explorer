<?php declare(strict_types = 1);

namespace App\Models\Console;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class UploadBlockchainToS3Command extends Command
{

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

	public function __construct(string $s3Key, string $s3Secret, string $citicashIoServer)
	{
		parent::__construct(null);
		$this->s3Key = $s3Key;
		$this->s3Secret = $s3Secret;
		$this->citicashIoServer = $citicashIoServer;
	}

	protected function configure(): void
	{
		parent::configure();
		$this->setName('upload-to-s3');
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
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

		$command = '/home/ubuntu/mounted2/citicash-blockchain-export --data-dir /home/ubuntu/mounted2/.citicash --output-file /home/ubuntu/mounted2/blockchain.raw.tmp';
		$output->writeln($command);
		$process = Process::fromShellCommandline($command);
		$process->run();

		$output->writeln('md5sum');
		$md5sumProcess = Process::fromShellCommandline('md5sum /home/ubuntu/mounted2/blockchain.raw.tmp > /home/ubuntu/mounted2/blockchain.raw.md5sum.txt');
		$md5sumProcess->run();

		$uploader = new MultipartUploader($s3Client, '/home/ubuntu/mounted2/blockchain.raw.tmp', [
			'bucket' => 'citicashblockchain',
			'key' => 'blockchain.raw',
		]);

		try {
			$result = $uploader->upload();
			$output->writeln(\sprintf('Upload complete: %s', $result['ObjectURL']));
		} catch (MultipartUploadException $e) {
			$output->writeln($e->getMessage());
		}

		$output->writeln('scp');
		$copyToAnother = \sprintf('scp /home/ubuntu/mounted2/blockchain.raw.md5sum.txt %s:/home/ubuntu/blockchain.raw.md5sum.txt', $this->citicashIoServer);
		$copyToAnotherProcess = Process::fromShellCommandline($copyToAnother);
		$copyToAnotherProcess->run();

		return 0;
	}
}