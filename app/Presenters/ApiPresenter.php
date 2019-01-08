<?php declare(strict_types = 1);

namespace App\Presenters;

use App\Models\RpcDaemon;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;

class ApiPresenter extends Presenter
{

	/**
	 * @var RpcDaemon
	 */
	private $rpcDaemon;

	public function __construct(RpcDaemon $rpcDaemon)
	{
		parent::__construct();
		$this->rpcDaemon = $rpcDaemon;
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
}
