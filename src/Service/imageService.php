<?php

namespace UploadImages\Service;

use Laminas\Form\Annotation\AnnotationBuilder;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrineAdapter;
use Doctrine\ORM\Tools\Pagination\Paginator as ORMPaginator;
use Laminas\Paginator\Paginator;

/*
 * Entities
 */

use Symfony\Component\VarDumper\VarDumper;
use UploadImages\Entity\Image;
use UploadImages\Entity\ImageType;
use function array_reverse;
use function array_slice;
use function ceil;
use function count;
use function end;
use function imap_fetch_overview;
use function imap_headerinfo;
use function imap_num_msg;
use function str_split;

class imageService implements imageServiceInterface
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

    public function deleteImage($image = null)
    {
        if (is_object($image)) {
            $imageTypes = $image->getImageTypes();

            foreach ($imageTypes as $imageType) {
                @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
                $this->em->remove($imageType);
            }

            $this->em->remove($image);
            $this->em->flush();
            return true;
        } else {
            return false;
        }
    }

    /*
     * 
     * Delete image file from server
     * 
     * @param type $imageUrl string
     * @return void
     * 
     */

    public function deleteImageFromServer($imageUrl = null)
    {
        if (!empty($imageUrl)) {
            $result = unlink('public/' . $imageUrl);

            return $result;
        } else {
            return false;
        }
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
                    @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
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
    public function createRedirectLink($aReturnURL = null)
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
     *
     * Get all images from specific folder
     *
     * @param rootPath $rootPath string
     * @return   array
     *
     */
    public function getAllImageFromFolder($rootPath)
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
                    $image['url'] = str_replace('/home/hosting/sander/WWW/public//', '', $fileinfo->getPathname());
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
            $length = (int) $length + (int) $start;
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
     * Check if images excist on server
     * @param path $path string
     * @param name $name string
     * @return   boolean
     */
    public function checkFileExcist($path = null, $name = null, $rootPath = null)
    {
        $path = trim($path);
        $name = trim($name);
        $rootPath = trim($rootPath);
        if (!empty($path) && !empty($name) && !empty($rootPath)) {
            $fullUrl = $_SERVER['DOCUMENT_ROOT'] . $path . $name;
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
     * @param type $imageType object
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

}
