<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\ContentService;

/**
 * Static-ish marketing pages whose copy is editable from the admin area.
 */
final class PageController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private ContentService $content
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function about(): Response
    {
        return $this->render('public/pages/about', [
            'pageTitle'       => 'About Almarah Foundation',
            'metaDescription' => 'Almarah Foundation is a Pakistani welfare organisation supporting orphaned children, widows and families in need.',
            'hero'            => $this->content->hero(),
            'impact'          => $this->content->impactNumbers(),
            'trust'           => $this->content->trust(),
        ], 'layouts/public');
    }

    public function faq(): Response
    {
        return $this->render('public/pages/faq', [
            'pageTitle'       => 'Frequently asked questions',
            'metaDescription' => 'Answers about donating, starting a fundraiser, payment methods, receipts and privacy.',
            'faqs'            => $this->content->faqs(true),
        ], 'layouts/public');
    }

    public function privacy(): Response
    {
        $policy = $this->content->privacyPolicy();

        return $this->render('public/pages/privacy', [
            'pageTitle'       => (string) ($policy['title'] ?? 'Privacy policy'),
            'metaDescription' => 'How Almarah Foundation collects, uses and protects your personal data.',
            'policy'          => $policy,
        ], 'layouts/public');
    }

    public function support(): Response
    {
        return $this->render('public/pages/support', [
            'pageTitle'       => 'Support & contact',
            'metaDescription' => 'Contact Almarah Foundation about donations, fundraisers, receipts or partnerships.',
            'support'         => $this->content->supportInfo(),
            'faqs'            => array_slice($this->content->faqs(true), 0, 6),
        ], 'layouts/public');
    }

    public function terms(): Response
    {
        $terms = $this->content->block('terms');

        return $this->render('public/pages/terms', [
            'pageTitle'       => (string) ($terms['title'] ?? 'Terms of use'),
            'metaDescription' => 'The terms that apply to fundraising and donating with Almarah Foundation.',
            'terms'           => $terms,
        ], 'layouts/public');
    }
}
