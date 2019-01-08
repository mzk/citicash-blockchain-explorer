<?php declare(strict_types = 1);

namespace App\Models;

use App\Models\Exception\RpcDaemonDownException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Nette\Application\BadRequestException;
use Nette\InvalidStateException;
use Nette\Utils\Json;
use stdClass;

class RpcDaemon
{

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var int
	 */
	private $port;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var ?resource
	 */
	private $curl;

	/**
	 * @var string[]
	 */
	private $requestsCount = [];

	public function __construct(string $host, int $port)
	{
		$this->host = $host;
		$this->port = $port;
		//$handler = new CurlHandler();
		$handler = new StreamHandler();
		$stack = HandlerStack::create($handler);
		$this->client = new Client(
			[
				'base_uri' => $this->host . ':' . $this->port,
				'timeout' => 15.0,
				'handler' => $stack,
			]
		);
	}

	public function getInfo(): InfoData
	{
		$body = [
			'method' => 'get_info',
		];

		$response = $this->getResponse('/json_rpc', $body);

		return InfoData::fromResponse($response);
	}

	public function getHeight(): int
	{
		$body = [
			'method' => 'getblockcount',
		];
		$response = $this->getResponse('/json_rpc', $body);

		return (int)$response->result->count;
	}

	public function getBlockByHeight(int $height): BlockData
	{
		$body = [
			'method' => 'getblock',
			'params' => [
				'height' => $height,
			],
		];

		$response = $this->getResponse('/json_rpc', $body);

		return BlockData::fromResponse($response);
	}

	/**
	 * @deprecated fees are in the `getBlockByHeight`
	 */
	public function getCoinbaseTxSum(int $height): CoinbaseTxSum
	{
		$body = [
			'method' => 'get_coinbase_tx_sum',
			'params' => [
				'height' => $height,
				'count' => 1,
			],
		];

		$response = $this->getResponse('/json_rpc', $body);

		return CoinbaseTxSum::fromResponse($response);
	}

	public function getBlockByHash(string $hash): BlockData
	{
		$body = [
			'method' => 'getblock',
			'params' => [
				'hash' => $hash,
			],
		];

		$response = $this->getResponse('/json_rpc', $body);

		return BlockData::fromResponse($response);
	}

	public function getTransactionPool(): TransactionsPoolData
	{
		$body = [
			'decode_as_json' => true,
		];
		$response = $this->getResponse('/get_transaction_pool', $body);

		return new TransactionsPoolData($response);
	}

	/**
	 * @return BlockData[]
	 */
	public function getBlocksByHeight(int $heightStart, int $limit): array
	{
		$response = [];

		for ($i = 0; $i < $limit; $i++) {
			$actualHeightPointer = $heightStart - $i;

			if ($actualHeightPointer < 1) {
				break;
			}

			$response[$actualHeightPointer] = $this->getBlockByHeight($actualHeightPointer);
		}

		return $response;
	}

	/**
	 * @param string[] $transactions
	 * @return TransactionData[]
	 * @throws BadRequestException
	 */
	public function getTransactions(array $transactions, ?string $viewKey = null): array
	{
		$body = [
			'txs_hashes' => $transactions,
			'decode_as_json' => true,
		];

		if ($viewKey !== null) {
			$body['txs_view_key'] = $viewKey;
		}

		$response = $this->getResponse('/gettransactions', $body);

		if (isset($response->missed_tx)) {
			throw new BadRequestException('Missed_tx');
		}

		if (isset($response->status) && ($response->status === 'Failed to parse hex representation of transaction hash')) {
			throw new BadRequestException($response->status);
		}

		if (isset($response->status) && ($response->status !== 'OK')) {
			throw new BadRequestException($response->status);
		}

		$return = [];

		foreach ($response->txs as $tx) {
			$return[] = TransactionData::fromResponse($tx);
		}

		return $return;
	}

	/**
	 * @param string $path
	 * @param mixed[] $body
	 * @return stdClass
	 * @throws BadRequestException
	 */
	private function getResponse(string $path, array $body): stdClass
	{
		//$response = $this->getResponseOld($path, $body);
		$response = $this->getResponseModern($path, $body);

		$this->requestsCount[] = $path;

		return $response;
	}

	/**
	 * @param string $path
	 * @param mixed[] $body
	 * @return stdClass
	 * @throws BadRequestException
	 * @throws InvalidStateException
	 */
	protected function getResponseOld(string $path, array $body): stdClass
	{
		if ($this->curl === null) {
			$this->curl = \curl_init();
		}

		if (\is_resource($this->curl) === false) {
			throw new InvalidStateException('Failed curl_init');
		}

		$curl = $this->curl;

		$body = Json::encode($body);
		$options = [
			'body' => $body,
			'debug' => false,
			'allow_redirects' => false,
			'synchronous' => false,
			//'version' => '2.0', fail on aws
			'curl.options' => [
				\CURLOPT_PORT => $this->port,
				\CURLOPT_URL => $this->host . $path,
				\CURLOPT_RETURNTRANSFER => true,
				\CURLOPT_ENCODING => '',
				\CURLOPT_MAXREDIRS => 0,
				\CURLOPT_TIMEOUT => 30,
				\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1,
				\CURLOPT_CUSTOMREQUEST => 'GET',
				\CURLOPT_POSTFIELDS => $body,
			],
		];

		\curl_setopt_array($curl, $options['curl.options']);

		$response = \curl_exec($curl);
		$err = \curl_error($curl);

		if ($err !== '') {
			throw new BadRequestException($err);
		}

		$responseJson = Json::decode($response);

		if (isset($responseJson->error) && isset($responseJson->error->message)) {
			throw new BadRequestException($responseJson->error->message);
		}

		return $responseJson;
	}

	/**
	 * @return string[]
	 */
	public function getRequestsCount(): array
	{
		return $this->requestsCount;
	}

	/**
	 * @param string $path
	 * @param mixed[] $body
	 * @return stdClass
	 * @throws BadRequestException
	 */
	private function getResponseModern(string $path, array $body): stdClass
	{
		$body = Json::encode($body);
		$options = [
			'body' => $body,
			'debug' => false,
			'allow_redirects' => false,
			'synchronous' => false,
			//'version' => '2.0', fail on aws
			'curl.options' => [
				\CURLOPT_RETURNTRANSFER => true,
				\CURLOPT_ENCODING => '',
				\CURLOPT_MAXREDIRS => 0,
				\CURLOPT_TIMEOUT => 30,
				\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1,
				\CURLOPT_CUSTOMREQUEST => 'GET',
				\CURLOPT_POSTFIELDS => $body,
				\CURLOPT_FORBID_REUSE => false,
				\CURLOPT_FRESH_CONNECT => false,
			],
		];

		try {
			$response = $this->client->request('GET', $path, $options);
		} catch (RequestException $e) {
			throw new RpcDaemonDownException($e->getMessage(), $e->getCode(), $e);
		}
		$responseJson = Json::decode($response->getBody()->getContents());

		if (isset($responseJson->error) && isset($responseJson->error->message)) {
			throw new BadRequestException($responseJson->error->message);
		}

		return $responseJson;
	}

	public function __destruct()
	{
		if (\is_resource($this->curl) === true) {
			\curl_close($this->curl);
		}
	}
}
