<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\adminpanel;

use Herbie;
use Herbie\Loader\FrontMatterLoader;
use Herbie\Menu;
use Twig_SimpleFunction;
use Symfony\Component\HttpFoundation\Session\Session;


class AdminpanelPlugin extends Herbie\Plugin
{

    protected $content;

    protected $panel;

    protected $session;

    protected $request;

    public function init()
    {
        $this->session = new Session();
        $this->session->start();
        $this->request = $this->getService('Request');
    }

    public function onTwigInitialized(Herbie\Event $event)
    {
        $function = new Twig_SimpleFunction('rawdata', function ($string) {
            return addcslashes($string, "\0..\37!@\177");
        });
        $event['twig']->addFunction($function);
    }

    /**
     * @param Herbie\Event $event
     */
    public function onPluginsInitialized(Herbie\Event $event)
    {
        if($this->config->isEmpty('plugins.config.adminpanel.no_page')) {
            $this->config->push('pages.extra_paths', '@plugin/adminpanel/pages');
        }
    }

    public function onOutputGenerated(Herbie\Event $event)
    {
        // return if response is not successful
        if (!$event['response']->isSuccessful()) {
            return;
        }

        if (!$this->isAdmin()) {
            if ($this->isAuthenticated()) {
                // prepend adminpanel to html body
                $controller = (0 === strpos($this->getService('Page')->path, '@post')) ? 'post' : 'page';
                $panel = $this->getService('Twig')->render('@plugin/adminpanel/views/panel.twig', [
                    'controller' => $controller
                ]);
                $regex = '/<body(.*)>/';
                $replace = '<body$1>' . $panel;
                $content = preg_replace($regex, $replace, $event['response']->getContent());
                $event['response']->setContent($content);
            }
        } else {
            $action = $this->session->get('LOGGED_IN') ? $this->request->query->get('action', 'page/index') : 'login';
            $pos = strpos($action, '/');
            if ($pos === false) {
                $controller = 'default';
            } else {
                $controller = substr($action, 0, $pos);
                $action = substr($action, ++$pos);
            }

            $controllerClass = '\\herbie\\plugin\\adminpanel\\controllers\\' . ucfirst($controller) . 'Controller';
            $method = $action . 'Action';

            $controllerObject = new $controllerClass($this->session);
            if (!method_exists($controllerObject, $method)) {
                $controllerObject = new controllers\DefaultController($this->session);
                $method = 'errorAction';
            }
            $controllerObject->controller = $controller;
            $controllerObject->action = $action;

            $params = ['query' => $this->request->query, 'request' => $this->request->request];
            $content = call_user_func_array([$controllerObject, $method], $params);
            $event['response']->setContent($content);
        }
    }

    /**
     * @return string
     */
    protected function getPathAlias()
    {
        return $this->config(
            'plugins.config.adminpanel.page.adminpanel',
            '@plugin/adminpanel/pages/adminpanel.html'
        );
    }

    protected function isAdmin()
    {
        return !empty($this->getService('Page')->adminpanel);
    }

    protected function isAuthenticated()
    {
        return (bool)$this->session->get('LOGGED_IN', false);
    }
}
