<?php declare(strict_types = 1);

namespace Tests;

use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Application\IResponse;
use Nette\Application\PresenterFactory;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\InvalidStateException;
use Nette\Reflection\ClassType;

abstract class BasePresenterTestCase extends BaseTestCase
{

	/**
	 * @var UrlScript
	 */
	protected $fakeUrl;

	/**
	 * @var IPresenter
	 */
	private $presenter;

	public function getContainer(): Container
	{
		return $this->container;
	}

	protected function openPresenter(string $fqa): void
	{
		//insert fake HTTP Request for Presenter - for presenter->link() etc.
		$params = $this->getContainer()->getParameters();
		$this->fakeUrl = new UrlScript($params['console']['url'] ?? null);

		$container = $this->getContainer();
		$container->removeService('httpRequest');
		$container->addService('httpRequest', new Request($this->fakeUrl, null, [], [], [], [], \PHP_SAPI, '127.0.0.1', '127.0.0.1'));

		/** @var PresenterFactory $presenterFactory */
		$presenterFactory = $this->container->getByType(IPresenterFactory::class);
		$presenterFactory->setMapping(['*' => 'App\*Module\Presenters\*Presenter']);
		$name = \substr($fqa, 0, $namePos = (int)\strrpos($fqa, ':'));
		$class = $presenterFactory->getPresenterClass($name);

		$overriddenPresenter = 'AppTests\\' . $class;

		if (!\class_exists($overriddenPresenter)) {
			$classPos = (int)\strrpos($class, '\\');
			eval('namespace AppTests\\' . \substr($class, 0, $classPos) . '; class ' . \substr($class, $classPos + 1) . ' extends \\' . $class . ' { protected function startup(): void { if ($this->getParameter("terminate") == TRUE) { $this->terminate(); } parent::startup(); } public static function getReflection(): \Nette\Application\UI\ComponentReflection { return new \Nette\Application\UI\ComponentReflection(get_parent_class()); } }');
		}

		$this->presenter = $container->createInstance($overriddenPresenter);
		$container->callInjects($this->presenter);

		$app = $this->container->getByType(Application::class);
		$appRefl = ClassType::from($app)->getProperty('presenter');
		$appRefl->setAccessible(true);
		$appRefl->setValue($app, $this->presenter);

		$this->presenter->autoCanonicalize = false;
		$action = \substr($fqa, $namePos + 1);

		if (\substr($fqa, $namePos + 1) === '') {
			$action = 'default';
		}

		$this->presenter->run(new \Nette\Application\Request($name, 'GET', ['action' => $action, 'terminate' => true]));
	}


	/**
	 * @param mixed[] $params
	 * @param mixed[] $post
	 */
	protected function runPresenterSignal(string $action, string $signal, ?array $params = [], ?array $post = []): IResponse
	{
		$method = 'GET';

		if (\count($post) > 0) {
			$method = 'POST';
		}

		return $this->runPresenterAction($action, $method, ['do' => $signal] + $params, $post);
	}

	/**
	 * @param mixed[] $params
	 * @param mixed[] $post
	 * @throws InvalidStateException
	 */
	protected function runPresenterAction(string $action, string $method = 'GET', ?array $params = [], ?array $post = []): IResponse
	{
		if (!isset($this->presenter)) {
			throw new InvalidStateException('You have to open the presenter using $this->openPresenter($name); before calling actions');
		}

		$request = new \Nette\Application\Request($this->presenter->getName(), $method, ['action' => $action] + $params, $post + $params);

		return $this->presenter->run($request);
	}
}
