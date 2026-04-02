<?php

declare(strict_types=1);

namespace Seo;

use PDO;

class Database {

    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        if (class_exists('\Database', false)) {
            // Reuse the shared connection pool from the parent app.
            $this->pdo = \Database::getInstanceFor(getenv('MYSQL_DATABASE') ?: 'seo_generator');
        } else {
            // Standalone mode — create our own PDO connection from env vars.
            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('MYSQL_DATABASE') ?: 'seo_generator';
            $user = getenv('MYSQL_USER') ?: 'seo_user';
            $pass = getenv('MYSQL_PASSWORD') ?: 'seo_pass';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }


    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }


    public function fetchColumn(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }


    public function insert(string $table, array $data): int {
        $columns = array_keys($data);
        $placeholders = array_map(fn(string $col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $this->pdoType($value));
        }
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }


    public function update(string $table, array $data = [], string $where, array $whereParams = [], array $expressions = []): int {
        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = sprintf('`%s` = :set_%s', $col, $col);
        }

        foreach ($expressions as $col => $expression) {
            $setClauses[] = sprintf('`%s` = %s', $col, $expression);
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s',
            $table, implode(', ', $setClauses), $where);

        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':set_' . $key, $value, $this->pdoType($value));
        }
        foreach ($whereParams as $key => $value) {
            $stmt->bindValue($key, $value, $this->pdoType($value));
        }

        $stmt->execute();
        return $stmt->rowCount();
    }


    public function delete(string $table, string $where, array $params = []): int {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }


    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void {
        $this->pdo->beginTransaction();
    }

    public function commit(): void {
        $this->pdo->commit();
    }

    public function rollback(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function transaction(callable $fn) {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function pdoType($value): int {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($value)) {
            return PDO::PARAM_INT;
        }
        return PDO::PARAM_STR;
    }
}
