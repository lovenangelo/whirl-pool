<?php

namespace App\Services\Clone;

use App\Services\Clone\WordpressSetup as CloneWordpressSetup;
use Exception;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use mysqli;
use mysqli_sql_exception;

class CloneService
{
    private array $steps = [];
    private array $status = ['message' => '', 'type' => 'info'];

    private $wordpressSetup;

    public function __construct()
    {
        $this->wordpressSetup = new CloneWordpressSetup();
    }

    public function cloneWordPressSite(array $config): array
    {
        try {
            $this->prepareConfig($config);

            $this->validateConfig($config);

            $this->validateSource($config);

            $this->processCloneType($config);

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

    private function prepareConfig(array &$config): void
    {
        $this->validateDatabase($config);
        $this->validatePaths($config);
        $this->assignConfigDefaults($config);
    }

    /**
     * Validates database configuration and checks database existence/availability
     *
     * @param array $config Database configuration array containing:
     *                     - targetDbName: Name of the target database
     *                     - sourceDbName: Name of the source database
     *                     - sourceDbHost: Source database host
     *                     - sourceDbUser: Source database username (optional)
     *                     - sourceDbPass: Source database password (optional)
     * @throws InvalidArgumentException If configuration is invalid
     * @throws mysqli_sql_exception If database validation fails
     */
    private function validateDatabase(array $config): void
    {
        // Validate required configuration keys
        $this->validateConfigStructure($config);

        // Validate database names
        $this->validateDatabaseName($config['targetDbName'], 'Target');
        $this->validateDatabaseName($config['sourceDbName'], 'Source');

        // Check database existence and accessibility
        $this->checkDatabaseExistence($config);
    }

    /**
     * Validates the structure of the configuration array
     *
     * @param array $config Configuration array
     * @throws InvalidArgumentException If required keys are missing
     */
    private function validateConfigStructure(array $config): void
    {
        $requiredKeys = ['targetDbName', 'sourceDbName', 'sourceDbHost'];

        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || !is_string($config[$key])) {
                throw new InvalidArgumentException("Missing or invalid required configuration key: '$key'");
            }
        }
    }

    /**
     * Validates a database name against MySQL naming conventions and security rules
     *
     * @param string $dbName Database name to validate
     * @param string $type Type of database (for error messages)
     * @throws mysqli_sql_exception If database name is invalid
     */
    private function validateDatabaseName(string $dbName, string $type): void
    {
        // Check if empty
        if (empty(trim($dbName))) {
            throw new mysqli_sql_exception("$type database name cannot be empty.");
        }

        // Check length (MySQL limit is 64 characters)
        if (strlen($dbName) > 64) {
            throw new mysqli_sql_exception("$type database name '$dbName' exceeds the maximum length of 64 characters.");
        }

        // Check for valid characters (alphanumeric, underscores, and dollar signs are allowed in MySQL)
        if (!preg_match('/^[a-zA-Z0-9_$]+$/', $dbName)) {
            throw new mysqli_sql_exception("$type database name '$dbName' contains invalid characters. Only alphanumeric characters, underscores, and dollar signs are allowed.");
        }

        // Check if starts with a number (not allowed in MySQL)
        if (preg_match('/^\d/', $dbName)) {
            throw new mysqli_sql_exception("$type database name '$dbName' cannot start with a number.");
        }

        // Check for reserved/system database names
        $reservedNames = ['mysql', 'information_schema', 'performance_schema', 'sys'];
        if (in_array(strtolower($dbName), $reservedNames)) {
            throw new mysqli_sql_exception("$type database name '$dbName' is reserved and cannot be used.");
        }

        // Additional security check for SQL injection patterns
        $dangerousPatterns = [
            '/--/',           // SQL comments
            '/;/',            // Statement terminators
            '/\/\*/',         // Multi-line comments start
            '/\*\//',         // Multi-line comments end
            '/\b(drop|delete|truncate|alter)\b/i', // Dangerous SQL keywords
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $dbName)) {
                throw new mysqli_sql_exception("$type database name '$dbName' contains potentially dangerous patterns.");
            }
        }
    }

    /**
     * Checks the existence and accessibility of source and target databases
     *
     * @param array $config Database configuration
     * @throws mysqli_sql_exception If database checks fail
     */
    private function checkDatabaseExistence(array $config): void
    {
        $connection = null;

        try {
            // Create connection
            $connection = $this->createDatabaseConnection($config);

            // Check if target database already exists
            if ($this->databaseExists($connection, $config['targetDbName'])) {
                throw new mysqli_sql_exception("Target database '{$config['targetDbName']}' already exists.");
            }

            // Check if source database exists and is accessible
            if (!$this->databaseExists($connection, $config['sourceDbName'])) {
                throw new mysqli_sql_exception("Source database '{$config['sourceDbName']}' does not exist or is not accessible.");
            }
        } catch (mysqli_sql_exception $e) {
            throw $e;
        } catch (Exception $e) {
            throw new mysqli_sql_exception("Database validation failed: " . $e->getMessage());
        } finally {
            // Ensure connection is closed
            if ($connection instanceof mysqli) {
                $connection->close();
            }
        }
    }

    /**
     * Creates a MySQL database connection
     *
     * @param array $config Database configuration
     * @return mysqli Database connection
     * @throws mysqli_sql_exception If connection fails
     */
    private function createDatabaseConnection(array $config): mysqli
    {
        $host = $config['sourceDbHost'];
        $username = $config['sourceDbUser'] ?? Env::get("WHIRL_POOL_SOURCE_DB_USERNAME");
        $password = $config['sourceDbPass'] ?? Env::get("WHIRL_POOL_SOURCE_DB_PASSWORD");

        // Validate credentials
        if (empty($username) || empty($password)) {
            throw new mysqli_sql_exception("Database credentials are missing or invalid.");
        }

        // Enable error reporting
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            // Create connection (note: we don't specify a database here for initial connection)
            $connection = new mysqli($host, $username, $password);

            // Set charset to prevent character set confusion attacks
            $connection->set_charset("utf8mb4");

            return $connection;
        } catch (mysqli_sql_exception $e) {
            throw new mysqli_sql_exception("Failed to connect to database server: " . $e->getMessage());
        }
    }

    /**
     * Checks if a database exists
     *
     * @param mysqli $connection Database connection
     * @param string $dbName Database name to check
     * @return bool True if database exists, false otherwise
     * @throws mysqli_sql_exception If query fails
     */
    private function databaseExists(mysqli $connection, string $dbName): bool
    {
        // Use prepared statement to prevent SQL injection
        $stmt = $connection->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");

        if (!$stmt) {
            throw new mysqli_sql_exception("Failed to prepare database existence check query: " . $connection->error);
        }

        try {
            $stmt->bind_param("s", $dbName);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->num_rows > 0;
        } finally {
            $stmt->close();
        }
    }

    private function validatePaths(array $config): void
    {
        $path = Env::get('WHIRL_POOL_SOURCE_PATH');
        if ($path === null) {
            throw new mysqli_sql_exception("Environment variable WHIRL_POOL_SOURCE_PATH is not set");
        }

        $source = $path . "/" . rtrim($config['sourcePath'], '/');
        $target = $path . "/" . rtrim($config['targetPath'], '/');

        if (isset($config['targetPath']) && $source === $target) {
            throw new mysqli_sql_exception("Source and clone name cannot be the same");
        }

        if (!is_dir($source)) {
            throw new mysqli_sql_exception("Source $source does not exist");
        }

        if (is_dir($target)) {
            throw new mysqli_sql_exception("Clone name $target already exists");
        }
    }

    private function assignConfigDefaults(array &$config): void
    {
        $sourcePath = isset($config['sourcePath'])
            ? Env::get('WHIRL_POOL_SOURCE_PATH') . "/" . rtrim($config['sourcePath'], '/')
            : Env::get('WHIRL_POOL_SOURCE_PATH', '/var/www/html');
        $targetPath = isset($config['sourcePath'])
            ? Env::get('WHIRL_POOL_SOURCE_PATH') . "/" . rtrim($config['targetPath'], '/')
            : Env::get('WHIRL_POOL_SOURCE_PATH', '/var/www/html');
        $sourceDbUser = isset($config['sourceDbUser'])
            ? rtrim($config['sourceDbUser'], '/')
            : Env::get('WHIRL_POOL_SOURCE_DB_USERNAME');
        $sourceDbPass = isset($config['sourceDbPass'])
            ? rtrim($config['sourceDbPass'], '/')
            : Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD');
        $targetDbUser = isset($config['targetDbUser'])
            ? rtrim($config['targetDbUser'], '/')
            : Env::get('WHIRL_POOL_SOURCE_DB_USERNAME');
        $targetDbPass = isset($config['targetDbPass'])
            ? rtrim($config['targetDbPass'], '/')
            : Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD');

        $config['sourcePath'] = $sourcePath;
        $config['sourceDbUser'] = $sourceDbUser;
        $config['sourceDbPass'] = $sourceDbPass;
        $config['targetDbUser'] = $targetDbUser;
        $config['targetDbPass'] = $targetDbPass;
        $config['targetPath'] = $targetPath;
    }

    private function validateSource(array $config): void
    {
        if ($config['cloneType'] !== 'database') {
            $this->addStep(0, 'Validating source WordPress installation...');
            if (!is_dir($config['sourcePath'])) {
                throw new mysqli_sql_exception("Source directory {$config['sourcePath']} does not exist");
            }
        } else {
        }
    }

    private function processCloneType(array $config): void
    {
        if (in_array($config['cloneType'], ['full', 'files'])) {
            $this->handleFileOperations($config);
        }

        if (in_array($config['cloneType'], ['full', 'database'])) {
            $this->handleDatabaseOperations($config);
        }

        if ($config['cloneType'] === 'full') {
            $this->wordpressSetup->setup($config, $config['targetPath'], $config['targetDbName']);
        }
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

        // Backup and restore database
        $this->addStep(4, 'Creating database backup...');
        $backupFile = $this->createDatabaseBackup($config);

        // Create target database
        $this->addStep(5, 'Creating target database...');
        $this->createTargetDatabase($config);

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
        $sourceDbHost = escapeshellarg($config['sourceDbHost']);
        $sourceDbUser = escapeshellarg($config['sourceDbUser']);
        $sourceDbName = escapeshellarg($config['sourceDbName']);
        $backupFile = storage_path("app/private/wp_clone_backup_" . $sourceDbName . time() . ".sql");

        $command = "MYSQL_PWD=" . escapeshellarg($config['sourceDbPass']) .
            " mysqldump -h $sourceDbHost -u $sourceDbUser $sourceDbName > $backupFile 2>&1";

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $errorMessage = implode("\n", $output);
            throw new mysqli_sql_exception("Failed to create database backup: " . $errorMessage);
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
