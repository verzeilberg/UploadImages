<?php

namespace UploadImages\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use function str_replace;
use function trim;

/**
 * UploadImagesHelper is responsible for generating HTML strings
 * that represent icons for file types and directories.
 */
class UploadImagesHelper extends AbstractHelper
{

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Renders the appropriate icon based on the given file type and extension.
     *
     * @param array $folder
     * @return string The HTML string for the corresponding icon.
     */
    public function render(array $folder): string
    {
        $link = '';
        $path = '';
        if ($folder['path']) {
            $path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $folder['path']);
        }
        if ($folder['type'] === 'dir') {
            $link = '<a href="?url=' . $path . '"><i class="far fa-folder"></i> ' . $folder['name'] . '</a>';
        } else if ($folder['type'] === 'file') {
            switch ($folder['ext']) {
                case 'gif':
                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'jfif':
                    $link = '<i class="fas fa-image"></i> ' . $folder['name'];
                    break;
            }
        }

        return $link;
    }

    /**
     * Generates a breadcrumb navigation based on the given URL.
     *
     * @param string $url The URL from which the breadcrumb will be generated.
     * @return string The HTML string representing the breadcrumb navigation.
     */
    public function renderBreadcrumb($url)
    {
        $publicPath = $this->config['imageUploadSettings']['publicPath'];
        $url = str_replace($publicPath, '', $url);

        // De URL parsen en splitsen op basis van de slashes
        $parsedUrl = parse_url($url);
        $path = trim($parsedUrl['path'], '/');  // De leidende en afsluitende slashes verwijderen
        $pathParts = explode('/', $path);  // De onderdelen van de URL opsplitsen

        // Het kruimelpad genereren
        $breadcrumb = "<i class='fas fa-kiwi-bird'></i> <a href='?url=" . $publicPath . "'>".$publicPath."</a>";
        $currentPath = '';

        foreach ($pathParts as $part) {
            if (empty($part)) {
                continue;
            }
            $currentPath .= "/" . $part;  // Bouw het pad op tot de huidige folder
            $breadcrumb .= "/<a href='?url=" . $publicPath . $currentPath . "'>" . $part . "</a>";
        }

        // Het kruimelpad terugsturen
        return $breadcrumb;
    }

}
