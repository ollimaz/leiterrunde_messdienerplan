<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!interface_exists(\OCP\IUser::class)) {
	eval('namespace OCP\\AppFramework\\Db; abstract class QBMapper { public function __construct(...$a) {} public function insert(Entity $e) { return $e; } public function update(Entity $e) { return $e; } public function delete(Entity $e) { return $e; } } abstract class Entity { protected $id; public function getId() { return $this->id; } public function setId($v) { $this->id = $v; } public function addType(string $p, string $t): void {} public function __call($n, $a) { $prop = lcfirst(preg_replace("/^(get|set)/", "", $n)); if (str_starts_with($n, "get")) { return $this->$prop ?? null; } $this->$prop = $a[0] ?? null; } }');
	eval('namespace OCP; interface IUser { public function getUID(): string; }');
	eval('namespace OCP; interface IL10N { public function t(string $text, array $parameters = []): string; public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string; }');
	eval('namespace OCP\\AppFramework\\Db; class DoesNotExistException extends \\Exception {}');
}

spl_autoload_register(static function (string $class): void {
	foreach (['OCA\\LeiterrundeMessdienerplan\\' => __DIR__ . '/../lib/', 'OCA\\Leiterrunde\\' => __DIR__ . '/../../leiterrunde/lib/'] as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
		}
	}
});
