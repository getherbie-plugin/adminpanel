<?php

namespace herbie\plugin\adminpanel\controllers;

class DefaultController extends Controller
{
    public function errorAction($query)
    {
        if ($this->request->isXmlHttpRequest()) {
            $this->sendErrorHeader($this->t('Invalid action parameter.'));
        }
        return $this->render('default/error.twig', []);
    }

    public function loginAction($query, $request)
    {
        if ($this->request->getMethod() == 'POST') {
            $password = $request->get('password', null);
            if (md5($password) == $this->config->get('plugins.config.adminpanel.password')) {
                $this->session->set('LOGGED_IN', true);
                $this->twig->environment->getExtension('herbie')->functionRedirect('adminpanel');
            }
        }
        return $this->render('default/login.twig', []);
    }

    public function logoutAction()
    {
        $this->session->set('LOGGED_IN', false);
        $this->twig->environment->getExtension('herbie')->functionRedirect('');
    }
}
