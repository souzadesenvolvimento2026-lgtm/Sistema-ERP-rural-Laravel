<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProductionDatabaseBackupService
{
    public function backup(string $destination): void
    {
        $destination = $this->absolutePath($destination);
        File::ensureDirectoryExists(dirname($destination));

        $defaultsFile = $this->createDefaultsFile();

        try {
            $process = new Process([
                $this->binary('dump_binary', 'mariadb-dump'),
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--skip-comments',
                '--result-file='.$destination,
                $this->databaseName(),
            ]);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Falha ao criar o backup do banco: '.trim($process->getErrorOutput() ?: $process->getOutput()));
            }

            if (! is_file($destination) || filesize($destination) === 0) {
                throw new RuntimeException('O backup do banco foi gerado vazio.');
            }
        } finally {
            File::delete($defaultsFile);
        }
    }

    public function restore(string $source): void
    {
        $source = $this->absolutePath($source);
        if (! is_file($source) || filesize($source) === 0) {
            throw new RuntimeException('Arquivo de backup do banco inexistente ou vazio.');
        }

        $defaultsFile = $this->createDefaultsFile();
        $input = fopen($source, 'rb');
        if ($input === false) {
            File::delete($defaultsFile);
            throw new RuntimeException('Nao foi possivel abrir o backup do banco.');
        }

        try {
            $process = new Process([
                $this->binary('client_binary', 'mariadb'),
                '--defaults-extra-file='.$defaultsFile,
                $this->databaseName(),
            ]);
            $process->setInput($input);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Falha ao restaurar o banco: '.trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        } finally {
            fclose($input);
            File::delete($defaultsFile);
        }
    }

    private function createDefaultsFile(): string
    {
        $connection = $this->connection();
        $path = tempnam(sys_get_temp_dir(), 'farmfort-db-');
        if ($path === false) {
            throw new RuntimeException('Nao foi possivel criar o arquivo temporario do banco.');
        }

        $lines = [
            '[client]',
            'host='.$this->quoteOption((string) ($connection['host'] ?? '127.0.0.1')),
            'port='.(int) ($connection['port'] ?? 3306),
            'user='.$this->quoteOption((string) ($connection['username'] ?? '')),
            'password='.$this->quoteOption((string) ($connection['password'] ?? '')),
            'default-character-set='.$this->quoteOption((string) ($connection['charset'] ?? 'utf8mb4')),
        ];

        if (! empty($connection['unix_socket'])) {
            $lines[] = 'socket='.$this->quoteOption((string) $connection['unix_socket']);
        } else {
            $lines[] = 'protocol=tcp';
        }

        if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            File::delete($path);
            throw new RuntimeException('Nao foi possivel escrever o arquivo temporario do banco.');
        }

        @chmod($path, 0600);

        return $path;
    }

    private function connection(): array
    {
        $name = (string) config('database.default');
        $connection = config('database.connections.'.$name);

        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup de producao exige uma conexao MySQL ou MariaDB.');
        }

        return $connection;
    }

    private function binary(string $key, string $fallback): string
    {
        return (string) ($this->connection()[$key] ?? $fallback);
    }

    private function databaseName(): string
    {
        $database = trim((string) ($this->connection()['database'] ?? ''));
        if ($database === '') {
            throw new RuntimeException('O nome do banco nao foi configurado.');
        }

        return $database;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function quoteOption(string $value): string
    {
        return '"'.str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '', ''],
            $value
        ).'"';
    }
}
