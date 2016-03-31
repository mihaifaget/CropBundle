<?php

/*
 * Copyright (c) 2011-2016 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBee\Bundle\CropBundle\Controller;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\Routing\RouteCollection;
use BackBee\Exception\BBException;
use BackBee\Rest\Controller\Annotations as Rest;
use BackBee\Rest\Controller\MediaController;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Revision;
use BackBee\Bundle\CropBundle\CropRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use BackBee\Security\Token\BBUserToken;

/**
 * CropBundle main class
 *
 * @category    BackBee
 * @package     BackBee\Bundle\CropBundle
 * @copyright   Lp digital system
 * @author      Mihai Faget <mihai.faget@lp-digital.fr>
 */
class CropController
{
    /**
     * This is the CropBundle config
     * @var Config
     */
    private $config;

    /**
     * @var RouteCollection
     */
    private $routeCollection;

    /**
     * @var EntityManager
     */
    private $_em;

    /**
     * The current application.
     * 
     * @var ApplicationInterface
     */
    private $application;

    /**
     * Revision repository
     * 
     * @var \BackBee\ClassContent\Repository\RevisionRepository
     */
    private $revisionRepository;

    /**
     * Media repository
     * 
     * @var \BackBee\NestedNode\Repository\MediaRepository
     */
    private $mediaRepository;

    /**
     * Crop repository
     * 
     * @var \BackBee\Bundle\CropBundle\Repository\CropRepository
     */
    private $cropRepository;

    /**
     * Media directory path
     * 
     * @var mediaDir
     */
    private $mediaDir;

    /**
     * CropController's constructor
     *
     * @param Config          $config
     * @param RouteCollection $routeCollection
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function __construct(BBApplication $application, Config $config, RouteCollection $routeCollection)
    {
        $this->application = $application;
        $this->config = $config;
        $this->routeCollection = $routeCollection;
        $this->_em = $application->getEntityManager();
        $this->revisionRepository = $this->_em->getRepository('BackBee\ClassContent\Revision');
        $this->mediaRepository = $this->_em->getRepository('BackBee\NestedNode\Media');
        $this->cropRepository = $this->_em->getRepository('BackBee\Bundle\CropBundle\Repository\CropRepo');
        $this->mediaDir = $application->getMediaDir();
    }

    /**
     * Image crop action
     * @Rest\Security("is_fully_authenticated() & has_role('ROLE_API_USER')")
     */
    public function cropAction(Request $request)
    {
        $this->cropAction = $request->get('cropAction');
        $this->cropX = $request->get('cropX');
        $this->cropY = $request->get('cropY');
        $this->cropW = $request->get('cropW');
        $this->cropH = $request->get('cropH');
        $this->cropNewW = $request->get('cropNewW');
        $this->cropNewH = $request->get('cropNewH');
        $this->originalUid = $request->get('originalUid');
        $this->selectedProportion = $request->get('selectedProportion');

        switch ($this->cropAction) {
            case 'replace':
                $this->saveReplace($request);
                break;
            case 'new':
                $this->saveNew($request);
                break;
            default: {
                break;
            }
        }
    }

    /**
     * Crops and replaces current edited image. If it's a draft, replaces draft's image, if revision is commited it creates a new revision
     */
    private function saveReplace()
    {
        $element = $this->_em->find(AbstractClassContent::getClassnameByContentType('Element/Image'), $this->originalUid);
        
        if (null !== $element) {
            
            $elementRevision = $this->revisionRepository->getDraft($element, $userToken = $this->application->getBBUserToken());
            
            if (null !== $elementRevision) {
                $elementRevisionData = $elementRevision->getData();
                
                $imagepath = $this->mediaDir.'/'.$elementRevisionData['path'];
                $this->cropImage($imagepath, $this->cropX, $this->cropY, $this->cropW, $this->cropH, $this->cropNewW, $this->cropNewH);

                $elementRevision->setParam('width', $this->cropNewW);
                $elementRevision->setParam('height', $this->cropNewH);
                $elementRevision->setParam('stat', json_encode(stat($imagepath)));
                $this->_em->flush($elementRevision);

            } else {
                $max_revision = 0;
                foreach ($elementRevisions = $this->revisionRepository->getRevisions($element) as $elementRevision) {
                    $max_revision = max($max_revision, $elementRevision->getRevision());
                }
                foreach ($elementRevisions = $this->revisionRepository->getRevisions($element) as $elementRevision) {
                    if ($elementRevision->getRevision() == $max_revision) {
                        break;
                    }
                }
                
                $newRevision = new Revision();
                
                $newRevisionUid = $newRevision->getUid();

                $newRevision->setAccept($element->getAccept());
                $newRevision->setContent($element);
                
                $scalar = $element->getData();
                $scalarObject = $element->getDataToObject();

                $newImagePath = substr($newRevisionUid,0,3).'/'.substr($newRevisionUid,3).'.'.pathinfo($scalar['originalname'], PATHINFO_EXTENSION);
                $oldImagePath = $this->mediaDir.'/'.$scalar['path'];
                $scalarObject['path'][0]['scalar'] = str_replace('/','\/', '"'.$newImagePath.'"');
                @mkdir($this->mediaDir.'/'.substr($newRevisionUid,0,3));
                @copy($oldImagePath, $this->mediaDir.'/'.$newImagePath);

                $newRevision->setData($scalarObject);

                $newRevision->setLabel($element->getLabel());
                $maxEntry = (array) $element->getMaxEntry();
                $minEntry = (array) $element->getMinEntry();
                $newRevision->setMaxEntry($maxEntry);
                $newRevision->setMinEntry($minEntry);

                $newRevision->setOwner($userToken->getUser());
                
                $newRevision->setParam('width', $this->cropNewW);
                $newRevision->setParam('height', $this->cropNewH);
                $newRevision->setParam('stat', json_encode(stat($this->mediaDir.'/'.$newImagePath)));

                $newRevision->setCreated(new \DateTime('now'));
                $newRevision->setModified(new \DateTime('now'));
                $newRevision->setRevision($max_revision);
                $newRevision->setState(Revision::STATE_MODIFIED);
                $this->_em->persist($newRevision);
                $this->_em->flush();

                $this->cropImage($this->mediaDir.'/'.$newImagePath, $this->cropX, $this->cropY, $this->cropW, $this->cropH, $this->cropNewW, $this->cropNewH);
            }
        } else {
            throw new BadRequestHttpException("invalid_element");
        }
    }

    /**
     * Saves a new entry in media library
     */
    private function saveNew()
    {
        $parentMediaUid = $this->cropRepository->getParentContentUidByUid($this->originalUid);
        
        if (0 === count($parentMediaUid)) {
            throw new BadRequestHttpException;
        }

        $parentMedia = $this->_em->find(AbstractClassContent::getClassnameByContentType('Media/Image'), $parentMediaUid[0]);
        
        $elementImage = $this->_em->find(AbstractClassContent::getClassnameByContentType('Element/Image'), $this->originalUid);

        if (null === $elementImage) {
            throw new BadRequestHttpException;
        }

        $elementImageRevision = $this->revisionRepository->getDraft($elementImage, $userToken = $this->application->getBBUserToken());
        if (null === $elementImageRevision) {
            $max_revision = 0;
            foreach ($elementImageRevisions = $this->revisionRepository->getRevisions($elementImage) as $elementImageRevision) {
                $max_revision = max($max_revision, $elementImageRevision->getRevision());
            }
            foreach ($elementImageRevisions = $this->revisionRepository->getRevisions($elementImage) as $elementImageRevision) {
                if ($elementImageRevision->getRevision() == $max_revision) {
                    break;
                }
            }
        }

        foreach ($parentMedia->getData() as $key => $value) {
            switch ($key) {
                case 'title':
                    $elementTitle = $this->_em->find(get_class($value), $value->getUid());
                    $elementTitleRevision = $this->revisionRepository->getDraft($elementTitle, $userToken = $this->application->getBBUserToken());
                    if (null === $elementTitleRevision) {
                        $max_revision = 0;
                        foreach ($elementTitleRevisions = $this->revisionRepository->getRevisions($elementTitle) as $elementTitleRevision) {
                            $max_revision = max($max_revision, $elementTitleRevision->getRevision());
                        }
                        foreach ($elementTitleRevisions = $this->revisionRepository->getRevisions($elementTitle) as $elementTitleRevision) {
                            if ($elementTitleRevision->getRevision() == $max_revision) {
                                break;
                            }
                        }
                    }
                    break;
            }
        }

        $imageClass = AbstractClassContent::getClassnameByContentType('Media/Image');
        $imageData = new $imageClass;
        
        $mediaData = new \BackBee\NestedNode\Media;
        $mediaData->setMediaFolder($this->_em->getRepository('BackBee\NestedNode\MediaFolder')->getRoot());
        $mediaData->setContent($imageData);
        if (null !== $elementTitleRevision) {
            $elementTitleRevisionData = $elementTitleRevision->getData();
            $mediaData->setTitle($elementTitleRevisionData['value'].' ['.$this->selectedProportion.']');
        }
        $mediaData->setDate(new \DateTime('now'));
        $mediaData->setCreated(new \DateTime('now'));
        $mediaData->setModified(new \DateTime('now'));
        $this->_em->persist($mediaData);
        $this->_em->flush();
        
        $mediaRevisions = $this->revisionRepository->getRevisions($imageData);
        foreach ($mediaRevisions[0]->getData() as $key => $value) {
            $element = $this->_em->find(get_class($value), $value->getUid());
            if (null !== $element) {
                $elementRevisions = $this->revisionRepository->getRevisions($element);
                $elementRevisionData = $this->revisionRepository->find($elementRevisions[0]->getUid());
                switch ($key) {
                    case 'title':
                        $scalar = $elementRevisionData->getDataToObject();
                        $scalar['value'][0]['scalar'] = $mediaData->getTitle();
                        $elementRevisionData->setData($scalar);
                        $this->_em->flush($elementRevisionData);
                        break;
                    case 'image':
                        $elementRevisionUid = $elementRevisionData->getUid();
                        $elementImageRevisionData = $elementImageRevision->getData();
                        $newImagePath = substr($elementRevisionUid,0,3).'/'.substr($elementRevisionUid,3).'.'.pathinfo($elementImageRevisionData['originalname'], PATHINFO_EXTENSION);

                        @mkdir($this->mediaDir.'/'.substr($elementRevisionUid,0,3));
                        @copy($this->mediaDir.'/'.$elementImageRevisionData['path'], $this->mediaDir.'/'.$newImagePath);

                        $scalar = $elementRevisionData->getDataToObject();
                        $scalar['path'][0]['scalar'] = str_replace('/','\/', '"'.$newImagePath.'"');
                        $scalar['originalname'][0]['scalar'] = '"'.$elementImageRevisionData['originalname'].'"';
                        $elementRevisionData->setData($scalar);
                        $elementRevisionData->setParam('width', $this->cropNewW);
                        $elementRevisionData->setParam('height', $this->cropNewH);
                        $elementRevisionData->setParam('stat', json_encode(stat($this->mediaDir.'/'.$newImagePath)));
                        $this->_em->flush($elementRevisionData);
                        
                        $this->cropImage($this->mediaDir.'/'.$newImagePath, $this->cropX, $this->cropY, $this->cropW, $this->cropH, $this->cropNewW, $this->cropNewH);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * Crop and replace $sourceImgFile
     */
    private function cropImage($sourceImgFile, $cropX, $cropY, $cropW, $cropH, $cropNewW, $cropNewH)
    {
        $targetImg = imagecreatetruecolor($cropW, $cropH);
        $mimeType = mime_content_type($sourceImgFile);
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImg = imagecreatefromjpeg($sourceImgFile);
                break;
            case 'image/png':
                $sourceImg = imagecreatefrompng($sourceImgFile);
                break;
            case 'image/gif':
                $sourceImg = imagecreatefromgif($sourceImgFile);
                break;
            default:
                die('invalid image');
        }

        imagecopy($targetImg, $sourceImg, 0, 0, $cropX, $cropY, $cropW, $cropH);
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($targetImg, $sourceImgFile);
                break;
            case 'image/png':
                imagepng($targetImg, $sourceImgFile);
                break;
            case 'image/gif':
                imagegif($targetImg, $sourceImgFile);
                break;
        }
        return $this->resizeImage($sourceImgFile, $cropNewW, $cropNewH);
    }

    /**
     * Resizes $source, keeping aspect ratio. New width & height can be bigger than image size, so it also stretches
     */
    private function resizeImage($source, $width, $height)
    {
        $size = getimagesize($source);
        $source_width = $size[0];
        $source_height = $size[1];
        $mimeType = mime_content_type($source);

        switch ($mimeType) {
            case 'image/jpeg':
                $source_img = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $source_img = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $source_img = imagecreatefromgif($source);
                break;
            default:
                die('error');
        }

        $ratio = min($width / $source_width, $height / $source_height);
        $width = $source_width * $ratio;
        $height = $source_height * $ratio;

        $target_img = imagecreatetruecolor($width, $height);

        if ('image/jpeg' !== $mimeType) {
            imagecolortransparent($target_img, imagecolorallocatealpha($target_img, 0, 0, 0, 127));
            imagealphablending($target_img, false);
            imagesavealpha($target_img, true);
        }

        imagecopyresampled($target_img, $source_img, 0, 0, 0, 0, $width, $height, $source_width, $source_height);

        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($target_img, $source);
                break;
            case 'image/png':
                return imagepng($target_img, $source);
                break;
            case 'image/gif':
                return imagegif($target_img, $source);
                break;
        }

    }

    protected function granted($attributes, $object = null, $message = 'Permission denied')
    {
        $security_context = $this->application->getSecurityContext();

        if (null !== $security_context->getACLProvider() && 
        	false === $this->application->getContainer()->get('security.context')->isGranted($attributes, $object)) {
            die($message);
        }

        return true;
    }
}