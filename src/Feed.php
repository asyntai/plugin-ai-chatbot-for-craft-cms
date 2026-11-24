<?php

namespace Asyntai\Chatbot;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\fields\BaseRelationField;
use craft\helpers\UrlHelper;

/**
 * The signed, read-only page feed in the Asyntai store feed format
 * (store_feed_v1). It shares the live entries that have a page on the
 * primary site. Nothing on the site is created or changed.
 */
class Feed
{
    /** How far a signed feed call may be from this server's clock. */
    public const CLOCK_SKEW = 300;

    /** Pages per feed page, and the most a caller may ask for. */
    public const PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 100;

    /** How deep nested blocks (Matrix and similar) are read. */
    private const MAX_DEPTH = 4;

    public static function feedUrl(string $which): string
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        return UrlHelper::siteUrl('asyntai-feed/' . $which, null, null, $siteId);
    }

    public static function siteRoot(): string
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $base = method_exists($site, 'getBaseUrl') ? (string)$site->getBaseUrl() : (string)$site->baseUrl;
        if ($base === '') {
            $base = UrlHelper::siteUrl('', null, null, $site->id);
        }
        return rtrim($base, '/');
    }

    /**
     * Checks the token and the signature. Returns null when the call is
     * allowed, or the reason it is refused.
     */
    public static function refusal(array $query): ?string
    {
        // The live token, and the one parked by the last "prepare". Asyntai
        // starts its first read the moment the owner signs in, a moment
        // BEFORE this site saves the sign-in result, so the parked token is
        // accepted too. It was only ever sent to Asyntai.
        $tokens = [];
        $live = Store::get('feed_token');
        if ($live !== '' && Store::get('feed_enabled') === '1') {
            $tokens[] = $live;
        }
        $pending = Store::get('handshake_token');
        if ($pending !== '' && Store::get('handshake_share') === '1') {
            $tokens[] = $pending;
        }
        if (!$tokens) {
            return 'Page sharing is switched off on this site.';
        }

        $page = (string)($query['page'] ?? '');
        $limit = (string)($query['limit'] ?? '');
        $ids = (string)($query['ids'] ?? '');
        $ts = (string)($query['ts'] ?? '');
        $sig = strtolower((string)($query['sig'] ?? ''));

        if ($ts === '' || !ctype_digit($ts)) {
            return 'Missing timestamp.';
        }
        if (abs(time() - (int)$ts) > self::CLOCK_SKEW) {
            return 'The request has expired.';
        }
        // Signed over the raw values, in a fixed order, before any clamping.
        $signed = 'page=' . $page . '&limit=' . $limit . '&ids=' . $ids . '&ts=' . $ts;
        foreach ($tokens as $token) {
            if ($sig !== '' && hash_equals(hash_hmac('sha256', $signed, $token), $sig)) {
                return null;
            }
        }
        return 'Bad signature.';
    }

    public static function storeInfo(): array
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $root = self::siteRoot();
        $name = trim(strip_tags((string)$site->name));
        return [
            'name' => $name !== '' ? $name : (string)parse_url($root, PHP_URL_HOST),
            'url' => $root,
            'currency' => '',
            'version' => Craft::$app->getVersion(),
            'pages_url' => self::feedUrl('pages'),
        ];
    }

    /** A Craft site sells nothing through this plugin, so the catalogue is empty. */
    public static function products(int $pageNo): array
    {
        return [
            'ok' => true,
            'store' => self::storeInfo(),
            'page' => $pageNo,
            'pages' => 0,
            'total' => 0,
            'categories' => [],
            'products' => [],
        ];
    }

    /** One page of the live entries that have their own page on the primary site. */
    public static function pages(int $pageNo, int $size): array
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $query = Entry::find()
            ->siteId($siteId)
            ->status('live')
            ->uri(':notempty:')
            ->orderBy(['elements.id' => SORT_ASC]);

        $total = (int)(clone $query)->count();
        $entries = $query->offset(($pageNo - 1) * $size)->limit($size)->all();

        $items = [];
        foreach ($entries as $entry) {
            $title = trim(html_entity_decode(strip_tags((string)$entry->title), ENT_QUOTES, 'UTF-8'));
            $url = (string)$entry->getUrl();
            $content = self::elementText($entry, 0);
            if ($title === '' || $url === '' || $content === '') {
                continue;
            }
            $items[] = [
                'type' => 'page',
                'title' => $title,
                'url' => $url,
                'content' => $content,
            ];
        }

        return [
            'ok' => true,
            'page' => $pageNo,
            'pages' => (int)ceil($total / max(1, $size)),
            'total' => $total,
            'items' => $items,
        ];
    }

    /** The element's own text fields, in field layout order, as plain text. */
    public static function elementText(ElementInterface $element, int $depth): string
    {
        $layout = $element->getFieldLayout();
        if (!$layout) {
            return '';
        }
        if (method_exists($layout, 'getCustomFields')) {
            $fields = $layout->getCustomFields();
        } else {
            $fields = $layout->getFields();
        }

        $parts = [];
        foreach ($fields as $field) {
            try {
                $text = self::fieldText($element, $field, $depth);
            } catch (\Throwable $e) {
                $text = '';
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return trim(implode("\n\n", $parts));
    }

    private static function fieldText(ElementInterface $element, $field, int $depth): string
    {
        $class = get_class($field);
        // Links to other entries, assets, users or categories are their own pages.
        if ($field instanceof BaseRelationField) {
            return '';
        }
        $value = $element->getFieldValue($field->handle);
        if ($value === null) {
            return '';
        }

        if ($field instanceof \craft\fields\PlainText) {
            return self::plainText(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
        }
        if (stripos($class, 'ckeditor') !== false || stripos($class, 'redactor') !== false) {
            return self::plainText((string)$value);
        }
        if ($field instanceof \craft\fields\Table && is_array($value)) {
            $lines = [];
            foreach ($value as $row) {
                $cells = [];
                foreach ((array)$row as $key => $cell) {
                    if (is_string($key) && strpos($key, 'col') === 0) {
                        continue;
                    }
                    if (is_scalar($cell) && trim((string)$cell) !== '') {
                        $cells[] = trim((string)$cell);
                    }
                }
                if ($cells) {
                    $lines[] = implode(' | ', $cells);
                }
            }
            return self::plainText(htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8'));
        }
        // Matrix and similar fields hold nested blocks with their own fields.
        if ($value instanceof ElementQueryInterface && $depth < self::MAX_DEPTH) {
            $parts = [];
            foreach ($value->all() as $nested) {
                $text = self::elementText($nested, $depth + 1);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
            return trim(implode("\n\n", $parts));
        }
        return '';
    }

    public static function plainText(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/blockquote|/figure|/tr)\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n\s*\n+/", "\n\n", $text);
        return trim($text);
    }
}
