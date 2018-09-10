<?php

/*
 * Copyright (C) 2015-2018 Libre Informatique
 *
 * This file is licensed under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace PiaApi\Controller\Pia;

use FOS\RestBundle\Controller\Annotations as FOSRest;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation as Nelmio;
use PiaApi\DataExchange\Transformer\JsonToEntityTransformer;
use PiaApi\DataHandler\RequestDataHandler;
use PiaApi\Entity\Pia\Pia;
use PiaApi\Entity\Pia\ProcessingTemplate;
use PiaApi\Entity\Pia\Processing;
use PiaApi\Entity\Pia\Answer;
use PiaApi\Entity\Pia\Measure;
use PiaApi\Entity\Pia\Evaluation;
use PiaApi\Entity\Pia\Comment;
use PiaApi\Entity\Pia\Attachment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as Swg;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class PiaController extends RestController
{
    /**
     * @var jsonToEntityTransformer
     */
    protected $jsonToEntityTransformer;

    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        JsonToEntityTransformer $jsonToEntityTransformer
    ) {
        parent::__construct($propertyAccessor);
        $this->jsonToEntityTransformer = $jsonToEntityTransformer;
    }

    /**
     * Lists all PIAs.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Get("/pias")
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns all PIAs",
     *     @Swg\Schema(
     *         type="array",
     *         @Swg\Items(ref=@Nelmio\Model(type=Pia::class, groups={"Default"}))
     *     )
     * )
     *
     * @Security("is_granted('CAN_SHOW_PIA')")
     *
     * @return array
     */
    public function listAction(Request $request)
    {
        $structure = $this->getUser()->getStructure();

        $criteria = array_merge($this->extractCriteria($request), ['structure' => $structure]);
        $collection = $this->getRepository()->findBy($criteria, ['createdAt' => 'DESC']);

        return $this->view($collection, Response::HTTP_OK);
    }

    /**
     * Shows one PIA by its ID.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Get("/pias/{id}", requirements={"id"="\d+"})
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="id",
     *     in="path",
     *     type="string",
     *     required=true,
     *     description="The ID of the PIA"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns one PIA",
     *     @Swg\Schema(
     *         type="object",
     *         ref=@Nelmio\Model(type=Pia::class, groups={"Default"})
     *     )
     * )
     *
     * @Security("is_granted('CAN_SHOW_PIA')")
     *
     * @return array
     */
    public function showAction(Request $request, $id)
    {
        $pia = $this->getRepository()->find($id);
        if ($pia === null) {
            return $this->view($pia, Response::HTTP_NOT_FOUND);
        }

        $this->canAccessResourceOr403($pia);

        return $this->view($pia, Response::HTTP_OK);
    }

    /**
     * Creates a PIA.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Post("/pias")
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="PIA",
     *     in="body",
     *     required=true,
     *     @Swg\Schema(
     *         type="object",
     *         required={
     *             "author_name",
     *             "evaluator_name",
     *             "validator_name",
     *             "status",
     *             "dpo_status",
     *             "dpo_opinion",
     *             "concerned_people_opinion",
     *             "concerned_people_status",
     *             "concerned_people_searched_opinion",
     *             "concerned_people_searched_content",
     *             "rejection_reason",
     *             "applied_adjustements",
     *             "dpos_names",
     *             "people_names",
     *             "processing"
     *         },
     *         @Swg\Property(property="author_name", type="string"),
     *         @Swg\Property(property="evaluator_name", type="string"),
     *         @Swg\Property(property="validator_name", type="string"),
     *         @Swg\Property(property="status", type="number"),
     *         @Swg\Property(property="dpo_status", type="number"),
     *         @Swg\Property(property="dpo_opinion", type="string"),
     *         @Swg\Property(property="concerned_people_opinion", type="string"),
     *         @Swg\Property(property="concerned_people_status", type="number"),
     *         @Swg\Property(property="concerned_people_searched_opinion", type="boolean"),
     *         @Swg\Property(property="concerned_people_searched_content", type="string"),
     *         @Swg\Property(property="rejection_reason", type="string"),
     *         @Swg\Property(property="applied_adjustements", type="string"),
     *         @Swg\Property(property="dpos_names", type="string"),
     *         @Swg\Property(property="people_names", type="string"),
     *         @Swg\Property(property="processing", type="object", required={"id"}, @Swg\Property(property="id", type="number"))
     *     ),
     *     description="The PIA content"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns the newly created PIA",
     *     @Swg\Schema(
     *         type="object",
     *         ref=@Nelmio\Model(type=Pia::class, groups={"Default"})
     *     )
     * )
     *
     * @Security("is_granted('CAN_CREATE_PIA')")
     *
     * @return array
     */
    public function createAction(Request $request)
    {
        $pia = $this->newFromRequest($request);
        $pia->setStructure($this->getUser()->getStructure());

        $processingId = $request->get('processing', ['id' => -1])['id'];

        $processing = $this->getResource($processingId, Processing::class);

        if ($processing === null) {
            return $this->view(['You must set Processing to create PIA'], Response::HTTP_BAD_REQUEST);
        }

        $pia->setProcessing($processing);
        $this->persist($pia);

        return $this->view($pia, Response::HTTP_OK);
    }

    /**
     * Creates a PIA from a template.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Post("/pias/new-from-template/{id}")
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="id",
     *     in="path",
     *     type="string",
     *     required=true,
     *     description="The ID of the PIA's template"
     * )
     * @Swg\Parameter(
     *     name="PIA",
     *     in="body",
     *     required=true,
     *     @Swg\Schema(
     *         type="object",
     *         required={"author_name","evaluator_name","validator_name","processing"},
     *         @Swg\Property(property="author_name", type="string"),
     *         @Swg\Property(property="evaluator_name", type="string"),
     *         @Swg\Property(property="validator_name", type="string"),
     *         @Swg\Property(property="processing", type="object", required={"id"}, @Swg\Property(property="id", type="number"))
     *     ),
     *     description="The PIA content"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns the newly created PIA",
     *     @Swg\Schema(
     *         type="object",
     *         ref=@Nelmio\Model(type=Pia::class, groups={"Default"})
     *     )
     * )
     *
     * @Security("is_granted('CAN_CREATE_PIA')")
     *
     * @return array
     */
    public function createFromTemplateAction(Request $request, $id)
    {
        /** @var ProcessingTemplate $pTemplate */
        $pTemplate = $this->getDoctrine()->getRepository(ProcessingTemplate::class)->find($id);
        if ($pTemplate === null) {
            return $this->view($pTemplate, Response::HTTP_NOT_FOUND);
        }

        $pia = $this->jsonToEntityTransformer->transform($pTemplate->getData());
        $pia->setAuthorName($request->get('author_name', $pia->getAuthorName()));
        $pia->setEvaluatorName($request->get('evaluator_name', $pia->getEvaluatorName()));
        $pia->setValidatorName($request->get('validator_name', $pia->getValidatorName()));
        $pia->setStructure($this->getUser()->getStructure());
        $pia->setProcessing($this->getResource($request->get('processing', ['id' => -1])['id'], Processing::class));
        $this->persist($pia);

        return $this->view($pia, Response::HTTP_OK);
    }

    /**
     * Updates a PIA.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Put("/pias/{id}", requirements={"id"="\d+"})
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="id",
     *     in="path",
     *     type="string",
     *     required=true,
     *     description="The ID of the PIA"
     * )
     * @Swg\Parameter(
     *     name="PIA",
     *     in="body",
     *     required=true,
     *     @Swg\Schema(
     *         type="object",
     *         @Swg\Property(property="author_name", type="string"),
     *         @Swg\Property(property="evaluator_name", type="string"),
     *         @Swg\Property(property="validator_name", type="string"),
     *         @Swg\Property(property="status", type="number"),
     *         @Swg\Property(property="dpo_status", type="number"),
     *         @Swg\Property(property="dpo_opinion", type="string"),
     *         @Swg\Property(property="concerned_people_opinion", type="string"),
     *         @Swg\Property(property="concerned_people_status", type="number"),
     *         @Swg\Property(property="concerned_people_searched_opinion", type="boolean"),
     *         @Swg\Property(property="concerned_people_searched_content", type="string"),
     *         @Swg\Property(property="rejection_reason", type="string"),
     *         @Swg\Property(property="applied_adjustements", type="string"),
     *         @Swg\Property(property="dpos_names", type="string"),
     *         @Swg\Property(property="people_names", type="string"),
     *         @Swg\Property(property="processing", type="object", required={"id"}, @Swg\Property(property="id", type="number"))
     *     ),
     *     description="The PIA content"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns the updated PIA",
     *     @Swg\Schema(
     *         type="object",
     *         ref=@Nelmio\Model(type=Pia::class, groups={"Default"})
     *     )
     * )
     *
     *
     * @Security("is_granted('CAN_EDIT_PIA')")
     *
     * @return array
     */
    public function updateAction(Request $request, $id)
    {
        $pia = $this->getResource($id);
        $this->canAccessResourceOr403($pia);

        $updatableAttributes = [
            'author_name'                        => RequestDataHandler::TYPE_STRING,
            'evaluator_name'                     => RequestDataHandler::TYPE_STRING,
            'validator_name'                     => RequestDataHandler::TYPE_STRING,
            'dpo_status'                         => RequestDataHandler::TYPE_INT,
            'concerned_people_status'            => RequestDataHandler::TYPE_INT,
            'status'                             => RequestDataHandler::TYPE_INT,
            'dpo_opinion'                        => RequestDataHandler::TYPE_STRING,
            'concerned_people_opinion'           => RequestDataHandler::TYPE_STRING,
            'concerned_people_searched_opinion'  => RequestDataHandler::TYPE_BOOL,
            'concerned_people_searched_content'  => RequestDataHandler::TYPE_STRING,
            'rejection_reason'                   => RequestDataHandler::TYPE_STRING,
            'applied_adjustments'                => RequestDataHandler::TYPE_STRING,
            'dpos_names'                         => RequestDataHandler::TYPE_STRING,
            'people_names'                       => RequestDataHandler::TYPE_STRING,
            'type'                               => RequestDataHandler::TYPE_STRING,
            'processing'                         => Processing::class,
        ];

        $this->mergeFromRequest($pia, $updatableAttributes, $request);

        $this->update($pia);

        return $this->view($pia, Response::HTTP_OK);
    }

    /**
     * Deletes a PIA.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Delete("/pias/{id}", requirements={"id"="\d+"})
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="id",
     *     in="path",
     *     type="string",
     *     required=true,
     *     description="The ID of the PIA"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Empty content"
     * )
     *
     * @Security("is_granted('CAN_DELETE_PIA')")
     *
     * @return array
     */
    public function deleteAction(Request $request, $id)
    {
        $pia = $this->getRepository()->find($id);
        $this->canAccessResourceOr403($pia);
        $this->remove($pia);

        return $this->view([], Response::HTTP_OK);
    }

    /**
     * Imports a PIA.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Post("/pias/import")
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="PIA Data",
     *     in="body",
     *     required=true,
     *     @Swg\Schema(
     *         type="object",
     *         @Swg\Property(property="pia", type="object", ref=@Nelmio\Model(type=Pia::class, groups={"Default"})),
     *         @Swg\Property(property="answers", type="array", @Swg\Items(ref=@Nelmio\Model(type=Answer::class, groups={"Default"}))),
     *         @Swg\Property(property="measures", type="array", @Swg\Items(ref=@Nelmio\Model(type=Measure::class, groups={"Default"}))),
     *         @Swg\Property(property="evaluations", type="array", @Swg\Items(ref=@Nelmio\Model(type=Evaluation::class, groups={"Default"}))),
     *         @Swg\Property(property="comments", type="array", @Swg\Items(ref=@Nelmio\Model(type=Comment::class, groups={"Default"}))),
     *         @Swg\Property(property="attachments", type="array", @Swg\Items(ref=@Nelmio\Model(type=Attachment::class, groups={"Default"})))
     *     ),
     *     description="The PIA content"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns the imported PIA"
     * )
     *
     * @Security("is_granted('CAN_IMPORT_PIA')")
     *
     * @return array
     */
    public function importAction(Request $request)
    {
        $importData = $request->get('data', null);
        if ($importData === null) {
            return $this->view($importData, Response::HTTP_BAD_REQUEST);
        }

        $pia = $this->jsonToEntityTransformer->transform($importData);
        $pia->setStructure($this->getUser()->getStructure());
        $this->persist($pia);

        return $this->view($pia, Response::HTTP_OK);
    }

    /**
     * Exports a PIA.
     *
     * @Swg\Tag(name="Pia")
     *
     * @FOSRest\Get("/pias/{id}/export", requirements={"id"="\d+"})
     *
     * @Swg\Parameter(
     *     name="Authorization",
     *     in="header",
     *     type="string",
     *     required=true,
     *     description="The API token. e.g.: Bearer <TOKEN>"
     * )
     * @Swg\Parameter(
     *     name="id",
     *     in="path",
     *     type="string",
     *     required=true,
     *     description="The ID of the PIA"
     * )
     *
     * @Swg\Response(
     *     response=200,
     *     description="Returns an export format of PIA",
     *     @Swg\Schema(
     *         type="object",
     *         @Swg\Property(property="pia", type="object", ref=@Nelmio\Model(type=Pia::class, groups={"Default"})),
     *         @Swg\Property(property="answers", type="array", @Swg\Items(ref=@Nelmio\Model(type=Answer::class, groups={"Default"}))),
     *         @Swg\Property(property="measures", type="array", @Swg\Items(ref=@Nelmio\Model(type=Measure::class, groups={"Default"}))),
     *         @Swg\Property(property="evaluations", type="array", @Swg\Items(ref=@Nelmio\Model(type=Evaluation::class, groups={"Default"}))),
     *         @Swg\Property(property="comments", type="array", @Swg\Items(ref=@Nelmio\Model(type=Comment::class, groups={"Default"}))),
     *         @Swg\Property(property="attachments", type="array", @Swg\Items(ref=@Nelmio\Model(type=Attachment::class, groups={"Default"})))
     *     )
     * )
     *
     * @Security("is_granted('CAN_EXPORT_PIA')")
     *
     * @return array
     */
    public function exportAction(Request $request, $id)
    {
        $this->canAccessRouteOr403();

        $pia = $this->getRepository()->find($id);
        $this->canAccessResourceOr403($pia);

        $serializedPia = $this->jsonToEntityTransformer->reverseTransform($pia);

        return new Response($serializedPia, Response::HTTP_OK);
    }

    protected function getEntityClass()
    {
        return Pia::class;
    }

    public function canAccessResourceOr403($resource): void
    {
        if (!$resource instanceof Pia) {
            throw new AccessDeniedHttpException();
        }
        $resourceStructure = $resource->getStructure();
        $structures = array_merge(
            [$this->getUser()->getStructure()],
            $this->getUser()->getProfile()->getPortfolioStructures()
        );

        if (!in_array($resourceStructure, $structures)) {
            throw new AccessDeniedHttpException();
        }
    }
}
