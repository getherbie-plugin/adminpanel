<?php

namespace herbie\plugin\adminpanel\controllers;

use Herbie\Menu\Page;
use Herbie\Helper\FilesystemHelper;

class PageController extends Controller
{
    use PageControllerTrait;

    public function indexAction($query, $request)
    {
        $route = $query->get('route', '');
        $tree = $this->getPageTree()->findByRoute($route);
        return $this->render('page/index.twig', [
            'tree' => $tree,
            'cancel' => $route,
            'breadcrumb' => $route,
            'dir' => $this->config->get('pages.path'),
            'parent' => $route // for macro.grid.addblock_js()
        ]);
    }

    public function addAction($query, $request)
    {
        $title = $request->get('name');
        $parent = $request->get('parent');
        if (empty($title)) {
            $this->sendErrorHeader($this->t('Name cannot be empty.'));
        }

        $filename = FilesystemHelper::sanitizeFilename($title);
        #$parent = FilesystemHelper::sanitizeFilename($parent);

        $parentRoute = $this->getPageTree()->findByRoute($parent);

        if ($parentRoute->isRoot()) {
            $filepath = $this->alias->get("@page/{$filename}.md");
        } else {
            $parentAlias = dirname($parentRoute->getMenuItem()->getPath());
            $filepath = $this->alias->get("{$parentAlias}/{$filename}.md");
        }

        if (is_file($filepath)) {
            $this->sendErrorHeader($this->t('A page with the same name already exists.'));
        }
        $eol = PHP_EOL;
        $pageTitle = $this->t('My new page');
        $data = "---{$eol}title: {$title}{$eol}disabled: 1{$eol}hidden: 1{$eol}---{$eol}{$pageTitle}{$eol}";
        if (!file_put_contents($filepath, $data)) {
            $this->sendErrorHeader($this->t('Page {name} can not be created.', ['{name}' => $title]));
        }

        if (!empty($parent)) {
            $query->set('route', $parent);
        }
        return $this->indexAction($query, $request);
    }

    public function deleteAction($query, $request)
    {
        $file = $request->get('file');
        $filepath = $this->alias->get($file);
        $basename = basename($filepath);
        if (empty($file)) {
            $this->sendErrorHeader($this->t('Invalid parameter!'));
        }
        if (!is_file($filepath)) {
            $this->sendErrorHeader($this->t('Page {name} does not exist.', ['{name}' => $basename]));
        }
        $tree = $this->getPageTree()->findBy('path', $file);
        $hasChildren = ($tree && $tree->hasChildren()) ? true : false;
        if ($hasChildren) {
            $this->sendErrorHeader($this->t('Page {name} has sub pages and can not be deleted.', ['{name}' => $basename]));
        }
        if (!@unlink($filepath)) {
            $this->sendErrorHeader($this->t('Page {name} can not be deleted.', ['{name}' => $basename]));
        }
        header('Content-Type: application/json');
        echo json_encode(true);
        exit;
    }

    protected function getPageTree()
    {
        $menu = $this->getService('Menu\Page\Builder')->buildCollection();
        return Page\Node::buildTree($menu);
    }

    protected function redirectBack($path)
    {
        $item = $this->getService('Menu\Page\Collection')->find($path, 'path');
        if (is_null($item)) {
            $item = $this->getService('Menu\Post\Collection')->find($path, 'path');
        }
        if (isset($item)) {
            $route = $item->route;
        } else {
            $route = '';
        }
        $this->twig->environment->getExtension('herbie')->functionRedirect($route);
    }

    /*public function editAction($query, $request)
    {
        $path = $query->get('path', null);

        $data = $this->getService('Loader\PageLoader')->load($path, false);

        $absPath = $this->alias->get($path);
        $action = strpos($path, '@page') !== false ? 'page/index' : 'post/index';

        if(is_null($path)) {
            throw new \Exception('Path must be set');
        }

        $data = $request->get('data', file_get_contents($absPath));
        $content = $request->get('content', file_get_contents($absPath));

        $saved = false;
        if($this->app['request']->getMethod() == 'POST') {
            $saved = file_put_contents($absPath, $content);

            if ($request->get('button2') !== null) {
                $this->twig->environment->getExtension('herbie')->functionRedirect('adminpanel?action=' . $action);
            }

            if ($request->get('button3') !== null) {
                $this->redirectBack($path);
            }
        }

        return $this->render('form.twig', [
            'data' => $data,
            'content' => $content,
            'path' => $path,
            'action' => $action,
            'saved' => $saved
        ]);
    }*/
}
