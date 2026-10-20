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
use App\Services\TypographyService;
use Throwable;

/**
 * Admin → Fonts: font, weight and size for headings, sub-headings and
 * paragraph text, applied across the public site.
 */
final class TypographyController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private TypographyService $typography,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('admin/typography/index', [
            'pageTitle' => 'Fonts',
            'current'   => $this->typography->current(),
            'catalog'   => $this->typography->catalogForForm(),
            'fontFaces' => $this->typography->fontFaceCss(),
            'sizeMin'   => TypographyService::SIZE_MIN,
            'sizeMax'   => TypographyService::SIZE_MAX,
        ], 'layouts/admin');
    }

    public function save(Request $request): Response
    {
        try {
            $this->typography->save($request->all(), (int) $this->auth->id());
            $this->audit->log('typography.updated', 'settings', 'typography', [], (int) $this->auth->id());
        } catch (Throwable $e) {
            error_log('[almarah] typography save failed: ' . $e->getMessage());
            $this->flashError('The fonts could not be saved. Please try again.');
            return $this->redirect('/admin/typography');
        }

        $this->flashSuccess('Fonts saved. They are live on the website now.');
        return $this->redirect('/admin/typography');
    }

    public function reset(Request $request): Response
    {
        try {
            $this->typography->reset((int) $this->auth->id());
            $this->audit->log('typography.reset', 'settings', 'typography', [], (int) $this->auth->id());
        } catch (Throwable $e) {
            error_log('[almarah] typography reset failed: ' . $e->getMessage());
            $this->flashError('The fonts could not be reset. Please try again.');
            return $this->redirect('/admin/typography');
        }

        $this->flashSuccess('Back to the original fonts.');
        return $this->redirect('/admin/typography');
    }
}
