<?php

namespace MdsSupportingClasses;

class GitHubPluginUpdater
{
    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $username; // GitHub username
    private $repo; // GitHub repo name
    private $pluginFile; // __FILE__ of our plugin
    private $githubAPIResult; // holds data from GitHub
    private $accessToken; // GitHub private repo token
    private $wasActivated; // Was the plugin active before updating

    /**
     * @param $pluginFile
     * @param $gitHubUsername
     * @param $gitHubProjectName
     * @param string $accessToken
     */
    public function __construct($pluginFile, $gitHubUsername, $gitHubProjectName, $accessToken = '')
    {
        $this->pluginFile = $pluginFile;
        $this->username = $gitHubUsername;
        $this->repo = $gitHubProjectName;
        $this->accessToken = $accessToken;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'setTransitent']);
        add_filter('plugins_api', [$this, 'setPluginInfo'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'postInstall'], 10, 3);
    }

    /**
     * Get information regarding our plugin from WordPress.
     */
    private function initPluginData()
    {
        $this->slug = plugin_basename($this->pluginFile);
        $this->pluginData = get_plugin_data($this->pluginFile);
        $this->wasActivated = is_plugin_active($this->slug);
    }

    /**
     * Get information regarding our plugin from GitHub.
     */
    private function getRepoReleaseInfo()
    {
        // Only do this once
        if (!empty($this->githubAPIResult)) {
            return;
        }

        // Query the GitHub API
        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

        // We need the access token for private repositories
        if (!empty($this->accessToken)) {
            $url = add_query_arg(['access_token' => $this->accessToken], $url);
        }

        // Get the results
        $this->githubAPIResult = wp_remote_retrieve_body(wp_remote_get($url));
        if (!empty($this->githubAPIResult)) {
            $this->githubAPIResult = @json_decode($this->githubAPIResult);
        }

        // Use only the latest release
        if (is_array($this->githubAPIResult)) {
            $this->githubAPIResult = $this->githubAPIResult[0];
        }
    }

    /**
     * Push in plugin version information to get the update notification.
     *
     * @param $transient
     *
     * @return mixed
     */
    public function setTransitent($transient)
    {
        // If we have checked the plugin data before, don't re-check
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        // Check the versions if we need to do an update
        $doUpdate = version_compare($this->githubAPIResult->tag_name, $transient->checked[$this->slug]);

        // Update the transient to include our updated plugin data
        if ($doUpdate == 1) {
            $package = $this->githubAPIResult->zipball_url;

            // Include the access token for private GitHub repos
            if (!empty($this->accessToken)) {
                $package = add_query_arg(['access_token' => $this->accessToken], $package);
            }

            $obj = new \stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $this->githubAPIResult->tag_name;
            $obj->url = $this->pluginData['PluginURI'];
            $obj->package = $package;
            $transient->response[$this->slug] = $obj;
        }

        return $transient;
    }

    /**
     * Push in plugin version information to display in the details lightbox.
     *
     * @param $false
     * @param $action
     * @param $response
     *
     * @return mixed
     */
    public function setPluginInfo($false, $action, $response)
    {
        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        // If nothing is found, do nothing
        if (empty($response->slug) || $response->slug != $this->slug) {
            return false;
        }

        // Add our plugin information
        $response->last_updated = $this->githubAPIResult->published_at;
        $response->slug = $this->slug;
        $response->plugin_name = $this->pluginData['Name'];
        $response->version = $this->githubAPIResult->tag_name;
        $response->author = $this->pluginData['AuthorName'];
        $response->homepage = $this->pluginData['PluginURI'];

        // This is our release download zip file
        $downloadLink = $this->githubAPIResult->zipball_url;

        // Include the access token for private GitHub repos
        if (!empty($this->accessToken)) {
            $downloadLink = add_query_arg(
                ['access_token' => $this->accessToken],
                $downloadLink
            );
        }
        $response->download_link = $downloadLink;

        // We're going to parse the GitHub markdown release notes, include the parser
        require_once plugin_dir_path(__FILE__).'ParseDown.php';

        // Create tabs in the lightbox
        $response->sections = [
            'description' => $this->pluginData['Description'],
            'changelog' => class_exists('ParseDown')
                    ? ParseDown::instance()->parse($this->githubAPIResult->body)
                    : $this->githubAPIResult->body,
        ];

        // Gets the required version of WP if available
        $matches = null;
        preg_match("/requires:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->requires = $matches[1];
                }
            }
        }

        // Gets the tested version of WP if available
        $matches = null;
        preg_match("/tested:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->tested = $matches[1];
                }
            }
        }

        return $response;
    }

    /**
     * Perform additional actions to successfully install our plugin.
     *
     * @param $true
     * @param $hook_extra
     * @param $result
     *
     * @return mixed
     */
    public function postInstall($true, $hook_extra, $result)
    {
        // Get plugin information
        $this->initPluginData();

        // Since we are hosted in GitHub, our plugin folder would have a dirname of
        // reponame-tagname change it to our original one:
        global $wp_filesystem;
        $pluginFolder = WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.dirname($this->slug);
        @$wp_filesystem->move($result['destination'], $pluginFolder);
        $result['destination'] = $pluginFolder;

        // Re-activate plugin if needed
        if ($this->wasActivated) {
            activate_plugin($this->slug);
        }

        MdsColliveryService::getInstance()
                           ->returnCacheClass()
                           ->delete();

        return $result;
    }
}
