<?php

namespace Asyntai\Chatbot\controllers;

use Asyntai\Chatbot\Feed;
use Craft;
use craft\web\Controller;

/**
 * The signed page feed. It is the only address of the plugin that answers
 * without a login, and only to calls signed with the token Asyntai holds.
 */
class FeedController extends Controller
{
    public $enableCsrfValidation = false;

    public function init(): void
    {
        // Before parent::init(), which normalises the value.
        $this->allowAnonymous = ['index'];
        parent::init();
    }

    public function actionIndex(string $which = 'products')
    {
        $request = Craft::$app->getRequest();
        $query = [
            'page' => $request->getQueryParam('page', ''),
            'limit' => $request->getQueryParam('limit', ''),
            'ids' => $request->getQueryParam('ids', ''),
            'ts' => $request->getQueryParam('ts', ''),
            'sig' => $request->getQueryParam('sig', ''),
        ];

        $refusal = Feed::refusal($query);
        if ($refusal !== null) {
            return $this->send(['ok' => false, 'error' => $refusal], 403);
        }

        $pageNo = max(1, (int)$query['page']);
        $size = (int)$query['limit'];
        if ($size < 1 || $size > Feed::MAX_PAGE_SIZE) {
            $size = Feed::PAGE_SIZE;
        }

        try {
            $data = $which === 'pages' ? Feed::pages($pageNo, $size) : Feed::products($pageNo);
        } catch (\Throwable $e) {
            Craft::error('Asyntai page feed: ' . $e->getMessage(), __METHOD__);
            return $this->send(['ok' => false, 'error' => 'Could not read pages.'], 500);
        }
        return $this->send($data, 200);
    }

    private function send(array $data, int $status)
    {
        $response = Craft::$app->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()->set('Cache-Control', 'no-store');
        $response->getHeaders()->set('X-Robots-Tag', 'noindex');
        return $this->asJson($data);
    }
}
