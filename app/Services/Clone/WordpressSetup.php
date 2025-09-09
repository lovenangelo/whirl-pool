<?php

namespace App\Services\Clone;

use Exception;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

ini_set('max_execution_time', 300); // 5 minutes

class WordpressSetup
{
    /**
     * WordPress Setup Function - PHP version of the bash script
     *
     * @param string $folderName The directory name for the WordPress installation
     * @param string $dbName The database name
     * @param array $config Optional configuration overrides
     * @return array Result with status, message, and credentials
     */
    public function setup($config, $targetPath, $dbName)
    {
        $config['webUser'] = $config['webUser'] ?? Env::get('WHIRL_POOL_WEB_USER', 'www-data');
        $config['webGroup'] = $config['webGroup'] ?? Env::get('WHIRL_POOL_WEB_GROUP', 'www-data');
        $config['dbUser'] = $config['dbUser'] ?? Env::get('WHIRL_POOL_SOURCE_DB_USERNAME', 'root');
        $config['dbPassword'] = $config['dbPassword'] ?? Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD', '');
        $config['dbHost'] = $config['dbHost'] ?? Env::get('WHIRL_POOL_SOURCE_DB_HOST', 'localhost');
        $config['baseUrl'] = $config['baseUrl'] ?? Env::get('WHIRL_POOL_BASE_URL', 'example.com');

        $startTime = microtime(true);
        $logContext = [
            'target_path' => $targetPath,
            'db_name' => $dbName,
            'config' => $config
        ];

        Log::info('WordPress setup started', $logContext);

        $result = [
            'success' => false,
            'message' => '',
            'credentials' => null,
            'deployment' => null
        ];

        // Validation
        if (empty($targetPath) || empty($dbName)) {
            $message = 'Folder name and database name are required';
            Log::error('WordPress setup validation failed', array_merge($logContext, [
                'error' => $message,
                'validation_failure' => 'missing_parameters'
            ]));
            $result['message'] = $message;
        } else {
            try {
                // Set permissions
                Log::info('Setting initial permissions', $logContext);
                if (!$this->setPermissions($targetPath, $config)) {
                    throw new Exception("Failed to set permissions");
                }
                Log::info('Initial permissions set successfully', $logContext);

                // Create wp-config.php
                Log::info('Creating wp-config.php', $logContext);
                if (!$this->createWpConfig($targetPath, $dbName, $config)) {
                    throw new Exception("Failed to create wp-config.php");
                }
                Log::info('wp-config.php created successfully', $logContext);

                // Install WordPress
                Log::info('Installing WordPress core', $logContext);
                if (!$this->installWordPress($targetPath, $config)) {
                    throw new Exception("Failed to install WordPress");
                }
                Log::info('WordPress core installed successfully', $logContext);

                // Configure plugins
                Log::info('Configuring plugins', $logContext);
                if (!$this->configurePlugins($targetPath)) {
                    throw new Exception("Failed to configure plugins");
                }
                Log::info('Plugins configured successfully', $logContext);

                // Final permissions
                Log::info('Setting final permissions', $logContext);
                $this->setFinalPermissions($targetPath, $config);
                Log::info('Final permissions set successfully', $logContext);

                // Success response
                $website = [
                    'admin_url' => "{$config['baseUrl']}/$targetPath/wp-admin",
                ];

                $duration = round(microtime(true) - $startTime, 2);
                Log::info('WordPress setup completed successfully', array_merge($logContext, [
                    'duration_seconds' => $duration,
                    'admin_url' => $website['admin_url']
                ]));

                $result['success'] = true;
                $result['message'] = 'WordPress cloning completed successfully';
                $result['deployment'] = $website;
            } catch (Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);
                Log::error('WordPress setup failed', array_merge($logContext, [
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString(),
                    'duration_seconds' => $duration,
                    'failure_stage' => $this->determineFailureStage($e->getMessage())
                ]));

                // Cleanup on failure
                Log::info('Starting cleanup after failure', $logContext);
                if (is_dir($targetPath)) {
                    $this->cleanupInstallation($targetPath, $dbName);
                    $this->cleanupFailedDatabase($dbName, $config);
                }
                Log::info('Cleanup completed', $logContext);

                $result['message'] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Determine which stage the setup failed at based on error message
     */
    private function determineFailureStage($errorMessage)
    {
        if (strpos($errorMessage, 'permissions') !== false) {
            return 'permissions';
        } elseif (strpos($errorMessage, 'wp-config') !== false) {
            return 'config_creation';
        } elseif (strpos($errorMessage, 'install WordPress') !== false) {
            return 'wordpress_installation';
        } elseif (strpos($errorMessage, 'plugins') !== false) {
            return 'plugin_configuration';
        } else {
            return 'unknown';
        }
    }

    /**
     * Cleanup database created during failed WordPress setup
     *
     * @param string $dbName The database name to drop
     * @param array $config Optional database configuration overrides
     * @return bool True if cleanup successful, false otherwise
     */
    public function cleanupFailedDatabase($dbName, $config = [])
    {
        $dbUser = $config['dbUser'];
        $dbPassword = $config['dbPassword'];
        $dbHost = $config['dbHost'];
        $logContext = ['db_name' => $dbName];
        Log::info('Starting database cleanup', $logContext);

        try {

            // Validate database name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
                throw new Exception("Invalid database name: $dbName");
            }

            // Drop database command
            $dropDbCommand = sprintf(
                'mysql -h %s -u %s -p%s -e "DROP DATABASE IF EXISTS %s;"',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPassword),
                escapeshellarg($dbName)
            );

            Log::debug('Executing database drop command', array_merge($logContext, [
                'db_host' => $dbHost,
                'db_user' => $dbUser
            ]));

            exec($dropDbCommand, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('Failed to drop database', array_merge($logContext, [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                    'db_host' => $dbHost,
                    'db_user' => $dbUser
                ]));
                return false;
            }

            Log::info('Database cleanup completed successfully', $logContext);
            return true;
        } catch (Exception $e) {
            Log::error('Database cleanup failed with exception', array_merge($logContext, [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]));
            return false;
        }
    }

    /**
     * Set file and directory permissions
     */
    private function setPermissions($folderName, $config)
    {
        $commands = [
            sprintf('find %s -type d -exec chmod 755 {} +', escapeshellarg($folderName)),
            sprintf('find %s -type f -exec chmod 644 {} +', escapeshellarg($folderName)),
            sprintf('chown -R %s:%s %s', $config['webUser'], $config['webGroup'], escapeshellarg($folderName))
        ];

        Log::debug('Setting permissions', [
            'folder_name' => $folderName,
            'commands' => $commands
        ]);

        foreach ($commands as $index => $command) {
            exec("$command", $output, $returnCode);

            Log::debug('Permission command executed', [
                'command_index' => $index,
                'command' => $command,
                'return_code' => $returnCode,
                'output' => $output
            ]);

            if ($returnCode !== 0) {
                Log::error('Permission command failed', [
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => $output,
                    'folder_name' => $folderName
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Create wp-config.php file
     */
    private function createWpConfig($fullPath, $dbName, $config)
    {
        chdir($fullPath);
        $command = sprintf(
            'wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbprefix=wp_ --extra-php=%s',
            escapeshellarg($dbName),
            escapeshellarg($config['dbUser']),
            escapeshellarg($config['dbPassword']),
            escapeshellarg($config['dbHost']),
            escapeshellarg("define('WP_DEBUG', false);")
        );

        Log::debug('Creating wp-config.php', [
            'full_path' => $fullPath,
            'db_name' => $dbName,
            'db_host' => $config['dbHost'],
            'db_user' => $config['dbUser']
        ]);

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('wp-config.php creation failed', [
                'full_path' => $fullPath,
                'db_name' => $dbName,
                'return_code' => $returnCode,
                'output' => $output
            ]);
        }

        return $returnCode === 0;
    }

    /**
     * Install WordPress with admin user
     */
    private function installWordPress($fullPath, $config)
    {
        chdir($fullPath);

        $baseUrl = Env::get('WHIRL_POOL_BASE_URL', 'example.com');
        if ($baseUrl === false) {
            Log::error('Base URL not set in environment variables', [
                'full_path' => $fullPath
            ]);
            throw new Exception("Base URL is not set in environment variables");
        }

        $siteUrl = "{$baseUrl}/$config[targetPath]";

        $command = sprintf(
            'wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s',
            escapeshellarg($siteUrl),
            escapeshellarg($config['targetPath']),
            escapeshellarg(isset($config['adminUser']) ? $config['adminUser'] : 'admin'),
            escapeshellarg(isset($config['adminPassword']) ? $config['adminPassword'] : 'password'),
            escapeshellarg(isset($config['adminEmail']) ? $config['adminEmail'] : 'ilovewhirlpool@example.com')
        );

        Log::debug('Installing WordPress', [
            'full_path' => $fullPath,
            'site_url' => $siteUrl,
            'admin_user' => isset($config['adminUser']) ? $config['adminUser'] : 'admin',
            'admin_email' => isset($config['adminEmail']) ? $config['adminEmail'] : 'ilovewhirlpool@example.com'
        ]);

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('WordPress installation failed', [
                'full_path' => $fullPath,
                'site_url' => $siteUrl,
                'return_code' => $returnCode,
                'output' => $output
            ]);
        }

        chdir($fullPath);
        return $returnCode === 0;
    }

    /**
     * Configure plugins (install migration plugin, remove default plugins)
     */
    private function configurePlugins($fullPath)
    {
        $commands = [
            'wp plugin install all-in-one-wp-migration --allow-root',
            'wp plugin deactivate akismet --allow-root',
            'wp plugin delete akismet --allow-root',
            'rm -f wp-content/plugins/hello.php'
        ];

        Log::debug('Configuring plugins', [
            'full_path' => $fullPath,
            'commands' => $commands
        ]);

        foreach ($commands as $index => $command) {
            exec($command, $output, $returnCode);

            Log::debug('Plugin command executed', [
                'command_index' => $index,
                'command' => $command,
                'return_code' => $returnCode,
                'output' => $output,
                'full_path' => $fullPath
            ]);

            // Log warnings for failed plugin commands but don't fail the entire process
            if ($returnCode !== 0) {
                Log::warning('Plugin command failed (continuing)', [
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => $output,
                    'full_path' => $fullPath
                ]);
            }
        }
        return true;
    }

    /**
     * Set final permissions
     */
    private function setFinalPermissions($folderName, $config)
    {
        $command = sprintf(
            'chown -R %s:%s %s',
            $config['webUser'] ?? 'www-data',
            $config['webGroup'] ?? 'www-data',
            escapeshellarg($folderName)
        );

        Log::debug('Setting final permissions', [
            'folder_name' => $folderName,
            'command' => $command
        ]);

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning('Final permissions command failed', [
                'command' => $command,
                'return_code' => $returnCode,
                'output' => $output,
                'folder_name' => $folderName
            ]);
        }
    }

    /**
     * Cleanup failed installation
     */
    private function cleanupInstallation($folderName, $dbName)
    {
        Log::info('Starting cleanup', [
            'folder_name' => $folderName,
            'db_name' => $dbName
        ]);

        // Remove directory
        if (is_dir($folderName)) {
            $rmCommand = sprintf('rm -rf %s', escapeshellarg($folderName));
            Log::debug('Removing directory', [
                'folder_name' => $folderName,
                'command' => $rmCommand
            ]);
            exec($rmCommand, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('Failed to remove directory during cleanup', [
                    'folder_name' => $folderName,
                    'return_code' => $returnCode,
                    'output' => $output
                ]);
            }
        }

        // Drop database
        $dropDbCommand = sprintf(
            'mysql -u %s -p%s -e "DROP DATABASE IF EXISTS %s;"',
            escapeshellarg(Env::get('WHIRL_POOL_SOURCE_DB_USERNAME', 'root')),
            escapeshellarg(Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD', '')),
            escapeshellarg($dbName)
        );

        Log::debug('Dropping database', [
            'db_name' => $dbName
        ]);

        exec($dropDbCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('Failed to drop database during cleanup', [
                'db_name' => $dbName,
                'return_code' => $returnCode,
                'output' => $output
            ]);
        } else {
            Log::info('Database dropped successfully during cleanup', [
                'db_name' => $dbName
            ]);
        }

        Log::info('Cleanup completed', [
            'folder_name' => $folderName,
            'db_name' => $dbName
        ]);
    }
}
