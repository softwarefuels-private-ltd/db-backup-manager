<?php

namespace Tests\Unit;

use App\Services\SystemRequirementService;
use Tests\TestCase;

class SystemRequirementServiceTest extends TestCase
{
    public function test_it_resolves_binary_from_specific_env_variable(): void
    {
        // Create a temporary mock binary file
        $tempFile = tempnam(sys_get_temp_dir(), 'dump_test_');
        file_put_contents($tempFile, '#!/bin/sh\necho "test"');
        chmod($tempFile, 0755);

        putenv("MYSQL_DUMP_PATH={$tempFile}");
        $_ENV['MYSQL_DUMP_PATH'] = $tempFile;

        try {
            $service = new SystemRequirementService;
            $found = $service->findBinary('mysqldump');

            $this->assertEquals($tempFile, $found);
        } finally {
            putenv('MYSQL_DUMP_PATH');
            unset($_ENV['MYSQL_DUMP_PATH']);
            @unlink($tempFile);
        }
    }

    public function test_it_resolves_binary_from_generic_dump_binary_dir_env(): void
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_bin_'.uniqid();
        mkdir($tempDir);
        $binFile = $tempDir.DIRECTORY_SEPARATOR.'mysqldump';
        file_put_contents($binFile, '#!/bin/sh\necho "test"');
        chmod($binFile, 0755);

        putenv("DUMP_BINARY_PATH={$tempDir}");
        $_ENV['DUMP_BINARY_PATH'] = $tempDir;

        try {
            $service = new SystemRequirementService;
            $found = $service->findBinary('mysqldump');

            $this->assertEquals($binFile, $found);
        } finally {
            putenv('DUMP_BINARY_PATH');
            unset($_ENV['DUMP_BINARY_PATH']);
            @unlink($binFile);
            @rmdir($tempDir);
        }
    }
}
