<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\MenuService;
use InvalidArgumentException;
use Throwable;

/**
 * Admin → Menus: create menus, assign them to the header or footer, and add,
 * remove and re-order their links.
 */
final class MenuController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private MenuService $menus,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('admin/menus/index', [
            'pageTitle' => 'Menus',
            'menus'     => $this->menus->all(),
            'locations' => MenuService::LOCATIONS,
            'maxMenus'  => MenuService::MAX_MENUS,
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->menus->create(
                (string) $request->input('name', ''),
                (string) $request->input('location', ''),
                (int) $this->auth->id()
            );
        } catch (InvalidArgumentException $e) {
            $this->session->flash('_old', ['name' => (string) $request->input('name', ''), 'location' => (string) $request->input('location', '')]);
            $this->flashError($e->getMessage());
            return $this->redirect('/admin/menus');
        } catch (Throwable $e) {
            error_log('[almarah] menu create failed: ' . $e->getMessage());
            $this->flashError('The menu could not be created. Please try again.');
            return $this->redirect('/admin/menus');
        }

        $this->audit->log('menu.created', 'menu', (string) $id, ['name' => (string) $request->input('name', '')], (int) $this->auth->id());
        $this->flashSuccess('Menu created. Now add the links you want in it.');
        return $this->redirect('/admin/menus/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        $menu = $this->menus->find((int) $request->routeParam('id', 0));
        if ($menu === null) {
            $this->flashError('That menu could not be found.');
            return $this->redirect('/admin/menus');
        }

        return $this->render('admin/menus/edit', [
            'pageTitle' => 'Edit menu',
            'menu'      => $menu,
            'locations' => MenuService::LOCATIONS,
            'pages'     => $this->menus->pageChoices(),
            'maxItems'  => MenuService::MAX_ITEMS,
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        if ($this->menus->find($id) === null) {
            $this->flashError('That menu could not be found.');
            return $this->redirect('/admin/menus');
        }

        try {
            $notice = $this->menus->update($id, $request->all(), (int) $this->auth->id());
        } catch (InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
            return $this->redirect('/admin/menus/' . $id . '/edit');
        } catch (Throwable $e) {
            error_log('[almarah] menu update failed: ' . $e->getMessage());
            $this->flashError('The menu could not be saved. Please try again.');
            return $this->redirect('/admin/menus/' . $id . '/edit');
        }

        $this->audit->log('menu.updated', 'menu', (string) $id, [], (int) $this->auth->id());
        $this->flashSuccess('Menu saved. The website shows it straight away.' . ($notice !== null ? ' ' . $notice : ''));
        return $this->redirect('/admin/menus/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $menu = $this->menus->find($id);
        if ($menu === null) {
            $this->flashError('That menu could not be found.');
            return $this->redirect('/admin/menus');
        }

        $this->menus->delete($id, (int) $this->auth->id());
        $this->audit->log('menu.deleted', 'menu', (string) $id, ['name' => (string) $menu['name']], (int) $this->auth->id());
        $this->flashSuccess('Menu deleted. The site uses its standard links in that place.');
        return $this->redirect('/admin/menus');
    }
}
