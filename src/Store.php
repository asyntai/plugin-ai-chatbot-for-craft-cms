<?php

namespace Asyntai\Chatbot;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * Plugin values kept in the plugin's own database table.
 *
 * Plugin settings live in the project config, which is read-only on most
 * production sites and is often committed to git. The connection and the
 * page feed token are therefore kept here instead.
 */
class Store
{
    public const TABLE = '{{%asyntai_store}}';

    /** @var array|null */
    private static $cache = null;

    public static function tableExists(): bool
    {
        try {
            return Craft::$app->getDb()->tableExists(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $values = [];
        if (self::tableExists()) {
            $rows = (new Query())->select(['name', 'value'])->from(self::TABLE)->all();
            foreach ($rows as $row) {
                $values[(string)$row['name']] = (string)$row['value'];
            }
        }
        return self::$cache = $values;
    }

    public static function get(string $name): string
    {
        $all = self::all();
        return isset($all[$name]) ? trim($all[$name]) : '';
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::all());
    }

    /**
     * The connection: from this table once the plugin has saved one here,
     * otherwise from the plugin settings of an earlier version.
     */
    public static function connection(): array
    {
        if (self::has('site_id')) {
            return [
                'siteId' => self::get('site_id'),
                'scriptUrl' => self::get('script_url'),
                'accountEmail' => self::get('account_email'),
            ];
        }
        $settings = Plugin::getInstance()->getSettings();
        return [
            'siteId' => trim((string)($settings->siteId ?? '')),
            'scriptUrl' => trim((string)($settings->scriptUrl ?? '')),
            'accountEmail' => trim((string)($settings->accountEmail ?? '')),
        ];
    }

    /** Only the Asyntai widget script may be loaded on the site. */
    public static function cleanScriptUrl(string $url): string
    {
        $url = trim($url);
        if (preg_match('#^https://asyntai\.com/[A-Za-z0-9/._-]+\.js$#', $url)) {
            return $url;
        }
        return 'https://asyntai.com/static/js/chat-widget.js';
    }

    /** Write some values and keep every other one. */
    public static function set(array $values): void
    {
        $db = Craft::$app->getDb();
        $now = Db::prepareDateForDb(new \DateTime());
        $existing = self::all();
        foreach ($values as $name => $value) {
            $value = (string)$value;
            if (array_key_exists($name, $existing)) {
                $db->createCommand()->update(self::TABLE, [
                    'value' => $value,
                    'dateUpdated' => $now,
                ], ['name' => $name], [], false)->execute();
            } else {
                $db->createCommand()->insert(self::TABLE, [
                    'name' => $name,
                    'value' => $value,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ], false)->execute();
            }
        }
        self::$cache = null;
    }

    public static function clear(): void
    {
        if (self::tableExists()) {
            Craft::$app->getDb()->createCommand()->delete(self::TABLE)->execute();
        }
        self::$cache = null;
    }
}
