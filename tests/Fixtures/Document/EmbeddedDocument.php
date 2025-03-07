<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineMongoDBAdminBundle\Tests\Fixtures\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\EmbeddedDocument
 */
class EmbeddedDocument
{
    /**
     * @ODM\Field(type="int")
     *
     * @var int
     */
    public $position;
    /**
     * @ODM\Field(type="bool")
     *
     * @var bool
     */
    public $plainField = true;

    public function __construct(int $position = 0)
    {
        $this->position = $position;
    }
}
