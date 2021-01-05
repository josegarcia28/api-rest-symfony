<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

//use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;


class VideoController extends AbstractController
{
    private function resjson($data){
        // Serealizar datos con servicios selializer
        $json = $this->get('serializer')->serialize($data, 'json');

        // Response con HttpFoundation
        $response = new Response();

        // Asignar contenido a la respuesta 
        $response->setContent($json);

        // Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        // Devolver la respuesta
        return $response;
    }

    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, $id = null){
        // Recoger el token
        $token = $request->headers->get('Authorization');
        
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'No se ha podido crear el nuevo video'
        ];

        // comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if($authCheck){
            // recoger los datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // Recoger el Objeto del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            if(!empty($json)){
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;

                if(!empty($user_id) && (!empty($title))){


                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id' => $user_id
                    ]);

                    if($id == null){
                        // Crear y guardar el objeto
                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');
    
                        $createdAd = new \DateTime('now');
                        $updateAd = new \DateTime('now');
                        $video->setCreatedAt($createdAd);
                        $video->setUpdatedAt($updateAd);
    
                        // Guardar en bd
                        $em->persist($video);
                        $em->flush();
    
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Se ha creado correctamente el video',
                            'video' => $video
                        ];
                    } else {
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id' => $id,
                            'user' => $identity->sub
                        ]);

                        if($video && is_object($video)){
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
        
                            $updateAd = new \DateTime('now');
                            $video->setUpdatedAt($updateAd);

                            $em->persist($video);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El video se ha Actualizado',
                                'video' => $video
                            ];
                        }
                    }
                }
            }
        }

        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator){
         // Recoger el token
         $token = $request->headers->get('Authorization');

         $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'No se ha podido listar los videos'
        ];
         
         // comprobar si es correcto
         $authCheck = $jwt_auth->checkToken($token);
         

        if($authCheck){
            $identity = $jwt_auth->checkToken($token, true);

            $em = $this->getDoctrine()->getManager();

            // Hacer una consulta para paginar
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = ($identity->sub) ORDER BY v.id DESC";
            $query = $em->createQuery($dql);

            // Recoger el parametro page de la url
            $page = $request->query->getInt('page', 1);
            $items_per_page = 6;

            // Invocar paginacion
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();



            $data = [
                'status' => 'success',
                'code' => 200,
                'total' => $total,
                'page' => $page,
                'item' => $items_per_page,
                'items_per_page' => ceil($items_per_page),
                'total_page' => ceil($total/$items_per_page),
                'video' => $pagination,
                'user_id' => $identity->sub
            ];
        }


        return $this->resjson($data);
    }

    public function video(Request $request, JwtAuth $jwt_auth, $id = null){
        // Recoger el token
        $token = $request->headers->get('Authorization');

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'No se ha encontrado el video indicado'
        ];
        
        // comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);
        

        if($authCheck){
            $identity = $jwt_auth->checkToken($token, true);
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);
        }

        if($video && is_object($video) && $identity->sub == $video->getUser()->getId()){
            $data = [
                'status' => 'success',
                'code' => 200,
                'video' => $video
            ];
        }

        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwt_auth, $id = null){
        // Recoger el token
        $token = $request->headers->get('Authorization');

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'No se ha encontrado el video indicado'
        ];
        
        // comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if($authCheck){
            $identity = $jwt_auth->checkToken($token, true);

            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            $video = $doctrine->getRepository(Video::class)->findOneBy(['id'=>$id]);

            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()){
                $em->remove($video);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }
        return $this->resjson($data);
    }
}
