<?php

/**
 * This is a filter for resizing images
 *
 * LICENSE:
 *
 * The PHP License, version 3.0
 *
 * Copyright (c) 1997-2005 The PHP Group
 *
 * This source file is subject to version 3.0 of the PHP license,
 * that is bundled with this package in the file LICENSE, and is
 * available through the world-wide-web at the following url:
 * http://www.php.net/license/3_0.txt.
 * If you did not receive a copy of the PHP license and are unable to
 * obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately.
 *
 * @author      Oleg Lobach <oleg@lobach.info>
 * @author      Ildar N. Shaimordanov <ildar-sh@mail.ru>
 * @license     http://www.php.net/license/3_0.txt
 *              The PHP License, version 3.0
 */

/**
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';

/**
 * Resize a given image and stores the resized file content
 *
 * @category   App
 * @package    App_Filter
 * @subpackage App_Filter_File
 * @version    0.1
 * @copyright  Copyright (c) 2009 Oleg Lobach <oleg@lobach.info>
 * @author     Oleg Lobach <oleg@lobach.info>
 * @author     Ildar N. Shaimordanov <ildar-sh@mail.ru>
 * @license    http://www.php.net/license/3_0.txt
 *             The PHP License, version 3.0
 */
class App_Filter_File_ImageResize implements Zend_Filter_Interface
{
    /**
     * Maximal scaling
     */
    const METHOD_SCALE_MAX = 0;
    /**
     * Minimal scaling
     */
    const METHOD_SCALE_MIN = 1;
    /**
     * Cropping of fragment
     */
    const METHOD_CROP      = 2;

    /**
     * Align center
     */
    const ALIGN_CENTER = 0;
    /**
     * Align left
     */
    const ALIGN_LEFT   = -1;
    /**
     * Align right
     */
    const ALIGN_RIGHT  = +1;
    /**
     * Align top
     */
    const ALIGN_TOP    = -1;
    /**
     * Align bottom
     */
    const ALIGN_BOTTOM = +1;
    
    /**
     * @var array Default options
     * @see setOptions
     */
    private $_options = array(
        'width'   => 800,                    // Width of image
        'height'  => 600,                    // Height of image
        'method'  => self::METHOD_SCALE_MAX, // Method of image creating
        'percent' => 0,                      // Size of image per size of original image
        'halign'  => self::ALIGN_CENTER,     // Horizontal align
        'valign'  => self::ALIGN_CENTER,     // Vertical align
    );
    
    /**
     * Constructor
     * 
     * @param array $options Filter options
     */
    public function __construct($options = array()) {
        $this->setOptions($options);
    }
    
    /**
     * Set options
     *
     * @param array|Zend_Config $options Thumbnail options
     *         <pre>
     *         width   int    Width of image
     *         height  int    Height of image
     *         percent number Size of image per size of original image
     *         method  int    Method of image creating
     *         halign  int    Horizontal align
     *         valign  int    Vertical align
     *         </pre>
     * @return object
     */
    public function setOptions($options)
    {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        } elseif (!is_array($options)) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception('Invalid options argument provided to filter');
        }
        
        foreach ($options as $k => $v) {
            if (array_key_exists($k, $this->_options)) {
                $this->_options[$k] = $v;
            }
        }
        return $this;
    }
    
    /**
     * Resize the file $value with the defined settings
     *
     * @param  string $value Full path of file to change
     * @return string The filename which has been set, or false when there were errors
     */
    public function filter($value)
    {
        if (!file_exists($value)) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception("File '$value' not found");
        }

        if (file_exists($value) and !is_writable($value)) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception("File '$value' is not writable");
        }

        $content = file_get_contents($value);
        if (!$content) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception("Problem while reading file '$value'");
        }

        $resized = $this->resize($content, $value);
        $result  = file_put_contents($value, $resized);

        if (!$result) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception("Problem while writing file '$value'");
        }

        return $value;
    }
    
    /**
     * Resize image
     *
     * @param $content Content of source imge
     * @param $value   Path to source file
     * @return Content of resized image
     */
    protected function resize($content, $value)
    {
        $sourceImage = ImageCreateFromString($content);
        if (!is_resource($sourceImage)) {
            require_once 'Zend/Filter/Exception.php';
            throw new Exception("Can't create image from given file");
        }
        
        $sourceWidth  = ImageSx($sourceImage);
        $sourceHeight = ImageSy($sourceImage);
        
        if ($sourceWidth <= $this->_options['width'] && $sourceHeight <= $this->_options['height']) {
            ImageDestroy($sourceImage);
            return $content;
        }
        
        list( , , $imageType) = GetImageSize($value);
        
        switch ($this->_options['method']) {
            case self::METHOD_CROP:
                list($X, $Y, $W, $H, $width, $height) = $this->__calculateCropCoord($sourceWidth, $sourceHeight);
                break;
            case self::METHOD_SCALE_MAX:
                list($X, $Y, $W, $H, $width, $height) = $this->__calculateScaleMaxCoord($sourceWidth, $sourceHeight);
                break;
            case self::METHOD_SCALE_MIN:
                list($X, $Y, $W, $H, $width, $height) = $this->__calculateScaleMinCoord($sourceWidth, $sourceHeight);
                break;
            default:
                require_once 'Zend/Filter/Exception.php';
                throw new Zend_Filter_Exception('Unknow resize method');
        }
        
        // Create the target image
        if (function_exists('imagecreatetruecolor')) {
            $targetImage = ImageCreateTrueColor($width, $height);
        } else {
            $targetImage = ImageCreate($width, $height);
        }
        if (!is_resource($targetImage)) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception('Cannot initialize new GD image stream');
        }
        
        // Copy the source image to the target image
        if ($this->_options['method'] == self::METHOD_CROP) {
            $result = ImageCopy($targetImage, $sourceImage, 0, 0, $X, $Y, $W, $H);
        } elseif (function_exists('imagecopyresampled')) {
            $result = ImageCopyResampled($targetImage, $sourceImage, 0, 0, $X, $Y, $width, $height, $W, $H);
        } else {
            $result = ImageCopyResized($targetImage, $sourceImage, 0, 0, $X, $Y, $width, $height, $W, $H);
        }
        ImageDestroy($sourceImage);
        if (!$result) {
            require_once 'Zend/Filter/Exception.php';
            throw new Zend_Filter_Exception('Cannot resize image');
        }
        
        ob_start();
        switch ($imageType)
        {
            case IMAGETYPE_GIF:
                ImageGif($targetImage);
                break;
            case IMAGETYPE_JPEG:
                ImageJpeg($targetImage, null, 100); // best quality
                break;
            case IMAGETYPE_PNG:
                ImagePng($targetImage, null, 0); // no compression
                break;
            default:
                ob_end_clean();
                require_once 'Zend/Filter/Exception.php';
                throw new Zend_Filter_Exception('Unknow resize method');
        }
        ImageDestroy($targetImage);
        $finalImage = ob_get_clean();
        
        return $finalImage;
    }
    
    /**
     * Calculate coordinates for crop method
     *
     * @param int $sourceWidth  Width of source image
     * @param int $sourceHeight Height of source image
     * @return array
     */
    private function __calculateCropCoord($sourceWidth, $sourceHeight)
    {
        if ( $this->_options['percent'] ) {
            $W = floor($this->_options['percent'] * $sourceWidth);
            $H = floor($this->_options['percent'] * $sourceHeight);
        } else {
            $W = $this->_options['width'];
            $H = $this->_options['height'];
        }
        
        $X = $this->__coord($this->_options['halign'], $sourceWidth,  $W);
        $Y = $this->__coord($this->_options['valign'], $sourceHeight, $H);
        
        return array($X, $Y, $W, $H, $W, $H);
    }

    /**
     * Calculate coordinates for Max scale method
     *
     * @param int $sourceWidth  Width of source image
     * @param int $sourceHeight Height of source image
     * @return array
     */
    private function __calculateScaleMaxCoord($sourceWidth, $sourceHeight)
    {
        if ( $this->_options['percent'] ) {
            $width  = floor($this->_options['percent'] * $sourceWidth);
            $height = floor($this->_options['percent'] * $sourceHeight);
        } else {
            $width  = $this->_options['width'];
            $height = $this->_options['height'];
            
            if ( $sourceHeight > $sourceWidth ) {
                $width  = floor($height / $sourceHeight * $sourceWidth);
            } else {
                $height = floor($width / $sourceWidth * $sourceHeight);
            }
        }
        return array(0, 0, $sourceWidth, $sourceHeight, $width, $height);
    }
    
    /**
     * Calculate coordinates for Min scale method
     *
     * @param int $sourceWidth  Width of source image
     * @param int $sourceHeight Height of source image
     * @return array
     */
    private function __calculateScaleMinCoord($sourceWidth, $sourceHeight)
    {
        $X = $Y = 0;
        
        $W = $sourceWidth;
        $H = $sourceHeight;
        
        if ( $this->__options['percent'] ) {
            $width  = floor($this->__options['percent'] * $W);
            $height = floor($this->__options['percent'] * $H);
        } else {
            $width  = $this->__options['width'];
            $height = $this->__options['height'];
            
            $Ww = $W / $width;
            $Hh = $H / $height;
            if ( $Ww > $Hh ) {
                $W = floor($width * $Hh);
                $X = $this->__coord($this->__options['halign'], $sourceWidth, $W);
            } else {
                $H = floor($height * $Ww);
                $Y = $this->__coord($this->__options['valign'], $sourceHeight, $H);
            }
        }
        return array($X, $Y, $W, $H, $width, $height);
    }
    
    /**
     * Calculation of the coordinates
     *
     * @param int $align Align type
     * @param int $src   Source size
     * @param int $dst   Destination size
     * @return int
     */
    private function __coord($align, $src, $dst)
    {
        if ( $align < self::ALIGN_CENTER ) {
            $result = 0;
        } elseif ( $align > self::ALIGN_CENTER ) {
            $result = $src - $dst;
        } else {
            $result = ($src - $dst) >> 1;
        }
        return $result;
    }
}