<?php

namespace App\SportBundle\Controller;

use App\SportBundle\Entity\Sport;
use App\Util\Controller\AbstractRestController as Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\View\View;
use JMS\Serializer\SerializerBuilder;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Exception\AccessDeniedException;

/**
 * Sports resource.
 *
 * @author Robin Chalas <rchalas@sutunam.com>
 */
class SportsController extends Controller
{
    /**
     * Get sports.
     *
     * @Rest\Get("/sports")
     * @Rest\View
     *
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   200="OK",
     * 	   401="Unauthorized"
     * 	 },
     * )
     *
     * @return array
     */
    public function getListAction()
    {
        $em = $this->getEntityManager();
        $repo = $em->getRepository('AppSportBundle:Sport');
        $entities = $repo->findBy(['isActive' => 1]);

        return $entities;
    }

    /**
     * Creates a new Sport entity.
     *
     * @Rest\Post("/sports")
     * @Rest\RequestParam(name="name", requirements="[^/]+", allowBlank=false, description="Name")
     * @Rest\RequestParam(name="isActive", requirements="true|false", nullable=true, description="Active")
     * @Rest\RequestParam(name="icon", requirements="[^/]+", nullable=true, description="Icon")
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   201="Created",
     * 	   400="Bad Request",
     * 	   401="Unauthorized",
     * 	 },
     * )
     *
     * @param ParamFetcher $paramFetcher
     *
     * @return JsonResponse
     */
    public function createAction(ParamFetcher $paramFetcher)
    {
        $rolesManager = $this->getRolesManager();

        if (false === $this->getRolesManager()->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not allowed to access this part of the application', 403);
        }

        $em = $this->getEntityManager();
        $repo = $em->getRepository('AppSportBundle:Sport');
        $sport = ['name' => $paramFetcher->get('name')];
        $isActive = $paramFetcher->get('isActive');
        $icon = $paramFetcher->get('icon');

        $repo->findOneByAndFail($sport);

        if (true == $isActive) {
            $sport['isActive'] = true;
        }

        if ($icon) {
            $sport->setIcon($icon);
        }

        $view = View::create()
           ->setStatusCode(201)
           ->setData($repo->create($sport));

         return $this->get('fos_rest.view_handler')->handle($view);
    }

    /**
     * Get Sport entity.
     *
     * @Rest\Get("/sports/{id}")
     *
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   200="OK",
     * 	   401="Unauthorized"
     * 	 },
     * )
     *
     * @param int $id Sport entity
     *
     * @return array
     */
    public function getAction($id)
    {
        $em = $this->getEntityManager();
        $entity = $em->getRepository('AppSportBundle:Sport')->findOrFail($id);

        return $entity;
    }

    /**
     * Update an existing entity.
     *
     * @Rest\Patch("/sports/{id}")
     * @Rest\RequestParam(name="name", requirements="[^/]+", nullable=true, description="Name")
     * @Rest\RequestParam(name="isActive", requirements="[^/]+", nullable=true, description="Name")
     * @Rest\RequestParam(name="icon", requirements="[^/]+", nullable=true, description="Name")
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   200="OK",
     * 	   401="Unauthorized"
     * 	 },
     * )
     *
     * @param int          $id
     * @param ParamFetcher $paramFetcher
     *
     * @return array
     */
    public function updateAction($id, ParamFetcher $paramFetcher)
    {
        $repo = $this
            ->getEntityManager()
            ->getRepository('AppSportBundle:Sport')
        ;
        $changes = [];
        $entity = $repo->findOrFail($id);
        $name = $paramFetcher->get('name');
        $isActive = $paramFetcher->get('isActive');

        if ($isActive) {
            $changes['isActive'] = 'false' == $isActive ? false : true;
        }

        if ($name) {
            if ($name == $entity->getName()) {
                return $entity;
            }

            $changes['name'] = $name;
        }

        $repo->findOneByAndFail($changes);

        return $repo->update($entity, $changes);;
    }

    /**
     * Delete a Sport entity.
     *
     * @Rest\Delete("/sports/{id}")
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   200="OK",
     * 	   401="Unauthorized"
     * 	 },
     * )
     *
     * @param int $id Sport entity
     *
     * @return array
     */
    public function deleteSportAction($id)
    {
        $repo = $this
            ->getEntityManager()
            ->getRepository('AppSportBundle:Sport')
        ;

        $sport = $repo->findOrFail($id);
        $repo->delete($sport);

        return ['success' => true];
    }

    /**
     * Get Icon image from Sport entity.
     *
     * @Rest\Get("/sports/{sport}/icon")
     * @ApiDoc(
     *   section="Sport",
     * 	 resource=true,
     * 	 statusCodes={
     * 	   200="OK",
     * 	   401="Unauthorized"
     * 	 },
     * )
     *
     * @param string|int $sport Sport entity
     *
     * @return Response
     */
    public function getIconBySportAction($sport)
    {
        return $this->forward('AppAdminBundle:SportAdmin:showIcon', array(
            'sport'          => $sport,
            '_sonata_admin'  => 'sonata.admin.sports',
        ));
    }
}
