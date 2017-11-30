<?php

namespace Apps\Webiny\Php\Lib\Entity\Attributes;

use Apps\Webiny\Php\Entities\Image;

/**
 * Images attribute
 */
class ImagesAttribute extends FilesAttribute
{
    protected $dimensions = [];
    protected $quality = 90;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
        $this->setEntity(Image::class)->setSorter('order');
    }

    /**
     * Set dimensions of images
     *
     * @param array $dimensions
     *
     * @return $this
     */
    public function setDimensions(array $dimensions)
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    /**
     * Set quality of images
     *
     * @param int $quality
     *
     * @return $this
     */
    public function setQuality($quality)
    {
        $this->quality = $quality;

        return $this;
    }

    public function getValue($params = [], $processCallbacks = true)
    {
        $values = parent::getValue($params, false);

        /* @var Image $value */
        foreach ($values as $value) {
            $value->setDimensions($this->dimensions);
            $value->setQuality($this->quality);
        }

        return $processCallbacks ? $this->processGetValue($values, $params) : $values;
    }
}