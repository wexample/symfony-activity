<?php

namespace Wexample\SymfonyActivity\Service;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Interface\ActivitySubjectInterface;

/**
 * Names whose history an object is: by its own words when it implements
 * ActivitySubjectInterface, by its mapped class and its id when it is an
 * entity — the class Doctrine maps, never a proxy's.
 */
class ActivitySubjectService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Whether $subject can have a history: what a caller holding an object
     * it did not choose — the signed-in user of an application — asks first.
     */
    public function supports(object $subject): bool
    {
        return $subject instanceof ActivitySubject
            || $subject instanceof ActivitySubjectInterface
            || ! $this->entityManager->getMetadataFactory()->isTransient($subject::class);
    }

    public function of(object $subject): ActivitySubject
    {
        if ($subject instanceof ActivitySubject) {
            return $subject;
        }

        if ($subject instanceof ActivitySubjectInterface) {
            return new ActivitySubject($subject->getActivitySubjectType(), $subject->getActivitySubjectId());
        }

        if ($this->entityManager->getMetadataFactory()->isTransient($subject::class)) {
            throw new LogicException(sprintf('%s is neither an entity nor an %s: there is no telling whose history it is.', $subject::class, ActivitySubjectInterface::class));
        }

        $metadata = $this->entityManager->getClassMetadata($subject::class);

        return new ActivitySubject(
            $metadata->getName(),
            implode('-', $metadata->getIdentifierValues($subject))
        );
    }

    /**
     * What a screen calls the subject $type $id: the entity it names, when it
     * still exists, by its own words — a string, a user identifier —, its
     * short type and id otherwise. Null id: anonymized, its short type alone.
     */
    public function label(string $type, ?string $id): string
    {
        $short = substr(strrchr('\\'.$type, '\\'), 1);

        if ($id === null) {
            return $short;
        }

        $entity = class_exists($type) && ! $this->entityManager->getMetadataFactory()->isTransient($type)
            ? $this->entityManager->find($type, $id)
            : null;

        return match (true) {
            $entity instanceof Stringable => (string) $entity,
            $entity instanceof UserInterface => $entity->getUserIdentifier(),
            default => $short.' #'.$id,
        };
    }
}
