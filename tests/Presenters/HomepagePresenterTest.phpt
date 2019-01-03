<?php declare(strict_types = 1);

namespace Tests\Presenters;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Bridges\ApplicationLatte\Template;
use Tester\Assert;
use Tests\BasePresenterTestCase;

$container = include __DIR__ . '/../bootstrap.php';

class HomepagePresenterTest extends BasePresenterTestCase
{

	public function testDefault(): void
	{
		$this->openPresenter('Homepage:');
		$response = $this->runPresenterAction('default');
		Assert::type(TextResponse::class, $response);
		Assert::type(Template::class, $response->getSource());
	}

	public function testDetailWidthHash(): void
	{
		$this->openPresenter('Homepage:');
		$response = $this->runPresenterAction('detail', 'GET', ['hash' => '62bc2548047526561cad42662ccbd699b66312809ed75354f25d5a4f399daaec']);
		Assert::type(TextResponse::class, $response);
		Assert::type(Template::class, $response->getSource());
	}

	public function testDetailWidtTransaction(): void
	{
		$this->openPresenter('Homepage:');
		$response = $this->runPresenterAction('detail', 'GET', ['hash' => '028a5a18a278af4e92947eba56f7190ef66a7eb6a8483a76d90ae78686e17aa0']);
		Assert::type(RedirectResponse::class, $response);
	}

	public function testTransaction(): void
	{
		$this->openPresenter('Homepage:');
		$response = $this->runPresenterAction('transaction', 'GET', ['hash' => '028a5a18a278af4e92947eba56f7190ef66a7eb6a8483a76d90ae78686e17aa0']);
		Assert::type(TextResponse::class, $response);
		Assert::type(Template::class, $response->getSource());
	}

	public function testTransactionHashFailed(): void
	{
		$this->openPresenter('Homepage:');

		Assert::exception(function (): void {
			$this->runPresenterAction('transaction', 'GET', ['hash' => 'not exists']);
		}, BadRequestException::class, 'Failed to parse hex representation of transaction hash');
	}

	public function testInfo(): void
	{
		$this->openPresenter('Homepage:');
		$response = $this->runPresenterAction('info');
		$payload = $response->getPayload();

		Assert::type(JsonResponse::class, $response);
		Assert::type('array', $payload);
		Assert::truthy($payload['difficulty']);
		Assert::truthy($payload['hashRate']);
		Assert::truthy($payload['reward']);
		Assert::truthy($payload['height']);
	}
}

$test = new HomepagePresenterTest($container);
$test->run();
