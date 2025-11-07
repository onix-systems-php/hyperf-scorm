<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license https://github.com/hyperf/blob/master/LICENSE
 */
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

$container = new Container((new DefinitionSourceFactory())());
ApplicationContext::setContainer($container);

return $container;
