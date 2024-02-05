<?php

namespace App\Form\DataTransformer;

use App\Entity\Prestation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PrestationToIdTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function transform($prestation)
    {
        if (null === $prestation) {
            return '';
        }

        return $prestation->getId();
    }

    public function reverseTransform($prestationId)
    {
        if (!$prestationId) {
            return null;
        }

        $prestation = $this->entityManager
            ->getRepository(Prestation::class)
            ->find($prestationId);

        if (null === $prestation) {
            throw new TransformationFailedException(sprintf(
                'La prestation avec l\'ID "%s" n\'existe pas!',
                $prestationId
            ));
        }

        return $prestation;
    }
}
