<?php

namespace UploadImages\Service;

use DirectoryIterator;
use Laminas\Form\Annotation\AnnotationBuilder;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrineAdapter;
use Doctrine\ORM\Tools\Pagination\Paginator as ORMPaginator;
use Laminas\Paginator\Paginator;

/*
 * Entities
 */

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\VarDumper\VarDumper;
use UploadImages\Entity\Image;
use UploadImages\Entity\ImageType;
use UploadImages\Exception\DeleteFileException;
use UploadImages\Exception\DeleteFolderException;
use function array_reverse;
use function array_slice;
use function ceil;
use function count;
use function end;
use function imap_fetch_overview;
use function imap_headerinfo;
use function imap_num_msg;
use function str_split;

class imageService
{

    protected $config;
    protected $em;

    public function __construct($em, $config)
    {
        $this->config = $config;
        $this->em = $em;
    }

    /**
     *
     * Create image object
     *
     * @return   object
     *
     */
    public function createImage()
    {
        return new Image();
    }

    /**
     *
     * Create image form
     *
     * @param image $image object
     * @return   form
     *
     */
    public function createImageForm($image)
    {
        $builder = new AnnotationBuilder($this->em);
        $formImage = $builder->createForm($image);
        $formImage->setHydrator(new DoctrineHydrator($this->em, 'UploadImages\Entity\Image'));
        $formImage->bind($image);

        return $formImage;
    }

    /*
     *
     * Delete image object
     *
     * @param type $image object
     * @return void
     *
     */

    /**
     * @throws DeleteFileException
     */
    public function deleteImage($image = null)
    {

        if (is_object($image)) {
            $imageTypes = $image->getImageTypes();
            foreach ($imageTypes as $imageType) {
                $this->deleteFile('public/' . $imageType->getFolder() . $imageType->getFileName());
                $this->em->remove($imageType);
            }

            $this->em->remove($image);
            $this->em->flush();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $imageUrl
     * @return bool
     */
    public function deleteImageFromServer($imageUrl = null)
    {
        $result = false;
        if (!empty($imageUrl)) {
            $this->deleteFile('public/' . $imageUrl);
        }
        return $result;
    }

    /**
     * @throws DeleteFolderException
     * @throws DeleteFileException
     */
    public function deleteDirectoryFromServer($directory) {
        // Check if the directory exists
        if (!is_dir($directory)) {
            throw new DeleteFolderException(sprintf('Directory "%s" doesn\'t excist', $directory));
        }

        // Create a DirectoryIterator instance
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            // If it's a directory, recursively delete it
            if ($fileinfo->isDir()) {
                $this->deleteDirectoryFromServer($fileinfo->getPathname());
            } else {
                // If it's a file, delete it
                $this->deleteFile($fileinfo->getPathname());
            }
        }


        // After deleting the contents, delete the directory itself
        if (!rmdir($directory)) {
            $error = error_get_last();
            throw new DeleteFolderException($error['message']);
        }

        return true;

    }

    /**
     *
     * Delete array of images
     *
     * @param type $images array
     * @return void
     *
     */
    public function deleteImages($images = null)
    {
        if (!empty($images)) {
            foreach ($images as $image) {
                $imageTypes = $image->getImageTypes();
                foreach ($imageTypes as $imageType) {
                    $this->deleteFile('public/' . $imageType->getFolder() . $imageType->getFileName());
                    $this->em->remove($imageType);
                }
                $this->em->remove($image);
                $this->em->flush();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * Create redirect URL
     *
     * @param aReturnURL $aReturnURL array
     * @return   redirect
     *
     */
    public function createRedirectLink($aReturnURL = null): redirect
    {
        if ($aReturnURL === null) {
            $this->redirect()->toRoute('home');
        } else {
            $route = $aReturnURL['route'];
            unset($aReturnURL['route']);
            return $this->redirect()->toRoute($route, $aReturnURL);
        }
    }

    /**
     * @param $rootPath
     * @return array
     */
    public function getAllFilesAndFolders($rootPath): array
    {
        $folders = [];
        $dir = new \DirectoryIterator($_SERVER['DOCUMENT_ROOT'] . $rootPath);
        foreach ($dir as $index => $fileinfo) {
            if (!$fileinfo->isDot()) {
                    $type = $fileinfo->getType();
                    $size = $fileinfo->getSize();
                    $path = $fileinfo->getRealPath();
                    if ($type === 'dir') {
                        $size = $this->getFolderSize($path);
                    }

                    $folders[$index]['prio']    = $type === 'dir'? 1:2;
                    $folders[$index]['type']    = $type;
                    $folders[$index]['name']    = $fileinfo->getFilename();
                    $folders[$index]['ext']     = $fileinfo->getExtension();
                    $folders[$index]['path']    = $path;
                    $folders[$index]['size']    = number_format($size / 1000, 2, ',', '');;
            }
        }
        //Sort folders and files by prio and name
        usort($folders, function ($a, $b) {
            // First, compare by 'prio'
            if ($a['prio'] == $b['prio']) {
                // If 'prio' is the same, compare by 'name'
                return strtolower($a['name']) <=> strtolower($b['name']);
            }
            // Compare by 'prio'
            return $a['prio'] <=> $b['prio'];
        });

        return $folders;
    }

    /**
     *
     * Get all images from specific folder
     *
     * @param $rootPath string
     * @return   array
     *
     */
    public function getAllImageFromFolder($rootPath): array
    {
        $images = [];
        $dir = new \DirectoryIterator($rootPath);

        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                if ($fileinfo->getType() == 'dir') {
                    $images = array_merge($images,
                        $this->getAllImageFromFolder($rootPath . '/' . $fileinfo->getFilename()));
                } else {
                    $image = [];
                    $image['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $fileinfo->getPathname());
                    $image['fileName'] = $fileinfo->getFilename();
                    $image['fileSize'] = $fileinfo->getSize();
                    $image['type'] = $fileinfo->getType();
                    $image['ext'] = $fileinfo->getExtension();
                    $image['baseName'] = $fileinfo->getBasename();

                    $images[] = $image;
                }
            }
        }

        return $images;


    }

    /**
     * Search for a file or folder for given path (haystack)
     * @param $needle
     * @param $haystack
     * @return array
     */
    public function searchForImageOrFolder($needle, $haystack): array
    {
        $haystack = $_SERVER['DOCUMENT_ROOT'] . $haystack;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($haystack));
        $files = [];
        $index = 0;
        foreach ($it as $fileinfo) {
            if (strpos(strtolower(basename($fileinfo)), strtolower($needle)) !== false) {

                $type = $fileinfo->getType();
                $size = $fileinfo->getSize();
                $path = $fileinfo->getRealPath();
                if ($type === 'dir') {
                    $size = $this->getFolderSize($path);
                }

                $files[$index]['prio']      = $type === 'dir'? 1:2;
                $files[$index]['type']      = $type;
                $files[$index]['name']      = $fileinfo->getFilename();
                $files[$index]['ext']       = $fileinfo->getExtension();
                $files[$index]['path']      = $path;
                $files[$index]['size']      = number_format($size / 1000, 2, ',', '');

                $index++;
            }
        }
        return $files;
    }

    /**
     * Get all Images from the database
     * @return mixed
     */
    public function getAllImages()
    {
        return $this->em->getRepository(Image::class)->findAll();
    }

    public function getAllImageTypes()
    {
        return $this->em->getRepository(ImageType::class)->findAll();
    }


    /**
     * Get array of images
     * @return      query
     */
    public function getImages()
    {
        $qb = $this->em->getRepository(ImageType::class)->createQueryBuilder('i')
            ->orderBy('i.fileName', 'DESC');

        return $qb->getQuery();
    }

    /**
     * @param $items
     * @param $itemsPage
     * @param $currentPage
     * @param $pageRange
     * @return array
     */
    public function createPagination($items = [], $itemsPage = 10, $currentPage = 1, $pageRange = 10)
    {
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $itemsPage);
        $arr2 = str_split($totalPages); // convert string to an array
        $endNumber = end($arr2);
        $endRange = $totalPages - $endNumber;

        $pagination = [];

        $arr = str_split($currentPage); // convert string to an array
        $calcNumber = end($arr);


        if (count($arr) > 1) {
            $backward = $currentPage - $calcNumber;
        } else {
            $backward = $currentPage - ($calcNumber - 1);
        }
        $forward = $currentPage + ($pageRange - $calcNumber);

        $previousPage = $currentPage - 1;
        $nextPage = $currentPage + 1;

        $pageRangeStart = $currentPage;
        $pageRangeEnd = $currentPage + $pageRange;

        $pagination['currentPage'] = $currentPage;
        $pagination['previousPage'] = $previousPage;
        $pagination['nextPage'] = $nextPage;
        $pagination['totalPages'] = $totalPages;
        $pagination['pageRangeStart'] = $backward;
        $pagination['pageRangeEnd'] = $forward;
        $pagination['pageRange'] = $pageRange;
        $pagination['endRange'] = $endRange;
        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination['pages'][$i] = $i;
        }
        return $pagination;
    }

    /**
     * @param $items
     * @param $itemsPage
     * @param $currentPage
     * @return array
     */
    public function getImagesForPagination($items, $itemsPage = 10, $currentPage = 1)
    {
        $totalImages = count($items);
        $length = $itemsPage;
        $start = $totalImages - ($currentPage * ($itemsPage));

        if ($start < 1) {
            $length = (int)$length + (int)$start;
            $start = 0;

        }

        return array_slice(array_reverse($items), $start, $length);
    }

    /**
     * Get array of languages  for pagination
     * @return      array
     * @var $currentPage current page
     * @var $itemsPerPage items on a page
     * @var $query query
     */
    public function getItemsForPagination($query, $currentPage = 1, $itemsPerPage = 10)
    {
        $adapter = new DoctrineAdapter(new ORMPaginator($query, false));
        $paginator = new Paginator($adapter);
        $paginator->setDefaultItemCountPerPage($itemsPerPage);
        $paginator->setCurrentPageNumber($currentPage);
        return $paginator;
    }

    /**
     * Get all imageTypes filename and folder by ImageID
     * @return   array
     * @var $imageId Image id
     */
    public function getOriginalImageByImageID($imageId)
    {
        //Get original image file
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder');
        $qb->join('i.imageTypes', 'it');
        $qb->where('it.isOriginal = 1');
        $qb->andWhere('i.ImageId = ' . $imageId);
        $imageOriginal = $qb->getQuery()->getSingleResult();

        return $imageOriginal;
    }

    /**
     * Get all imageTypes filename and folder by ImageID
     * @return   array
     * @var $imageId Image id
     */
    public function getImageTypesByImageID($imageId)
    {
        //Get all Crop images
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder');
        $qb->join('i.imageTypes', 'it');
        $qb->where('i.ImageId = ' . $imageId);
        $imageTypes = $qb->getQuery()->getArrayResult();

        return $imageTypes;
    }

    /**
     * Find image by path and image name
     * @param path $path string
     * @param name $name string
     * @return   boolean
     */
    public function findImageByPathAndName($path = null, $name = null)
    {
        $path = trim($path);
        $name = trim($name);
        if (!empty($path) && !empty($name)) {
            $qb = $this->em->getRepository('UploadImages\Entity\ImageType')->createQueryBuilder('it');
            $qb->where('it.fileName = :name');
            $qb->andWhere('it.folder = :path');
            $qb->setParameter('path', $path);
            $qb->setParameter('name', $name);
            $query = $qb->getQuery();
            $single = $query->getScalarResult();
            if (empty($single)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Check if a image exist on the server
     * @param $path
     * @param $name
     * @param $rootPath
     * @return bool
     */
    public function checkFileExcist($path = null, $name = null, $rootPath = null)
    {
        $path = trim($path);
        $name = trim($name);
        $rootPath = trim($rootPath);
        if (!empty($path) && !empty($name) && !empty($rootPath)) {
            $fullUrl = 'public/' . $path . $name;
            if (file_exists($fullUrl)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $path
     * @return bool
     */
    public function checkFileExistInDatabase($path): bool
    {
        $result = false;
        $fileParts = $this->getFileAndFolderName($path);
        $imageType = $this->em->getRepository(ImageType::class)
            ->findOneBy(
                [
                    'fileName'  => $fileParts['fileName'],
                    'folder'    => $fileParts['folder']
                ], []);
        if ($imageType) {
            $result = true;
        }

        return $result;
    }

    /**
     * Get imageType object based on id
     * @param id $id The id to fetch the imageType from the database
     * @return      object
     */
    public function getImageTypeById($id)
    {
        $imageType = $this->em->getRepository(ImageType::class)
            ->findOneBy(['id' => $id], []);

        return $imageType;
    }

    /**
     * Delete imageType
     * @param null $imageType object
     * @return void
     */
    public function deleteImageType($imageType = null)
    {
        if ($imageType != null) {
            $this->em->remove($imageType);
            $this->em->flush();

            return true;
        } else {
            return false;
        }
    }

    /**
     * Delete imageType
     * @param type $imageType object
     * @return void
     */
    public function saveImage($image)
    {
        $this->em->persist($image);
        $this->em->flush();
    }

    /**
     *
     * Extract folder and file name from the URL.
     *
     * @param string $url The URL from which to extract the folder and file name.
     * @return array An associative array containing 'folder' and 'fileName'.
     *
     */
    private function getFileAndFolderName(string $url): array
    {
        $result = [];
        $lastSlashPos = strrpos($url, '/');
        if ($lastSlashPos !== false) {
            // Deel voor de laatste slash
            $folder = substr($url, 0, $lastSlashPos);
            // Deel na de laatste slash
            $fileName = substr($url, $lastSlashPos + 1);
            $result['folder'] = ltrim($folder, '/') . '/';
            $result['fileName'] = $fileName;
        }

        return $result;
    }


    /**
     * @param $folderPath
     * @return int
     */
    private function getFolderSize($folderPath): int
    {
        $size = 0;

        // Controleer of het pad een geldige directory is
        if (is_dir($folderPath)) {
            $directory = new DirectoryIterator($folderPath);

            foreach ($directory as $fileinfo) {
                // Negeer '.' en '..'
                if (!$fileinfo->isDot()) {
                    // Als het een bestand is, voeg de grootte toe
                    if ($fileinfo->isFile()) {
                        $size += $fileinfo->getSize();
                    } // Als het een directory is, kun je recursief verdergaan (optioneel)
                    elseif ($fileinfo->isDir()) {
                        // Recursief de grootte van submappen optellen
                        $size += $this->getFolderSize($fileinfo->getPathname());
                    }
                }
            }
        }

        return $size;
    }

    /**
     * Delete a file from the file system.
     *
     * @param string $file The path to the file to be deleted.
     * @return void True if the file was successfully deleted.
     * @throws DeleteFileException If an error occurs during file deletion.
     */
    private function deleteFile(string $file)
    {
        if (!unlink($file)) {
            $error = error_get_last();
            throw new DeleteFileException("Er is een fout opgetreden bij het verwijderen van het bestand: " . $error['message']);
        }

        return true;

    }

}
