<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Form\OrderType;
use App\Entity\Product;
use App\Entity\Client;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class LandingPageController extends AbstractController
{
    /**
     * @Route("/", name="landing_page")
     * @throws \Exception
     */
    public function index(Request $request,HttpClientInterface $clientHttp, ProductRepository $productRepository, AddressRepository $addressRepository)
    {
        //Your code here
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);
        $products = $productRepository->findAllDesc();
        
        if ($form->isSubmitted() && $form->isValid()) {
            $idProduct = $request->get('product') ;
            $paymentMethod = $request->get('paymentMethod');
            
            $product = $productRepository->find($idProduct);
            
            $order->setProduct($product);
            $order->setPayment($paymentMethod);
            $order->setStatus('WAITING');
            $order->getDeliveryAddress();
            $entityManager = $this->getDoctrine()->getManager();

            if(!($order->getDeliveryAddress()->getAddress()) || !($order->getDeliveryAddress()->getCity()) || !($order->getDeliveryAddress()->getCountry()) || !($order->getDeliveryAddress()->getPostalCode()) ){
            $address= $order->getclient();
            $addressDelivery = new Address(); 
            $addressDelivery->setAddress($address->getAddress());
            $addressDelivery->setAddressComplement($address->getAddressComplement());
            $addressDelivery->setCountry($address->getCountry());
            $addressDelivery->setPostalCode($address->getPostalCode());
            $addressDelivery->setCity($address->getCity());
            
            $entityManager->persist($addressDelivery);
            $entityManager->flush();
            $order->setDeliveryAddress($addressDelivery);
            }
            $entityManager->persist($order);
            $entityManager->flush();

            $orderArray = $order->arrayJson($order->getClient(), $order->getDeliveryAddress(), $order->getProduct());
            $orderAPI= json_encode($orderArray);
            $content= $this->api_request($orderAPI);
            $order->setIdApi($content['order_id']);
            $entityManager->persist($order);
            $entityManager->flush();

            if($paymentMethod === 'paypal'){
                return $this->redirectToRoute('paypal', [
                    'id'=> $order->getId()
                ]); 
    
            }elseif($paymentMethod === 'stripe'){
                return $this->redirectToRoute('stripe', [
                    'id'=> $order->getId()
                ]); 
            }
            return $this->redirectToRoute('landing_page'); 
        }

        return $this->render('landing_page/index_new.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'products' => $products,
        ]);
    }
    /**
     * @Route("/confirmation", name="confirmation")
     */
    public function confirmation()
    {
        return $this->render('landing_page/confirmation.html.twig', [
       
        ]);
    }
     /**
     * @Route("/{id}/paypal", name="paypal",methods={"GET"} )
     */
    public function paypal(Order $order)
    {
        return $this->render('landing_page/paypal.html.twig', [
            'order'=> $order
        ]);
    }
     /**
     * @Route("/{id}/stripe", name="stripe", methods={"GET"})
     */
    public function stripe(Order $order)
    {
        return $this->render('landing_page/stripe.html.twig', [
            'order'=> $order
        ]);
    }

    /**
     * @Route("/payment", name="payment")
     */
    public function payment(Request $request, OrderRepository $orderRepository)
    {
        $idOrder = $request->get('orderId');
        $order = $orderRepository->findBy(['id'=> $idOrder]);
        $paymentMethod = $request->get('method');
        if ($request->isMethod('POST') && $paymentMethod === 'stripe') {
            $stripe= $this->stripeProcess($order, $orderRepository);
            if($stripe){
                return $this->redirectToRoute('confirmation');
            }else{
                return $this->redirectToRoute('stripe', [
                    'id'=> $order[0]->getId()
                ]); 
            }
        }elseif($request->isMethod('POST') && $paymentMethod === 'paypal'){
            $statusPaid = ['status'=> 'PAID'];
            $status = json_encode($statusPaid);
        
            $this->fetchInformation($order[0]->getIdApi(), $status);
    
            $order[0]->setStatus('PAID');
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($order[0]);
            $entityManager->flush();
            return $this->redirectToRoute('confirmation');  
        }
        
    }

    public function stripeProcess($order, $orderRepository){
    
        \Stripe\Stripe::setApiKey('sk_test_51ItYGCKhlLJk2wgHv52DEGyqHx445wQzkuPt3k3Ef8KW0eICrP76yN9AhgHUKXfvNZzMYl5Vu6ThxIkSmAuhTLL800FD1FnXcz');
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $order[0]->getProduct()->getPrice()*100,
            'currency' => 'eur',
        ]);
        $output = [
            'clientSecret' => $paymentIntent->client_secret,
        ];
        if($output){
        $statusPaid = ['status'=> 'PAID'];
        $status = json_encode($statusPaid);
    
        $this->fetchInformation($order[0]->getIdApi(), $status);

        $order[0]->setStatus('PAID');
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($order[0]);
        $entityManager->flush();
        

        }
    return $output;
    }

    public function fetchInformation($id, $status): array
    {
           $httpClient = HttpClient::create([], 6, 50);
            $response = $httpClient->request('POST', 'https://api-commerce.simplon-roanne.com/order/'.$id.'/status', 
                ['headers' => [
                    'Authorization' => 'Bearer mJxTXVXMfRzLg6ZdhUhM4F6Eutcm1ZiPk4fNmvBMxyNR4ciRsc8v0hOmlzA0vTaX',
                    'Content-type' =>'application/json',
                    ],
                'body' => $status
                ]);

        $statusCode = $response->getStatusCode();
        // $statusCode = 200
        $contentType = $response->getHeaders()['content-type'][0];
        // $contentType = 'application/json'
        $content = $response->getContent();
        // $content = 400'{"id":521583, "name":"symfony-docs", ...}'
        $content = $response->toArray();
        // $content = ['id' => 521583, 'name' => 'symfony-docs', ...]

        return $content;
    }
    public function api_request($orderAPI){

        $token = 'mJxTXVXMfRzLg6ZdhUhM4F6Eutcm1ZiPk4fNmvBMxyNR4ciRsc8v0hOmlzA0vTaX';

            $httpClient = HttpClient::create([], 6, 50);
            $response = $httpClient->request('POST', 'https://api-commerce.simplon-roanne.com/order', 
                ['headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-type' =>'application/json',
                    ],
                'body' => $orderAPI
                ]);
                
            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaders()['content-type'][0];
            $content = $response->getContent();
            $content = $response->toArray();
        
            return $content;

    }
}
