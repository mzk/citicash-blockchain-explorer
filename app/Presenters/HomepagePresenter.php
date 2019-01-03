<?php declare(strict_types = 1);

namespace App\Presenters;

use App\Forms\ViewKeyFormFactory;
use App\Models\RedisStorageService;
use App\Models\RpcDaemon;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Form;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Caching\Cache;
use Nette\Utils\Paginator;

/**
 * @property-read Template $template
 */
class HomepagePresenter extends BasePresenter
{

	private const ITEMS_PER_PAGE = 30;

	/**
	 * @var RpcDaemon
	 */
	private $rpcDaemon;

	/**
	 * @var ViewKeyFormFactory
	 */
	private $viewKeyFormFactory;

	/**
	 * @var RedisStorageService
	 */
	private $redisStorageService;

	public function __construct(RpcDaemon $rpcDaemon, ViewKeyFormFactory $viewKeyFormFactory, RedisStorageService $redisStorageService)
	{
		parent::__construct();
		$this->rpcDaemon = $rpcDaemon;
		$this->viewKeyFormFactory = $viewKeyFormFactory;
		$this->redisStorageService = $redisStorageService;
	}

	public function beforeRender(): void
	{
		$infoData = $this->rpcDaemon->getInfo();
		$this->template->info = $infoData;
		$settings = $this->context->getParameters()['settings'];
		$this->template->linkToCitiCash = $settings['citiCashUrl'];
		$this->template->linkToCitiCashShort = $settings['citiCashUrlShort'];
	}

	public function renderDefault(int $heightStart = 0): void
	{
		$lastHeight = $this->rpcDaemon->getHeight() - 1;

		if ($heightStart === 0) {
			$heightStart = $lastHeight;
		}

		$blocks = $this->rpcDaemon->getBlocksByHeight($heightStart, self::ITEMS_PER_PAGE);

		foreach ($blocks as $block) {
			if (\count($block->getTxHashes()) > 0) {
				$block->setTransactions($this->rpcDaemon->getTransactions($block->getTxHashes()));
			}
		}

		$this->template->blocks = $blocks;
		$this->template->heightStart = $heightStart;
		$paginator = new Paginator();
		$paginator->setItemCount($lastHeight);
		$paginator->setItemsPerPage(self::ITEMS_PER_PAGE);
		$paginator->setPage($heightStart / self::ITEMS_PER_PAGE);
		$paginator->setBase(1);
		$this->template->paginator = $paginator;

		if ($heightStart === $lastHeight) {
			$this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=20');
			$cache = new Cache($this->redisStorageService->getStorage());
			$this->template->tpData = $cache->load('mempool', function (&$expiration) {
				$expiration = [Cache::EXPIRE => '10 seconds'];

				return $this->rpcDaemon->getTransactionPool()->getAllData();
			});
		} else {
			$this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=2592000'); //month
		}
	}

	public function renderDetail(string $hash): void
	{
		try {
			$blockData = $this->rpcDaemon->getBlockByHash($hash);
			//\dump($blockData);
			$this->template->block = $blockData;

			if ($blockData->getAge() > (30 * 60)) {
				$this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=31536000'); //365 days
			} else {
				$this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=30'); //365 days
			}
		} catch (BadRequestException $e) {
			$this->redirect('transaction', $hash);
		}
	}

	public function renderDetailByHeight(int $height): void
	{
		$this->template->block = $this->rpcDaemon->getBlockByHeight($height);
		$this->setView('detail');
	}

	public function renderTransaction(string $hash): void
	{
		$this['viewKeyForm']; // fix session problem
		$transactions = $this->rpcDaemon->getTransactions([$hash], $this->request->getPost('viewKey'));
		$transaction = $transactions[0]->getData();
		//\dump($transaction);
		$block = null;

		if ($transaction->in_pool === false) {
			$block = $this->rpcDaemon->getBlockByHeight((int)$transaction->block_height);
		}

		//dump($block);
		if ($this->request->isMethod('POST')) {
			$this->getHttpResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
		} else {
			$this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=31536000'); //365 days
		}

		$this->template->block = $block;
		$this->template->transaction = $transaction;
	}

	public function createComponentViewKeyForm(): Form
	{
		$onClear = function (): void {
			$this->redirect('this');
		};

		return $this->viewKeyFormFactory->create($onClear);
	}

	public function renderInfo(): JsonResponse
	{
		$infoData = $this->rpcDaemon->getInfo();
		$lastHeight = $infoData->getHeight() - 1;
		$block = $this->rpcDaemon->getBlockByHeight($lastHeight);

		$response = [
			'difficulty' => $infoData->getDifficulty(),
			'hashRate' => $infoData->getHashRate(),
			'reward' => $block->getReward(),
			'dateTime' => $block->getDateTime(),
			'height' => $lastHeight,
		];

		$this->sendJson($response);
	}

	protected function afterRender(): void
	{
		\bdump($this->rpcDaemon->getRequestsCount(), 'requests count:');
		parent::afterRender();
	}
}
