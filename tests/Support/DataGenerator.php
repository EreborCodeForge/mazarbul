<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use Generator;

final class DataGenerator
{
    /**
     * @return Generator<int, array{0: int, 1: string}>
     */
    public static function rows(int $count): Generator
    {
        for ($i = 0; $i < $count; ++$i) {
            yield [$i, 'value-' . $i];
        }
    }

    /**
     * @return Generator<int, array{id: int, name: string, email: string}>
     */
    public static function associativeUsers(int $count, int $startId = 1): Generator
    {
        for ($i = 0; $i < $count; ++$i) {
            $id = $startId + $i;
            yield [
                'id' => $id,
                'name' => 'User ' . $id,
                'email' => 'user' . $id . '@example.test',
            ];
        }
    }
}
