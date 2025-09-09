<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use mysqli;
use mysqli_sql_exception;

class CloneService
{
    private array $steps = [];
    private array $status = ['message' => '', 'type' => 'info'];

    public function cloneWordPressSite(array $config): array
    {
        try {
            $sourcePath = isset($config['sourcePath']) ? rtrim($config['sourcePath'], '/') : Env::get('WHIRL_POOL_SOURCE_PATH', '/var/www/html');
            $sourceDbUser = isset($config['sourceDbUser']) ? rtrim($config['sourceDbUser'], '/') : Env::get('WHIRL_POOL_SOURCE_DB_USERNAME');
            $sourceDbPass = isset($config['sourcePath']) ? rtrim($config['sourceDbPassword'], '/') : Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD');
            $targetDbUser = isset($config['targetDbUser']) ? rtrim($config['targetDbUser'], '/') : Env::get('WHIRL_POOL_SOURCE_DB_USERNAME');
            $targetDbPass = isset($config['targetDbPass']) ? rtrim($config['targetDbPass'], '/') : Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD');

            $config['sourcePath'] = $sourcePath;
            $config['sourceDbUser'] = $sourceDbUser;
            $config['sourceDbPass'] = $sourceDbPass;
            $config['targetDbUser'] = $targetDbUser;
            $config['targetDbPass'] = $targetDbPass;

            $this->validateConfig($config);
            // Validate source directory
            $this->addStep(0, 'Validating source WordPress installation...');
            if (!is_dir($sourcePath)) {
                throw new mysqli_sql_exception("Source directory {$config['sourcePath']} does not exist");
            }

            // Handle file operations
            if (in_array($config['cloneType'], ['full', 'files'])) {
                $this->handleFileOperations($config);
            }

            // Handle database operations
            if (in_array($config['cloneType'], ['full', 'database'])) {
                $this->handleDatabaseOperations($config);
            }

            // Handle WordPress configuration updates
            if ($config['cloneType'] === 'full') {
                $this->updateWordPressConfig($config);

                if (!empty($config['newDomain'])) {
                    $this->updateSiteUrls($config);
                }

                $this->setFilePermissions($config['targetPath']);
            }

            $this->addStep(9, 'Verifying cloned installation...');
            $this->addStep(10, 'Clone completed successfully!');
            $this->setStatus('WordPress site cloned successfully!', 'success');
        } catch (Exception $e) {
            Log::error('WordPress clone failed', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);

            $this->setStatus('Error: ' . $e->getMessage(), 'error');
        }

        return [
            'errors' => $this->status['type'] === 'error' ? [$this->status['message']] : [],
            'status' => $this->status,
            'steps' => $this->steps
        ];
    }

    private function validateConfig(array $config): void
    {
        $required = ['sourcePath', 'cloneType'];

        if (in_array($config['cloneType'], ['full', 'files'])) {
            $required[] = 'targetPath';
        }

        if (in_array($config['cloneType'], ['full', 'database'])) {
            $required = array_merge($required, [
                'sourceDbHost',
                'sourceDbName',
                'sourceDbUser',
                'sourceDbPass',
                'targetDbHost',
                'targetDbName',
                'targetDbUser',
                'targetDbPass'
            ]);
        }

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new mysqli_sql_exception("Required field '$field' is missing");
            }
        }
    }

    private function handleFileOperations(array $config): void
    {
        $this->addStep(1, 'Creating target directory structure...');

        if (!mkdir($config['targetPath'], 0755, true) && !is_dir($config['targetPath'])) {
            throw new mysqli_sql_exception("Failed to create target directory {$config['targetPath']}");
        }

        $this->addStep(2, 'Copying WordPress files...');

        $sourcePath = escapeshellarg($config['sourcePath']);
        $targetPath = escapeshellarg($config['targetPath']);

        exec("cp -r $sourcePath/* $targetPath/", $output, $returnVar);

        if ($returnVar !== 0) {
            throw new mysqli_sql_exception("Failed to copy WordPress files");
        }
    }

    private function handleDatabaseOperations(array $config): void
    {
        // Check if source database exists
        $this->addStep(3, 'Validating source database connection...');
        $this->validateDatabaseConnection($config['sourceDbHost'], $config['sourceDbName'], $config['sourceDbUser'], $config['sourceDbPass']);
        // Create target database
        $this->addStep(4, 'Creating target database...');
        $this->createTargetDatabase($config);

        // Backup and restore database
        $this->addStep(5, 'Creating database backup...');
        $backupFile = $this->createDatabaseBackup($config);

        $this->addStep(6, 'Importing database to target...');
        $this->importDatabase($config, $backupFile);

        // Clean up backup file
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
    }

    private function validateDatabaseConnection(string $host, string $dbName, string $user, string $pass): void
    {
        $conn = new mysqli($host, $user, $pass, $dbName);

        if ($conn->connect_error) {
            throw new mysqli_sql_exception("Connection failed: " . $conn->connect_error);
        }

        $conn->close();
    }

    private function createTargetDatabase(array $config): void
    {
        $conn = new mysqli(
            $config['targetDbHost'],
            Env::get('DB_USERNAME', 'root'),
            Env::get('DB_PASSWORD', ''),
        );

        if ($conn->connect_error) {
            throw new mysqli_sql_exception("Target DB connection failed: " . $conn->connect_error);
        }

        $targetDbName = $conn->real_escape_string($config['targetDbName']);

        $query = "SHOW DATABASES LIKE '$targetDbName'";

        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $error = "Database '$targetDbName' exists";
            throw new mysqli_sql_exception("Failed to create target database: " . $error);
        }

        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$targetDbName`")) {
            throw new mysqli_sql_exception("Failed to create target database: " . $conn->error);
        }

        $conn->close();
    }

    private function createDatabaseBackup(array $config): string
    {
        $backupFile = storage_path("app/wp_clone_backup_" . time() . ".sql");

        $sourceDbHost = escapeshellarg($config['sourceDbHost']);
        $sourceDbUser = escapeshellarg($config['sourceDbUser']);
        $sourceDbPass = escapeshellarg($config['sourceDbPass']);
        $sourceDbName = escapeshellarg($config['sourceDbName']);

        $command = "mysqldump -h $sourceDbHost -u $sourceDbUser -p$sourceDbPass $sourceDbName > $backupFile";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new mysqli_sql_exception("Failed to create database backup");
        }

        return $backupFile;
    }

    private function importDatabase(array $config, string $backupFile): void
    {
        $targetDbHost = escapeshellarg($config['targetDbHost']);
        $targetDbUser = escapeshellarg($config['targetDbUser']);
        $targetDbPass = escapeshellarg($config['targetDbPass']);
        $targetDbName = escapeshellarg($config['targetDbName']);

        $command = "mysql -h $targetDbHost -u $targetDbUser -p$targetDbPass $targetDbName < $backupFile";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new mysqli_sql_exception("Failed to import database");
        }
    }

    private function updateWordPressConfig(array $config): void
    {
        $this->addStep(6, 'Updating wp-config.php...');

        $wpConfigPath = rtrim($config['targetPath'], '/') . '/wp-config.php';

        if (!file_exists($wpConfigPath)) {
            throw new mysqli_sql_exception("wp-config.php not found at $wpConfigPath");
        }

        $content = file_get_contents($wpConfigPath);

        $replacements = [
            'DB_NAME' => $config['targetDbName'],
            'DB_USER' => $config['targetDbUser'],
            'DB_PASSWORD' => $config['targetDbPass'],
            'DB_HOST' => $config['targetDbHost'],
        ];

        foreach ($replacements as $constant => $value) {
            $pattern = "/define\s*\(\s*'$constant'\s*,.*?\);/";
            $replacement = "define('$constant', '$value');";
            $content = preg_replace($pattern, $replacement, $content);
        }

        file_put_contents($wpConfigPath, $content);
    }

    private function updateSiteUrls(array $config): void
    {
        $this->addStep(7, 'Updating site URLs...');

        $conn = new mysqli(
            $config['targetDbHost'],
            $config['targetDbUser'],
            $config['targetDbPass'],
            $config['targetDbName']
        );

        if ($conn->connect_error) {
            throw new mysqli_sql_exception("Target DB connection failed: " . $conn->connect_error);
        }

        // Get old domain
        $result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='home' LIMIT 1");
        $oldDomain = $result ? $result->fetch_row()[0] : '';

        if ($oldDomain) {
            $newDomain = $conn->real_escape_string($config['newDomain']);
            $oldDomainEscaped = $conn->real_escape_string($oldDomain);

            // Update WordPress options
            $conn->query("UPDATE wp_options SET option_value='$newDomain' WHERE option_name='home'");
            $conn->query("UPDATE wp_options SET option_value='$newDomain' WHERE option_name='siteurl'");

            // Update content
            $conn->query("UPDATE wp_posts SET post_content=REPLACE(post_content, '$oldDomainEscaped', '$newDomain')");
            $conn->query("UPDATE wp_comments SET comment_content=REPLACE(comment_content, '$oldDomainEscaped', '$newDomain')");
        }

        $conn->close();
    }

    private function setFilePermissions(string $targetPath): void
    {
        $this->addStep(8, 'Setting proper file permissions...');

        $targetPathEscaped = escapeshellarg($targetPath);

        exec("chown -R www-data:www-data $targetPathEscaped");
        exec("find $targetPathEscaped -type d -exec chmod 755 {} \\;");
        exec("find $targetPathEscaped -type f -exec chmod 644 {} \\;");
        exec("chmod 600 $targetPathEscaped/wp-config.php");
    }

    private function addStep(int $step, string $message): void
    {
        $this->steps[] = [
            'step' => $step,
            'message' => $message
        ];
    }

    private function setStatus(string $message, string $type = 'info'): void
    {
        $this->status['message'] = $message;
        $this->status['type'] = $type;
    }
}
