<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class UserController extends AbstractController
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
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);
        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];

       // $users = $user_repo->findAll();
        $videos = $video_repo->findAll();
        //$user = $user_repo->find(1);

        // foreach($users as $user){
        //     echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";

        //     foreach ($user->getVideos() as $video) {
        //        echo "<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
        //     }
        // }
        // die(); // detiene la ejecucion
        //var_dump($user); // imprime el contenido de un objeto

        return $this->resjson($videos);
    }

    public function create(Request $request){
        // Recoger los datos por post
        $json = $request-> get('json', null);

        // Decodificar por defecto
        $params = json_decode($json);

        // Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado.',
            'json' => $params
        ];

        // Comprobar y validar datos
        if($json != null){
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
    
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)){
                $user = new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));

                // Cifrar la contrasena
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);

                // Comprobar si el usuario existe
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

                if(count($isset_user) == 0){
                    // Guardando el usuario
                    $em->persist($user);
                    $em->flush();
                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El usuario se ha creado.',
                        'user' => $user
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 500,
                        'message' => 'El email ya existe'
                    ];
                }

                //$data = $user;
                
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'Validacion incorrecta'
                ];
            }
        }

        // Hacer la respuesta en json
        return $this->resjson($data);
    }

    public function login(Request $request, JwtAuth $jwt_auth){
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha podido identificar'
        ];
        // Recibir los datos por post
        $json = $request-> get('json', null);

        // Decodificar por defecto
        $params = json_decode($json);

        if($json != null){
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && !empty($password) && count($validate_email) == 0){
                // Cifrar la contra
                $pwd = hash('sha256', $password);

                if($gettoken){
                    $signup = $jwt_auth->signup($email, $pwd, $gettoken);
                } else {
                    $signup = $jwt_auth->signup($email, $pwd);
                }
                return new JsonResponse($signup);
            } 
        } 

        return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwt_auth){
        // Recoger la cabecera de la Autenticacion
        $token = $request->headers->get('Authorization');

        // Verificamos segun metodo que el token sea valido
        $authCheck = $jwt_auth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuario no actualizado'
            
        ];

        if($authCheck){
            // Conseguir entity manager
            $em = $this->getDoctrine()->getManager();

            // Conseguir los datos del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            // Conseguir el usuario 
            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);

            // Recoger los datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // Comprobar y validar los datos
            if(!empty($json)){
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;
        
                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);

                if(!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)){
                   // Asignar nuevos datos al objeto
                    $user->setName($name);
                    $user->setSurname($surname);
                    $user->setEmail($email);

                    // Comprobar si hay duplicados
                    $isset_user = $user_repo->findBy([
                        'email' => $email
                    ]);

                    if(count($isset_user) == 0 || $identity->email == $email){
                        // Guardo los datos
                        $em->persist($user);
                        $em->flush();
                        $data = [
                            'status' => 'success',
                            'code' => 00,
                            'message' => 'Usuario actualizado!!',
                            'user' => $user
                            
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Usuario duplicado'
                            
                        ];
                    }

                }    
             
            }

        }
        
        return $this->resjson($data);
    }
}
