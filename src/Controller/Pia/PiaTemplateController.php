<?php

/*
 * Copyright (C) 2015-2018 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace PiaApi\Controller\Pia;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use FOS\RestBundle\View\View;
use FOS\RestBundle\Controller\Annotations as FOSRest;
use PiaApi\Entity\Pia\PiaTemplate;
use PiaApi\DataExchange\Transformer\JsonToEntityTransformer;

class PiaTemplateController extends RestController
{
    /**
     * @var JsonToEntityTransformer
     */
    protected $jsonToEntityTransformer;

    public function __construct(JsonToEntityTransformer $jsonToEntityTransformer)
    {
        $this->jsonToEntityTransformer = $jsonToEntityTransformer;
    }

    /**
     * @FOSRest\Get("/pia-templates")
     * @Security("is_granted('ROLE_PIA_LIST')")
     *
     * @return array
     */
    public function listAction(Request $request)
    {
        $this->canAccessRouteOr304();

        $structure = $this->getUser()->getStructure();
        $collection = $this->getRepository()->findAvailablePiaTemplatesForStructure($structure);

        return $this->view($collection, Response::HTTP_OK);
    }

    /**
     * @FOSRest\Get("/pia-templates/{id}")
     * @Security("is_granted('ROLE_PIA_VIEW')")
     *
     * @return array
     */
    public function showAction(Request $request, $id)
    {
        $this->canAccessRouteOr304();

        $piaTemplate = $this->getRepository()->find($id);
        if ($piaTemplate === null) {
            return $this->view($piaTemplate, Response::HTTP_NOT_FOUND);
        }

        $this->canAccessResourceOr304($piaTemplate);

        return $this->view($piaTemplate, Response::HTTP_OK);
    }

    protected function getEntityClass()
    {
        return PiaTemplate::class;
    }

    public function canAccessResourceOr304($resource): void
    {
        if (!$resource instanceof PiaTemplate) {
            throw new AccessDeniedHttpException();
        }

        if ($resource->getStructure() !== $this->getUser()->getStructure()) {
            throw new AccessDeniedHttpException();
        }
    }
}