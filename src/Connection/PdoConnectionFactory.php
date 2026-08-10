<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

use EreborCodeForge\Mazarbul\Contract\ConnectionFactory;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use PDO;
use PDOException;
use Throwable;

final class PdoConnectionFactory implements ConnectionFactory
{
    public function create(ConnectionConfig $config): PDO
    {
        $options = $config->options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO(
                $config->dsn,
                $config->username,
                $config->password,
                $options,
            );
        } catch (PDOException $e) {
            throw ConnectionException::openFailed('pdo', $e);
        } catch (Throwable $e) {
            throw ConnectionException::openFailed('pdo', $e);
        }
    }
}
