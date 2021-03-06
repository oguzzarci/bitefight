<?php
/**
 * Created by PhpStorm.
 * User: osein
 * Date: 08/01/17
 * Time: 17:09
 */

define('APP_PATH', dirname(dirname(__FILE__)));
define('APP_START_TIME', microtime(true));
define('APP_START_MEMORY', memory_get_usage());

setlocale(LC_ALL, 'en_US.utf-8');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('utf-8');
}

if (function_exists('mb_substitute_character')) {
    mb_substitute_character('none');
}

include_once APP_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$loader = new \Phalcon\Loader();
$loader->registerNamespaces([
    'Bitefight\Controllers' => APP_PATH.DIRECTORY_SEPARATOR.'controllers',
    'Bitefight\Library' => APP_PATH.DIRECTORY_SEPARATOR.'library',
    'Bitefight\Models' => APP_PATH.DIRECTORY_SEPARATOR.'models'
]);
$loader->register();

$run = new Whoops\Run;

if(\Bitefight\Config::DEBUG) {
    $debugHandler = new \Whoops\Handler\PrettyPageHandler();
    $debugHandler->addDataTable('Path details', [
        'APP_PATH' => APP_PATH
    ]);
    $debugHandler->setPageTitle("Whoops! There was a problem.");
    $run->pushHandler($debugHandler);

} else {
    $prodHandler = new \Whoops\Handler\CallbackHandler(function() {
        $request = new \Phalcon\Http\Request();
        if($request->isAjax()) {
            // Todo make ajax response interface
        } else {
            $response = new \Phalcon\Http\Response();
            $response->setContent(
                '<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
            <html>
            <head>
                <title>'.\Bitefight\Library\Translate::_('500_error_page_title').'</title>
            </head>
            <body style=\'background-color: #220202; color: #FFF;\'>
            <h1>'.\Bitefight\Library\Translate::_('500_error_page_header').'</h1>
            <p>'.\Bitefight\Library\Translate::_('500_error_page_p1').'</p>
            <p>'.\Bitefight\Library\Translate::_('500_error_page_p2').'</p>
            </body>
            </html>'
            );
            $response->send();
        }
    });
    $run->pushHandler($prodHandler);
}

$run->register();

/** @noinspection PhpUndefinedFieldInspection */
ORM::configure(\Bitefight\Config::DB_ADAPTER.':host='.\Bitefight\Config::DB_HOST.';dbname='.\Bitefight\Config::DB_NAME);
/** @noinspection PhpUndefinedFieldInspection */
ORM::configure('username', \Bitefight\Config::DB_USERNAME);
/** @noinspection PhpUndefinedFieldInspection */
ORM::configure('password', \Bitefight\Config::DB_PASSWORD);

/*
 * If you don't want your ide to warn you about missing phalcon
 * classes, go download phalcon developer tools
 * and add phalcon stubs to your include path
 */
$di = new \Phalcon\Di\FactoryDefault();

$di->set('router', function () {
    $router = require APP_PATH . DIRECTORY_SEPARATOR . 'routes.php';
    return $router;
});

$di->set('dispatcher', function() use ($di) {
    $dispatcher = new \Phalcon\Mvc\Dispatcher();
    $dispatcher->setActionSuffix('');

    $evManager = $di->getShared('eventsManager');

    /** @noinspection PhpUnusedParameterInspection */
    $evManager->attach("dispatch:beforeException", function($event, $dispatcher, $exception) {
            /**
             * @var Exception $exception
             * @var \Phalcon\Mvc\Dispatcher $dispatcher
             */
            switch ($exception->getCode()) {
                case \Phalcon\Mvc\Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                case \Phalcon\Mvc\Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                    $dispatcher->forward(
                        array(
                            'controller' => 'error',
                            'action'     => 'show404',
                        )
                    );
                    return false;
            }

            return true;
        }
    );

    return $dispatcher;
});

$di->setShared("session", function () {
    $session = new \Phalcon\Session\Adapter\Files();
    $session->start();
    return $session;
});

$di->set("flashSession", function () {
    $flash = new \Phalcon\Flash\Session(
        [
            "error"   => "error",
            "success" => "success",
            "notice"  => "info",
            "warning" => "warning",
        ]
    );

    return $flash;
});

$di->set('view', function () {
    $view = new \Phalcon\Mvc\View();
    $view->setViewsDir(APP_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
    $view->disableLevel([
        \Phalcon\Mvc\View::LEVEL_LAYOUT
    ]);
    return $view;
}, true);

$application = new \Phalcon\Mvc\Application($di);
echo $application->handle()->getContent();