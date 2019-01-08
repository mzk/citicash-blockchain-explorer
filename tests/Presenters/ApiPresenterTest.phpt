<?php declare(strict_types = 1);

namespace Tests\Presenters;

use Nette\Application\Responses\JsonResponse;
use Tester\Assert;
use Tests\BasePresenterTestCase;

$container = include __DIR__ . '/../bootstrap.php';

class ApiPresenterTest extends BasePresenterTestCase
{

	public function testInfo(): void
	{
		$this->openPresenter('Api:');
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

$test = new ApiPresenterTest($container);
$test->run();
