<?php

namespace App\Services\Clone;

use Exception;
use Illuminate\Support\Env;

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
        $result = [
            'success' => false,
            'message' => '',
            'credentials' => null,
            'deployment' => null
        ];

        // Validation
        if (empty($targetPath) || empty($dbName)) {
            $result['message'] = 'Folder name and database name are required';
        } elseif (is_dir($targetPath)) {
            $result['message'] = "Directory '$targetPath' already exists. Operation cancelled.";
        } else {
            try {
                // Set permissions
                if (!$this->setPermissions($targetPath, $config)) {
                    throw new Exception("Failed to set permissions");
                }

                // Create wp-config.php
                if (!$this->createWpConfig($targetPath, $dbName, $config)) {
                    throw new Exception("Failed to create wp-config.php");
                }

                // Install WordPress
                if (!$this->installWordPress($targetPath, $config)) {
                    throw new Exception("Failed to install WordPress");
                }

                // Configure plugins
                if (!$this->configurePlugins($targetPath)) {
                    throw new Exception("Failed to configure plugins");
                }

                // Final permissions
                $this->setFinalPermissions($targetPath, $config);

                // Success response
                $website = [
                    'admin_url' => "{$config['baseUrl']}/$targetPath/wp-admin",
                ];

                $result['success'] = true;
                $result['message'] = 'WordPress cloning completed successfully';
                $result['deployment'] = $website;
            } catch (Exception $e) {
                // Cleanup on failure
                if (is_dir($targetPath)) {
                    $this->cleanupInstallation($targetPath, $dbName);
                }
                $result['message'] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Create directory and set initial permissions
     */
    private function createDirectory($folderName, $config)
    {
        if (!mkdir($folderName, 0775, true)) {
            return false;
        }

        chmod($folderName, 0775);

        $chownCommand = sprintf(
            'chown %s:%s %s',
            $config['webUser'],
            $config['webGroup'],
            escapeshellarg($folderName)
        );

        exec($chownCommand, $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Download WordPress core files
     */
    private function downloadWordPressCore($folderName)
    {
        $originalDir = getcwd();
        chdir($folderName);

        exec('wp core download --allow-root', $output, $returnCode);

        chdir($originalDir);
        return $returnCode === 0;
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

        foreach ($commands as $command) {
            exec("sudo $command", $output, $returnCode);
            if ($returnCode !== 0) {
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
            'sudo -u %s wp config create --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --dbprefix=wp_ --extra-php=%s',
            $config['webUser'],
            escapeshellarg($dbName),
            escapeshellarg($config['dbUser']),
            escapeshellarg($config['dbPassword']),
            escapeshellarg($config['dbHost']),
            escapeshellarg("define('WP_DEBUG', false);")
        );

        exec($command, $output, $returnCode);
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
            throw new Exception("Base URL is not set in environment variables");
        }

        $siteUrl = "{$baseUrl}/$config[targetPath]";

        $command = sprintf(
            'sudo -u %s wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s',
            $config['webUser'],
            escapeshellarg($siteUrl),
            escapeshellarg($config['targetPath']),
            escapeshellarg(isset($config['adminUser']) ? $config['adminUser'] : 'admin'),
            escapeshellarg(isset($config['adminPassword']) ? $config['adminPassword'] : 'password'),
            escapeshellarg(isset($config['adminEmail']) ? $config['adminEmail'] : 'ilovewhirlpool@example.com')
        );

        exec($command, $output, $returnCode);

        chdir($fullPath);
        return $returnCode === 0;
    }

    /**
     * Configure plugins (install migration plugin, remove default plugins)
     */
    private function configurePlugins()
    {
        $commands = [
            'wp plugin install all-in-one-wp-migration --allow-root',
            'wp plugin deactivate akismet --allow-root',
            'wp plugin delete akismet --allow-root',
            'rm -f wp-content/plugins/hello.php'
        ];

        foreach ($commands as $command) {
            exec($command, $output, $returnCode);
            // Continue even if some commands fail (plugins might not exist)
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
            isset($config['webUser']) ? $config['webUser'] : 'www-data',
            isset($config['webGroup']) ? $config['webGroup'] : 'www-data',
            escapeshellarg($folderName)
        );

        exec($command);
    }

    /**
     * Cleanup failed installation
     */
    private function cleanupInstallation($folderName, $dbName)
    {
        // Remove directory
        if (is_dir($folderName)) {
            exec(sprintf('rm -rf %s', escapeshellarg($folderName)));
        }

        // Drop database
        $dropDbCommand = sprintf(
            'mysql -u %s -p%s -e "DROP DATABASE IF EXISTS %s;"',
            escapeshellarg(Env::get('WHIRL_POOL_SOURCE_DB_USERNAME', 'root')),
            escapeshellarg(Env::get('WHIRL_POOL_SOURCE_DB_PASSWORD', '')),
            escapeshellarg($dbName)
        );

        exec($dropDbCommand);
    }
}
