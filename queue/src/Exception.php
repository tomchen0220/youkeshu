<?php

namespace queue;

/**
 * Class Exception
 * @package queue
 * @author longli
 */
class Exception extends \Exception
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return null;
    }
}