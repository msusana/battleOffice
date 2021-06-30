<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use App\Entity\Client;
use App\Entity\Address;
use App\Entity\Product;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderRepository::class)
 * @ORM\Table(name="`order`")
 */
class Order
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=client::class, cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $client;

    /**
     * @ORM\ManyToOne(targetEntity=product::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $product;

    /**
     * @ORM\OneToOne(targetEntity=address::class, cascade={"persist", "remove"})
     */
    private $deliveryAddress;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $payment;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $idApi;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?client
    {
        return $this->client;
    }

    public function setClient(client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getProduct(): ?product
    {
        return $this->product;
    }

    public function setProduct(?product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getDeliveryAddress(): ?address
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?address $deliveryAddress): self
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getPayment(): ?string
    {
        return $this->payment;
    }

    public function setPayment(string $payment): self
    {
        $this->payment = $payment;

        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
    
    public function arrayJson(client $client, address $deliveryAddress, product $product)
    {
        return['order'=>[
            'id'=> $this->getId(),
            'product' => $product->getName(),
            'payment_method'=> $this->getPayment(),
            'status'=>$this->getStatus(),
            'client'=>[
                'firstname'=>$client->getFirstName(),
                'lastname'=>$client->getLastName(),
                'email'=>$client->getMail()
            ],
            "addresses"=>[
                "billing"=>[
                    "address_line1"=>$client->getAddress(),
                    "address_line2"=>$client->getAddressComplement(),
                    "city"=>$client->getCity(),
                    "zipcode"=>$client->getPostalCode(),
                    "country"=>$client->getCountry(),
                    "phone"=>$client->getPhone()
                ],
                "shipping"=>[
                    "address_line1"=>$deliveryAddress->getAddress(),
                    "address_line2"=>$deliveryAddress->getAddressComplement(),
                    "city"=>$deliveryAddress->getCity(),
                    "zipcode"=>$deliveryAddress->getPostalCode(),
                    "country"=>$deliveryAddress->getCountry(),
                    "phone"=>$client->getPhone()

                ]
            ]
        ]
            
    ];
    }

    public function getIdApi(): ?string
    {
        return $this->idApi;
    }

    public function setIdApi(?string $idApi): self
    {
        $this->idApi = $idApi;

        return $this;
    }

    
}
