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

namespace Sonata\DoctrineMongoDBAdminBundle\Util;

use Doctrine\ODM\MongoDB\DocumentManager;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Security\Handler\AclSecurityHandlerInterface;
use Sonata\AdminBundle\Util\ObjectAclManipulator as BaseObjectAclManipulator;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

final class ObjectAclManipulator extends BaseObjectAclManipulator
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function batchConfigureAcls(OutputInterface $output, AdminInterface $admin, ?UserSecurityIdentity $securityIdentity = null): void
    {
        $securityHandler = $admin->getSecurityHandler();
        if (!$securityHandler instanceof AclSecurityHandlerInterface) {
            $output->writeln('Admin class is not configured to use ACL : <info>ignoring</info>');

            return;
        }

        $output->writeln(sprintf(' > generate ACLs for %s', $admin->getCode()));
        $objectOwnersMsg = null === $securityIdentity ? '' : ' and set the object owner';

        $om = $this->registry->getManagerForClass($admin->getClass());
        \assert($om instanceof DocumentManager);

        $qb = $om->createQueryBuilder($admin->getClass());

        $count = 0;
        $countUpdated = 0;
        $countAdded = 0;

        try {
            $batchSize = 20;
            $batchSizeOutput = 200;
            $objectIds = [];
            $objectIdIterator = new \ArrayIterator();

            foreach ($qb->getQuery()->getIterator() as $row) {
                $objectIds[] = ObjectIdentity::fromDomainObject($row);
                $objectIdIterator = new \ArrayIterator($objectIds);

                // detach from Doctrine, so that it can be Garbage-Collected immediately
                $om->detach($row);

                ++$count;

                if (0 === ($count % $batchSize)) {
                    [$batchAdded, $batchUpdated] = $this->configureAcls($output, $admin, $objectIdIterator, $securityIdentity);
                    $countAdded += $batchAdded;
                    $countUpdated += $batchUpdated;
                    $objectIds = [];
                }

                if (0 === ($count % $batchSizeOutput)) {
                    $output->writeln(sprintf(
                        '   - generated class ACEs%s for %s objects (added %s, updated %s)',
                        $objectOwnersMsg,
                        $count,
                        $countAdded,
                        $countUpdated
                    ));
                }
            }

            if (\count($objectIds) > 0) {
                [$batchAdded, $batchUpdated] = $this->configureAcls($output, $admin, $objectIdIterator, $securityIdentity);
                $countAdded += $batchAdded;
                $countUpdated += $batchUpdated;
            }
        } catch (\BadMethodCallException $e) {
            throw new ModelManagerException('', 0, $e);
        }

        $output->writeln(sprintf(
            '   - [TOTAL] generated class ACEs%s for %s objects (added %s, updated %s)',
            $objectOwnersMsg,
            $count,
            $countAdded,
            $countUpdated
        ));
    }
}
