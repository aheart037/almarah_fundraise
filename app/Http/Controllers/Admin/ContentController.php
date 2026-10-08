<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\ContentService;

/**
 * Editable marketing copy and FAQs. Only keys that exist in
 * ContentService::defaults() may be written, so an admin cannot inject
 * arbitrary rows.
 */
final class ContentController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private ContentService $content,
        private AuditService $audit
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        return $this->render('admin/content/index', [
            'pageTitle' => 'Site content',
            'blocks'    => [
                'homepage_hero'  => $this->content->block('homepage_hero'),
                'impact_numbers' => $this->content->block('impact_numbers'),
                'trust'          => $this->content->block('trust'),
                'support'        => $this->content->block('support'),
                'privacy_policy' => $this->content->block('privacy_policy'),
                'terms'          => $this->content->block('terms'),
            ],
            'faqs'     => $this->content->faqs(false),
            'editFaq'  => null,
        ], 'layouts/admin');
    }

    public function saveBlock(Request $request): Response
    {
        $block = (string) $request->routeParam('block', '');
        $defaults = $this->content->defaults();

        if (!array_key_exists($block, $defaults)) {
            abort(404, 'Unknown content block.');
        }

        $existing = $this->content->block($block);
        $submitted = $request->all();

        $payload = [];
        foreach (array_keys($defaults[$block]) as $field) {
            if ($field === 'items') {
                $items = $request->input('items', []);
                $rows = [];
                if (is_array($items)) {
                    foreach ($items as $index => $item) {
                        $value = trim((string) ($item['value'] ?? ''));
                        $label = trim((string) ($item['label'] ?? ''));
                        if ($value === '' && $label === '') {
                            continue;
                        }
                        $rows[] = ['value' => $value, 'label' => $label];
                    }
                }
                $payload['items'] = $rows !== [] ? $rows : ($defaults[$block]['items'] ?? []);
                continue;
            }

            if (array_key_exists($field, $submitted)) {
                $payload[$field] = is_string($submitted[$field]) ? trim($submitted[$field]) : $submitted[$field];
            } elseif (array_key_exists($field, $existing)) {
                $payload[$field] = $existing[$field];
            }
        }

        // Long-form fields have a sane ceiling so a paste accident cannot fill
        // the column.
        foreach ($payload as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 40000) {
                $this->flashError('The ' . str_replace('_', ' ', $key) . ' field is too long.');
                return $this->redirect('/admin/content');
            }
        }

        $this->content->saveBlock($block, $payload, (int) $this->auth->id());

        $this->audit->log('content.block_saved', 'content_block', $block, [
            'fields' => array_keys($payload),
        ], (int) $this->auth->id());

        $this->flashSuccess('Content saved. The change is live now.');
        return $this->redirect('/admin/content#' . $block);
    }

    public function saveFaq(Request $request): Response
    {
        $id = (int) $request->input('id', 0);

        $validator = Validator::make($request->all(), [
            'question'   => 'required|string|min:8|max:300',
            'answer'     => 'required|string|min:10|max:10000',
            'sort_order' => 'nullable|integer',
            'status'     => 'required|in:published,draft',
        ]);

        if ($validator->fails()) {
            return $this->flashFormState($validator->errors(), $request->all());
        }

        $faqId = $this->content->saveFaq($id > 0 ? $id : null, [
            'question'   => (string) $request->input('question'),
            'answer'     => (string) $request->input('answer'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'status'     => (string) $request->input('status'),
        ]);

        $this->audit->log($id > 0 ? 'content.faq_updated' : 'content.faq_created', 'faq', (string) $faqId, [], (int) $this->auth->id());

        $this->flashSuccess($id > 0 ? 'FAQ updated.' : 'FAQ added.');
        return $this->redirect('/admin/content#faqs');
    }

    public function deleteFaq(Request $request): Response
    {
        $id = (int) $request->routeParam('id', 0);
        $faq = $this->content->findFaq($id);

        if ($faq === null) {
            $this->flashError('That FAQ could not be found.');
            return $this->redirect('/admin/content#faqs');
        }

        $this->content->deleteFaq((int) $id);
        $this->audit->log('content.faq_deleted', 'faq', (string) $id, [], (int) $this->auth->id());

        $this->flashSuccess('FAQ removed.');
        return $this->redirect('/admin/content#faqs');
    }
}
